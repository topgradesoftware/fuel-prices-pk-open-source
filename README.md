# Fuel Prices PK (TopGrade)

Fuel Prices PK helps drivers in Pakistan compare petrol, high-speed diesel (HSD), and LPG prices, find fuel stations, and estimate trip fuel costs. It includes a progressive web app and a Flutter client for Android and iOS. [Open the Fuel Prices PK web app](https://oilprice.topgradesoftware.com/)

**Laravel** is the source of truth for app data. The Flutter client, web app, and admin interface use `/api/v1`. **Firebase** handles authentication, push notifications, crash reporting, and analytics.

## Surfaces

| Audience | URL / Path | Purpose |
|----------|------------|---------|
| **Public PWA** | `https://oilprice.topgradesoftware.com/` | Live prices, stations, garage, calculators, offline cache |
| **Staff ops** | `/admin/login` → `/admin/ops` | Sync, company toggle, manual prices |
| **Legal** | `/privacy` · `/terms` | Store / public legal pages |
| **Flutter** | `mobile/` | Native Android / iOS client |
| **API** | `https://oilprice.topgradesoftware.com/api/v1/*` | Versioned JSON envelope + `X-Request-ID` |

Demo ops: `admin@oilprice.test` / `password`

## Layout

| Path | What |
|------|------|
| `backend/` | Laravel API, PWA, Admin |
| `mobile/` | Flutter app |

## API highlights

- Envelope: `{ success, message, data, meta, errors }`
- Health: `/api/v1/health`, `/status`, `/version`, `/features`
- Auth: guest + Firebase Google → Sanctum (`Apple` / `email` / `OTP` stubs)
- Devices: register / revoke / revoke-all
- Notifications: history + queued FCM via provider abstraction

## Local run (PWA + API)

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan fuel:sync
php artisan serve --host=0.0.0.0 --port=8010
# optional: php artisan queue:work
# optional: npm run dev
```

Phone on same Wi‑Fi: `http://YOUR-LAN-IP:8010/` → Add to Home Screen.

## Flutter

```bash
cd mobile
# Production default API: https://oilprice.topgradesoftware.com/api/v1
flutter pub get
flutterfire configure   # for Google + FCM
flutter run
# Local API override:
# flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8010/api/v1
```

## Contributing

For a price-data report, include the city, fuel type, company, date, and the public source you used to check the price. For a software issue, include steps to reproduce it and what you expected to happen.

After installing the dependencies, run the checks for the part of the project you changed.

```bash
# Laravel API and PWA (from the repository root)
(cd backend && composer test)

# Flutter client (from the repository root)
(cd mobile && flutter analyze && flutter test)
```

In a pull request, describe the change and include the checks you ran.

## License

The project's original code is licensed under the [MIT License](LICENSE). Third-party dependencies carry their own licenses.
