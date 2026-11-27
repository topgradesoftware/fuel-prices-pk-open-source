/// Mirrors the `cities` entity from Spec §9.1.
class City {
  final String id;
  final String name;
  final String? province;
  final String countryCode;
  final bool isActive;

  const City({
    required this.id,
    required this.name,
    this.province,
    this.countryCode = 'PK',
    this.isActive = true,
  });

  factory City.fromJson(Map<String, dynamic> json) => City(
        id: json['id'].toString(),
        name: json['name'] as String,
        province: json['province'] as String?,
        countryCode: (json['country_code'] ?? json['countryCode'] ?? 'PK').toString(),
        isActive: json['is_active'] as bool? ?? true,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'province': province,
        'country_code': countryCode,
        'is_active': isActive,
      };
}

/// Offline fallback; prefer `/api/v1/cities` (full seeded catalog).
const List<City> kDefaultCities = [
  City(id: 'national', name: 'National'),
  City(id: 'karachi', name: 'Karachi', province: 'Sindh'),
  City(id: 'lahore', name: 'Lahore', province: 'Punjab'),
  City(id: 'islamabad', name: 'Islamabad', province: 'ICT'),
  City(id: 'rawalpindi', name: 'Rawalpindi', province: 'Punjab'),
  City(id: 'faisalabad', name: 'Faisalabad', province: 'Punjab'),
  City(id: 'multan', name: 'Multan', province: 'Punjab'),
  City(id: 'peshawar', name: 'Peshawar', province: 'KPK'),
  City(id: 'quetta', name: 'Quetta', province: 'Balochistan'),
  City(id: 'hyderabad', name: 'Hyderabad', province: 'Sindh'),
  City(id: 'gujranwala', name: 'Gujranwala', province: 'Punjab'),
  City(id: 'sialkot', name: 'Sialkot', province: 'Punjab'),
  City(id: 'bahawalpur', name: 'Bahawalpur', province: 'Punjab'),
  City(id: 'sargodha', name: 'Sargodha', province: 'Punjab'),
  City(id: 'attock', name: 'Attock', province: 'Punjab'),
  City(id: 'jhang', name: 'Jhang', province: 'Punjab'),
  City(id: 'chiniot', name: 'Chiniot', province: 'Punjab'),
  City(id: 'sheikhupura', name: 'Sheikhupura', province: 'Punjab'),
  City(id: 'kasur', name: 'Kasur', province: 'Punjab'),
  City(id: 'okara', name: 'Okara', province: 'Punjab'),
  City(id: 'sahiwal', name: 'Sahiwal', province: 'Punjab'),
  City(id: 'jhelum', name: 'Jhelum', province: 'Punjab'),
  City(id: 'gujrat', name: 'Gujrat', province: 'Punjab'),
  City(id: 'dera-ghazi-khan', name: 'Dera Ghazi Khan', province: 'Punjab'),
  City(id: 'mianwali', name: 'Mianwali', province: 'Punjab'),
  City(id: 'sukkur', name: 'Sukkur', province: 'Sindh'),
  City(id: 'larkana', name: 'Larkana', province: 'Sindh'),
  City(id: 'abbottabad', name: 'Abbottabad', province: 'KPK'),
  City(id: 'mardan', name: 'Mardan', province: 'KPK'),
  City(id: 'swat', name: 'Swat', province: 'KPK'),
  City(id: 'dera-ismail-khan', name: 'Dera Ismail Khan', province: 'KPK'),
  City(id: 'gwadar', name: 'Gwadar', province: 'Balochistan'),
  City(id: 'gilgit', name: 'Gilgit', province: 'GB'),
  City(id: 'muzaffarabad', name: 'Muzaffarabad', province: 'AJK'),
  City(id: 'mirpur', name: 'Mirpur', province: 'AJK'),
];

const List<City> kDefaultCitiesIn = [
  City(id: 'national', name: 'National', countryCode: 'IN'),
  City(id: 'new-delhi', name: 'New Delhi', province: 'Delhi', countryCode: 'IN'),
  City(id: 'mumbai', name: 'Mumbai', province: 'Maharashtra', countryCode: 'IN'),
  City(id: 'bengaluru', name: 'Bengaluru', province: 'Karnataka', countryCode: 'IN'),
  City(id: 'hyderabad', name: 'Hyderabad', province: 'Telangana', countryCode: 'IN'),
  City(id: 'chennai', name: 'Chennai', province: 'Tamil Nadu', countryCode: 'IN'),
  City(id: 'kolkata', name: 'Kolkata', province: 'West Bengal', countryCode: 'IN'),
  City(id: 'pune', name: 'Pune', province: 'Maharashtra', countryCode: 'IN'),
  City(id: 'ahmedabad', name: 'Ahmedabad', province: 'Gujarat', countryCode: 'IN'),
];

const List<City> kDefaultCitiesBd = [
  City(id: 'national', name: 'National', countryCode: 'BD'),
  City(id: 'dhaka', name: 'Dhaka', province: 'Dhaka', countryCode: 'BD'),
  City(id: 'chattogram', name: 'Chattogram', province: 'Chattogram', countryCode: 'BD'),
  City(id: 'khulna', name: 'Khulna', province: 'Khulna', countryCode: 'BD'),
  City(id: 'rajshahi', name: 'Rajshahi', province: 'Rajshahi', countryCode: 'BD'),
  City(id: 'sylhet', name: 'Sylhet', province: 'Sylhet', countryCode: 'BD'),
];

List<City> defaultCitiesForCountry(String code) {
  return switch (code.toUpperCase()) {
    'IN' => kDefaultCitiesIn,
    'BD' => kDefaultCitiesBd,
    _ => kDefaultCities,
  };
}
