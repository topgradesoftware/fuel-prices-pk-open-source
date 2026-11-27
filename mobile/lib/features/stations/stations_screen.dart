import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:geolocator/geolocator.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/services/app_providers.dart';
import '../../core/services/app_settings.dart';
import '../../core/services/station_api.dart';
import '../../core/theme/app_theme.dart';
import '../../models/fuel_company.dart';
import '../../shared/widgets/searchable_picker.dart';
import '../../shared/widgets/ui/app_button.dart';
import '../../shared/widgets/ui/app_states.dart';
import '../../shared/widgets/ui/country_field.dart';

final _stationCompanyFilterProvider = StateProvider<String?>((ref) => null);
final _stationQueryProvider = StateProvider<String>((ref) => '');
final _useNearbyProvider = StateProvider<bool>((ref) => true);

class StationsResult {
  const StationsResult({
    required this.stations,
    required this.mode,
    this.noticeKey,
  });

  final List<FuelStation> stations;
  final String mode;
  final String? noticeKey;
}

final stationsProvider = FutureProvider.autoDispose<StationsResult>((ref) async {
  final city = ref.watch(selectedCityProvider);
  final company = ref.watch(_stationCompanyFilterProvider);
  final useNearby = ref.watch(_useNearbyProvider);
  final api = StationApi();

  if (useNearby) {
    final serviceOn = await Geolocator.isLocationServiceEnabled();
    if (!serviceOn) {
      final cityList = await api.list(city: city.id, company: company);
      return StationsResult(
        stations: cityList,
        mode: 'city',
        noticeKey: 'locationFailed',
      );
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      final cityList = await api.list(city: city.id, company: company);
      return StationsResult(
        stations: cityList,
        mode: 'city',
        noticeKey: 'locationBlocked',
      );
    }

    try {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 15),
        ),
      );
      final nearby = await api.nearby(
        lat: pos.latitude,
        lng: pos.longitude,
        company: company,
      );
      return StationsResult(
        stations: nearby.items,
        mode: 'nearby',
        noticeKey: null,
      );
    } catch (e) {
      final cityList = await api.list(city: city.id, company: company);
      return StationsResult(
        stations: cityList,
        mode: 'city',
        noticeKey: 'nearbyLoadFailed',
      );
    }
  }

  final cityList = await api.list(city: city.id, company: company);
  return StationsResult(
    stations: cityList,
    mode: 'city',
  );
});

class StationsScreen extends ConsumerStatefulWidget {
  const StationsScreen({super.key});

  @override
  ConsumerState<StationsScreen> createState() => _StationsScreenState();
}

class _StationsScreenState extends ConsumerState<StationsScreen> {
  final _queryController = TextEditingController();

