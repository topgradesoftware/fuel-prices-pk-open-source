import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/services/app_providers.dart';
import '../../core/services/app_settings.dart';
import '../../core/services/share_service.dart';
import '../../core/theme/app_theme.dart';
import '../../models/fuel_price.dart';
import '../../models/fuel_type.dart';
import '../../shared/widgets/ui/app_states.dart';
import '../../shared/widgets/ui/country_field.dart';
import 'widgets/price_card.dart';

class _HomeCard {
  const _HomeCard({
    required this.id,
    required this.name,
    required this.mark,
    required this.fuelType,
    required this.price,
  });

  final String id;
  final String name;
  final String mark;
  final FuelType fuelType;
  final FuelPrice? price;
}

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  static const _primaryIds = ['petrol', 'hsd', 'lpg'];
  static const _octaneIds = ['hobc', 'octane_plus', 'hi_octane', 'premium', 'super'];
  static const _otherIds = ['kerosene', 'ldo', 'e10', 'cng'];

  static const _octaneShort = {
    'hobc': 'HOBC',
    'octane_plus': 'Octane+',
    'hi_octane': 'Hi-Octane',
    'premium': 'Premium',
    'super': 'Super',
  };

  FuelType? _fuelById(String id) {
    for (final f in kDefaultFuelTypes) {
      if (f.id == id) return f;
    }
    return null;
  }

  List<_HomeCard> _primaryCards(Map<String, FuelPrice> byFuel) {
    final cards = <_HomeCard>[];
    for (final id in _primaryIds) {
      final fuel = _fuelById(id);
      final price = byFuel[id];
      if (fuel == null || price == null || price.price <= 0) continue;
      cards.add(_HomeCard(
        id: id,
        name: fuel.name,
        mark: fuelMark(fuel.name),
        fuelType: fuel,
        price: price,
      ));
    }
    return cards;
  }

  List<_HomeCard> _specialtyCards(Map<String, FuelPrice> byFuel) {
    final cards = <_HomeCard>[];

    final octaneRows = <({String id, FuelType fuel, FuelPrice price})>[];
    for (final id in _octaneIds) {
      final fuel = _fuelById(id);
      final price = byFuel[id];
      if (fuel == null || price == null || price.price <= 0) continue;
      octaneRows.add((id: id, fuel: fuel, price: price));
    }

    final byPrice = <String, List<({String id, FuelType fuel, FuelPrice price})>>{};
    for (final row in octaneRows) {
      final key = row.price.price.toStringAsFixed(2);
      byPrice.putIfAbsent(key, () => []).add(row);
    }

    final groups = byPrice.values.toList()
      ..sort((a, b) => _octaneIds.indexOf(a.first.id) - _octaneIds.indexOf(b.first.id));

    for (final group in groups) {
      group.sort((a, b) => _octaneIds.indexOf(a.id) - _octaneIds.indexOf(b.id));
      final lead = group.first;
      final name = group.length == 1
          ? lead.fuel.name
          : group.map((g) => _octaneShort[g.id] ?? g.fuel.name).join(' / ');
      cards.add(_HomeCard(
        id: lead.id,
        name: name,
        mark: group.length == 1 ? fuelMark(lead.fuel.name) : 'HO',
        fuelType: lead.fuel,
        price: lead.price,
      ));
    }

    for (final id in _otherIds) {
      final fuel = _fuelById(id);
      final price = byFuel[id];
      if (fuel == null || price == null || price.price <= 0) continue;
      cards.add(_HomeCard(
        id: id,
        name: fuel.name,
        mark: fuelMark(fuel.name),
        fuelType: fuel,
        price: price,
      ));
    }

    return cards;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);
    final currency = ref.watch(currencyLabelProvider);
    final city = ref.watch(selectedCityProvider);
    final pricesAsync = ref.watch(latestPricesProvider);
    final today = DateFormat('d MMM yyyy').format(DateTime.now());

    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => ref.refresh(latestPricesProvider.future),
          child: pricesAsync.when(
            loading: () => const AppSkeletonList(count: 5),
            error: (err, _) => AppErrorState(
              title: s('loadPricesFailed'),
              message: s('retry'),
              retryLabel: s('retry'),
              onRetry: () => ref.invalidate(latestPricesProvider),
            ),
            data: (result) {
              final byFuel = <String, FuelPrice>{};
              for (final p in result.prices) {
                if (p.companyId == 'pso') {
                  byFuel[p.fuelTypeId] = p;
                }
              }
              for (final p in result.prices) {
                byFuel.putIfAbsent(p.fuelTypeId, () => p);
              }

              final primary = _primaryCards(byFuel);
              final specialty = _specialtyCards(byFuel);

              return ListView(
                padding: const EdgeInsets.fromLTRB(
                  Space.md,
                  Space.sm,
                  Space.md,
                  Space.navContentClearance,
                ),
                children: [
                  Text(
                    s('todayPrices'),
                    style: TypeTokens.h2.copyWith(color: AppColors.textHi),
                  ),
                  const SizedBox(height: Space.xs),
                  Text(
                    '${s('effective')} $today · ${city.name}',
                    style: TypeTokens.caption.copyWith(color: AppColors.textSecondary),
                  ),
                  const SizedBox(height: Space.md),
                  const LocationFields(),
                  if (result.fromCache) ...[
                    const SizedBox(height: Space.sm),
                    _OfflineBanner(label: s('offlineCached'), cachedAt: result.cachedAt),
                  ],
                  if (primary.isNotEmpty) ...[
                    const SizedBox(height: Space.lg),
                    Text(
                      s('mainFuels').toUpperCase(),
                      style: TypeTokens.label.copyWith(color: AppColors.textSecondary),
                    ),
                    const SizedBox(height: Space.sm),
                    _EffectiveDateCard(label: s('effective'), value: today),
                    const SizedBox(height: Space.sm),
                    for (final card in primary) ...[
                      PriceCard(
                        fuelType: card.fuelType,
                        price: card.price,
                        displayName: card.name,
                        markOverride: card.mark,
                        unavailableLabel: s('unavailable'),
                        unchangedLabel: s('unchanged').toLowerCase(),
                        noPriorLabel: s('noPriorChange'),
                        currencyLabel: currency,
                        unitLitreLabel: s('unitLitre'),
                        unitKgLabel: s('unitKg'),
                      ),
                      const SizedBox(height: Space.sm),
                    ],
                  ],
                  if (specialty.isNotEmpty) ...[
                    const SizedBox(height: Space.sm),
                    Text(
                      s('specialtyFuels').toUpperCase(),
                      style: TypeTokens.label.copyWith(color: AppColors.textSecondary),
                    ),
                    const SizedBox(height: Space.sm),
                    for (final card in specialty) ...[
                      PriceCard(
                        fuelType: card.fuelType,
                        price: card.price,
                        displayName: card.name,
                        markOverride: card.mark,
                        specialty: true,
                        unavailableLabel: s('unavailable'),
                        unchangedLabel: s('unchanged').toLowerCase(),
                        noPriorLabel: s('noPriorChange'),
                        currencyLabel: currency,
                        unitLitreLabel: s('unitLitre'),
                        unitKgLabel: s('unitKg'),
                      ),
                      const SizedBox(height: Space.sm),
                    ],
                  ],
                  _ShareCard(
                    title: s('share'),
                    lead: s('shareCardLead'),
                    sub: s('sharePricesSub'),
                    onTap: () => ShareService.sharePrices(
                      result.prices,
                      s,
                      currencySymbol: currency,
                    ),
                  ),
                  const SizedBox(height: Space.lg),
                  Text(
                    s('quickAccess'),
                    style: TypeTokens.title.copyWith(color: AppColors.textHi),
                  ),
                  const SizedBox(height: Space.sm),
                  const _QuickAccessGrid(),
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class _EffectiveDateCard extends StatelessWidget {
  const _EffectiveDateCard({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.bgPanel,
        borderRadius: Radii.lgAll,
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: AppColors.amber.withValues(alpha: 0.15),
                  borderRadius: Radii.smAll,
                ),
                child: Icon(Icons.event, size: 16, color: AppColors.amber),
              ),
              const SizedBox(width: 8),
              Text(
                label.toUpperCase(),
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.8,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            value,
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: AppColors.textHi,
            ),
          ),
        ],
      ),
    );
  }
}

