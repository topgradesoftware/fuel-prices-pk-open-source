import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/i18n/strings.dart';
import '../../core/services/app_providers.dart';
import '../../core/services/app_settings.dart';
import '../../core/theme/app_theme.dart';
import '../../models/fuel_price.dart';

enum _HistoryRange { weekly, monthly, yearly }

enum _HistoryLayout { line, area, bars, cards }

const _kHistoryFuels = ['petrol', 'hsd', 'lpg'];

const _kFuelColors = {
  'petrol': Color(0xFFF2A63F),
  'hsd': Color(0xFF5FB3D9),
  'lpg': Color(0xFF4FBE8D),
};

class HistoryScreen extends ConsumerStatefulWidget {
  const HistoryScreen({super.key});

  @override
  ConsumerState<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends ConsumerState<HistoryScreen> {
  _HistoryRange _range = _HistoryRange.monthly;
  _HistoryLayout _layout = _HistoryLayout.area;
  String _fuelFocus = 'all'; // all | petrol | hsd | lpg

  int get _fetchDays => switch (_range) {
        _HistoryRange.weekly => 14,
        _HistoryRange.monthly => 45,
        _HistoryRange.yearly => 365,
      };

  bool _inCurrentPeriod(DateTime d) {
    final now = DateTime.now();
    final local = DateTime(d.year, d.month, d.day);
    if (_range == _HistoryRange.weekly) {
      final mondayOffset = (now.weekday + 6) % 7; // Mon=0 … Sun=6
      final start = DateTime(now.year, now.month, now.day)
          .subtract(Duration(days: mondayOffset));
      final end = start.add(const Duration(days: 6));
      return !local.isBefore(start) && !local.isAfter(end);
    }
    if (_range == _HistoryRange.monthly) {
      return local.year == now.year && local.month == now.month;
    }
    return local.year == now.year;
  }

  String _periodHint(S s) {
    final now = DateTime.now();
    final loc = s.isUrdu ? 'ur' : 'en';
    if (_range == _HistoryRange.weekly) return s('thisWeek');
    if (_range == _HistoryRange.monthly) {
      return DateFormat.yMMMM(loc).format(now);
    }
    return '${now.year}';
  }

  String _shortFuel(S s, String id) => switch (id) {
        'petrol' => s('petrolLabel'),
        'hsd' => s('dieselShort'),
        'lpg' => s('lpgShort'),
        _ => id,
      };

  String _fuelName(S s, String id) => switch (id) {
        'petrol' => s('petrolLabel'),
        'hsd' => s('dieselShort'),
        'lpg' => s('lpgShort'),
        _ => id,
      };

  Color _color(String id) => _kFuelColors[id] ?? AppColors.amber;

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    final bundleAsync = ref.watch(historyBundleProvider(_fetchDays));

    return Scaffold(
      appBar: AppBar(title: Text(s('historyTitle'))),
      body: bundleAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(s('loadPricesFailed'))),
        data: (bundle) {
          final available = _kHistoryFuels
              .where((id) => (bundle[id] ?? const []).isNotEmpty)
              .toList();
          final focusIds = _fuelFocus == 'all'
              ? available
              : available.where((id) => id == _fuelFocus).toList();

          // Merge by date for focused fuels, filtered to current period.
          final dateMap = <String, Map<String, double>>{};
          for (final id in available) {
            for (final p in bundle[id] ?? const <FuelPricePoint>[]) {
              if (!_inCurrentPeriod(p.date)) continue;
              final key = DateFormat('yyyy-MM-dd').format(p.date);
              dateMap.putIfAbsent(key, () => {});
              dateMap[key]![id] = p.price;
            }
          }
          final dates = dateMap.keys.toList()..sort();
          final seriesIds = focusIds
              .where((id) => dates.any((d) => dateMap[d]!.containsKey(id)))
              .toList();

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            children: [
              Text(s('historySub'), style: TextStyle(color: AppColors.textMid, fontSize: 13)),
              const SizedBox(height: 6),
              Text(
                _periodHint(s),
                style: TextStyle(
                  color: AppColors.amber,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 12),
              _ChipRow(
                children: [
                  for (final r in _HistoryRange.values)
                    _Chip(
                      label: s(switch (r) {
                        _HistoryRange.weekly => 'weekly',
                        _HistoryRange.monthly => 'monthly',
                        _HistoryRange.yearly => 'yearly',
                      }),
                      active: _range == r,
                      onTap: () => setState(() => _range = r),
                    ),
                ],
              ),
              const SizedBox(height: 8),
              _ChipRow(
                children: [
                  for (final l in _HistoryLayout.values)
                    _Chip(
                      label: s(switch (l) {
                        _HistoryLayout.line => 'chartLine',
                        _HistoryLayout.area => 'chartArea',
                        _HistoryLayout.bars => 'chartBars',
                        _HistoryLayout.cards => 'chartCards',
                      }),
                      active: _layout == l,
                      onTap: () => setState(() => _layout = l),
                    ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: _Chip(
                      label: s('allFuels'),
                      active: _fuelFocus == 'all',
                      onTap: () => setState(() => _fuelFocus = 'all'),
                    ),
                  ),
                  for (final id in available) ...[
                    const SizedBox(width: 6),
                    Expanded(
                      child: _Chip(
                        label: _shortFuel(s, id),
                        active: _fuelFocus == id,
                        onTap: () => setState(() => _fuelFocus = id),
                      ),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 16),
              if (seriesIds.isEmpty || dates.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 48),
                  child: Center(
                    child: Text(
                      s('noHistoryData'),
                      style: TextStyle(color: AppColors.textMid),
                      textAlign: TextAlign.center,
                    ),
                  ),
                )
              else if (_layout == _HistoryLayout.cards)
                ...seriesIds.map((id) {
                  final vals = [
                    for (final d in dates)
                      if (dateMap[d]!.containsKey(id)) dateMap[d]![id]!,
                  ];
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _FuelStatCard(
                      name: _fuelName(s, id),
                      color: _color(id),
                      values: vals,
                      s: s,
                    ),
                  );
                })
              else
                _MainChart(
                  layout: _layout,
                  dates: dates,
                  dateMap: dateMap,
                  ids: seriesIds,
                  colorOf: _color,
                  labelOf: (id) => _fuelName(s, id),
                  range: _range,
                  locale: s.isUrdu ? 'ur' : 'en',
                ),
            ],
          );
        },
      ),
    );
  }
}

