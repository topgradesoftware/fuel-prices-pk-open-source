import 'package:hive_flutter/hive_flutter.dart';
import 'package:uuid/uuid.dart';

import '../network/api_client.dart';
import '../validation/schemas.dart';

class DeviceRegistrationService {
  DeviceRegistrationService({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;
  static const _uuidKey = 'device_uuid';
  static const _secretKey = 'device_secret';

  Box get _box => Hive.box('fuelpk_prefs');

  String deviceUuid() {
    final existing = _box.get(_uuidKey) as String?;
    if (existing != null && existing.isNotEmpty) return existing;
    final id = const Uuid().v4();
    _box.put(_uuidKey, id);
    return id;
  }

  Future<void> register({
    required String platform,
    String? firebaseToken,
    String locale = 'en',
    bool alertsEnabled = true,
    String? appVersion,
  }) async {
    final secret = _box.get(_secretKey) as String?;
    final body = deviceRegisterBody(
      deviceUuid: deviceUuid(),
      platform: platform,
      firebaseToken: firebaseToken,
      locale: locale,
      timezone: DateTime.now().timeZoneName.length <= 64
          ? DateTime.now().timeZoneName
          : DateTime.now().timeZoneOffset.toString(),
      alertsEnabled: alertsEnabled,
      appVersion: appVersion ?? '0.2.0',
      deviceName: 'Fuel Prices PK',
      deviceSecret: (secret != null && secret.isNotEmpty) ? secret : null,
    );
    final result = await _client.post('/devices', body: body);

    // Persist the secret issued by the server so future updates are authorized.
    final issued = result['device_secret'] as String?;
    if (issued != null && issued.isNotEmpty) {
      await _box.put(_secretKey, issued);
    }
  }
}
