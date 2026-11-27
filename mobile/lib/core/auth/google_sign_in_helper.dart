import 'package:google_sign_in/google_sign_in.dart';

import '../config/app_config.dart';
import '../network/api_client.dart';

/// One-shot Google Sign-In bootstrap for Android (`serverClientId` required).
class GoogleSignInHelper {
  GoogleSignInHelper._();

  static Future<void>? _initFuture;

  static Future<void> ensureInitialized({ApiClient? client}) {
    return _initFuture ??= _initialize(client: client);
  }

  static Future<void> _initialize({ApiClient? client}) async {
    var serverClientId = AppConfig.googleServerClientId.trim();
    if (serverClientId.isEmpty) {
      serverClientId = await _fetchServerClientId(client ?? ApiClient()) ?? '';
    }
    if (serverClientId.isEmpty) {
      throw StateError(
        'Google Sign-In is not configured (missing serverClientId).',
      );
    }
    await GoogleSignIn.instance.initialize(serverClientId: serverClientId);
  }

  static Future<String?> _fetchServerClientId(ApiClient client) async {
    try {
      final features = await client.get('/features');
      final id = features['google_client_id'];
      if (id is String && id.trim().isNotEmpty) return id.trim();
    } catch (_) {}
    return null;
  }

  /// Returns a Google ID token suitable for `POST /api/v1/auth/google`.
  static Future<String> signInIdToken({ApiClient? client}) async {
    await ensureInitialized(client: client);
    final account = await GoogleSignIn.instance.authenticate();
    final idToken = account.authentication.idToken;
    if (idToken == null || idToken.isEmpty) {
      throw StateError('Google Sign-In did not return an ID token.');
    }
    return idToken;
  }
}
