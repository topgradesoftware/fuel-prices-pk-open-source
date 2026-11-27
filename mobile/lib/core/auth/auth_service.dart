import 'package:hive_flutter/hive_flutter.dart';

import '../network/api_client.dart';
import '../validation/schemas.dart';

class AuthService {
  AuthService({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;
  static const _tokenKey = 'sanctum_token';

  Box get _box => Hive.box('fuelpk_prefs');

  String? get token => _box.get(_tokenKey) as String?;

  bool get isLoggedIn => token != null && token!.isNotEmpty;

  Future<Map<String, dynamic>> loginAsGuest({String? name, String? deviceUuid}) async {
    final data = await _client.post('/auth/guest', body: {
      if (name != null && name.length <= 120) 'name': name,
      if (parseUuid(deviceUuid, field: 'device_uuid') != null)
        'device_uuid': parseUuid(deviceUuid, field: 'device_uuid'),
    });
    await _persist(data);
    return data;
  }

  Future<Map<String, dynamic>> loginWithFirebase({
    required String idToken,
    String? deviceUuid,
  }) async {
    final body = googleAuthBody(idToken: idToken, deviceUuid: deviceUuid);
    final data = await _client.post('/auth/firebase', body: body);
    await _persist(data);
    return data;
  }

  /// Google Identity Services / native Google Sign-In ID token (same as PWA).
  Future<Map<String, dynamic>> loginWithGoogle({
    required String idToken,
    String? deviceUuid,
  }) async {
    final body = googleAuthBody(idToken: idToken, deviceUuid: deviceUuid);
    final data = await _client.post('/auth/google', body: body);
    await _persist(data);
    return data;
  }

  Future<void> logout() async {
    try {
      await _client.post('/auth/logout');
    } catch (_) {}
    await _box.delete(_tokenKey);
  }

  Future<void> _persist(Map<String, dynamic> data) async {
    final token = data['token'] as String?;
    if (token != null) {
      await _box.put(_tokenKey, token);
    }
  }
}
