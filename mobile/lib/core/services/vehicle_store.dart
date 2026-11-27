import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';

import '../../models/vehicle.dart';

class VehicleStore {
  static const _boxName = 'fuelpk_prefs';
  static const _vehiclesKey = 'vehicles';
  static const _selectedKey = 'selectedVehicleId';

  static Box get _box => Hive.box(_boxName);

  static List<Vehicle> loadVehicles() {
    final raw = _box.get(_vehiclesKey);
    if (raw is! String || raw.isEmpty) return List.of(kSampleVehicles);
    try {
      final list = jsonDecode(raw) as List;
      final vehicles = list
          .map((e) => Vehicle.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList();
      return vehicles.isEmpty ? List.of(kSampleVehicles) : vehicles;
    } catch (_) {
      return List.of(kSampleVehicles);
    }
  }

  static Future<void> saveVehicles(List<Vehicle> vehicles) async {
    await _box.put(
      _vehiclesKey,
      jsonEncode(vehicles.map((v) => v.toJson()).toList()),
    );
  }

  static String? loadSelectedId() => _box.get(_selectedKey) as String?;

  static Future<void> saveSelectedId(String id) async {
    await _box.put(_selectedKey, id);
  }
}
