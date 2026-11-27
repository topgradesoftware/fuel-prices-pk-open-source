import 'package:flutter/material.dart';

/// Corner radius tokens.
class Radii {
  Radii._();

  static const double sm = 10;
  static const double md = 16;
  static const double lg = 20;
  static const double xl = 24;
  static const double xxl = 28;
  static const double pill = 999;

  static BorderRadius get smAll => BorderRadius.circular(sm);
  static BorderRadius get mdAll => BorderRadius.circular(md);
  static BorderRadius get lgAll => BorderRadius.circular(lg);
  static BorderRadius get xlAll => BorderRadius.circular(xl);
  static BorderRadius get xxlAll => BorderRadius.circular(xxl);
  static BorderRadius get pillAll => BorderRadius.circular(pill);

  static BorderRadius get sheetTop =>
      const BorderRadius.vertical(top: Radius.circular(xxl));
}
