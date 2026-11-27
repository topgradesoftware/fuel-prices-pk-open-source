import 'package:flutter/material.dart';

/// Motion durations and curves for micro-interactions.
class Motion {
  Motion._();

  static const Duration press = Duration(milliseconds: 100);
  static const Duration fast = Duration(milliseconds: 180);
  static const Duration page = Duration(milliseconds: 280);
  static const Duration sheet = Duration(milliseconds: 320);
  static const Duration shimmer = Duration(milliseconds: 1200);
  static const Duration success = Duration(milliseconds: 400);

  static const Curve standard = Curves.easeOutCubic;
  static const Curve emphasized = Curves.easeOutBack;
  static const Curve sheetCurve = Curves.easeOutCubic;

  /// Button press scale.
  static const double pressScale = 0.97;
}
