import 'package:flutter/material.dart';

/// Semantic color tokens — light and dark — for Fuel Prices PK.
class ColorTokens {
  const ColorTokens({
    required this.primary,
    required this.secondary,
    required this.accent,
    required this.success,
    required this.warning,
    required this.danger,
    required this.info,
    required this.background,
    required this.surface,
    required this.surfaceAlt,
    required this.card,
    required this.container,
    required this.textPrimary,
    required this.textSecondary,
    required this.textMuted,
    required this.border,
    required this.borderStrong,
    required this.overlay,
    required this.pressed,
    required this.hover,
    required this.disabled,
    required this.onPrimary,
    required this.navBlur,
  });

  final Color primary;
  final Color secondary;
  final Color accent;
  final Color success;
  final Color warning;
  final Color danger;
  final Color info;
  final Color background;
  final Color surface;
  final Color surfaceAlt;
  final Color card;
  final Color container;
  final Color textPrimary;
  final Color textSecondary;
  final Color textMuted;
  final Color border;
  final Color borderStrong;
  final Color overlay;
  final Color pressed;
  final Color hover;
  final Color disabled;
  final Color onPrimary;
  final Color navBlur;

  /// Price up = red (Pakistan convention: rise hurts drivers).
  Color get up => danger;

  /// Price down = green.
  Color get down => success;

  static const dark = ColorTokens(
    primary: Color(0xFFF5AA3B),
    secondary: Color(0xFFE08E20),
    accent: Color(0xFFF5AA3B),
    success: Color(0xFF3DD68C),
    warning: Color(0xFFF5AA3B),
    danger: Color(0xFFFF4D4D),
    info: Color(0xFF38BDF8),
    background: Color(0xFF070A0E),
    surface: Color(0xFF11161F),
    surfaceAlt: Color(0xFF19212E),
    card: Color(0xFF11161F),
    container: Color(0xFF1E2838),
    textPrimary: Color(0xFFF8FAFC),
    textSecondary: Color(0xFF94A3B8),
    textMuted: Color(0xFF64748B),
    border: Color(0xFF222C3D),
    borderStrong: Color(0xFF334155),
    overlay: Color(0xCC070A0E),
    pressed: Color(0x33F5AA3B),
    hover: Color(0x1AF5AA3B),
    disabled: Color(0xFF334155),
    onPrimary: Color(0xFF0F172A),
    navBlur: Color(0xCC11161F),
  );

  static const light = ColorTokens(
    primary: Color(0xFFD97706),
    secondary: Color(0xFFB45309),
    accent: Color(0xFFD97706),
    success: Color(0xFF10B981),
    warning: Color(0xFFD97706),
    danger: Color(0xFFEF4444),
    info: Color(0xFF0284C7),
    background: Color(0xFFF4F6F9),
    surface: Color(0xFFFFFFFF),
    surfaceAlt: Color(0xFFE2E8F0),
    card: Color(0xFFFFFFFF),
    container: Color(0xFFF8FAFC),
    textPrimary: Color(0xFF0F172A),
    textSecondary: Color(0xFF475569),
    textMuted: Color(0xFF94A3B8),
    border: Color(0xFFE2E8F0),
    borderStrong: Color(0xFFCBD5E1),
    overlay: Color(0x660F172A),
    pressed: Color(0x22D97706),
    hover: Color(0x12D97706),
    disabled: Color(0xFFCBD5E1),
    onPrimary: Color(0xFFFFFFFF),
    navBlur: Color(0xCCFFFFFF),
  );
}