class _ChipRow extends StatelessWidget {
  const _ChipRow({required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Wrap(spacing: 8, runSpacing: 8, children: children);
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.label, required this.active, required this.onTap});
  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: active ? AppColors.amber : AppColors.bgPanelAlt,
          borderRadius: Radii.pillAll,
          border: Border.all(color: active ? AppColors.amber : AppColors.border),
          boxShadow: active
              ? [
                  BoxShadow(
                    color: AppColors.amber.withValues(alpha: 0.3),
                    blurRadius: 10,
                    offset: const Offset(0, 3),
                  ),
                ]
              : null,
        ),
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontSize: 12.5,
            fontWeight: active ? FontWeight.w700 : FontWeight.w500,
            color: active ? AppColors.onPrimary : AppColors.textSecondary,
          ),
        ),
      ),
    );
  }
}

class _MainChart extends StatelessWidget {
  const _MainChart({
    required this.layout,
    required this.dates,
    required this.dateMap,
    required this.ids,
    required this.colorOf,
    required this.labelOf,
    required this.range,
    required this.locale,
  });

  final _HistoryLayout layout;
  final List<String> dates;
  final Map<String, Map<String, double>> dateMap;
  final List<String> ids;
  final Color Function(String) colorOf;
  final String Function(String) labelOf;
  final _HistoryRange range;
  final String locale;

