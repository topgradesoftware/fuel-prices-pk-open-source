import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/services/app_settings.dart';
import '../../core/theme/app_theme.dart';

class CalculatorHubScreen extends ConsumerWidget {
  const CalculatorHubScreen({super.key});

  static const _routes = [
    (Icons.calculate_outlined, 'fuelCost', '/calculator/fuel'),
    (Icons.local_gas_station_outlined, 'tankFill', '/calculator/tank-fill'),
    (Icons.alt_route_outlined, 'tripCost', '/calculator/trip'),
    (Icons.calendar_month_outlined, 'monthlyBudget', '/calculator/budget'),
  ];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);

    return Scaffold(
      appBar: AppBar(title: Text(s('calculators'))),
      body: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, Space.navContentClearance),
        itemCount: _routes.length,
        separatorBuilder: (_, __) => const SizedBox(height: 14),
        itemBuilder: (context, i) {
          final item = _routes[i];
          return Material(
            color: AppColors.bgPanel,
            borderRadius: Radii.xlAll,
            child: InkWell(
              borderRadius: Radii.xlAll,
              onTap: () => context.push(item.$3),
              child: Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  borderRadius: Radii.xlAll,
                  border: Border.all(color: AppColors.border),
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
                      child: Icon(item.$1, color: AppColors.amber, size: 22),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Text(
                        s(item.$2),
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 16,
                          color: AppColors.textHi,
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: AppColors.bgPanelAlt,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Directionality.of(context) == TextDirection.rtl
                            ? Icons.chevron_left
                            : Icons.chevron_right,
                        color: AppColors.amber,
                        size: 18,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
