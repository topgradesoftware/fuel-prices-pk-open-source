import 'dart:math' as math;

import 'package:geolocator/geolocator.dart';

import '../../models/city.dart';

/// Approx city centers for GPS → city matching (mirrors web App.vue).
const Map<String, List<double>> kCityCoords = {
  'karachi': [24.8607, 67.0011],
  'lahore': [31.5204, 74.3587],
  'islamabad': [33.6844, 73.0479],
  'rawalpindi': [33.5651, 73.0169],
  'faisalabad': [31.4504, 73.135],
  'multan': [30.1575, 71.5249],
  'peshawar': [34.0151, 71.5249],
  'quetta': [30.1798, 66.975],
  'hyderabad': [25.396, 68.3578],
  'gujranwala': [32.1877, 74.1945],
  'sialkot': [32.4945, 74.5229],
  'bahawalpur': [29.3956, 71.6836],
  'sargodha': [32.0836, 72.6711],
  'rahim-yar-khan': [28.4212, 70.2989],
  'sukkur': [27.7052, 68.8574],
  'abbottabad': [34.1688, 73.2215],
  'mardan': [34.1989, 72.0231],
  'attock': [33.7667, 72.36],
  'jhang': [31.2781, 72.3317],
  'chiniot': [31.72, 72.9789],
  'sheikhupura': [31.7131, 73.9783],
  'kasur': [31.1167, 74.45],
  'okara': [30.8081, 73.4458],
  'sahiwal': [30.6708, 73.1064],
  'jhelum': [32.9333, 73.7333],
  'gujrat': [32.5733, 74.0789],
  'dera-ghazi-khan': [30.05, 70.6333],
  'muzaffargarh': [30.0703, 71.1933],
  'mianwali': [32.5853, 71.5436],
  'chakwal': [32.93, 72.8556],
  'murree': [33.907, 73.3903],
  'wah-cantonment': [33.7715, 72.7511],
  'wah': [33.7715, 72.7511],
  'larkana': [27.559, 68.212],
  'mirpur-khas': [25.527, 69.012],
  'nawabshah': [26.2442, 68.41],
  'jacobabad': [28.281, 68.438],
  'thatta': [24.747, 67.924],
  'dera-ismail-khan': [31.831, 70.901],
  'd-i-khan': [31.831, 70.901],
  'bannu': [32.985, 70.604],
  'kohat': [33.587, 71.443],
  'nowshera': [34.015, 71.975],
  'swabi': [34.12, 72.47],
  'mansehra': [34.333, 73.2],
  'haripur': [33.997, 72.935],
  'mingora': [34.7717, 72.36],
  'swat': [35.2227, 72.4258],
  'gwadar': [25.1216, 62.3254],
  'gawadar': [25.1216, 62.3254],
  'turbat': [26.004, 63.043],
  'hub': [25.04, 66.88],
  'chaman': [30.921, 66.45],
  'gilgit': [35.9208, 74.3144],
  'skardu': [35.2971, 75.6339],
  'muzaffarabad': [34.37, 73.471],
  'mirpur': [33.148, 73.752],
  'kotli': [33.518, 73.902],
  'new-delhi': [28.6139, 77.209],
  'mumbai': [19.076, 72.8777],
  'bengaluru': [12.9716, 77.5946],
  'chennai': [13.0827, 80.2707],
  'kolkata': [22.5726, 88.3639],
  'pune': [18.5204, 73.8567],
  'ahmedabad': [23.0225, 72.5714],
  'dhaka': [23.8103, 90.4125],
  'chattogram': [22.3569, 91.7832],
  'khulna': [22.8456, 89.5403],
  'rajshahi': [24.3745, 88.6042],
  'sylhet': [24.8949, 91.8687],
};

double _haversineKm(double aLat, double aLng, double bLat, double bLng) {
  const r = 6371.0;
  final dLat = _toRad(bLat - aLat);
  final dLng = _toRad(bLng - aLng);
  final s = math.sin(dLat / 2) * math.sin(dLat / 2) +
      math.cos(_toRad(aLat)) *
          math.cos(_toRad(bLat)) *
          math.sin(dLng / 2) *
          math.sin(dLng / 2);
  return 2 * r * math.asin(math.sqrt(s));
}

double _toRad(double d) => d * math.pi / 180;

/// Nearest catalog city within [maxKm], or null if none / National-only.
String? nearestCityId(
  double lat,
  double lng,
  List<City> cities, {
  double maxKm = 120,
}) {
  String? bestId;
  var bestKm = double.infinity;
  for (final c in cities) {
    if (c.id == 'national') continue;
    final coords = kCityCoords[c.id];
    if (coords == null) continue;
    final km = _haversineKm(lat, lng, coords[0], coords[1]);
    if (km < bestKm) {
      bestKm = km;
      bestId = c.id;
    }
  }
  if (bestId == null || bestKm > maxKm) return null;
  return bestId;
}

/// Resolve the user's city from GPS. Returns null if denied / unavailable.
Future<String?> detectCityId(List<City> cities) async {
  final serviceOn = await Geolocator.isLocationServiceEnabled();
  if (!serviceOn) return null;

  var permission = await Geolocator.checkPermission();
  if (permission == LocationPermission.denied) {
    permission = await Geolocator.requestPermission();
  }
  if (permission == LocationPermission.denied ||
      permission == LocationPermission.deniedForever) {
    return null;
  }

  try {
    final pos = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.medium,
        timeLimit: Duration(seconds: 12),
      ),
    );
    return nearestCityId(pos.latitude, pos.longitude, cities);
  } catch (_) {
    return null;
  }
}
