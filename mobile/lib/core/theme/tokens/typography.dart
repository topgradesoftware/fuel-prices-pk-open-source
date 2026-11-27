import 'package:flutter/material.dart';

/// Type scale — platform fonts (SF Pro / Roboto) via Material defaults.
class TypeTokens {
  TypeTokens._();

  static const display = TextStyle(
    fontSize: 34,
    fontWeight: FontWeight.w700,
    height: 1.15,
    letterSpacing: -0.5,
  );

  static const h1 = TextStyle(
    fontSize: 28,
    fontWeight: FontWeight.w700,
    height: 1.2,
    letterSpacing: -0.4,
  );

  static const h2 = TextStyle(
    fontSize: 22,
    fontWeight: FontWeight.w700,
    height: 1.25,
    letterSpacing: -0.3,
  );

  static const h3 = TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w600,
    height: 1.3,
    letterSpacing: -0.2,
  );

  static const title = TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.w600,
    height: 1.35,
  );

  static const subtitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w500,
    height: 1.4,
  );

  static const body = TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w400,
    height: 1.45,
  );

  static const caption = TextStyle(
    fontSize: 12.5,
    fontWeight: FontWeight.w400,
    height: 1.35,
  );

  static const label = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w600,
    height: 1.25,
    letterSpacing: 0.2,
  );

  static const button = TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w600,
    height: 1.2,
    letterSpacing: 0.1,
  );

  /// LCD-style monospace digits for fuel prices.
  static TextStyle lcdDigits({Color? color, double size = 28}) => TextStyle(
        fontFamily: 'monospace',
        fontWeight: FontWeight.w700,
        fontSize: size,
        letterSpacing: 0.5,
        height: 1.1,
        color: color,
      );

  static TextTheme materialTheme(Color textPrimary, Color textSecondary) {
    return TextTheme(
      displayLarge: display.copyWith(color: textPrimary),
      displayMedium: h1.copyWith(color: textPrimary),
      displaySmall: h2.copyWith(color: textPrimary),
      headlineMedium: h3.copyWith(color: textPrimary),
      titleLarge: title.copyWith(color: textPrimary),
      titleMedium: subtitle.copyWith(color: textPrimary),
      bodyLarge: body.copyWith(color: textPrimary),
      bodyMedium: body.copyWith(fontSize: 14, color: textPrimary),
      bodySmall: caption.copyWith(color: textSecondary),
      labelLarge: button.copyWith(color: textPrimary),
      labelMedium: label.copyWith(color: textSecondary),
      labelSmall: label.copyWith(fontSize: 11, color: textSecondary),
    );
  }
}
