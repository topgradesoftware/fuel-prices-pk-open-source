import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/services/app_settings.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/settings_option_card.dart';

class ThemeSettingsScreen extends ConsumerWidget {
  const ThemeSettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);
    final selected = ref.watch(themeModeIdProvider);

    final options = [
      (
        'system',
        Icons.brightness_auto_outlined,
        s('themeSystem'),
        s('themeSystemSub'),
      ),
      (
        'light',
        Icons.light_mode_outlined,
        s('themeLight'),
        s('themeLightSub'),
      ),
      (
        'dark',
        Icons.dark_mode_outlined,
        s('themeDark'),
        s('themeDarkSub'),
      ),
    ];

    return Scaffold(
      appBar: AppBar(title: Text(s('themeSettings'))),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, Space.navContentClearance),
        children: [
          Text(
            s('themeHint'),
            style: TextStyle(color: AppColors.textMid, fontSize: 13, height: 1.35),
          ),
          const SizedBox(height: 16),
          for (final o in options) ...[
            SettingsOptionCard(
              icon: o.$2,
              title: o.$3,
              subtitle: o.$4,
              selected: selected == o.$1,
              onTap: () async {
                ref.read(themeModeIdProvider.notifier).state = o.$1;
                await AppSettings.saveThemeId(o.$1);
              },
            ),
            const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }
}
