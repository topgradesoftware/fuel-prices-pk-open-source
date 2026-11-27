import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';

/// Persisted in-app notification feed (local price-change alerts).
class NotificationStore {
  static const boxName = 'fuelpk_prefs';
  static const _key = 'notification_feed';
  static const _maxItems = 40;

  static Box get _box => Hive.box(boxName);

  static List<AppNotification> load() {
    final raw = _box.get(_key);
    if (raw is! String || raw.isEmpty) return const [];
    try {
      final list = jsonDecode(raw) as List;
      return list
          .map((e) => AppNotification.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList();
    } catch (_) {
      return const [];
    }
  }

  static Future<void> add(AppNotification item) async {
    final items = load();
    final next = [item, ...items].take(_maxItems).toList();
    await _box.put(
      _key,
      jsonEncode(next.map((n) => n.toJson()).toList()),
    );
  }

  static Future<void> clear() async {
    await _box.delete(_key);
  }

  static bool alertsEnabled() => _box.get('alerts', defaultValue: true) as bool;

  static Future<void> setAlertsEnabled(bool value) async {
    await _box.put('alerts', value);
  }
}

class AppNotification {
  final String id;
  final String title;
  final String body;
  final DateTime createdAt;
  final String kind; // increase | decrease | unchanged | info

  const AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.createdAt,
    this.kind = 'info',
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) => AppNotification(
        id: json['id'].toString(),
        title: json['title'] as String? ?? '',
        body: json['body'] as String? ?? '',
        createdAt: DateTime.parse(json['created_at'] as String),
        kind: json['kind'] as String? ?? 'info',
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'title': title,
        'body': body,
        'created_at': createdAt.toIso8601String(),
        'kind': kind,
      };
}
