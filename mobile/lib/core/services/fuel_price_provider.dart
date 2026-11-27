import '../../models/fuel_price.dart';

/// Mirrors the backend `FuelPriceProvider` PHP interface from Spec §4.2.
/// Every data source (TrackMate, a company-specific API, a scraper, or
/// manual admin entry) implements this same contract so the rest of the
/// app never has to know which source supplied a given price.
abstract class FuelPriceProvider {
  /// Latest prices across all tracked products/companies/cities.
  Future<List<FuelPrice>> latestPrices({String? cityId, String? countryCode});

  /// Historical prices for a single fuel type, optionally scoped to a
  /// company. `days` is capped at 365 per Spec §4.1.
  Future<List<FuelPricePoint>> history({
    required String fuelTypeId,
    String? companyId,
    String? countryCode,
    int days = 30,
  });

  /// Provider health — last successful run, source availability, etc.
  Future<ProviderHealth> health();
}

class ProviderHealth {
  final bool healthy;
  final DateTime? lastSuccessfulRun;
  final List<String> failedSources;

  const ProviderHealth({
    required this.healthy,
    this.lastSuccessfulRun,
    this.failedSources = const [],
  });
}
