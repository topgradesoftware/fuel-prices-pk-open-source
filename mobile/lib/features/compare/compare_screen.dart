import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/services/app_providers.dart';
import '../../core/services/app_settings.dart';
import '../../core/theme/app_theme.dart';
import '../../models/fuel_company.dart';
import '../../models/fuel_price.dart';
import '../../models/fuel_type.dart';
import '../../shared/widgets/ui/country_field.dart';
import '../home/widgets/price_card.dart';

/// Company side-by-side prices — matches PWA Compare tab.
class CompareScreen extends ConsumerStatefulWidget {
  const CompareScreen({super.key});

  @override
  ConsumerState<CompareScreen> createState() => _CompareScreenState();
}

class _CompareScreenState extends ConsumerState<CompareScreen> {
  static const _kAll = '__all__';
  String _fuelId = _kAll;
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    final currency = ref.watch(currencyLabelProvider);
    final pricesAsync = ref.watch(latestPricesProvider);
    final companiesAsync = ref.watch(companiesProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(s('compare')),
      ),
      body: pricesAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(s('loadPricesFailed'))),
        data: (result) {
          final companies = <String, FuelCompany>{};
          for (final c in companiesAsync.value ?? const <FuelCompany>[]) {
            companies[c.slug] = c;
            companies[c.id] = c;
          }

          // Build byCompany: if 'All', pick the lowest-price fuel per company.
          final byCompany = <String, FuelPrice>{};
          final pricesToScan = _fuelId == _kAll
              ? result.prices
              : result.prices.where((p) => p.fuelTypeId == _fuelId);
          for (final p in pricesToScan) {
            final existing = byCompany[p.companyId];
            if (existing == null || p.price < existing.price) {
              byCompany[p.companyId] = p;
            }
          }

          final rows = byCompany.entries.where((e) {
            if (_query.trim().isEmpty) return true;
            final q = _query.trim().toLowerCase();
            final co = companies[e.key];
            final name = (co?.name ?? e.key).toLowerCase();
            final short = (co?.shortName ?? '').toLowerCase();
            return name.contains(q) || short.contains(q) || e.key.contains(q);
          }).toList()
            ..sort((a, b) => a.value.price.compareTo(b.value.price));

          final cheapestPrice = rows.isEmpty ? null : rows.first.value.price;
          final hasPriceSpread = rows.length >= 2 &&
              rows.first.value.price < rows.last.value.price;
          final fuelMatch = kDefaultFuelTypes.where((f) => f.id == _fuelId);
          final unit = fuelMatch.isEmpty
              ? '$currency/${s('unitL')}'
              : fuelMatch.first.unit;

          return Column(
            children: [
              const Padding(
                padding: EdgeInsets.fromLTRB(16, 8, 16, 4),
                child: LocationFields(),
              ),
              SizedBox(
                height: 48,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
                  children: [
                    Padding(
                      padding: const EdgeInsetsDirectional.only(end: 8),
                      child: ChoiceChip(
                        label: Text(s('allCompanies'), overflow: TextOverflow.ellipsis),
                        selected: _fuelId == _kAll,
                        onSelected: (_) => setState(() => _fuelId = _kAll),
                        selectedColor: AppColors.amber.withValues(alpha: 0.2),
                        labelStyle: TextStyle(
                          fontSize: 12.5,
                          fontWeight: _fuelId == _kAll ? FontWeight.w700 : FontWeight.w500,
                          color: _fuelId == _kAll ? AppColors.amber : AppColors.textHi,
                        ),
                        side: BorderSide(
                          color: _fuelId == _kAll ? AppColors.amber : AppColors.border,
                        ),
                        backgroundColor: AppColors.bgPanelAlt,
                        shape: RoundedRectangleBorder(borderRadius: Radii.pillAll),
                      ),
                    ),
                    for (final f in kDefaultFuelTypes)
                      Padding(
                        padding: const EdgeInsetsDirectional.only(end: 8),
                        child: ChoiceChip(
                          label: Text(f.name, overflow: TextOverflow.ellipsis),
                          selected: _fuelId == f.id,
                          onSelected: (_) => setState(() => _fuelId = f.id),
                          selectedColor: AppColors.amber.withValues(alpha: 0.2),
                          labelStyle: TextStyle(
                            fontSize: 12.5,
                            fontWeight: _fuelId == f.id ? FontWeight.w700 : FontWeight.w500,
                            color: _fuelId == f.id ? AppColors.amber : AppColors.textHi,
                          ),
                          side: BorderSide(
                            color: _fuelId == f.id ? AppColors.amber : AppColors.border,
                          ),
                          backgroundColor: AppColors.bgPanelAlt,
                          shape: RoundedRectangleBorder(borderRadius: Radii.pillAll),
                        ),
                      ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                child: TextField(
                  onChanged: (v) => setState(() => _query = v),
                  decoration: InputDecoration(
                    hintText: s('searchCompany'),
                    prefixIcon: const Icon(Icons.search, size: 20),
                    filled: true,
                    fillColor: AppColors.bgPanelAlt,
                    border: OutlineInputBorder(
                      borderRadius: Radii.lgAll,
                      borderSide: BorderSide(color: AppColors.border),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: Radii.lgAll,
                      borderSide: BorderSide(color: AppColors.border),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: Radii.lgAll,
                      borderSide: BorderSide(color: AppColors.amber, width: 1.5),
                    ),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  ),
                ),
              ),
              Expanded(
                child: rows.isEmpty
                    ? Center(
                        child: Text(
                          s('noCompanyMatch'),
                          style: TextStyle(color: AppColors.textSecondary),
                        ),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, Space.navContentClearance),
                        itemCount: rows.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (context, i) {
                          final e = rows[i];
                          final co = companies[e.key];
                          final name = co?.name ?? e.key;
                          final short = co?.shortName ?? fuelMark(name);
                          final accent = _companyAccent(e.key);
                          final best = hasPriceSpread &&
                              cheapestPrice != null &&
                              e.value.price == cheapestPrice;
                          return _CompanyCard(
                            name: name,
                            mark: short.length >= 2
                                ? short.substring(0, 2).toUpperCase()
                                : short.toUpperCase(),
                            accent: accent,
                            price: e.value.price,
                            unit: unit,
                            best: best,
                            bestLabel: s('bestPrice'),
                          );
                        },
                      ),
              ),
            ],
          );
        },
      ),
    );
  }

  Color _companyAccent(String id) {
    const map = {
      'pso': Color(0xFF00A651),
      'shell': Color(0xFFFBCE07),
      'wafi': Color(0xFFDD1D21),
      'total': Color(0xFFED1C24),
      'attock': Color(0xFF2E7D32),
      'apl': Color(0xFF2E7D32),
      'go': Color(0xFFF57C00),
      'hascol': Color(0xFF1565C0),
      'puma': Color(0xFFC62828),
      'cnergyico': Color(0xFF00838F),
      'national': Color(0xFF01411C),
    };
    return map[id] ?? const Color(0xFFF2A63F);
  }
}

