import 'package:intl/intl.dart';
import 'package:share_plus/share_plus.dart';

import '../../models/fuel_price.dart';
import '../config/app_config.dart';
import '../i18n/strings.dart';

/// Shares today's headline prices as text (mirrors the PWA share format).
class ShareService {
  ShareService._();

  /// Prefer PSO price for a fuel, else the first company carrying it.
  static FuelPrice? _headline(List<FuelPrice> prices, String fuelTypeId) {
    FuelPrice? fallback;
    for (final p in prices) {
      if (p.fuelTypeId != fuelTypeId || p.price <= 0) continue;
      if (p.companyId == 'pso') return p;
      fallback ??= p;
    }
    return fallback;
  }

  static String buildText(List<FuelPrice> prices, S s, {String? currencySymbol}) {
    final currency = currencySymbol ?? s('currencyRs');
    final petrol = _headline(prices, 'petrol');
    final hsd = _headline(prices, 'hsd');
    final today = DateFormat('d MMM yyyy').format(DateTime.now());

    final lines = <String>[
      s('appName'),
      if (petrol != null)
        '${s('petrolLabel')}: $currency ${petrol.price.toStringAsFixed(2)}/${s('unitL')}',
      if (hsd != null)
        '${s('hsdLabel')}: $currency ${hsd.price.toStringAsFixed(2)}/${s('unitL')}',
      today,
      AppConfig.siteUrl,
    ];
    return lines.join('\n');
  }

  static Future<void> sharePrices(
    List<FuelPrice> prices,
    S s, {
    String? currencySymbol,
  }) async {
    await SharePlus.instance.share(
      ShareParams(
        text: buildText(prices, s, currencySymbol: currencySymbol),
        subject: s('appName'),
      ),
    );
  }
}