  @override
  void dispose() {
    _queryController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    final stationsAsync = ref.watch(stationsProvider);
    final companyFilter = ref.watch(_stationCompanyFilterProvider);
    final companiesAsync = ref.watch(companiesProvider);
    final useNearby = ref.watch(_useNearbyProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(s('stations')),
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(stationsProvider),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(Space.md, Space.sm, Space.md, Space.xs),
              child: LocationFields(
                onCityChanged: (_) {
                  ref.read(_useNearbyProvider.notifier).state = false;
                  ref.invalidate(stationsProvider);
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(Space.md, Space.xs, Space.md, Space.xs),
              child: Row(
                children: [
                  Expanded(
                    child: _ModeChip(
                      label: s('useMyLocation'),
                      selected: useNearby,
                      onTap: () {
                        ref.read(_useNearbyProvider.notifier).state = true;
                        ref.invalidate(stationsProvider);
                      },
                    ),
                  ),
                  const SizedBox(width: Space.xs),
                  Expanded(
                    child: _ModeChip(
                      label: s('cityStations'),
                      selected: !useNearby,
                      onTap: () {
                        ref.read(_useNearbyProvider.notifier).state = false;
                        ref.invalidate(stationsProvider);
                      },
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(Space.md, 0, Space.md, Space.xs),
              child: TextField(
                controller: _queryController,
                onChanged: (v) => ref.read(_stationQueryProvider.notifier).state = v,
                decoration: InputDecoration(
                  hintText: s('searchLocation'),
                  prefixIcon: const Icon(Icons.search, size: 20),
                  suffixIcon: companyFilter == null
                      ? IconButton(
                          tooltip: s('companyLabel'),
                          onPressed: () async {
                            final companies =
                                companiesAsync.value ?? const <FuelCompany>[];
                            if (companies.isEmpty) return;
                            final picked = await showSearchablePicker<FuelCompany>(
                              context: context,
                              title: s('companyLabel'),
                              searchHint: s('searchCompany'),
                              items: companies,
                              labelOf: (c) => c.shortName,
                              subtitleOf: (c) => c.name,
                              idOf: (c) => c.slug,
                            );
                            if (picked != null) {
                              ref.read(_stationCompanyFilterProvider.notifier).state =
                                  picked.slug;
                            }
                          },
                          icon: const Icon(Icons.business_outlined),
                        )
                      : IconButton(
                          tooltip: s('clear'),
                          onPressed: () =>
                              ref.read(_stationCompanyFilterProvider.notifier).state = null,
                          icon: const Icon(Icons.clear),
                        ),
                ),
              ),
            ),
            Expanded(
              child: stationsAsync.when(
                loading: () => const AppSkeletonList(count: 6),
                error: (e, _) => AppErrorState(
                  title: s('nearbyLoadFailed'),
                  message: s('retry'),
                  retryLabel: s('retry'),
                  onRetry: () => ref.invalidate(stationsProvider),
                ),
                data: (result) {
                  final q = ref.watch(_stationQueryProvider).trim().toLowerCase();
                  final filtered = q.isEmpty
                      ? result.stations
                      : result.stations.where((st) {
                          final hay = [
                            st.name,
                            st.address ?? '',
                            st.company ?? '',
                            st.city ?? '',
                            st.companyId ?? '',
                          ].join(' ').toLowerCase();
                          return hay.contains(q);
                        }).toList();

                  return Column(
                    children: [
                      if (result.noticeKey != null)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(Space.md, 0, Space.md, Space.xs),
                          child: Container(
                            width: double.infinity,
                            padding: const EdgeInsets.symmetric(
                              horizontal: Space.sm,
                              vertical: Space.xs,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.bgPanelAlt,
                              borderRadius: Radii.mdAll,
                              border: Border.all(color: AppColors.border),
                            ),
                            child: Text(
                              s(result.noticeKey!),
                              style: TypeTokens.caption.copyWith(color: AppColors.textSecondary),
                            ),
                          ),
                        ),
                      Expanded(child: _StationList(stations: filtered)),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ModeChip extends StatelessWidget {
  const _ModeChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? AppColors.amber.withValues(alpha: 0.18) : AppColors.bgPanelAlt,
      borderRadius: Radii.pillAll,
      child: InkWell(
        onTap: onTap,
        borderRadius: Radii.pillAll,
        child: Container(
          alignment: Alignment.center,
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
          decoration: BoxDecoration(
            borderRadius: Radii.pillAll,
            border: Border.all(
              color: selected ? AppColors.amber : AppColors.border,
            ),
          ),
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 13,
              fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              color: selected ? AppColors.amber : AppColors.textHi,
            ),
          ),
        ),
      ),
    );
  }
}

class _StationList extends ConsumerWidget {
  const _StationList({required this.stations});
  final List<FuelStation> stations;

  Future<void> _openDirections(FuelStation st) async {
    final url = st.mapsUrl.isNotEmpty
        ? st.mapsUrl
        : FuelStation.directionsUrl(st.lat, st.lng);
    final uri = Uri.parse(url);
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);

    if (stations.isEmpty) {
      return AppEmptyState(
        title: s('noStations'),
        icon: Icons.local_gas_station_outlined,
        actionLabel: s('retry'),
        onAction: () => ref.invalidate(stationsProvider),
      );
    }

    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(Space.md, Space.xs, Space.md, Space.navContentClearance),
      itemCount: stations.length,
      separatorBuilder: (_, __) => const SizedBox(height: Space.sm),
      itemBuilder: (context, i) {
        final st = stations[i];
        return Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.bgPanel,
            borderRadius: Radii.xlAll,
            border: Border.all(color: AppColors.border),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.06),
                blurRadius: 16,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      st.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textHi,
                      ),
                    ),
                  ),
                  if (st.distanceKm != null) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppColors.amber.withValues(alpha: 0.18),
                        borderRadius: Radii.pillAll,
                        border: Border.all(color: AppColors.amber.withValues(alpha: 0.35)),
                      ),
                      child: Text(
                        '${st.distanceKm!.toStringAsFixed(1)} ${s('unitKm')}',
                        style: TextStyle(
                          color: AppColors.amber,
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  if (st.isOpenNow != null) ...[
                    Container(
                      width: 8,
                      height: 8,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: st.isOpenNow! ? AppColors.down : AppColors.up,
                      ),
                    ),
                    const SizedBox(width: 6),
                  ],
                  Expanded(
                    child: Text(
                      [
                        st.company ?? '—',
                        if (st.city != null && st.city!.isNotEmpty) st.city!,
                        if (st.isOpenNow == true) s('openNow'),
                        if (st.isOpenNow == false) s('closedNow'),
                      ].join(' · '),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TypeTokens.caption.copyWith(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                ],
              ),
              if (st.address != null && st.address!.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(
                  st.address!,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TypeTokens.caption.copyWith(color: AppColors.textMuted),
                ),
              ],
              const SizedBox(height: 12),
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: AppButton(
                  label: s('directions'),
                  icon: Icons.near_me,
                  variant: AppButtonVariant.primary,
                  onPressed: () => _openDirections(st),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
