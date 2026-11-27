import 'package:dio/dio.dart';

import '../../models/fuel_price.dart';
import '../config/app_config.dart';
import 'fuel_price_provider.dart';

/// Default MVP data source per Spec §4.1: an unofficial aggregator with
/// no auth, ~60 req/min/IP rate limit. Responses should be cached for at
/// least an hour (see AppConfig.priceCacheTtl) since prices change
/// infrequently — a real deployment fronts this with the Laravel backend
/// rather than calling TrackMate straight from the phone, but the shape
/// of the calls is identical either way.
class TrackMateProvider implements FuelPriceProvider {
  TrackMateProvider({Dio? dio})
      : _dio = dio ??
            Dio(BaseOptions(
              baseUrl: AppConfig.trackMateBaseUrl,
              connectTimeout: const Duration(seconds: 10),
              receiveTimeout: const Duration(seconds: 10),
            ));

  final Dio _dio;

  @override
  Future<List<FuelPrice>> latestPrices({String? cityId, String? countryCode}) async {
    final response = await _dio.get(
      '/prices',
      queryParameters: {if (cityId != null) 'city': cityId},
    );
    final data = response.data['data'] as List? ?? response.data as List;
    return data
        .map((e) => FuelPrice.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  @override
  Future<List<FuelPricePoint>> history({
    required String fuelTypeId,
    String? companyId,
    String? countryCode,
    int days = 30,
  }) async {
    final cappedDays = days > 365 ? 365 : days;
    final response = await _dio.get(
      '/history',
      queryParameters: {
        'product': fuelTypeId,
        if (companyId != null) 'source': companyId,
        'days': cappedDays,
      },
    );
    final data = response.data['data'] as List? ?? response.data as List;
    return data
        .map((e) => FuelPricePoint(
              date: DateTime.parse(e['date'] as String),
              price: (e['price'] as num).toDouble(),
            ))
        .toList();
  }

  @override
  Future<ProviderHealth> health() async {
    try {
      final response = await _dio.get('/health');
      final body = response.data as Map<String, dynamic>;
      return ProviderHealth(
        healthy: body['status'] == 'ok',
        lastSuccessfulRun: body['last_successful_run'] != null
            ? DateTime.tryParse(body['last_successful_run'] as String)
            : null,
        failedSources: (body['failed_sources'] as List?)?.cast<String>() ?? const [],
      );
    } on DioException {
      return const ProviderHealth(healthy: false);
    }
  }
}
