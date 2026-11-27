import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/services/app_providers.dart';
import '../../../core/services/app_settings.dart';
import '../../../core/theme/app_theme.dart';
import '../../../models/city.dart';
import '../searchable_picker.dart';

/// Full-width city control matching the web sticky city SearchSelect.
class CityField extends ConsumerWidget {
  const CityField({super.key, this.onChanged});

  final ValueChanged<City>? onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);
    final city = ref.watch(selectedCityProvider);
    final citiesAsync = ref.watch(citiesProvider);

    return Container(
      decoration: BoxDecoration(
        color: AppColors.bgPanelAlt,
        borderRadius: Radii.lgAll,
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: AppColors.amber.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: Radii.lgAll,
        child: InkWell(
          borderRadius: Radii.lgAll,
          onTap: () async {
            final country = ref.read(selectedCountryProvider);
            final fallback = defaultCitiesForCountry(country.code);
            final cities = sortCitiesForPicker(
              citiesAsync.value ?? fallback,
              countryCode: country.code,
            );
            final picked = await showSearchablePicker<City>(
              context: context,
              title: s('selectCity'),
              searchHint: s('searchCity'),
              items: cities,
              labelOf: (c) => c.name,
              subtitleOf: (c) => c.province,
              idOf: (c) => c.id,
              selected: city,
              pinIds: pinnedCityIdsFor(country.code),
            );
            if (picked != null) {
              ref.read(selectedCityProvider.notifier).state = picked;
              await AppSettings.saveCityId(picked.id);
              onChanged?.call(picked);
            }
          },
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: Space.md, vertical: 12),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: AppColors.amber.withValues(alpha: 0.15),
                    borderRadius: Radii.mdAll,
                  ),
                  child: Icon(Icons.location_on, size: 18, color: AppColors.amber),
                ),
                const SizedBox(width: Space.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        city.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TypeTokens.title.copyWith(
                          color: AppColors.textHi,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if (city.province != null && city.province!.isNotEmpty) ...[
                        const SizedBox(height: 1),
                        Text(
                          city.province!,
                          style: TypeTokens.caption.copyWith(color: AppColors.textSecondary),
                        ),
                      ],
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.bgPanel,
                    borderRadius: Radii.pillAll,
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        s('change'),
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: AppColors.amber,
                        ),
                      ),
                      const SizedBox(width: 2),
                      Icon(Icons.unfold_more, size: 14, color: AppColors.amber),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Major cities pinned at top of picker (matches web dropdown order).
const List<String> kPinnedCityIds = [
  'national',
  'karachi',
  'lahore',
  'islamabad',
  'rawalpindi',
  'peshawar',
  'quetta',
  'multan',
  'faisalabad',
];

const List<String> kPinnedCityIdsIn = [
  'national',
  'new-delhi',
  'mumbai',
  'bengaluru',
  'hyderabad',
  'chennai',
  'kolkata',
  'pune',
];

const List<String> kPinnedCityIdsBd = [
  'national',
  'dhaka',
  'chattogram',
  'khulna',
  'rajshahi',
  'sylhet',
];

List<String> pinnedCityIdsFor(String countryCode) {
  return switch (countryCode.toUpperCase()) {
    'IN' => kPinnedCityIdsIn,
    'BD' => kPinnedCityIdsBd,
    _ => kPinnedCityIds,
  };
}

List<City> sortCitiesForPicker(List<City> cities, {String countryCode = 'PK'}) {
  final pins = pinnedCityIdsFor(countryCode);
  final rank = {for (var i = 0; i < pins.length; i++) pins[i]: i};
  final copy = [...cities];
  copy.sort((a, b) {
    final pa = rank[a.id];
    final pb = rank[b.id];
    if (pa != null || pb != null) {
      return (pa ?? 999).compareTo(pb ?? 999);
    }
    return a.name.toLowerCase().compareTo(b.name.toLowerCase());
  });
  return copy;
}
