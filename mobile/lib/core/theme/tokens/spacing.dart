/// Spacing scale (logical pixels).
class Space {
  Space._();

  static const double xxs = 4;
  static const double xs = 8;
  static const double sm = 12;
  static const double md = 16;
  static const double lg = 20;
  static const double xl = 24;
  static const double xxl = 32;
  static const double xxxl = 40;
  static const double huge = 48;
  static const double giant = 64;

  /// Minimum interactive target (WCAG / Apple HIG).
  static const double tapTarget = 48;

  /// Floating nav inset from screen edges.
  static const double navInset = 16;

  /// Floating nav height (excluding safe area).
  static const double navBarHeight = 64;

  /// Extra bottom padding so scroll content clears floating nav.
  static const double navContentClearance = 88;
}
