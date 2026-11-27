# Play Store / App Store — Fuel Prices PK

## Before build

1. Install Flutter SDK (stable).
2. Platform folders are generated (`android/`, `ios/`). Re-run `flutter create .` only if missing.
3. API URL (production default in `lib/core/config/app_config.dart`):
   - Release: `https://oilprice.topgradesoftware.com/api/v1`
   - Local device:  
     `flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8010/api/v1`
4. Legal pages (must be live before store submit):
   - Privacy: `https://oilprice.topgradesoftware.com/privacy`
   - Terms: `https://oilprice.topgradesoftware.com/terms`
5. Run `flutterfire configure` for Firebase Auth + FCM + Crashlytics + Analytics.
6. Do **not** enable Firestore for app data.

## Auth

- Guest → `POST /api/v1/auth/guest` → Sanctum token
- Google → native Google Sign-In ID token (`serverClientId` = Web OAuth client) → `POST /api/v1/auth/google` → Sanctum token
- Optional: `flutter run --dart-define=GOOGLE_SERVER_CLIENT_ID=....apps.googleusercontent.com`
- Apple / Email / OTP → stubs (501) — hide on store builds until Phase B

## Devices + push

- App registers via `POST /api/v1/devices` (UUID + FCM token)
- Server sends FCM on `FuelPriceUpdated` through `notifications` queue
- OS channel id: `price_alerts`

## Build

Flutter SDK on this machine: `C:\flutter\bin` (add to PATH).

```bash
cd mobile
flutter pub get
flutter build apk --release
flutter build appbundle --release
# iOS (macOS + Xcode only):
flutter build ipa --release
```

### Output paths (after successful build)

| Artifact | Path |
|----------|------|
| Installable APK | `build/app/outputs/flutter-apk/app-release.apk` |
| Play Store AAB | `build/app/outputs/bundle/release/app-release.aab` |

- **applicationId:** `com.topgradesoftware.fuelpk`
- **App name:** Fuel Prices PK
- Release currently signs with the **debug keystore** so you can install the APK now. Before Play Console upload, create an upload keystore and wire it in `android/app/build.gradle.kts`.
- Firebase (`google-services.json` / `firebase_options.dart`) is optional for first install — push/Google sign-in stay best-effort until configured.

See root [`docs/CLOUD.md`](../docs/CLOUD.md) for server-side Firebase secrets and Redis workers.
