import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../../models/fuel_price.dart';
import '../../models/fuel_type.dart';
import 'notification_store.dart';

/// Local price-change alerts (FCM-style Android channel pattern).
/// When a Firebase project is added later, remote messages can reuse the same
/// channel id `price_alerts` and [NotificationStore] feed.
class PriceAlertService {
  PriceAlertService._();

  static final PriceAlertService instance = PriceAlertService._();

  static const _channelId = 'price_alerts';
  static const _channelName = 'Price Alerts';
  static const _channelDesc = 'Petrol and diesel price changes';

  /// Fuel types that trigger user-facing alerts (profile toggle).
  static const _watchedFuels = {'petrol', 'hsd', 'lpg'};

  final FlutterLocalNotificationsPlugin _plugin = FlutterLocalNotificationsPlugin();
  bool _ready = false;

  Future<void> init() async {
    if (_ready) return;
    try {
      const android = AndroidInitializationSettings('@mipmap/ic_launcher');
      const ios = DarwinInitializationSettings();
      await _plugin.initialize(
        settings: const InitializationSettings(android: android, iOS: ios),
      );

      final androidPlugin = _plugin.resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin>();
      await androidPlugin?.createNotificationChannel(
        const AndroidNotificationChannel(
          _channelId,
          _channelName,
          description: _channelDesc,
          importance: Importance.high,
        ),
      );
      await androidPlugin?.requestNotificationsPermission();
      _ready = true;
    } catch (_) {
      // Platforms not generated yet, or permission denied — feed still works.
      _ready = false;
    }
  }

  /// Compare previous Hive snapshot with freshly fetched prices.
  /// Skips the first successful fetch (no prior snapshot) to avoid spam.
  Future<void> detectChanges({
    required List<FuelPrice>? previous,
    required List<FuelPrice> current,
  }) async {
    if (!NotificationStore.alertsEnabled()) return;
    if (previous == null || previous.isEmpty) return;

    final prevMap = {
      for (final p in previous) _key(p): p,
    };

    for (final next in current) {
      if (!_watchedFuels.contains(next.fuelTypeId)) continue;
      // Prefer PSO as the headline company when present.
      if (next.companyId != 'pso' &&
          current.any((p) =>
              p.fuelTypeId == next.fuelTypeId &&
              p.companyId == 'pso' &&
              p.cityId == next.cityId)) {
        continue;
      }

      final old = prevMap[_key(next)];
      if (old == null) continue;
      final delta = next.price - old.price;
      if (delta.abs() < 0.01) continue;

      final fuelName = _fuelLabel(next.fuelTypeId);
      final direction = delta > 0 ? 'increased' : 'decreased';
      final sign = delta > 0 ? '+' : '';
      final title = '$fuelName $direction $sign${delta.toStringAsFixed(2)}';
      final body =
          '${old.price.toStringAsFixed(2)} → ${next.price.toStringAsFixed(2)} ${next.unit}';

      final item = AppNotification(
        id: '${next.fuelTypeId}_${next.companyId}_${DateTime.now().millisecondsSinceEpoch}',
        title: title,
        body: body,
        createdAt: DateTime.now(),
        kind: delta > 0 ? 'increase' : 'decrease',
      );
      await NotificationStore.add(item);
      await _show(item);
    }
  }

  Future<void> _show(AppNotification item) async {
    if (!_ready) await init();
    if (!_ready) return;
    try {
      await _plugin.show(
        id: item.id.hashCode,
        title: item.title,
        body: item.body,
        notificationDetails: const NotificationDetails(
          android: AndroidNotificationDetails(
            _channelId,
            _channelName,
            channelDescription: _channelDesc,
            importance: Importance.high,
            priority: Priority.high,
            icon: '@mipmap/ic_launcher',
          ),
          iOS: DarwinNotificationDetails(),
        ),
      );
    } catch (_) {
      // System notification unavailable; in-app feed already stored.
    }
  }

  static String _key(FuelPrice p) =>
      '${p.companyId}|${p.cityId}|${p.fuelTypeId}';

  static String _fuelLabel(String id) {
    for (final f in kDefaultFuelTypes) {
      if (f.id == id) return f.name;
    }
    return id;
  }
}
