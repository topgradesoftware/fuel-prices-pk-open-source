import 'package:dio/dio.dart';

import '../../models/fuel_price.dart';
import '../network/api_client.dart';
import '../validation/schemas.dart';
import 'fuel_price_provider.dart';

/// Talks to Laravel `/api/v1/*` using the standard JSON envelope.
class LaravelApiProvider implements FuelPriceProvider {
  LaravelApiProvider({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  @override
  Future<List<FuelPrice>> latestPrices({String? cityId, String? countryCode}) async {
    final query = pricesQuery(
      city: (cityId != null && cityId != 'national') ? cityId : null,
      country: countryCode,
    );
    final data = await _client.get('/prices', query: query);
    final items = data['items'] as List? ?? data['data'] as List? ?? const [];
    return items
        .map((e) => FuelPrice.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  @override
  Future<List<FuelPricePoint>> history({
    required String fuelTypeId,
    String? companyId,
    String? countryCode,
    int days = 30,
  }) async {
    final query = historyQuery(
      product: fuelTypeId,
      company: companyId,
      country: countryCode,
      days: days > 365 ? 365 : days,
    );
    final data = await _client.get('/history', query: query);
    final items = data['items'] as List? ?? data['data'] as List? ?? const [];
    return items
        .map((e) => FuelPricePoint(
              date: DateTime.parse(e['date'] as String),
              price: (e['price'] as num).toDouble(),
            ))
        .toList();
  }

  @override
  Future<ProviderHealth> health() async {
    try {
      final data = await _client.get('/status');
      final last = data['last_sync'] as Map<String, dynamic>?;
      return ProviderHealth(
        healthy: data['status'] == 'ok',
        lastSuccessfulRun: last?['finished_at'] != null
            ? DateTime.tryParse(last!['finished_at'] as String)
            : null,
      );
    } on DioException {
      return const ProviderHealth(healthy: false);
    }
  }
}