  @override
  Widget build(BuildContext context) {
    final values = <double>[
      for (final d in dates)
        for (final id in ids)
          if (dateMap[d]!.containsKey(id)) dateMap[d]![id]!,
    ];
    final minV = values.reduce((a, b) => a < b ? a : b);
    final maxV = values.reduce((a, b) => a > b ? a : b);
    final pad = ((maxV - minV) * 0.08).clamp(1.0, 50.0);
    final minY = (minV - pad).clamp(0.0, double.infinity);
    final maxY = maxV + pad;

    return Container(
      height: 330,
      padding: const EdgeInsets.fromLTRB(12, 20, 16, 12),
      decoration: BoxDecoration(
        color: AppColors.bgPanel,
        borderRadius: Radii.xxlAll,
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.1),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        children: [
          Expanded(
            child: layout == _HistoryLayout.bars
                ? _buildBars(minY, maxY)
                : _buildLines(minY, maxY, fill: layout == _HistoryLayout.area),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 16,
            runSpacing: 6,
            children: [
              for (final id in ids)
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 10,
                      height: 10,
                      decoration: BoxDecoration(
                        color: colorOf(id),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: colorOf(id).withValues(alpha: 0.4),
                            blurRadius: 6,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      labelOf(id),
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.textHi),
                    ),
                  ],
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildLines(double minY, double maxY, {required bool fill}) {
    return LineChart(
      LineChartData(
        minY: minY,
        maxY: maxY,
        minX: 0,
        maxX: (dates.length - 1).clamp(0, 9999).toDouble(),
        gridData: FlGridData(
          show: true,
          drawVerticalLine: false,
          getDrawingHorizontalLine: (_) => FlLine(color: AppColors.border, strokeWidth: 1),
        ),
        titlesData: FlTitlesData(
          topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          leftTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 42,
              getTitlesWidget: (v, _) => Text(
                v.toStringAsFixed(0),
                style: TextStyle(fontSize: 10, color: AppColors.textMid),
              ),
            ),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 28,
              interval: _xInterval(),
              getTitlesWidget: (v, _) {
                final i = v.round();
                if (i < 0 || i >= dates.length) return const SizedBox.shrink();
                return Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    _shortDate(dates[i]),
                    style: TextStyle(fontSize: 9, color: AppColors.textMid),
                  ),
                );
              },
            ),
          ),
        ),
        borderData: FlBorderData(show: false),
        lineBarsData: [
          for (final id in ids)
            LineChartBarData(
              spots: [
                for (var i = 0; i < dates.length; i++)
                  if (dateMap[dates[i]]!.containsKey(id))
                    FlSpot(i.toDouble(), dateMap[dates[i]]![id]!),
              ],
              isCurved: false,
              color: colorOf(id),
              barWidth: fill ? 2 : 2.8,
              dotData: FlDotData(show: !fill || dates.length < 2),
              belowBarData: BarAreaData(
                show: fill,
                color: colorOf(id).withValues(alpha: 0.22),
              ),
            ),
        ],
        lineTouchData: LineTouchData(
          touchTooltipData: LineTouchTooltipData(
            getTooltipColor: (_) => AppColors.bgPanelAlt,
          ),
        ),
      ),
    );
  }

  Widget _buildBars(double minY, double maxY) {
    return BarChart(
      BarChartData(
        minY: minY,
        maxY: maxY,
        gridData: FlGridData(
          show: true,
          drawVerticalLine: false,
          getDrawingHorizontalLine: (_) => FlLine(color: AppColors.border, strokeWidth: 1),
        ),
        titlesData: FlTitlesData(
          topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          leftTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 42,
              getTitlesWidget: (v, _) => Text(
                v.toStringAsFixed(0),
                style: TextStyle(fontSize: 10, color: AppColors.textMid),
              ),
            ),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 28,
              getTitlesWidget: (v, _) {
                final i = v.toInt();
                if (i < 0 || i >= dates.length) return const SizedBox.shrink();
                final step = _barLabelStep();
                if (i % step != 0 && i != dates.length - 1) {
                  return const SizedBox.shrink();
                }
                return Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    _shortDate(dates[i]),
                    style: TextStyle(fontSize: 9, color: AppColors.textMid),
                  ),
                );
              },
            ),
          ),
        ),
        borderData: FlBorderData(show: false),
        barGroups: [
          for (var i = 0; i < dates.length; i++)
            BarChartGroupData(
              x: i,
              barsSpace: 2,
              barRods: [
                for (final id in ids)
                  if (dateMap[dates[i]]!.containsKey(id))
                    BarChartRodData(
                      toY: dateMap[dates[i]]![id]!,
                      fromY: minY,
                      width: ids.length == 1 ? 14 : 8,
                      color: colorOf(id),
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(3)),
                    ),
              ],
            ),
        ],
      ),
    );
  }

  double _xInterval() {
    final maxLabels = range == _HistoryRange.yearly ? 6 : 7;
    return (dates.length / maxLabels).ceilToDouble().clamp(1, 999);
  }

  int _barLabelStep() {
    final maxLabels = range == _HistoryRange.yearly ? 6 : 7;
    return (dates.length / maxLabels).ceil().clamp(1, 999);
  }

  String _shortDate(String ymd) {
    final d = DateTime.tryParse(ymd);
    if (d == null) return ymd;
    if (range == _HistoryRange.yearly) {
      return DateFormat.MMM(locale).format(d);
    }
    return DateFormat.MMMd(locale).format(d);
  }
}

