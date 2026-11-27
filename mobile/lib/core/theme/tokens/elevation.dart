import 'package:flutter/material.dart';

/// Elevation recipes — prefer border + tonal lift; soft shadows in light only.
class Elevation {
  Elevation._();

  static List<BoxShadow> xs(Brightness brightness) {
    if (brightness == Brightness.dark) return const [];
    return [
      BoxShadow(
        color: const Color(0x0A0B0F14),
        blurRadius: 4,
        offset: const Offset(0, 1),
      ),
    ];
  }

  static List<BoxShadow> sm(Brightness brightness) {
    if (brightness == Brightness.dark) return const [];
    return [
      BoxShadow(
        color: const Color(0x120B0F14),
        blurRadius: 8,
        offset: const Offset(0, 2),
      ),
    ];
  }

  static List<BoxShadow> md(Brightness brightness) {
    if (brightness == Brightness.dark) {
      return [
        BoxShadow(
          color: const Color(0x40000000),
          blurRadius: 16,
          offset: const Offset(0, 6),
        ),
      ];
    }
    return [
      BoxShadow(
        color: const Color(0x180B0F14),
        blurRadius: 16,
        offset: const Offset(0, 6),
      ),
    ];
  }

  static List<BoxShadow> lg(Brightness brightness) {
    return [
      BoxShadow(
        color: Color(brightness == Brightness.dark ? 0x66000000 : 0x220B0F14),
        blurRadius: 28,
        offset: const Offset(0, 12),
      ),
    ];
  }

  static List<BoxShadow> xl(Brightness brightness) {
    return [
      BoxShadow(
        color: Color(brightness == Brightness.dark ? 0x80000000 : 0x2A0B0F14),
        blurRadius: 40,
        offset: const Offset(0, 18),
      ),
    ];
  }

  /// Floating bottom nav shadow.
  static List<BoxShadow> nav(Brightness brightness) => md(brightness);
}
