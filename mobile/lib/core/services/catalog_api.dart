import '../../models/city.dart';
import '../../models/country.dart';
import '../../models/fuel_company.dart';
import '../../models/fuel_type.dart';
import '../network/api_client.dart';
import '../validation/schemas.dart';

class CatalogApi {
  CatalogApi({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  Future<List<Country>> countries() async {
    final data = await _client.get('/countries');
    final items = data['items'] as List? ?? const [];
    return items
        .map((e) => Country.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<List<City>> cities({String? q, String? country}) async {
    final data = await _client.get(
      '/cities',
      query: {
        if (parseSearch(q) != null) 'q': parseSearch(q),
        if (parseCountry(country) != null) 'country': parseCountry(country),
      },
    );
    final items = data['items'] as List? ?? const [];
    final countryCode = (data['country'] ?? country ?? 'PK').toString();
    return items.map((e) {
      final m = Map<String, dynamic>.from(e as Map);
      return City(
        id: (m['slug'] ?? m['id']).toString(),
        name: m['name'] as String,
        province: m['province'] as String?,
        countryCode: countryCode,
      );
    }).toList();
  }

  Future<List<FuelCompany>> companies({String? q, String? country}) async {
    final data = await _client.get(
      '/companies',
      query: {
        if (parseSearch(q) != null) 'q': parseSearch(q),
        if (parseCountry(country) != null) 'country': parseCountry(country),
      },
    );
    final items = data['items'] as List? ?? const [];
    return items.map((e) {
      final m = Map<String, dynamic>.from(e as Map);
      final slug = (m['slug'] ?? m['id']).toString();
      return FuelCompany.fromJson({
        ...m,
        'id': slug,
        'slug': slug,
      });
    }).toList();
  }

  Future<List<FuelType>> fuelTypes({String? q}) async {
    final data = await _client.get(
      '/fuel-types',
      query: {if (parseSearch(q) != null) 'q': parseSearch(q)},
    );
    final items = data['items'] as List? ?? const [];
    return items.map((e) {
      final m = Map<String, dynamic>.from(e as Map);
      final slug = (m['slug'] ?? m['id']).toString();
      return FuelType.fromJson({
        ...m,
        'id': slug,
      });
    }).toList();
  }
}
