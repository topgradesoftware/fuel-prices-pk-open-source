import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/services/app_providers.dart';
import '../../core/services/app_settings.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/calc_widgets.dart';

class TankFillScreen extends ConsumerStatefulWidget {
  const TankFillScreen({super.key});

  @override
  ConsumerState<TankFillScreen> createState() => _TankFillScreenState();
}

class _TankFillScreenState extends ConsumerState<TankFillScreen> {
  double _tankCapacity = 42;
  double _currentFuelPercent = 25;
  double _pricePerLitre = 310.71;

  @override
  void initState() {
    super.initState();
    final vehicle = ref.read(selectedVehicleProvider);
    _tankCapacity = vehicle.tankCapacityL;
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    final rsPerL = '${s('currencyRs')}/${s('unitL')}';
    final litresToFill = _tankCapacity * (1 - _currentFuelPercent / 100);
    final cost = litresToFill * _pricePerLitre;

    return Scaffold(
      appBar: AppBar(title: Text(s('tankFill'))),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, Space.navContentClearance),
        children: [
          CalcNumberField(
            label: s('tankCap'),
            value: _tankCapacity,
            onChanged: (v) => setState(() => _tankCapacity = v),
          ),
          const SizedBox(height: 14),
          Text('${s('fuelLevel')}: ${_currentFuelPercent.toStringAsFixed(0)}%'),
          Slider(
            value: _currentFuelPercent,
            min: 0,
            max: 100,
            divisions: 20,
            label: '${_currentFuelPercent.toStringAsFixed(0)}%',
            onChanged: (v) => setState(() => _currentFuelPercent = v),
          ),
          const SizedBox(height: 6),
          CalcNumberField(
            label: s('fuelLabel'),
            value: _pricePerLitre,
            suffix: rsPerL,
            onChanged: (v) => setState(() => _pricePerLitre = v),
          ),
          const SizedBox(height: 22),
          CalcOutputCard(rows: [
            (s('litresToFill'), '${litresToFill.toStringAsFixed(2)} ${s('unitL')}'),
            (s('amountPayable'), '${s('currencyRs')} ${cost.toStringAsFixed(0)}'),
          ]),
        ],
      ),
    );
  }
}
