import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';

import '../../models/city.dart';
import '../../models/country.dart';
import '../../models/fuel_company.dart';
import '../../models/fuel_price.dart';
import '../../models/vehicle.dart';
import 'app_settings.dart';
import 'cached_fuel_price_provider.dart';
import 'catalog_api.dart';
import 'fuel_price_provider.dart';
import 'laravel_api_provider.dart';
import 'location_city_service.dart';
import 'notification_store.dart';
import 'price_cache_store.dart';
import 'vehicle_store.dart';

/// Default: Laravel `/api/v1` with Hive offline cache.
/// Offline demo: wrap `DemoFuelPriceProvider()` instead.
/// Direct TrackMate: wrap `TrackMateProvider()` instead.
final fuelPriceProviderProvider = Provider<FuelPriceProvider>((ref) {
  return CachedFuelPriceProvider(LaravelApiProvider());
});

final selectedCountryProvider = StateProvider<Country>((ref) {
  final code = AppSettings.loadCountry();
  return kDefaultCountries.firstWhere(
    (c) => c.code == code,
    orElse: () => kDefaultCountry,
  );
});

final currencyLabelProvider = Provider<String>((ref) {
  return ref.watch(selectedCountryProvider).currencySymbol;
});

final countriesProvider = FutureProvider.autoDispose<List<Country>>((ref) async {
  try {
    final items = await CatalogApi().countries();
    if (items.isEmpty) return kDefaultCountries;
    return items;
  } catch (_) {
    return kDefaultCountries;
  }
});

City _defaultCityForCountry(String code) {
  final cities = defaultCitiesForCountry(code);
  final saved = AppSettings.loadCityId();
  if (saved != null) {
    for (final c in cities) {
      if (c.id == saved) return c;
    }
  }
  // Prefer a real city over National until GPS resolves.
  return cities.firstWhere(
    (c) => c.id != 'national',
    orElse: () => cities.first,
  );
}

final selectedCityProvider = StateProvider<City>((ref) {
  final code = AppSettings.loadCountry();
  return _defaultCityForCountry(code);
});

/// Boot GPS → default city to the user's current location (not National).
final locationCityBootstrapProvider = FutureProvider.autoDispose<void>((ref) async {
  final country = ref.watch(selectedCountryProvider);
  List<City> cities;
  try {
    cities = await ref.watch(citiesProvider.future);
  } catch (_) {
    cities = defaultCitiesForCountry(country.code);
  }
  if (cities.isEmpty) return;

  final matchedId = await detectCityId(cities);
  if (matchedId == null) return;

  City? matched;
  for (final c in cities) {
    if (c.id == matchedId) {
      matched = c;
      break;
    }
  }
  if (matched == null) return;

  ref.read(selectedCityProvider.notifier).state = matched;
  await AppSettings.saveCityId(matched.id);
});

/// Cities from API for the selected country (fallback to defaults offline).
final citiesProvider = FutureProvider.autoDispose<List<City>>((ref) async {
  final country = ref.watch(selectedCountryProvider);
  final fallback = defaultCitiesForCountry(country.code);
  try {
    final items = await CatalogApi().cities(country: country.code);
    if (items.isEmpty) return fallback;
    final hasNational = items.any((c) => c.id == 'national');
    return [
      if (!hasNational) City(id: 'national', name: 'National', countryCode: country.code),
      ...items,
    ];
  } catch (_) {
    return fallback;
  }
});

final companiesProvider = FutureProvider.autoDispose<List<FuelCompany>>((ref) async {
  final country = ref.watch(selectedCountryProvider);
  try {
    return await CatalogApi().companies(country: country.code);
  } catch (_) {
    return const [];
  }
});

final vehiclesProvider = StateProvider<List<Vehicle>>((ref) {
  return VehicleStore.loadVehicles();
});

final selectedVehicleProvider = StateProvider<Vehicle>((ref) {
  final vehicles = ref.watch(vehiclesProvider);
  final savedId = VehicleStore.loadSelectedId();
  for (final v in vehicles) {
    if (v.id == savedId) return v;
  }
  for (final v in vehicles) {
    if (v.isDefault) return v;
  }
  return vehicles.first;
});

/// In-app notification feed (local price alerts).
final notificationFeedProvider =
    StateProvider<List<AppNotification>>((ref) => NotificationStore.load());

/// Latest prices for the currently selected city (network-first, cache fallback).
final latestPricesProvider =
    FutureProvider.autoDispose<PriceFetchResult>((ref) async {
  final provider = ref.watch(fuelPriceProviderProvider);
  final city = ref.watch(selectedCityProvider);
  final country = ref.watch(selectedCountryProvider);
  final PriceFetchResult result;
  if (provider is CachedFuelPriceProvider) {
    result = await provider.latestPricesCached(
      cityId: city.id,
      countryCode: country.code,
    );
  } else {
    final prices = await provider.latestPrices(
      cityId: city.id,
      countryCode: country.code,
    );
    result = PriceFetchResult(prices: prices);
  }
  // Keep the in-app feed in sync after alert detection runs.
  ref.read(notificationFeedProvider.notifier).state = NotificationStore.load();
  return result;
});

/// Price history for a given fuel type, used by the History tab.
final priceHistoryProvider = FutureProvider.autoDispose
    .family<List<FuelPricePoint>, ({String fuelTypeId, int days})>((ref, args) async {
  final provider = ref.watch(fuelPriceProviderProvider);
  final country = ref.watch(selectedCountryProvider);
  return provider.history(
    fuelTypeId: args.fuelTypeId,
    days: args.days,
    countryCode: country.code,
  );
});

/// Petrol + diesel + LPG history for the History multi-layout charts.
final historyBundleProvider = FutureProvider.autoDispose
    .family<Map<String, List<FuelPricePoint>>, int>((ref, days) async {
  const ids = ['petrol', 'hsd', 'lpg'];
  final provider = ref.watch(fuelPriceProviderProvider);
  final country = ref.watch(selectedCountryProvider);
  final entries = await Future.wait(
    ids.map((id) async => MapEntry(
          id,
          await provider.history(
            fuelTypeId: id,
            days: days,
            countryCode: country.code,
          ),
        )),
  );
  return {for (final e in entries) e.key: e.value};
});
