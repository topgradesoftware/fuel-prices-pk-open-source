import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/fuel_price.dart';
import '../../../models/fuel_type.dart';

/// Brand accent per fuel — mirrors PWA `FUEL_THEME`.
const Map<String, Color> kFuelAccents = {
  'petrol': Color(0xFF00A651),
  'hobc': Color(0xFFF2A63F),
  'hsd': Color(0xFF1565C0),
  'lpg': Color(0xFF6A1B9A),
  'kerosene': Color(0xFF8D6E63),
  'ldo': Color(0xFF00838F),
  'octane_plus': Color(0xFFF57C00),
  'hi_octane': Color(0xFFE31837),
  'premium': Color(0xFFC9A227),
  'e10': Color(0xFF00ACC1),
  'cng': Color(0xFF455A64),
};

const Color kSpecialtyAccent = Color(0xFF6B7C8F);

Color fuelAccent(String fuelId) =>
    kFuelAccents[fuelId] ?? const Color(0xFFF2A63F);

Color _markInk(Color accent) {
  final luminance = accent.computeLuminance();
  return luminance > 0.65 ? const Color(0xFF0B0F14) : Colors.white;
}

String fuelMark(String name) {
  final buffer = StringBuffer();
  for (final char in name.codeUnits) {
    if ((char >= 48 && char <= 57) || (char >= 65 && char <= 90) || (char >= 97 && char <= 122)) {
      buffer.writeCharCode(char);
    }
  }
  final letters = buffer.toString();
  if (letters.isEmpty) return 'FU';
  return letters.substring(0, letters.length >= 2 ? 2 : 1).toUpperCase();
}

String changeMoodEmoji({
  required bool noPrior,
  required bool flat,
  required bool up,
  required double absChange,
}) {
  if (noPrior || flat) return '😐';
  if (up) {
    if (absChange >= 10) return '😭';
    if (absChange >= 3) return '😡';
    return '😢';
  }
  if (absChange >= 10) return '🥳';
  if (absChange >= 3) return '😄';
  return '🙂';
}

/// Browser-style fuel card: accent edge, initials badge, Rs / litre layout.
class PriceCard extends StatelessWidget {
  const PriceCard({
    super.key,
    required this.fuelType,
    this.price,
    this.displayName,
    this.markOverride,
    this.specialty = false,
    this.unavailableLabel = 'not in feed',
    this.unchangedLabel = 'unchanged',
    this.noPriorLabel = 'no prior',
    this.currencyLabel = 'Rs',
    this.unitLitreLabel = 'litre',
    this.unitKgLabel = 'kg',
  });

  final FuelType fuelType;
  final FuelPrice? price;
  final String? displayName;
  final String? markOverride;
  final bool specialty;
  final String unavailableLabel;
  final String unchangedLabel;
  final String noPriorLabel;
  final String currencyLabel;
  final String unitLitreLabel;
  final String unitKgLabel;

  String get _unitWord {
    final u = fuelType.unit.toLowerCase();
    if (u.contains('kg')) return unitKgLabel;
    return unitLitreLabel;
  }

  @override
  Widget build(BuildContext context) {
    final accent = specialty ? kSpecialtyAccent : fuelAccent(fuelType.id);
    final soft = accent.withValues(alpha: 0.14);
    final hasPrice = price != null && price!.price > 0;
    final edge = Color.lerp(accent, AppColors.border, 0.55)!;
    final title = displayName ?? fuelType.name;
    final mark = markOverride ?? fuelMark(title);

    return Material(
      color: AppColors.bgPanel,
      borderRadius: Radii.xlAll,
      clipBehavior: Clip.antiAlias,
      child: Container(
        width: double.infinity,
        decoration: BoxDecoration(
          borderRadius: Radii.xlAll,
          border: Border.all(color: edge, width: 1.2),
          gradient: LinearGradient(
            begin: AlignmentDirectional.centerStart,
            end: AlignmentDirectional.centerEnd,
            colors: [
              accent.withValues(alpha: 0.25),
              soft,
              AppColors.bgPanel,
            ],
            stops: const [0.0, 0.02, 0.18],
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.08),
              blurRadius: 16,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Container(
                  width: 34,
                  height: 34,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: accent,
                    borderRadius: Radii.mdAll,
                    boxShadow: [
                      BoxShadow(
                        color: accent.withValues(alpha: 0.35),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Text(
                    mark,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: _markInk(accent),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    softWrap: true,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textHi,
                      height: 1.25,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: AlignmentDirectional.centerStart,
              child: hasPrice
                  ? Text.rich(
                      TextSpan(
                        children: [
                          TextSpan(
                            text: '$currencyLabel ',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                              color: AppColors.textSecondary,
                            ),
                          ),
                          TextSpan(
                            text: price!.price.toStringAsFixed(2),
                            style: TextStyle(
                              fontFamily: 'monospace',
                              fontSize: specialty ? 24 : 30,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.5,
                              color: AppColors.textHi,
                            ),
                          ),
                          TextSpan(
                            text: ' / $_unitWord',
                            style: TextStyle(
                              fontSize: 13.5,
                              fontWeight: FontWeight.w500,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    )
                  : Text(
                      '—',
                      style: TextStyle(
                        fontFamily: 'monospace',
                        fontSize: 28,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textSecondary,
                      ),
                    ),
            ),
            const SizedBox(height: 10),
            if (!hasPrice)
              Text(
                '— $unavailableLabel',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
              )
            else
              _ChangeIndicator(
                price: price!,
                unchangedLabel: unchangedLabel,
                noPriorLabel: noPriorLabel,
              ),
          ],
        ),
      ),
    );
  }
}

class _ChangeIndicator extends StatelessWidget {
  const _ChangeIndicator({
    required this.price,
    required this.unchangedLabel,
    required this.noPriorLabel,
  });

  final FuelPrice price;
  final String unchangedLabel;
  final String noPriorLabel;

  @override
  Widget build(BuildContext context) {
    final noPrior = price.previousPrice == null;
    final flat = !noPrior && price.isUnchanged;
    final up = price.isIncrease;
    final abs = price.changeAmount.abs();
    final mood = changeMoodEmoji(
      noPrior: noPrior,
      flat: flat,
      up: up,
      absChange: abs,
    );

    if (noPrior || flat) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: AppColors.bgPanelAlt,
          borderRadius: Radii.pillAll,
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(mood, style: const TextStyle(fontSize: 13)),
            const SizedBox(width: 6),
            Text(
              '— ${noPrior ? noPriorLabel : unchangedLabel}',
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary, fontWeight: FontWeight.w500),
            ),
          ],
        ),
      );
    }

    final color = up ? AppColors.up : AppColors.down;
    final sign = up ? '+' : '';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: Radii.pillAll,
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(mood, style: const TextStyle(fontSize: 13)),
          const SizedBox(width: 6),
          Icon(up ? Icons.trending_up : Icons.trending_down, size: 16, color: color),
          const SizedBox(width: 4),
          Text(
            '$sign${price.changeAmount.toStringAsFixed(2)}',
            style: TextStyle(fontSize: 12, color: color, fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }
}
