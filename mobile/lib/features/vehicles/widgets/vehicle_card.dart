import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/services/app_settings.dart';
import '../../../core/theme/app_theme.dart';
import '../../../models/vehicle.dart';

class VehicleCard extends ConsumerWidget {
  const VehicleCard({super.key, required this.vehicle, required this.onTap});

  final Vehicle vehicle;
  final VoidCallback onTap;

  IconData get _icon {
    switch (vehicle.type) {
      case VehicleType.car:
        return Icons.directions_car_outlined;
      case VehicleType.bike:
        return Icons.two_wheeler_outlined;
      case VehicleType.van:
        return Icons.airport_shuttle_outlined;
      case VehicleType.truck:
        return Icons.local_shipping_outlined;
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);

    return Material(
      color: AppColors.bgPanel,
      borderRadius: Radii.xlAll,
      child: InkWell(
        borderRadius: Radii.xlAll,
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            borderRadius: Radii.xlAll,
            border: Border.all(
              color: vehicle.isDefault ? AppColors.amber.withValues(alpha: 0.5) : AppColors.border,
              width: vehicle.isDefault ? 1.5 : 1,
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.06),
                blurRadius: 16,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: AppColors.amber.withValues(alpha: 0.15),
                  borderRadius: Radii.lgAll,
                  border: Border.all(color: AppColors.amber.withValues(alpha: 0.3)),
                ),
                child: Icon(_icon, color: AppColors.amber, size: 24),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          vehicle.nickname,
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 16,
                            color: AppColors.textHi,
                          ),
                        ),
                        if (vehicle.isDefault) ...[
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: AppColors.amber,
                              borderRadius: Radii.pillAll,
                              boxShadow: [
                                BoxShadow(
                                  color: AppColors.amber.withValues(alpha: 0.3),
                                  blurRadius: 6,
                                  offset: const Offset(0, 2),
                                ),
                              ],
                            ),
                            child: Text(
                              s('defaultBadge'),
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                                color: AppColors.onPrimary,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${s('mileage')}: ${vehicle.mileageKmPerL.toStringAsFixed(0)} ${s('unitKmPerL')} · '
                      '${s('tankCap')}: ${vehicle.tankCapacityL.toStringAsFixed(0)} ${s('unitL')}',
                      style: TextStyle(color: AppColors.textSecondary, fontSize: 12.5, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
              Icon(
                Directionality.of(context) == TextDirection.rtl
                    ? Icons.chevron_left
                    : Icons.chevron_right,
                color: AppColors.textSecondary,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
