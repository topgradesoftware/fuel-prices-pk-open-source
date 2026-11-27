import '../network/api_client.dart';
import '../validation/schemas.dart';

/// Station DTO from Laravel `/api/v1/stations` and `/stations/nearby`.
/// Google Places stays on the server — Flutter never holds a Maps API key.
class FuelStation {
  final String id;
  final String name;
  final String? address;
  final double lat;
  final double lng;
  final bool isOpen24h;
  final bool? isOpenNow;
  final double? distanceKm;
  final String? company;
  final String? companyId;
  final String? city;
  final String? cityId;
  final String mapsUrl;
  final String? source;

  const FuelStation({
    required this.id,
    required this.name,
    this.address,
    required this.lat,
    required this.lng,
    required this.isOpen24h,
    this.isOpenNow,
    this.distanceKm,
    this.company,
    this.companyId,
    this.city,
    this.cityId,
    required this.mapsUrl,
    this.source,
  });

  factory FuelStation.fromJson(Map<String, dynamic> json) {
    final lat = (json['lat'] as num?)?.toDouble() ?? 0;
    final lng = (json['lng'] as num?)?.toDouble() ?? 0;
    final apiMaps = (json['maps_url'] as String?)?.trim() ?? '';
    return FuelStation(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? 'Station',
      address: json['address'] as String?,
      lat: lat,
      lng: lng,
      isOpen24h: json['is_open_24h'] as bool? ?? false,
      isOpenNow: json['is_open_now'] as bool?,
      distanceKm: (json['distance_km'] as num?)?.toDouble(),
      company: json['company'] as String?,
      companyId: json['company_id']?.toString(),
      city: json['city'] as String?,
      cityId: json['city_id']?.toString(),
      mapsUrl: apiMaps.isNotEmpty ? apiMaps : directionsUrl(lat, lng),
      source: json['source'] as String?,
    );
  }

  /// External Google Maps directions (opens outside the app).
  static String directionsUrl(double lat, double lng) {
    return 'https://www.google.com/maps/dir/?api=1&destination=$lat,$lng';
  }
}

/// Laravel catalog client — sole path for station data on mobile.
class StationApi {
  StationApi({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  Future<List<FuelStation>> list({
    String? city,
    String? company,
    String? q,
  }) async {
    final data = await _client.get(
      '/stations',
      query: {
        if (parseSlug(city, field: 'city') != null) 'city': parseSlug(city, field: 'city'),
        if (parseSlug(company, field: 'company') != null)
          'company': parseSlug(company, field: 'company'),
        if (parseSearch(q) != null) 'q': parseSearch(q),
        'limit': 100,
      },
    );
    return _parseItems(data);
  }

  /// Near-me: phone sends GPS → server calls Google Places → JSON list back.
  Future<({List<FuelStation> items, String? source})> nearby({
    required double lat,
    required double lng,
    int radius = 5000,
    String? company,
    String? q,
  }) async {
    final query = nearbyQuery(
      lat: lat,
      lng: lng,
      radius: radius,
      limit: 20,
      company: company,
      q: q,
    );
    final data = await _client.get('/stations/nearby', query: query);
    return (
      items: _parseItems(data),
      source: data['source']?.toString(),
    );
  }

  List<FuelStation> _parseItems(Map<String, dynamic> data) {
    final items = data['items'] as List? ?? const [];
    return items
        .map((e) => FuelStation.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }
}
