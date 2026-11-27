/// Strict client-side schemas mirroring backend Form Requests.
class SchemaException implements Exception {
  SchemaException(this.field, this.message);
  final String field;
  final String message;

  @override
  String toString() => '$field: $message';
}

final _slugRe = RegExp(r'^[a-z0-9]+(?:[_-][a-z0-9]+)*$');
final _countryRe = RegExp(r'^[A-Za-z]{2}$');
final _uuidRe = RegExp(
  r'^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
  caseSensitive: false,
);

String? _opt(String? value) {
  if (value == null) return null;
  final t = value.trim();
  return t.isEmpty ? null : t;
}

String? parseSlug(String? value, {String field = 'slug', int max = 64}) {
  final s = _opt(value);
  if (s == null) return null;
  if (s.length > max || !_slugRe.hasMatch(s)) {
    throw SchemaException(field, 'Invalid slug.');
  }
  return s;
}

String? parseCountry(String? value) {
  final s = _opt(value);
  if (s == null) return null;
  if (!_countryRe.hasMatch(s)) {
    throw SchemaException('country', 'Invalid country code.');
  }
  return s.toUpperCase();
}

String? parseSearch(String? value, {String field = 'q', int max = 120}) {
  final s = _opt(value);
  if (s == null) return null;
  if (s.length > max) {
    throw SchemaException(field, 'Max length is $max.');
  }
  return s;
}

String? parseUuid(String? value, {String field = 'uuid'}) {
  final s = _opt(value);
  if (s == null) return null;
  if (!_uuidRe.hasMatch(s)) {
    throw SchemaException(field, 'Invalid UUID.');
  }
  return s;
}

Map<String, dynamic> pricesQuery({
  String? city,
  String? company,
  String? product,
  String? country,
}) {
  return {
    if (parseSlug(city, field: 'city') != null) 'city': parseSlug(city, field: 'city'),
    if (parseSlug(company, field: 'company') != null)
      'company': parseSlug(company, field: 'company'),
    if (parseSlug(product, field: 'product') != null)
      'product': parseSlug(product, field: 'product'),
    if (parseCountry(country) != null) 'country': parseCountry(country),
  };
}

Map<String, dynamic> historyQuery({
  String? product,
  String? company,
  String? country,
  int days = 30,
}) {
  if (days < 1 || days > 365) {
    throw SchemaException('days', 'Days must be 1–365.');
  }
  return {
    if (parseSlug(product, field: 'product') != null)
      'product': parseSlug(product, field: 'product'),
    if (parseSlug(company, field: 'company') != null)
      'company': parseSlug(company, field: 'company'),
    if (parseCountry(country) != null) 'country': parseCountry(country),
    'days': days,
  };
}

Map<String, dynamic> nearbyQuery({
  required double lat,
  required double lng,
  int? radius,
  int limit = 20,
  String? q,
  String? company,
}) {
  if (lat < -90 || lat > 90) {
    throw SchemaException('lat', 'Invalid latitude.');
  }
  if (lng < -180 || lng > 180) {
    throw SchemaException('lng', 'Invalid longitude.');
  }
  if (radius != null && (radius < 500 || radius > 20000)) {
    throw SchemaException('radius', 'Radius must be 500–20000.');
  }
  if (limit < 1 || limit > 20) {
    throw SchemaException('limit', 'Limit must be 1–20.');
  }
  return {
    'lat': lat,
    'lng': lng,
    if (radius != null) 'radius': radius,
    'limit': limit,
    if (parseSearch(q) != null) 'q': parseSearch(q),
    if (parseSlug(company, field: 'company') != null)
      'company': parseSlug(company, field: 'company'),
  };
}

Map<String, dynamic> deviceRegisterBody({
  required String deviceUuid,
  required String platform,
  String? deviceName,
  String? appVersion,
  String? osVersion,
  String? firebaseToken,
  String? locale,
  String? timezone,
  bool? alertsEnabled,
  String? deviceSecret,
}) {
  final uuid = parseUuid(deviceUuid, field: 'device_uuid');
  if (uuid == null) {
    throw SchemaException('device_uuid', 'UUID is required.');
  }
  if (!const {'android', 'ios', 'web'}.contains(platform)) {
    throw SchemaException('platform', 'Invalid platform.');
  }
  if (deviceName != null && deviceName.length > 120) {
    throw SchemaException('device_name', 'Max length is 120.');
  }
  if (appVersion != null && appVersion.length > 40) {
    throw SchemaException('app_version', 'Max length is 40.');
  }
  if (osVersion != null && osVersion.length > 40) {
    throw SchemaException('os_version', 'Max length is 40.');
  }
  if (firebaseToken != null && firebaseToken.length > 4096) {
    throw SchemaException('firebase_token', 'Max length is 4096.');
  }
  if (locale != null && locale.length > 16) {
    throw SchemaException('locale', 'Max length is 16.');
  }
  if (timezone != null && timezone.length > 64) {
    throw SchemaException('timezone', 'Max length is 64.');
  }
  if (deviceSecret != null && deviceSecret.length > 128) {
    throw SchemaException('device_secret', 'Max length is 128.');
  }

  return {
    'device_uuid': uuid,
    'platform': platform,
    if (deviceName != null) 'device_name': deviceName,
    if (appVersion != null) 'app_version': appVersion,
    if (osVersion != null) 'os_version': osVersion,
    if (firebaseToken != null) 'firebase_token': firebaseToken,
    if (locale != null) 'locale': locale,
    if (timezone != null) 'timezone': timezone,
    if (alertsEnabled != null) 'alerts_enabled': alertsEnabled,
    if (deviceSecret != null) 'device_secret': deviceSecret,
  };
}

Map<String, dynamic> googleAuthBody({
  required String idToken,
  String? deviceUuid,
}) {
  if (idToken.length < 20 || idToken.length > 8192) {
    throw SchemaException('id_token', 'Invalid ID token.');
  }
  return {
    'id_token': idToken,
    if (parseUuid(deviceUuid, field: 'device_uuid') != null)
      'device_uuid': parseUuid(deviceUuid, field: 'device_uuid'),
  };
}

double parseCalcNumber(
  String? raw, {
  required String field,
  double min = 0,
  double max = 1e7,
}) {
  final s = _opt(raw);
  if (s == null) {
    throw SchemaException(field, 'Required.');
  }
  final n = double.tryParse(s);
  if (n == null || n < min || n > max) {
    throw SchemaException(field, 'Must be between $min and $max.');
  }
  return n;
}
