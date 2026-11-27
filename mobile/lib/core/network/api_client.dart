import 'package:dio/dio.dart';
import 'package:hive_flutter/hive_flutter.dart';

import '../config/app_config.dart';

class ApiClient {
  ApiClient({Dio? dio})
      : _dio = dio ??
            Dio(BaseOptions(
              baseUrl: AppConfig.apiBaseUrl,
              connectTimeout: const Duration(seconds: 15),
              receiveTimeout: const Duration(seconds: 15),
              headers: {'Accept': 'application/json'},
            )) {
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        final box = Hive.box('fuelpk_prefs');
        final token = box.get('sanctum_token') as String?;
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        options.headers['X-Request-ID'] =
            DateTime.now().microsecondsSinceEpoch.toString();
        handler.next(options);
      },
    ));
  }

  final Dio _dio;

  Dio get raw => _dio;

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) async {
    final response = await _dio.get(path, queryParameters: query);
    return _unwrap(response.data);
  }

  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? body}) async {
    final response = await _dio.post(path, data: body);
    return _unwrap(response.data);
  }

  Future<Map<String, dynamic>> delete(String path) async {
    final response = await _dio.delete(path);
    return _unwrap(response.data);
  }

  Map<String, dynamic> _unwrap(dynamic data) {
    if (data is! Map) {
      throw DioException(
        requestOptions: RequestOptions(path: ''),
        message: 'Invalid API response',
      );
    }
    final map = Map<String, dynamic>.from(data);
    if (map['success'] == false) {
      throw DioException(
        requestOptions: RequestOptions(path: ''),
        message: map['message']?.toString() ?? 'Request failed',
      );
    }
    final payload = map['data'];
    if (payload is Map<String, dynamic>) return payload;
    if (payload is Map) return Map<String, dynamic>.from(payload);
    return {'value': payload};
  }
}
