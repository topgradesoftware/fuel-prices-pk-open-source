import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/auth/auth_service.dart';
import '../../core/auth/google_sign_in_helper.dart';
import '../../core/notifications/device_registration_service.dart';
import '../../core/services/app_settings.dart';
import '../../core/theme/app_theme.dart';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key, required this.onDone});
  final VoidCallback onDone;

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  @override
  void initState() {
    super.initState();
    Future.delayed(const Duration(milliseconds: 1100), widget.onDone);
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);

    return Scaffold(
      backgroundColor: AppColors.bgDeep,
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 84,
              height: 84,
              decoration: BoxDecoration(
                borderRadius: Radii.xxlAll,
                gradient: LinearGradient(
                  colors: [AppColors.amber, AppColors.amberDim],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.amber.withValues(alpha: 0.4),
                    blurRadius: 28,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: const Icon(Icons.local_gas_station, color: Color(0xFF1A1204), size: 42),
            ),
            const SizedBox(height: 24),
            Text(
              s('appName'),
              style: TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 22,
                letterSpacing: -0.5,
                color: AppColors.textHi,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'v0.2.3',
              style: TextStyle(
                color: AppColors.textSecondary,
                fontSize: 12,
                letterSpacing: 1.0,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key, required this.onDone});
  final VoidCallback onDone;

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  final _controller = PageController();
  int _page = 0;

  static const _slideKeys = [
    (Icons.speed_outlined, 'onboardPricesTitle', 'onboardPricesBody'),
    (Icons.calculate_outlined, 'onboardCalcTitle', 'onboardCalcBody'),
    (Icons.notifications_active_outlined, 'onboardAlertsTitle', 'onboardAlertsBody'),
    (Icons.map_outlined, 'onboardStationsTitle', 'onboardStationsBody'),
  ];

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Align(
              alignment: AlignmentDirectional.topEnd,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                child: TextButton(
                  onPressed: widget.onDone,
                  child: Text(
                    s('skip'),
                    style: TextStyle(color: AppColors.amber, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
            ),
            Expanded(
              child: PageView(
                controller: _controller,
                onPageChanged: (i) => setState(() => _page = i),
                children: [
                  for (final slide in _slideKeys)
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 36),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            width: 100,
                            height: 100,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: AppColors.amber.withValues(alpha: 0.15),
                              border: Border.all(color: AppColors.amber.withValues(alpha: 0.35)),
                              boxShadow: [
                                BoxShadow(
                                  color: AppColors.amber.withValues(alpha: 0.2),
                                  blurRadius: 24,
                                ),
                              ],
                            ),
                            child: Icon(slide.$1, size: 48, color: AppColors.amber),
                          ),
                          const SizedBox(height: 32),
                          Text(
                            s(slide.$2),
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w800,
                              color: AppColors.textHi,
                              letterSpacing: -0.5,
                            ),
                          ),
                          const SizedBox(height: 12),
                          Text(
                            s(slide.$3),
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: AppColors.textSecondary,
                              fontSize: 14.5,
                              height: 1.45,
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (var i = 0; i < _slideKeys.length; i++)
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    width: i == _page ? 24 : 8,
                    height: 8,
                    decoration: BoxDecoration(
                      borderRadius: Radii.pillAll,
                      color: i == _page ? AppColors.amber : AppColors.border,
                    ),
                  ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(24),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.amber,
                    foregroundColor: AppColors.onPrimary,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: Radii.lgAll),
                  ),
                  onPressed: () {
                    if (_page == _slideKeys.length - 1) {
                      widget.onDone();
                    } else {
                      _controller.nextPage(
                          duration: const Duration(milliseconds: 300), curve: Curves.easeOutCubic);
                    }
                  },
                  child: Text(
                    _page == _slideKeys.length - 1 ? s('getStarted') : s('next'),
                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key, required this.onDone});
  final VoidCallback onDone;

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  bool _busy = false;
  String? _error;

  Future<void> _guest() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final auth = AuthService();
      final devices = DeviceRegistrationService();
      await auth.loginAsGuest(deviceUuid: devices.deviceUuid());
      if (mounted) widget.onDone();
    } catch (e) {
      final s = ref.read(stringsProvider);
      setState(() => _error = s('signInFailed'));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _google() async {
    final s = ref.read(stringsProvider);
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final idToken = await GoogleSignInHelper.signInIdToken();
      final auth = AuthService();
      final devices = DeviceRegistrationService();
      await auth.loginWithGoogle(
        idToken: idToken,
        deviceUuid: devices.deviceUuid(),
      );
      if (mounted) widget.onDone();
    } catch (e) {
      setState(() => _error = s('signInFailed'));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _stub(String name) {
    final s = ref.read(stringsProvider);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$name · ${s('signInFailed')}')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: AppColors.amber.withValues(alpha: 0.15),
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.amber.withValues(alpha: 0.35)),
                ),
                child: Icon(Icons.local_gas_station, size: 36, color: AppColors.amber),
              ),
              const SizedBox(height: 20),
              Text(
                s('welcome'),
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textHi,
                  letterSpacing: -0.5,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                s('signInSubtitle'),
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.textSecondary, fontSize: 14),
              ),
              const SizedBox(height: 36),
              if (_error != null) ...[
                Text(_error!, style: TextStyle(color: AppColors.up, fontSize: 13, fontWeight: FontWeight.w600)),
                const SizedBox(height: 14),
              ],
              _AuthButton(
                icon: Icons.g_mobiledata,
                label: s('signInGoogle'),
                onTap: _busy ? () {} : _google,
              ),
              const SizedBox(height: 12),
              _AuthButton(
                icon: Icons.apple,
                label: s('continueApple'),
                onTap: () => _stub(s('continueApple')),
              ),
              const SizedBox(height: 12),
              _AuthButton(
                icon: Icons.sms_outlined,
                label: s('continuePhone'),
                onTap: () => _stub(s('continuePhone')),
              ),
              const SizedBox(height: 24),
              if (_busy)
                const CircularProgressIndicator()
              else
                TextButton(
                  onPressed: _guest,
                  child: Text(
                    s('continueGuest'),
                    style: TextStyle(color: AppColors.textSecondary, fontWeight: FontWeight.w600, fontSize: 14),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AuthButton extends StatelessWidget {
  const _AuthButton({required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: onTap,
        icon: Icon(icon, size: 22),
        label: Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
        style: OutlinedButton.styleFrom(
          padding: const EdgeInsets.symmetric(vertical: 16),
          side: BorderSide(color: AppColors.border),
          shape: RoundedRectangleBorder(borderRadius: Radii.lgAll),
          foregroundColor: AppColors.textHi,
          backgroundColor: AppColors.bgPanelAlt,
        ),
      ),
    );
  }
}
