# Fuel Prices PK — Mobile UI System (Web Parity)

Flutter mobile app mirrors the **web PWA** information architecture and core UX.

## Information architecture (parity with web)

| Tab | Role |
|-----|------|
| Home | Today’s prices, city picker, main + specialty fuels |
| Compare | OMC comparison |
| History | Charts + ranges |
| Calc | Calculator hub |
| Stations | Near me / By city pumps |
| More | Profile, alerts, theme, language, garage, legal |

## Design tokens

Source: `lib/core/theme/tokens/`

- **Colors** — light + dark petrol/amber system (`ColorTokens`)
- **Typography** — Display → Button (`TypeTokens`)
- **Spacing** — 4–64 + tap target 48 (`Space`)
- **Radii** — sm / md / lg / xl / pill (`Radii`)
- **Elevation** — XS–XL soft shadows (`Elevation`)
- **Motion** — press / page / sheet / shimmer (`Motion`)

`AppColors` remains a thin facade for existing call sites.

## Shared components

| Widget | Path |
|--------|------|
| Floating 6-tab nav | `shared/widgets/app_shell.dart` |
| City field (full width) | `shared/widgets/ui/city_field.dart` |
| Buttons / FAB | `shared/widgets/ui/app_button.dart` |
| Skeleton / empty / error | `shared/widgets/ui/app_states.dart` |
| Searchable picker | `shared/widgets/searchable_picker.dart` |

## Web parity rules

1. **City** — dropdown/sheet only (no city chip row); major cities pinned.
2. **Home fuels** — full-width stacked cards (no horizontal carousel).
3. **Stations** — city change switches to By city; Near me / By city modes.
4. **Bottom nav** — same six destinations as web; floating bar for native polish.
5. **Brand** — amber accent, charcoal/cool surfaces; no purple/cream template look.

## Screen checklist (Phase 1)

- [x] Home — city field, stacked cards, skeleton/error
- [x] Stations — city field, modes, skeleton/empty/error
- [x] More — flat profile header, city field, clearance for floating nav
- [x] Shell — 6-tab floating nav
- [ ] Calc / History visual polish (Phase 2)
- [ ] Auth / onboarding polish (Phase 2)

## Do / Don’t

- Do use tokens (`Space`, `Radii`, `TypeTokens`) for new UI.
- Do keep Compare as its own tab (web parity).
- Don’t add city chip strips.
- Don’t use heavy gradients on profile chrome.
