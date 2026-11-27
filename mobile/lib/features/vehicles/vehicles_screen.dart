import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/i18n/strings.dart';
import '../../core/services/app_providers.dart';
import '../../core/services/app_settings.dart';
import '../../core/services/vehicle_store.dart';
import '../../core/theme/app_theme.dart';
import '../../models/vehicle.dart';
import 'widgets/vehicle_card.dart';

class VehiclesScreen extends ConsumerWidget {
  const VehiclesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);
    final vehicles = ref.watch(vehiclesProvider);
    final selected = ref.watch(selectedVehicleProvider);

    return Scaffold(
      appBar: AppBar(title: Text(s('garageTitle'))),
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppColors.amber,
        foregroundColor: const Color(0xFF1A1204),
        onPressed: () => _showVehicleDialog(context, ref, s),
        child: const Icon(Icons.add),
      ),
      body: vehicles.isEmpty
          ? Center(child: Text(s('noVehicles')))
          : ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 90),
              itemCount: vehicles.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, i) {
                final v = vehicles[i];
                return VehicleCard(
                  vehicle: v.copyWith(isDefault: v.id == selected.id),
                  onTap: () async {
                    ref.read(selectedVehicleProvider.notifier).state = v;
                    await VehicleStore.saveSelectedId(v.id);
                  },
                );
              },
            ),
    );
  }

  void _showVehicleDialog(BuildContext context, WidgetRef ref, S s) {
    final nicknameCtrl = TextEditingController();
    final mileageCtrl = TextEditingController();
    final tankCtrl = TextEditingController();
    var type = VehicleType.car;

    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setState) => AlertDialog(
          backgroundColor: AppColors.bgPanel,
          shape: RoundedRectangleBorder(
            borderRadius: Radii.xxlAll,
            side: BorderSide(color: AppColors.border),
          ),
          title: Text(
            s('addVehicle'),
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: AppColors.textHi),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: nicknameCtrl,
                decoration: InputDecoration(
                  labelText: s('nickname'),
                  prefixIcon: const Icon(Icons.edit_outlined, size: 20),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: mileageCtrl,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: s('mileage'),
                  prefixIcon: const Icon(Icons.speed, size: 20),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: tankCtrl,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: s('tankCap'),
                  prefixIcon: const Icon(Icons.local_gas_station_outlined, size: 20),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<VehicleType>(
                initialValue: type,
                dropdownColor: AppColors.bgPanel,
                decoration: InputDecoration(
                  prefixIcon: const Icon(Icons.directions_car_outlined, size: 20),
                ),
                items: [
                  DropdownMenuItem(value: VehicleType.car, child: Text(s('typeCar'))),
                  DropdownMenuItem(value: VehicleType.bike, child: Text(s('typeBike'))),
                  DropdownMenuItem(value: VehicleType.van, child: Text(s('typeVan'))),
                  DropdownMenuItem(value: VehicleType.truck, child: Text(s('typeTruck'))),
                ],
                onChanged: (v) => setState(() => type = v ?? type),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(s('cancel'), style: TextStyle(color: AppColors.textSecondary)),
            ),
            FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.amber,
                foregroundColor: AppColors.onPrimary,
                shape: RoundedRectangleBorder(borderRadius: Radii.mdAll),
              ),
              onPressed: () async {
                if (nicknameCtrl.text.trim().isEmpty) return;
                final vehicle = Vehicle(
                  id: DateTime.now().millisecondsSinceEpoch.toString(),
                  type: type,
                  nickname: nicknameCtrl.text.trim(),
                  mileageKmPerL: double.tryParse(mileageCtrl.text) ?? 15,
                  tankCapacityL: double.tryParse(tankCtrl.text) ?? 40,
                  fuelTypeId: 'petrol',
                );
                final next = [...ref.read(vehiclesProvider), vehicle];
                ref.read(vehiclesProvider.notifier).state = next;
                await VehicleStore.saveVehicles(next);
                if (context.mounted) Navigator.pop(context);
              },
              child: Text(s('save'), style: const TextStyle(fontWeight: FontWeight.w700)),
            ),
          ],
        ),
      ),
    );
  }
}
