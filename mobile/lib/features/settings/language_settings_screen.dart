import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/services/app_settings.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/settings_option_card.dart';

class LanguageSettingsScreen extends ConsumerWidget {
  const LanguageSettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);
    final selected = ref.watch(localeCodeProvider);

    final options = [
      (
        'en',
        Icons.language_outlined,
        s('english'),
        s('englishSub'),
      ),
      (
        'ur',
        Icons.translate_outlined,
        s('urdu'),
        s('urduSub'),
      ),
    ];

    return Scaffold(
      appBar: AppBar(title: Text(s('languageSettings'))),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, Space.navContentClearance),
        children: [
          Text(
            s('languageHint'),
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
                ref.read(localeCodeProvider.notifier).state = o.$1;
                await AppSettings.saveLocale(o.$1);
              },
            ),
            const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }
}