class _FuelStatCard extends StatelessWidget {
  const _FuelStatCard({
    required this.name,
    required this.color,
    required this.values,
    required this.s,
  });

  final String name;
  final Color color;
  final List<double> values;
  final S s;

  @override
  Widget build(BuildContext context) {
    final latest = values.isEmpty ? null : values.last;
    final high = values.isEmpty ? null : values.reduce((a, b) => a > b ? a : b);
    final low = values.isEmpty ? null : values.reduce((a, b) => a < b ? a : b);
    final change = values.length >= 2 ? values.last - values.first : null;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.bgPanel,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name, style: const TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 2),
                    Text(
                      '${s('chartLatest')} ${latest?.toStringAsFixed(2) ?? '—'}',
                      style: TextStyle(fontSize: 12, color: AppColors.textMid),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: change == null
                      ? AppColors.bgPanelAlt
                      : change > 0
                          ? AppColors.up.withValues(alpha: 0.15)
                          : change < 0
                              ? AppColors.down.withValues(alpha: 0.15)
                              : AppColors.bgPanelAlt,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  change == null
                      ? '—'
                      : '${change > 0 ? '+' : ''}${change.toStringAsFixed(2)}',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: change == null
                        ? AppColors.textMid
                        : change > 0
                            ? AppColors.up
                            : change < 0
                                ? AppColors.down
                                : AppColors.textMid,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 48,
            child: values.length < 2
                ? Align(
                    alignment: Alignment.centerLeft,
                    child: Container(
                      width: 36,
                      height: 3,
                      color: color,
                    ),
                  )
                : LineChart(
                    LineChartData(
                      minY: low! - 1,
                      maxY: high! + 1,
                      gridData: const FlGridData(show: false),
                      titlesData: const FlTitlesData(show: false),
                      borderData: FlBorderData(show: false),
                      lineTouchData: const LineTouchData(enabled: false),
                      lineBarsData: [
                        LineChartBarData(
                          spots: [
                            for (var i = 0; i < values.length; i++)
                              FlSpot(i.toDouble(), values[i]),
                          ],
                          isCurved: true,
                          color: color,
                          barWidth: 2.2,
                          dotData: const FlDotData(show: false),
                          belowBarData: BarAreaData(
                            show: true,
                            color: color.withValues(alpha: 0.12),
                          ),
                        ),
                      ],
                    ),
                  ),
          ),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '${s('chartHigh')} ${high?.toStringAsFixed(2) ?? '—'}',
                style: TextStyle(fontSize: 11, color: AppColors.textMid),
              ),
              Text(
                '${s('chartLow')} ${low?.toStringAsFixed(2) ?? '—'}',
                style: TextStyle(fontSize: 11, color: AppColors.textMid),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
