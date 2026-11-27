import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'tokens/colors.dart';
import 'tokens/motion.dart';
import 'tokens/radii.dart';
import 'tokens/spacing.dart';
import 'tokens/typography.dart';

export 'tokens/colors.dart';
export 'tokens/elevation.dart';
export 'tokens/motion.dart';
export 'tokens/radii.dart';
export 'tokens/spacing.dart';
export 'tokens/typography.dart';

/// Semantic palette — bound to the active [Brightness] so existing
/// `AppColors.xxx` call sites adapt when the user picks light/dark/system.
class AppColors {
  AppColors._();

  static ColorTokens _t = ColorTokens.dark;

  /// Call from [MaterialApp.builder] whenever the resolved theme changes.
  static void bind(Brightness brightness) {
    _t = brightness == Brightness.dark ? ColorTokens.dark : ColorTokens.light;
  }

  static ColorTokens get tokens => _t;

  static Color get bgDeep => _t.background;
  static Color get bgPanel => _t.surface;
  static Color get bgPanelAlt => _t.surfaceAlt;
  static Color get amber => _t.primary;
  static Color get amberDim => _t.secondary;
  static Color get up => _t.up;
  static Color get down => _t.down;
  static Color get textHi => _t.textPrimary;
  static Color get textMid => _t.textSecondary;
  static Color get textPrimary => _t.textPrimary;
  static Color get textSecondary => _t.textSecondary;
  static Color get border => _t.border;

  static Color get success => _t.success;
  static Color get warning => _t.warning;
  static Color get danger => _t.danger;
  static Color get info => _t.info;
  static Color get textMuted => _t.textMuted;
  static Color get onPrimary => _t.onPrimary;
  static Color get navBlur => _t.navBlur;
  static Color get card => _t.card;
  static Color get container => _t.container;
}

class AppTheme {
  static ThemeData get dark => _build(Brightness.dark, ColorTokens.dark);

  static ThemeData get light => _build(Brightness.light, ColorTokens.light);

  static ThemeData _build(Brightness brightness, ColorTokens t) {
    final base = brightness == Brightness.dark
        ? ThemeData.dark(useMaterial3: true)
        : ThemeData.light(useMaterial3: true);

    final textTheme = TypeTokens.materialTheme(t.textPrimary, t.textSecondary);

    return base.copyWith(
      brightness: brightness,
      scaffoldBackgroundColor: t.background,
      colorScheme: base.colorScheme.copyWith(
        brightness: brightness,
        primary: t.primary,
        secondary: t.secondary,
        surface: t.surface,
        error: t.danger,
        onPrimary: t.onPrimary,
        onSurface: t.textPrimary,
        outline: t.border,
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: t.surface,
        foregroundColor: t.textPrimary,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        systemOverlayStyle: brightness == Brightness.dark
            ? SystemUiOverlayStyle.light
            : SystemUiOverlayStyle.dark,
        titleTextStyle: TypeTokens.h3.copyWith(color: t.textPrimary),
      ),
      cardTheme: CardThemeData(
        color: t.card,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: Radii.mdAll,
          side: BorderSide(color: t.border),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: t.navBlur,
        indicatorColor: t.primary.withValues(alpha: 0.18),
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return TypeTokens.label.copyWith(
            color: selected ? t.primary : t.textSecondary,
            fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(color: selected ? t.primary : t.textSecondary, size: 22);
        }),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: t.surfaceAlt,
        contentPadding: const EdgeInsets.symmetric(horizontal: Space.md, vertical: Space.sm),
        border: OutlineInputBorder(
          borderRadius: Radii.mdAll,
          borderSide: BorderSide(color: t.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: Radii.mdAll,
          borderSide: BorderSide(color: t.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: Radii.mdAll,
          borderSide: BorderSide(color: t.primary, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: Radii.mdAll,
          borderSide: BorderSide(color: t.danger),
        ),
        labelStyle: TextStyle(color: t.textSecondary),
        hintStyle: TextStyle(color: t.textMuted),
      ),
      textTheme: textTheme,
      dividerColor: t.border,
      listTileTheme: ListTileThemeData(
        iconColor: t.textSecondary,
        textColor: t.textPrimary,
        minVerticalPadding: Space.sm,
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith((s) {
          return s.contains(WidgetState.selected) ? t.primary : t.textSecondary;
        }),
        trackColor: WidgetStateProperty.resolveWith((s) {
          return s.contains(WidgetState.selected)
              ? t.primary.withValues(alpha: 0.35)
              : t.border;
        }),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: t.primary,
          foregroundColor: t.onPrimary,
          minimumSize: const Size(64, Space.tapTarget),
          padding: const EdgeInsets.symmetric(horizontal: Space.lg, vertical: Space.sm),
          shape: RoundedRectangleBorder(borderRadius: Radii.mdAll),
          textStyle: TypeTokens.button,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: t.textPrimary,
          minimumSize: const Size(64, Space.tapTarget),
          side: BorderSide(color: t.borderStrong),
          shape: RoundedRectangleBorder(borderRadius: Radii.mdAll),
          textStyle: TypeTokens.button,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: t.primary,
          minimumSize: const Size(48, Space.tapTarget),
          textStyle: TypeTokens.button,
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: t.container,
        contentTextStyle: TypeTokens.body.copyWith(color: t.textPrimary),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: Radii.mdAll),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: t.surface,
        modalBackgroundColor: t.surface,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(Radii.xl))),
        showDragHandle: true,
        dragHandleColor: t.borderStrong,
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: t.primary),
      splashFactory: InkRipple.splashFactory,
      extensions: <ThemeExtension<dynamic>>[
        AppMotion(press: Motion.press, page: Motion.page, sheet: Motion.sheet),
      ],
    );
  }
}

/// Theme extension for motion (accessible via Theme.of).
class AppMotion extends ThemeExtension<AppMotion> {
  const AppMotion({
    required this.press,
    required this.page,
    required this.sheet,
  });

  final Duration press;
  final Duration page;
  final Duration sheet;

  @override
  AppMotion copyWith({Duration? press, Duration? page, Duration? sheet}) {
    return AppMotion(
      press: press ?? this.press,
      page: page ?? this.page,
      sheet: sheet ?? this.sheet,
    );
  }

  @override
  AppMotion lerp(ThemeExtension<AppMotion>? other, double t) {
    if (other is! AppMotion) return this;
    return this;
  }
}

/// LCD-style monospace digits for prices.
TextStyle get kLcdDigits => TypeTokens.lcdDigits(color: AppColors.amber);
