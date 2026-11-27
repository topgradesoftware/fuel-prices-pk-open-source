import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/services/app_providers.dart';
import '../../core/services/app_settings.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/calc_widgets.dart';

class FuelCalculatorScreen extends ConsumerStatefulWidget {
  const FuelCalculatorScreen({super.key});

  @override
  ConsumerState<FuelCalculatorScreen> createState() => _FuelCalculatorScreenState();
}

class _FuelCalculatorScreenState extends ConsumerState<FuelCalculatorScreen> {
  double _distance = 50;
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
    final litres = _mileage > 0 ? _distance / _mileage : 0;
    final cost = litres * _pricePerLitre;
    final costPerKm = _distance > 0 ? cost / _distance : 0;

    return Scaffold(
      appBar: AppBar(title: Text(s('fuelCost'))),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, Space.navContentClearance),
        children: [
          CalcNumberField(
            label: s('distance'),
            value: _distance,
            onChanged: (v) => setState(() => _distance = v),
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
            (s('fuelRequired'), '${litres.toStringAsFixed(2)} ${s('unitL')}'),
            (s('totalCost'), '${s('currencyRs')} ${cost.toStringAsFixed(0)}'),
            (s('costPerKm'), '${s('currencyRs')} ${costPerKm.toStringAsFixed(2)}'),
          ]),
        ],
      ),
    );
  }
}
