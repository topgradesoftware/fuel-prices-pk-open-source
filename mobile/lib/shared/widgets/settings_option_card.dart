import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

/// Selectable settings card (mirrors KMU ThemeSettings item layout).
class SettingsOptionCard extends StatelessWidget {
  const SettingsOptionCard({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected
          ? AppColors.amber.withValues(alpha: 0.12)
          : AppColors.bgPanel,
      borderRadius: Radii.lgAll,
      child: InkWell(
        onTap: onTap,
        borderRadius: Radii.lgAll,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          decoration: BoxDecoration(
            borderRadius: Radii.lgAll,
            border: Border.all(
              color: selected ? AppColors.amber : AppColors.border,
              width: selected ? 1.5 : 1,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: selected
                      ? AppColors.amber.withValues(alpha: 0.2)
                      : AppColors.bgPanelAlt,
                  border: Border.all(
                    color: selected ? AppColors.amber : AppColors.border,
                  ),
                ),
                child: Icon(icon, color: AppColors.amber, size: 22),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                        color: selected ? AppColors.amber : AppColors.textHi,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: TextStyle(color: AppColors.textSecondary, fontSize: 12.5),
                    ),
                  ],
                ),
              ),
              Icon(
                selected ? Icons.check_circle : Icons.circle_outlined,
                color: selected ? AppColors.amber : AppColors.textSecondary,
                size: 22,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
