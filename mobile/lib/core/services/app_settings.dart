import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:hive_flutter/hive_flutter.dart';

import '../i18n/strings.dart';

/// Persisted appearance + language (TopGrade school-style prefs).
class AppSettings {
  static const boxName = 'fuelpk_prefs';
  static const themeKey = 'theme_preference'; // system | light | dark
  static const localeKey = 'locale'; // en | ur
  static const countryKey = 'country'; // PK | IN | BD
  static const cityKey = 'last_city'; // city slug (not National by default)

  static Box get _box => Hive.box(boxName);

  static String loadThemeId() =>
      _box.get(themeKey, defaultValue: 'system') as String;

  static Future<void> saveThemeId(String id) => _box.put(themeKey, id);

  static String loadLocale() =>
      _box.get(localeKey, defaultValue: 'en') as String;

  static Future<void> saveLocale(String code) => _box.put(localeKey, code);

  static String loadCountry() =>
      (_box.get(countryKey, defaultValue: 'PK') as String).toUpperCase();

  static Future<void> saveCountry(String code) =>
      _box.put(countryKey, code.toUpperCase());

  static String? loadCityId() {
    final v = _box.get(cityKey) as String?;
    if (v == null || v.isEmpty || v == 'national') return null;
    return v;
  }

  static Future<void> saveCityId(String id) => _box.put(cityKey, id);

  static ThemeMode themeModeFromId(String id) {
    switch (id) {
      case 'light':
        return ThemeMode.light;
      case 'dark':
        return ThemeMode.dark;
      default:
        return ThemeMode.system;
    }
  }
}

final themeModeIdProvider = StateProvider<String>((ref) {
  return AppSettings.loadThemeId();
});

final themeModeProvider = Provider<ThemeMode>((ref) {
  return AppSettings.themeModeFromId(ref.watch(themeModeIdProvider));
});

final localeCodeProvider = StateProvider<String>((ref) {
  return AppSettings.loadLocale();
});

final stringsProvider = Provider<S>((ref) {
  return S.of(ref.watch(localeCodeProvider));
});

/// Bottom-nav tab in [AppShell]: 0 Home … 3 Garage … 6 More.
final shellTabIndexProvider = StateProvider<int>((ref) => 0);
