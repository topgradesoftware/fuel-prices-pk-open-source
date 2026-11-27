class AppConfig {
  AppConfig._();

  /// Compile-time override:
  /// `flutter run --dart-define=API_BASE_URL=https://oilprice.topgradesoftware.com/api/v1`
  /// Default is production HTTPS. Use LAN override for device debugging.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://oilprice.topgradesoftware.com/api/v1',
  );

  /// Spec §4.1 — TrackMate (direct fallback; prefer LaravelApiProvider).
  static const String trackMateBaseUrl = 'https://fuel.trackmate.page/api';

  /// Spec §4.1 — recommended local cache lifetime for price data.
  static const Duration priceCacheTtl = Duration(hours: 1);

  /// Spec §4.6 — scheduler runs roughly every 6 hours server-side;
  /// the app itself just refreshes on foreground + pull-to-refresh.
  static const Duration foregroundRefreshInterval = Duration(hours: 6);

  static const String appName = 'Fuel Prices PK';

  /// Public site root, used in share text.
  static const String siteUrl = 'https://oilprice.topgradesoftware.com/';

  /// Public legal pages (same host as the PWA / API origin).
  static const String privacyUrl = String.fromEnvironment(
    'PRIVACY_URL',
    defaultValue: 'https://oilprice.topgradesoftware.com/privacy',
  );

  static const String termsUrl = String.fromEnvironment(
    'TERMS_URL',
    defaultValue: 'https://oilprice.topgradesoftware.com/terms',
  );

  /// Local debug helper — Android emulator → http://10.0.2.2:8010/api/v1
  static const String localDebugApiBaseUrl = 'http://192.168.1.10:8010/api/v1';

  /// Web OAuth **client ID** (public by design — not a client secret).
  /// Same value as server `GOOGLE_CLIENT_ID`. Required on Android as
  /// `GoogleSignIn.initialize(serverClientId: ...)`.
  ///
  /// Prefer build-time injection:
  /// `--dart-define=GOOGLE_SERVER_CLIENT_ID=....apps.googleusercontent.com`
  /// Empty default forces explicit configuration in release CI.
  static const String googleServerClientId = String.fromEnvironment(
    'GOOGLE_SERVER_CLIENT_ID',
    defaultValue: '',
  );
}
