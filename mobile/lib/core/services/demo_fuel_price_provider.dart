import 'dart:math';

import '../../models/fuel_price.dart';
import 'fuel_price_provider.dart';

/// A ManualProvider-style stand-in (Spec §4.2) used for local development
/// and demos before the Laravel backend + TrackMate integration is wired
/// up. Ships the same illustrative numbers used in petrol_price_dashboard.jsx
/// so the web mockup and the app agree while there's no live data source.
///
/// Swap this for TrackMateProvider (via Riverpod override) once the API
/// is reachable — nothing else in the app needs to change, since both
/// implement FuelPriceProvider.
class DemoFuelPriceProvider implements FuelPriceProvider {
  static const _companies = [
    'pso',
    'go',
    'total',
    'attock',
    'shell',
    'hascol',
    'puma',
    'cnergyico',
    'be_energy',
  ];
  static const _companyDelta = {
    'pso': 0.0,
    'shell': 1.2,
    'total': 0.85,
    'attock': -0.5,
    'go': -1.1,
    'hascol': -0.75,
  };
  static const _cityDelta = {
    'khi': 0.0,
    'lhr': 1.0,
    'isb': 1.5,
    'rwp': 1.4,
    'fsd': 0.8,
    'pew': 2.2,
    'uet': 3.5,
    'mux': 0.6,
  };
  static const _base = {
    'petrol': 310.71,
    'premium': 340.15,
    'hi_octane': 340.15,
    'hsd': 323.30,
    'ldo': 199.98,
    'lpg': 241.43,
    'kerosene': 233.71,
  };
  static const _prev = {
    'petrol': 302.35,
    'premium': 331.05,
    'hi_octane': 331.05,
    'hsd': 325.45,
    'ldo': 201.10,
    'lpg': 238.23,
    'kerosene': 234.76,
  };
  static const _units = {
    'petrol': 'Rs/L',
    'premium': 'Rs/L',
    'hi_octane': 'Rs/L',
    'hsd': 'Rs/L',
    'ldo': 'Rs/L',
    'lpg': 'Rs/kg',
    'kerosene': 'Rs/L',
  };

  @override
  Future<List<FuelPrice>> latestPrices({String? cityId, String? countryCode}) async {
    final city = cityId ?? 'khi';
    final cityDelta = _cityDelta[city] ?? 0;
    final today = DateTime.now();
    final results = <FuelPrice>[];
    var idx = 0;
    for (final fuelId in _base.keys) {
      for (final companyId in _companies) {
        final companyDelta = _companyDelta[companyId] ?? 0;
        final price = double.parse(
            (_base[fuelId]! + companyDelta + cityDelta).toStringAsFixed(2));
        final prevAdjusted = double.parse(
            (_prev[fuelId]! + companyDelta + cityDelta).toStringAsFixed(2));
        results.add(FuelPrice(
          id: '${fuelId}_${companyId}_${city}_${idx++}',
          companyId: companyId,
          cityId: city,
          fuelTypeId: fuelId,
          price: price,
          previousPrice: prevAdjusted,
          unit: _units[fuelId] ?? 'Rs/L',
          effectiveDate: today,
          source: 'manual_seed',
          verified: false,
        ));
      }
    }
    return results;
  }

  @override
  Future<List<FuelPricePoint>> history({
    required String fuelTypeId,
    String? companyId,
    String? countryCode,
    int days = 30,
  }) async {
    // Deterministic pseudo-random walk, same shape as the fortnightly
    // revision cycle simulated in petrol_price_dashboard.jsx.
    final rand = Random(42);
    const revisionEvery = 14;
    var value = _base[fuelTypeId] ?? 300;
    final today = DateTime.now();
    final points = <FuelPricePoint>[];
    for (var i = days - 1; i >= 0; i--) {
      final date = today.subtract(Duration(days: i));
      if (i > 0 && i % revisionEvery == 0) {
        final swing = (rand.nextDouble() - 0.45) * 14;
        value = max(50, value - swing);
      }
      points.add(FuelPricePoint(date: date, price: double.parse(value.toStringAsFixed(2))));
    }
    return points;
  }

  @override
  Future<ProviderHealth> health() async =>
      ProviderHealth(healthy: true, lastSuccessfulRun: DateTime.now());
}
