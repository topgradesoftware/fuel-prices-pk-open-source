import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

class CalcNumberField extends StatelessWidget {
  const CalcNumberField({
    super.key,
    required this.label,
    required this.value,
    required this.onChanged,
    this.suffix,
  });

  final String label;
  final double value;
  final ValueChanged<double> onChanged;
  final String? suffix;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.bgPanelAlt,
        borderRadius: Radii.lgAll,
        border: Border.all(color: AppColors.border),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: TextFormField(
        initialValue: value == 0 ? '' : _trim(value),
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        style: TextStyle(
          fontFamily: 'monospace',
          fontSize: 18,
          fontWeight: FontWeight.w700,
          color: AppColors.textHi,
        ),
        decoration: InputDecoration(
          border: InputBorder.none,
          enabledBorder: InputBorder.none,
          focusedBorder: InputBorder.none,
          fillColor: Colors.transparent,
          labelText: label,
          labelStyle: TextStyle(color: AppColors.textSecondary, fontSize: 13),
          suffixText: suffix,
          suffixStyle: TextStyle(
            color: AppColors.amber,
            fontWeight: FontWeight.w600,
            fontSize: 13,
          ),
        ),
        onChanged: (v) => onChanged(double.tryParse(v) ?? 0),
      ),
    );
  }

  String _trim(double v) => v == v.roundToDouble() ? v.toStringAsFixed(0) : v.toString();
}

class CalcOutputCard extends StatelessWidget {
  const CalcOutputCard({super.key, required this.rows});

  final List<(String, String)> rows;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.bgPanel,
        borderRadius: Radii.xxlAll,
        border: Border.all(color: AppColors.amber.withValues(alpha: 0.35)),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.amber.withValues(alpha: 0.12),
            AppColors.bgPanel,
          ],
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.amber.withValues(alpha: 0.08),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        children: [
          for (var i = 0; i < rows.length; i++) ...[
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    rows[i].$1,
                    style: TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 13.5,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  Text(
                    rows[i].$2,
                    style: TextStyle(
                      color: AppColors.amber,
                      fontFamily: 'monospace',
                      fontWeight: FontWeight.w700,
                      fontSize: 18,
                    ),
                  ),
                ],
              ),
            ),
            if (i < rows.length - 1)
              Divider(height: 1, color: AppColors.border.withValues(alpha: 0.5)),
          ],
        ],
      ),
    );
  }
}