class _ShareCard extends StatelessWidget {
  const _ShareCard({
    required this.title,
    required this.lead,
    required this.sub,
    required this.onTap,
  });

  final String title;
  final String lead;
  final String sub;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.bgPanelAlt,
      borderRadius: Radii.xlAll,
      child: InkWell(
        onTap: onTap,
        borderRadius: Radii.xlAll,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            borderRadius: Radii.xlAll,
            border: Border.all(color: AppColors.amber.withValues(alpha: 0.3)),
            gradient: LinearGradient(
              colors: [
                AppColors.amber.withValues(alpha: 0.1),
                AppColors.bgPanelAlt,
              ],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.amber,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.amber.withValues(alpha: 0.35),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Icon(Icons.ios_share, size: 20, color: AppColors.onPrimary),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      lead,
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textHi,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(sub, style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OfflineBanner extends StatelessWidget {
  const _OfflineBanner({required this.label, this.cachedAt});
  final String label;
  final DateTime? cachedAt;

  @override
  Widget build(BuildContext context) {
    final when = cachedAt != null
        ? DateFormat('d MMM · HH:mm').format(cachedAt!.toLocal())
        : '—';
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.amber.withValues(alpha: 0.12),
        borderRadius: Radii.lgAll,
        border: Border.all(color: AppColors.amber.withValues(alpha: 0.35)),
      ),
      child: Row(
        children: [
          Icon(Icons.cloud_off, size: 18, color: AppColors.amber),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              '$label ($when)',
              style: TextStyle(color: AppColors.amber, fontSize: 13, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

class _QuickAccessGrid extends ConsumerWidget {
  const _QuickAccessGrid();

  static const _items = [
    (Icons.calculate_outlined, 'fuelCost', '/calculator/fuel'),
    (Icons.local_gas_station_outlined, 'tankFill', '/calculator/tank-fill'),
    (Icons.alt_route_outlined, 'tripCost', '/calculator/trip'),
    (Icons.map_outlined, 'stationsTitle', '/stations'),
    (Icons.directions_car_outlined, 'garageTitle', '/garage'),
  ];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);

    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        for (final item in _items)
          Material(
            color: AppColors.bgPanelAlt,
            borderRadius: Radii.pillAll,
            child: InkWell(
              borderRadius: Radii.pillAll,
              onTap: () {
                if (item.$3 == '/stations') {
                  ref.read(shellTabIndexProvider.notifier).state = 4;
                  return;
                }
                context.push(item.$3);
              },
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  borderRadius: Radii.pillAll,
                  border: Border.all(color: AppColors.border),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(item.$1, size: 18, color: AppColors.amber),
                    const SizedBox(width: 8),
                    Text(
                      s(item.$2),
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textHi),
                    ),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}

