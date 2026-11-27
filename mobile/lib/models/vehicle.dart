enum VehicleType { car, bike, van, truck }

/// Mirrors the `Vehicle` entity from Spec §9.2 / §3.7.
class Vehicle {
  final String id;
  final VehicleType type;
  final String nickname;
  final double mileageKmPerL;
  final double tankCapacityL;
  final String fuelTypeId;
  final String? registrationNumber;
  final bool isDefault;

  const Vehicle({
    required this.id,
    required this.type,
    required this.nickname,
    required this.mileageKmPerL,
    required this.tankCapacityL,
    required this.fuelTypeId,
    this.registrationNumber,
    this.isDefault = false,
  });

  Vehicle copyWith({
    String? nickname,
    double? mileageKmPerL,
    double? tankCapacityL,
    String? fuelTypeId,
    String? registrationNumber,
    bool? isDefault,
  }) =>
      Vehicle(
        id: id,
        type: type,
        nickname: nickname ?? this.nickname,
        mileageKmPerL: mileageKmPerL ?? this.mileageKmPerL,
        tankCapacityL: tankCapacityL ?? this.tankCapacityL,
        fuelTypeId: fuelTypeId ?? this.fuelTypeId,
        registrationNumber: registrationNumber ?? this.registrationNumber,
        isDefault: isDefault ?? this.isDefault,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'type': type.name,
        'nickname': nickname,
        'mileageKmPerL': mileageKmPerL,
        'tankCapacityL': tankCapacityL,
        'fuelTypeId': fuelTypeId,
        'registrationNumber': registrationNumber,
        'isDefault': isDefault,
      };

  factory Vehicle.fromJson(Map<String, dynamic> json) => Vehicle(
        id: json['id'] as String,
        type: VehicleType.values.firstWhere(
          (e) => e.name == json['type'],
          orElse: () => VehicleType.car,
        ),
        nickname: json['nickname'] as String,
        mileageKmPerL: (json['mileageKmPerL'] as num).toDouble(),
        tankCapacityL: (json['tankCapacityL'] as num).toDouble(),
        fuelTypeId: json['fuelTypeId'] as String? ?? 'petrol',
        registrationNumber: json['registrationNumber'] as String?,
        isDefault: json['isDefault'] as bool? ?? false,
      );
}

const List<Vehicle> kSampleVehicles = [
  Vehicle(id: 'v1', type: VehicleType.car, nickname: 'Honda City', mileageKmPerL: 15, tankCapacityL: 42, fuelTypeId: 'petrol', isDefault: true),
  Vehicle(id: 'v2', type: VehicleType.bike, nickname: 'CD 70', mileageKmPerL: 55, tankCapacityL: 10, fuelTypeId: 'petrol'),
];
