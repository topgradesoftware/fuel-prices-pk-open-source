import 'package:dio/dio.dart';

import '../../models/fuel_price.dart';
import 'fuel_price_provider.dart';
import 'price_alert_service.dart';
import 'price_cache_store.dart';

/// Network-first prices with Hive offline fallback + local change alerts.
class CachedFuelPriceProvider implements FuelPriceProvider {
  CachedFuelPriceProvider(this._inner);

  final FuelPriceProvider _inner;

  Future<PriceFetchResult> latestPricesCached({
    String? cityId,
    String? countryCode,
  }) async {
    final key = '${countryCode ?? 'PK'}:${cityId ?? 'national'}';
    final previous = PriceCacheStore.loadLatest(key);

    try {
      final prices = await _inner.latestPrices(
        cityId: cityId,
        countryCode: countryCode,
      );
      await PriceAlertService.instance.detectChanges(
        previous: previous?.prices,
        current: prices,
      );
      await PriceCacheStore.saveLatest(key, prices);
      return PriceFetchResult(prices: prices);
    } on DioException {
      if (previous != null) {
        return PriceFetchResult(
          prices: previous.prices,
          fromCache: true,
          cachedAt: previous.cachedAt,
        );
      }
      rethrow;
    } catch (_) {
      if (previous != null) {
        return PriceFetchResult(
          prices: previous.prices,
          fromCache: true,
          cachedAt: previous.cachedAt,
        );
      }
      rethrow;
    }
  }

  @override
  Future<List<FuelPrice>> latestPrices({String? cityId, String? countryCode}) async {
    final result = await latestPricesCached(
      cityId: cityId,
      countryCode: countryCode,
    );
    return result.prices;
  }

  @override
  Future<List<FuelPricePoint>> history({
    required String fuelTypeId,
    String? companyId,
    String? countryCode,
    int days = 30,
  }) =>
      _inner.history(
        fuelTypeId: fuelTypeId,
        companyId: companyId,
        countryCode: countryCode,
        days: days,
      );

  @override
  Future<ProviderHealth> health() => _inner.health();
}
