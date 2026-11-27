import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../services/notification_store.dart';
import '../services/price_alert_service.dart';
import 'device_registration_service.dart';

/// Best-effort Firebase bootstrap. Works once `firebase_options.dart` exists.
class FirebasePushService {
  FirebasePushService._();
  static final instance = FirebasePushService._();

  bool ready = false;

  Future<void> init() async {
    // Web doesn't have Firebase configured — skip entirely for UI preview.
    if (kIsWeb) return;

    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp();
      }
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();
      final token = await messaging.getToken();

      FirebaseMessaging.onMessage.listen((message) async {
        final title = message.notification?.title ?? 'Fuel Prices PK';
        final body = message.notification?.body ?? '';
        await NotificationStore.add(AppNotification(
          id: message.messageId ?? DateTime.now().millisecondsSinceEpoch.toString(),
          title: title,
          body: body,
          createdAt: DateTime.now(),
          kind: (message.data['type'] == 'PRICE_CHANGE') ? 'increase' : 'info',
        ));
        await PriceAlertService.instance.init();
      });

      await DeviceRegistrationService().register(
        platform: defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
        firebaseToken: token,
      );
      ready = true;
    } catch (_) {
      // Firebase not configured — local alerts still work.
      ready = false;
      try {
        await DeviceRegistrationService().register(
          platform: defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
        );
      } catch (_) {}
    }
  }
}
