enum FuelCategory { petrol, diesel, gas, industrial, other }

/// Mirrors the `fuel_types` entity from Spec §9.1.
class FuelType {
  final String id;
  final String name;
  final FuelCategory category;
  final String unit; // "Rs/L" or "Rs/kg"

  const FuelType({
    required this.id,
    required this.name,
    required this.category,
    required this.unit,
  });

  factory FuelType.fromJson(Map<String, dynamic> json) => FuelType(
        id: json['id'].toString(),
        name: json['name'] as String,
        category: FuelCategory.values.firstWhere(
          (c) => c.name == json['category'],
          orElse: () => FuelCategory.other,
        ),
        unit: json['unit'] as String? ?? 'Rs/L',
      );
}

/// Fallback list; Laravel `/api/v1/fuel-types` is the source of truth.
const List<FuelType> kDefaultFuelTypes = [
  FuelType(id: 'petrol', name: 'Petrol (MS / PMG)', category: FuelCategory.petrol, unit: 'Rs/L'),
  FuelType(id: 'hobc', name: 'HOBC (High Octane)', category: FuelCategory.petrol, unit: 'Rs/L'),
  FuelType(id: 'hi_octane', name: 'Hi-Octane', category: FuelCategory.petrol, unit: 'Rs/L'),
  FuelType(id: 'octane_plus', name: 'Octane+', category: FuelCategory.petrol, unit: 'Rs/L'),
  FuelType(id: 'premium', name: 'Premium Petrol', category: FuelCategory.petrol, unit: 'Rs/L'),
  FuelType(id: 'e10', name: 'E-10 Petrol', category: FuelCategory.petrol, unit: 'Rs/L'),
  FuelType(id: 'hsd', name: 'HSD Diesel', category: FuelCategory.diesel, unit: 'Rs/L'),
  FuelType(id: 'ldo', name: 'Light Diesel Oil', category: FuelCategory.diesel, unit: 'Rs/L'),
  FuelType(id: 'lpg', name: 'LPG', category: FuelCategory.gas, unit: 'Rs/kg'),
  FuelType(id: 'cng', name: 'CNG', category: FuelCategory.gas, unit: 'Rs/kg'),
  FuelType(id: 'kerosene', name: 'Kerosene (SKO)', category: FuelCategory.other, unit: 'Rs/L'),
];
