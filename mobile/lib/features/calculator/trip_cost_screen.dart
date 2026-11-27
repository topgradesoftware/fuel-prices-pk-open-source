import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/services/app_providers.dart';
import '../../core/services/app_settings.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/calc_widgets.dart';

class TripCostScreen extends ConsumerStatefulWidget {
  const TripCostScreen({super.key});

  @override
  ConsumerState<TripCostScreen> createState() => _TripCostScreenState();
}

class _TripCostScreenState extends ConsumerState<TripCostScreen> {
  double _distance = 120;
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
    final oneWayLitres = _mileage > 0 ? _distance / _mileage : 0;
    final oneWayCost = oneWayLitres * _pricePerLitre;
    final roundTripCost = oneWayCost * 2;

    return Scaffold(
      appBar: AppBar(title: Text(s('tripCost'))),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, Space.navContentClearance),
        children: [
          CalcNumberField(
            label: s('tripDist'),
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
            (s('fuelOneWay'), '${oneWayLitres.toStringAsFixed(2)} ${s('unitL')}'),
            (s('oneWay'), '${s('currencyRs')} ${oneWayCost.toStringAsFixed(0)}'),
            (s('roundTrip'), '${s('currencyRs')} ${roundTripCost.toStringAsFixed(0)}'),
          ]),
        ],
      ),
    );
  }
}
