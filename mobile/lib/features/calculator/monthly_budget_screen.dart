import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/services/app_providers.dart';
import '../../core/services/app_settings.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/calc_widgets.dart';

class MonthlyBudgetScreen extends ConsumerStatefulWidget {
  const MonthlyBudgetScreen({super.key});

  @override
  ConsumerState<MonthlyBudgetScreen> createState() => _MonthlyBudgetScreenState();
}

class _MonthlyBudgetScreenState extends ConsumerState<MonthlyBudgetScreen> {
  double _dailyDistance = 30;
  double _mileage = 15;
  double _pricePerLitre = 310.71;

  @override
  void initState() {
    super.initState();
    final vehicle = ref.read(selectedVehicleProvider);
    _mileage = vehicle.mileageKmPerL;
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    final rsPerL = '${s('currencyRs')}/${s('unitL')}';
    final dailyLitres = _mileage > 0 ? _dailyDistance / _mileage : 0;
    final dailyCost = dailyLitres * _pricePerLitre;
    final weeklyCost = dailyCost * 7;
    final monthlyCost = dailyCost * 30;
    final yearlyCost = dailyCost * 365;
    final rs = s('currencyRs');

    return Scaffold(
      appBar: AppBar(title: Text(s('monthlyBudget'))),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, Space.navContentClearance),
        children: [
          CalcNumberField(
            label: s('dailyKm'),
            value: _dailyDistance,
            onChanged: (v) => setState(() => _dailyDistance = v),
          ),
          const SizedBox(height: 14),
          CalcNumberField(
            label: s('mileage'),
            value: _mileage,
            onChanged: (v) => setState(() => _mileage = v),
          ),
          const SizedBox(height: 14),
          CalcNumberField(
            label: s('fuelLabel'),
            value: _pricePerLitre,
            suffix: rsPerL,
            onChanged: (v) => setState(() => _pricePerLitre = v),
          ),
          const SizedBox(height: 22),
          CalcOutputCard(rows: [
            (s('totalCost'), '$rs ${dailyCost.toStringAsFixed(0)}'),
            (s('weekly'), '$rs ${weeklyCost.toStringAsFixed(0)}'),
            (s('monthlySpend'), '$rs ${monthlyCost.toStringAsFixed(0)}'),
            (s('yearly'), '$rs ${yearlyCost.toStringAsFixed(0)}'),
          ]),
        ],
      ),
    );
  }
}
