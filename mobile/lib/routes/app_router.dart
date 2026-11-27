import 'package:go_router/go_router.dart';

import '../features/auth/auth_screens.dart';
import '../features/calculator/fuel_calculator_screen.dart';
import '../features/calculator/monthly_budget_screen.dart';
import '../features/calculator/tank_fill_screen.dart';
import '../features/calculator/trip_cost_screen.dart';
import '../features/history/history_screen.dart';
import '../features/notifications/notifications_screen.dart';
import '../features/settings/language_settings_screen.dart';
import '../features/settings/theme_settings_screen.dart';
import '../features/stations/stations_screen.dart';
import '../features/vehicles/vehicles_screen.dart';
import '../shared/widgets/app_shell.dart';

final appRouter = GoRouter(
  initialLocation: '/splash',
  routes: [
    GoRoute(
      path: '/splash',
      builder: (context, state) => SplashScreen(onDone: () => context.go('/onboarding')),
    ),
    GoRoute(
      path: '/onboarding',
      builder: (context, state) => OnboardingScreen(onDone: () => context.go('/login')),
    ),
    GoRoute(
      path: '/login',
      builder: (context, state) => LoginScreen(onDone: () => context.go('/home')),
    ),
    GoRoute(path: '/home', builder: (context, state) => const AppShell()),
    GoRoute(path: '/calculator/fuel', builder: (context, state) => const FuelCalculatorScreen()),
    GoRoute(path: '/calculator/tank-fill', builder: (context, state) => const TankFillScreen()),
    GoRoute(path: '/calculator/trip', builder: (context, state) => const TripCostScreen()),
    GoRoute(path: '/calculator/budget', builder: (context, state) => const MonthlyBudgetScreen()),
    GoRoute(path: '/history', builder: (context, state) => const HistoryScreen()),
    GoRoute(path: '/garage', builder: (context, state) => const VehiclesScreen()),
    GoRoute(path: '/stations', builder: (context, state) => const StationsScreen()),
    GoRoute(path: '/notifications', builder: (context, state) => const NotificationsScreen()),
    GoRoute(path: '/settings/theme', builder: (context, state) => const ThemeSettingsScreen()),
    GoRoute(path: '/settings/language', builder: (context, state) => const LanguageSettingsScreen()),
  ],
);
