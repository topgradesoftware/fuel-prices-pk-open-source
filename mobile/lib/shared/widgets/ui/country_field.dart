import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/services/app_providers.dart';
import '../../../core/services/app_settings.dart';
import '../../../core/theme/app_theme.dart';
import '../../../models/city.dart';
import '../../../models/country.dart';
import '../searchable_picker.dart';
import 'city_field.dart';

/// Country picker for multi-country Phase 1 (PK / IN / BD).
class CountryField extends ConsumerWidget {
  const CountryField({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);
    final country = ref.watch(selectedCountryProvider);
    final countriesAsync = ref.watch(countriesProvider);

    return Material(
      color: AppColors.bgPanelAlt,
      borderRadius: Radii.mdAll,
      child: InkWell(
        borderRadius: Radii.mdAll,
        onTap: () async {
          final countries = countriesAsync.value ?? kDefaultCountries;
          final picked = await showSearchablePicker<Country>(
            context: context,
            title: s('selectCountry'),
            searchHint: s('searchCountry'),
            items: countries,
            labelOf: (c) => c.name,
            subtitleOf: (c) => c.currency,
            idOf: (c) => c.code,
            selected: country,
          );
          if (picked == null || picked.code == country.code) return;
          await AppSettings.saveCountry(picked.code);
          ref.read(selectedCountryProvider.notifier).state = picked;
          final cities = defaultCitiesForCountry(picked.code);
          final provisional = cities.firstWhere(
            (c) => c.id != 'national',
            orElse: () => cities.first,
          );
          ref.read(selectedCityProvider.notifier).state = provisional;
          await AppSettings.saveCityId(provisional.id);
          ref.invalidate(citiesProvider);
          ref.invalidate(companiesProvider);
          ref.invalidate(latestPricesProvider);
          ref.invalidate(historyBundleProvider);
          ref.invalidate(locationCityBootstrapProvider);
        },
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: Space.tapTarget),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: Space.md, vertical: Space.sm),
            decoration: BoxDecoration(
              borderRadius: Radii.mdAll,
              border: Border.all(color: AppColors.border),
            ),
            child: Row(
              children: [
                Icon(Icons.public, size: 20, color: AppColors.amber),
                const SizedBox(width: Space.xs),
                Expanded(
                  child: Text(
                    country.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TypeTokens.subtitle.copyWith(color: AppColors.textHi),
                  ),
                ),
                Text(
                  country.currencySymbol,
                  style: TypeTokens.caption.copyWith(color: AppColors.textSecondary),
                ),
                const SizedBox(width: Space.xs),
                Icon(Icons.expand_more, size: 20, color: AppColors.textMuted),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Country + city stack used on Home / Compare / Stations.
class LocationFields extends ConsumerWidget {
  const LocationFields({super.key, this.onCityChanged});

  final ValueChanged<City>? onCityChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Column(
      children: [
        const CountryField(),
        const SizedBox(height: Space.sm),
        CityField(onChanged: onCityChanged),
      ],
    );
  }
}
