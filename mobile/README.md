# Fuel Prices PK — Flutter app

Mobile client for the Laravel Oil Price API (`/api/v1`).

## Architecture (standard)

| Layer | Responsibility |
|-------|----------------|
| **Laravel** | Source of truth: prices, catalog, Google Places nearby (`GOOGLE_MAPS_API_KEY` on server only) |
| **Flutter** | UI + device GPS + calls API + opens external Maps for Directions |
| **Not in APK** | No `google_maps_flutter`, no Places API key, no scraping |

**Stations Near me flow**

1. `geolocator` → lat/lng  
2. `GET /api/v1/stations/nearby?lat=&lng=`  
3. List cards from JSON  
4. **Directions** → `url_launcher` → Google Maps app/browser  

**By city** → `GET /api/v1/stations?city=`

## Tabs

Home · Compare · Calc · Stations · Garage · More

## Run / build

```bash
cd mobile
flutter pub get
flutter run
# Production API is default (oilprice.topgradesoftware.com)
# Local: flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8010/api/v1

flutter build apk --release
# → build/app/outputs/flutter-apk/app-release.apk
```

## Related

- Server Maps key: `backend/.env` → `GOOGLE_MAPS_API_KEY`
- Spec / cloud notes: `docs/CLOUD.md`
