import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';

import '../../models/fuel_price.dart';
import '../config/app_config.dart';

/// Offline snapshot of latest prices (Hive), with TTL from Spec §4.1.
class PriceCacheStore {
  static const boxName = 'fuelpk_price_cache';

  static Box get _box => Hive.box(boxName);

  static String _pricesKey(String cityId) => 'prices:$cityId';
  static String _atKey(String cityId) => 'at:$cityId';

  static Future<void> saveLatest(String cityId, List<FuelPrice> prices) async {
    await _box.put(
      _pricesKey(cityId),
      jsonEncode(prices.map((p) => p.toJson()).toList()),
    );
    await _box.put(_atKey(cityId), DateTime.now().toIso8601String());
  }

  static CachedPrices? loadLatest(String cityId) {
    final raw = _box.get(_pricesKey(cityId));
    final atRaw = _box.get(_atKey(cityId));
    if (raw is! String || raw.isEmpty || atRaw is! String) return null;
    try {
      final list = jsonDecode(raw) as List;
      final prices = list
          .map((e) => FuelPrice.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList();
      final cachedAt = DateTime.parse(atRaw);
      return CachedPrices(prices: prices, cachedAt: cachedAt);
    } catch (_) {
      return null;
    }
  }

  static bool isFresh(DateTime cachedAt) =>
      DateTime.now().difference(cachedAt) <= AppConfig.priceCacheTtl;
}

class CachedPrices {
  final List<FuelPrice> prices;
  final DateTime cachedAt;

  const CachedPrices({required this.prices, required this.cachedAt});

  bool get isFresh => PriceCacheStore.isFresh(cachedAt);
}

/// Result of a price fetch — live network or offline Hive fallback.
class PriceFetchResult {
  final List<FuelPrice> prices;
  final bool fromCache;
  final DateTime? cachedAt;

  const PriceFetchResult({
    required this.prices,
    this.fromCache = false,
    this.cachedAt,
  });
}