class _CompanyCard extends StatelessWidget {
  const _CompanyCard({
    required this.name,
    required this.mark,
    required this.accent,
    required this.price,
    required this.unit,
    required this.best,
    required this.bestLabel,
  });

  final String name;
  final String mark;
  final Color accent;
  final double price;
  final String unit;
  final bool best;
  final String bestLabel;

  @override
  Widget build(BuildContext context) {
    final soft = accent.withValues(alpha: 0.14);
    final edge = best ? AppColors.down : Color.lerp(accent, AppColors.border, 0.45)!;
    final markInk = accent.computeLuminance() > 0.65 ? const Color(0xFF0B0F14) : Colors.white;

    return Container(
      decoration: BoxDecoration(
        color: AppColors.bgPanel,
        borderRadius: Radii.xlAll,
        border: Border.all(color: edge, width: best ? 1.5 : 1),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(width: 4, color: best ? AppColors.down : accent),
            Expanded(
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [soft, AppColors.bgPanel],
                    stops: const [0.0, 0.45],
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 32,
                          height: 32,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: accent,
                            borderRadius: Radii.mdAll,
                          ),
                          child: Text(
                            mark,
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: markInk,
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              color: AppColors.textHi,
                            ),
                          ),
                        ),
                        if (best) ...[
                          const SizedBox(width: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: AppColors.down,
                              borderRadius: Radii.pillAll,
                              boxShadow: [
                                BoxShadow(
                                  color: AppColors.down.withValues(alpha: 0.3),
                                  blurRadius: 8,
                                  offset: const Offset(0, 2),
                                ),
                              ],
                            ),
                            child: Text(
                              bestLabel,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 10.5,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF072116),
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Flexible(
                          child: FittedBox(
                            fit: BoxFit.scaleDown,
                            alignment: AlignmentDirectional.centerStart,
                            child: Text(
                              price.toStringAsFixed(2),
                              style: TextStyle(
                                fontFamily: 'monospace',
                                fontSize: 26,
                                fontWeight: FontWeight.w800,
                                color: AppColors.textHi,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Text(unit, style: TextStyle(fontSize: 12.5, color: AppColors.textSecondary)),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
