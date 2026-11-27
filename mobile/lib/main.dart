import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'core/config/app_config.dart';
import 'core/notifications/firebase_push_service.dart';
import 'core/services/app_settings.dart';
import 'core/services/price_alert_service.dart';
import 'core/services/price_cache_store.dart';
import 'core/theme/app_theme.dart';
import 'routes/app_router.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Future.wait([
    initializeDateFormatting('en'),
    initializeDateFormatting('ur'),
  ]);
  await Hive.initFlutter();
  await Hive.openBox('fuelpk_prefs');
  await Hive.openBox(PriceCacheStore.boxName);
  await PriceAlertService.instance.init();
  await FirebasePushService.instance.init();
  runApp(const ProviderScope(child: PetrolPriceApp()));
}

class PetrolPriceApp extends ConsumerWidget {
  const PetrolPriceApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final themeMode = ref.watch(themeModeProvider);
    final strings = ref.watch(stringsProvider);

    return MaterialApp.router(
      title: AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: themeMode,
      locale: Locale(strings.code),
      builder: (context, child) {
        AppColors.bind(Theme.of(context).brightness);
        return Directionality(
          textDirection: strings.textDirection,
          child: child ?? const SizedBox.shrink(),
        );
      },
      routerConfig: appRouter,
    );
  }
}
