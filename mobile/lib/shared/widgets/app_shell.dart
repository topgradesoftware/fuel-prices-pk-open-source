import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/services/app_providers.dart';
import '../../core/services/app_settings.dart';
import '../../core/theme/app_theme.dart';
import '../../features/calculator/calculator_hub_screen.dart';
import '../../features/compare/compare_screen.dart';
import '../../features/history/history_screen.dart';
import '../../features/home/home_screen.dart';
import '../../features/profile/profile_screen.dart';
import '../../features/stations/stations_screen.dart';
import '../../features/vehicles/vehicles_screen.dart';

/// Bottom nav matches web PWA: Home · Compare · Stations · Garage · Calc · History · More
class AppShell extends ConsumerStatefulWidget {
  const AppShell({super.key});

  @override
  ConsumerState<AppShell> createState() => _AppShellState();
}

class _AppShellState extends ConsumerState<AppShell> {
  final Set<int> _opened = {0};

  void _go(int i) {
    ref.read(shellTabIndexProvider.notifier).state = i;
    setState(() => _opened.add(i));
  }

  Widget _page(int i) {
    if (!_opened.contains(i)) {
      return const SizedBox.expand();
    }
    switch (i) {
      case 0:
        return const HomeScreen();
      case 1:
        return const CompareScreen();
      case 2:
        return const StationsScreen();
      case 3:
        return const VehiclesScreen();
      case 4:
        return const CalculatorHubScreen();
      case 5:
        return const HistoryScreen();
      case 6:
        return const ProfileScreen();
      default:
        return const SizedBox.expand();
    }
  }

  @override
  Widget build(BuildContext context) {
    // Default city = GPS current location (not National).
    ref.watch(locationCityBootstrapProvider);
    final s = ref.watch(stringsProvider);
    final index = ref.watch(shellTabIndexProvider);
    _opened.add(index);

    final tabs = <(IconData, IconData, String)>[
      (Icons.local_gas_station_outlined, Icons.local_gas_station, s('home')),
      (Icons.apartment_outlined, Icons.apartment, s('compare')),
      (Icons.place_outlined, Icons.place, s('stations')),
      (Icons.directions_car_outlined, Icons.directions_car, s('garage')),
      (Icons.calculate_outlined, Icons.calculate, s('calc')),
      (Icons.show_chart_outlined, Icons.show_chart, s('history')),
      (Icons.more_horiz, Icons.more_horiz, s('more')),
    ];

    final bottomPad = MediaQuery.paddingOf(context).bottom;

    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth >= 720;

        if (isWide) {
          return Scaffold(
            body: Row(
              children: [
                NavigationRail(
                  selectedIndex: index,
                  onDestinationSelected: _go,
                  labelType: NavigationRailLabelType.all,
                  backgroundColor: AppColors.bgPanel,
                  indicatorColor: AppColors.amber.withValues(alpha: 0.2),
                  selectedIconTheme: IconThemeData(color: AppColors.amber),
                  unselectedIconTheme: IconThemeData(color: AppColors.textSecondary),
                  selectedLabelTextStyle: TextStyle(
                    color: AppColors.amber,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                  unselectedLabelTextStyle: TextStyle(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w500,
                    fontSize: 12,
                  ),
                  destinations: [
                    for (final tab in tabs)
                      NavigationRailDestination(
                        icon: Icon(tab.$1),
                        selectedIcon: Icon(tab.$2),
                        label: Text(tab.$3),
                      ),
                  ],
                ),
                const VerticalDivider(thickness: 1, width: 1),
                Expanded(
                  child: IndexedStack(
                    index: index,
                    children: [for (var i = 0; i < tabs.length; i++) _page(i)],
                  ),
                ),
              ],
            ),
          );
        }

        return Scaffold(
          extendBody: true,
          body: IndexedStack(
            index: index,
            children: [for (var i = 0; i < tabs.length; i++) _page(i)],
          ),
          bottomNavigationBar: Padding(
            padding: EdgeInsets.fromLTRB(
              Space.navInset,
              0,
              Space.navInset,
              Space.xs + bottomPad,
            ),
            child: ClipRRect(
              borderRadius: Radii.xxlAll,
              child: BackdropFilter(
                filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
                child: Container(
                  decoration: BoxDecoration(
                    color: AppColors.navBlur,
                    borderRadius: Radii.xxlAll,
                    border: Border.all(
                      color: AppColors.border.withValues(alpha: 0.8),
                      width: 1.2,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.2),
                        blurRadius: 24,
                        offset: const Offset(0, 8),
                      ),
                    ],
                  ),
                  child: SizedBox(
                    height: Space.navBarHeight,
                    child: Row(
                      children: [
                        for (var i = 0; i < tabs.length; i++)
                          Expanded(
                            child: _NavTab(
                              icon: index == i ? tabs[i].$2 : tabs[i].$1,
                              label: tabs[i].$3,
                              selected: index == i,
                              onTap: () => _go(i),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _NavTab extends StatelessWidget {
  const _NavTab({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.amber : AppColors.textSecondary;
    return InkWell(
      onTap: onTap,
      borderRadius: Radii.lgAll,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            curve: Curves.easeOutCubic,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
            decoration: BoxDecoration(
              color: selected ? AppColors.amber.withValues(alpha: 0.18) : Colors.transparent,
              borderRadius: Radii.pillAll,
            ),
            child: Icon(icon, size: 20, color: color),
          ),
          const SizedBox(height: 2),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 1),
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                label,
                maxLines: 1,
                softWrap: false,
                style: TextStyle(
                  fontSize: 9.0,
                  height: 1.05,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                  color: color,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
