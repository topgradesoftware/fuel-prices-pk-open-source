import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/config/app_config.dart';
import '../../core/i18n/strings.dart';
import '../../core/services/app_providers.dart';
import '../../core/services/app_settings.dart';
import '../../core/services/notification_store.dart';
import '../../core/services/price_alert_service.dart';
import '../../core/services/share_service.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/settings_option_card.dart';
import '../../shared/widgets/ui/country_field.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  bool _notifications = true;

  @override
  void initState() {
    super.initState();
    _notifications = NotificationStore.alertsEnabled();
  }

  Future<void> _setAlerts(bool value) async {
    await NotificationStore.setAlertsEnabled(value);
    if (value) await PriceAlertService.instance.init();
    setState(() => _notifications = value);
  }

  String _themeLabel(String id, S s) {
    switch (id) {
      case 'light':
        return s('themeLight');
      case 'dark':
        return s('themeDark');
      default:
        return s('themeSystem');
    }
  }

  Future<void> _openThemePicker(BuildContext context, WidgetRef ref, S s) async {
    final selected = ref.read(themeModeIdProvider);
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: AppColors.bgPanel,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 36,
                    height: 4,
                    margin: const EdgeInsets.only(bottom: 14),
                    decoration: BoxDecoration(
                      color: AppColors.border,
                      borderRadius: BorderRadius.circular(99),
                    ),
                  ),
                ),
                Text(
                  s('themeSettings'),
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textHi,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  s('themeHint'),
                  style: TextStyle(fontSize: 12.5, color: AppColors.textMid),
                ),
                const SizedBox(height: 14),
                for (final o in [
                  ('system', Icons.brightness_auto_outlined, s('themeSystem'), s('themeSystemSub')),
                  ('light', Icons.light_mode_outlined, s('themeLight'), s('themeLightSub')),
                  ('dark', Icons.dark_mode_outlined, s('themeDark'), s('themeDarkSub')),
                ]) ...[
                  SettingsOptionCard(
                    icon: o.$2,
                    title: o.$3,
                    subtitle: o.$4,
                    selected: selected == o.$1,
                    onTap: () async {
                      ref.read(themeModeIdProvider.notifier).state = o.$1;
                      await AppSettings.saveThemeId(o.$1);
                      if (sheetContext.mounted) Navigator.pop(sheetContext);
                    },
                  ),
                  const SizedBox(height: 10),
                ],
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    final themeId = ref.watch(themeModeIdProvider);
    final locale = ref.watch(localeCodeProvider);
    final city = ref.watch(selectedCityProvider);
    final vehicle = ref.watch(selectedVehicleProvider);

    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            Space.md,
            Space.xs,
            Space.md,
            Space.navContentClearance,
          ),
          children: [
            Text(
              s('more'),
              style: TypeTokens.h1.copyWith(color: AppColors.textHi),
            ),
            const SizedBox(height: Space.md),

            Container(
              padding: const EdgeInsets.all(Space.md),
              decoration: BoxDecoration(
                borderRadius: Radii.lgAll,
                border: Border.all(color: AppColors.border),
                color: AppColors.bgPanel,
              ),
              child: Row(
                children: [
                  Container(
                    width: 56,
                    height: 56,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppColors.bgPanelAlt,
                      border: Border.all(
                        color: AppColors.amber.withValues(alpha: 0.45),
                        width: 1.5,
                      ),
                    ),
                    child: Icon(Icons.person_outline, color: AppColors.amber, size: 28),
                  ),
                  const SizedBox(width: Space.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          s('guest'),
                          style: TypeTokens.title.copyWith(color: AppColors.textHi),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          s('guestSub'),
                          style: TypeTokens.caption.copyWith(color: AppColors.textSecondary),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: Space.xl),

            _SectionTitle(s('preferences')),
            const SizedBox(height: 8),
            _Group(
              children: [
                _RowItem(
                  icon: Icons.notifications_outlined,
                  tint: const Color(0xFF4FBE8D),
                  title: s('alerts'),
                  subtitle: s('alertsSub'),
                  trailing: Switch.adaptive(
                    value: _notifications,
                    activeTrackColor: AppColors.amber.withValues(alpha: 0.55),
                    activeThumbColor: AppColors.amber,
                    onChanged: _setAlerts,
                  ),
                ),
                _RowItem(
                  icon: Icons.inbox_outlined,
                  tint: const Color(0xFF5FB3D9),
                  title: s('notifications'),
                  subtitle: s('notificationsSub'),
                  trailing: Icon(
                    Directionality.of(context) == TextDirection.rtl
                        ? Icons.chevron_left
                        : Icons.chevron_right,
                    color: AppColors.textMid,
                  ),
                  onTap: () => context.push('/notifications'),
                ),
                _RowItem(
                  icon: Icons.share_outlined,
                  tint: const Color(0xFF4FBE8D),
                  title: s('share'),
                  subtitle: s('shareSub'),
                  trailing: Icon(Icons.ios_share, size: 16, color: AppColors.textMid),
                  onTap: () {
                    final prices =
                        ref.read(latestPricesProvider).value?.prices ?? [];
                    ShareService.sharePrices(prices, s);
                  },
                ),
                _RowItem(
                  icon: Icons.palette_outlined,
                  tint: AppColors.amber,
                  title: s('theme'),
                  subtitle: _themeLabel(themeId, s),
                  trailing: Icon(
                    Directionality.of(context) == TextDirection.rtl
                        ? Icons.chevron_left
                        : Icons.chevron_right,
                    color: AppColors.textMid,
                  ),
                  onTap: () => _openThemePicker(context, ref, s),
                ),
                _RowItem(
                  icon: Icons.language_outlined,
                  tint: const Color(0xFF5B9CFF),
                  title: s('language'),
                  subtitle: locale == 'ur' ? s('urdu') : s('english'),
                  trailing: Icon(
                    Directionality.of(context) == TextDirection.rtl
                        ? Icons.chevron_left
                        : Icons.chevron_right,
                    color: AppColors.textMid,
                  ),
                  onTap: () => context.push('/settings/language'),
                  showDivider: false,
                ),
              ],
            ),
            const SizedBox(height: 22),

            _SectionTitle(s('defaults')),
            const SizedBox(height: 8),
            _Group(
              children: [
                _RowItem(
                  icon: Icons.directions_car_outlined,
                  tint: const Color(0xFFB57BFF),
                  title: s('defaultVehicle'),
                  subtitle: vehicle.nickname,
                  trailing: Icon(
                    Directionality.of(context) == TextDirection.rtl
                        ? Icons.chevron_left
                        : Icons.chevron_right,
                    color: AppColors.textMid,
                  ),
                  onTap: () => context.push('/garage'),
                ),
                _RowItem(
                  icon: Icons.place_outlined,
                  tint: AppColors.danger,
                  title: s('defaultCity'),
                  subtitle: city.name,
                  showDivider: false,
                ),
              ],
            ),
            const SizedBox(height: Space.sm),
            const LocationFields(),
            const SizedBox(height: Space.xl),

            _SectionTitle(s('about')),
            const SizedBox(height: 8),
            _Group(
              children: [
                _RowItem(
                  icon: Icons.info_outline,
                  tint: AppColors.textMid,
                  title: s('about'),
                  subtitle: s('aboutSub'),
                ),
                _RowItem(
                  icon: Icons.privacy_tip_outlined,
                  tint: AppColors.textMid,
                  title: s('privacy'),
                  trailing: Icon(Icons.open_in_new, size: 16, color: AppColors.textMid),
                  onTap: () => launchUrl(
                    Uri.parse(AppConfig.privacyUrl),
                    mode: LaunchMode.externalApplication,
                  ),
                ),
                _RowItem(
                  icon: Icons.gavel_outlined,
                  tint: AppColors.textMid,
                  title: s('terms'),
                  trailing: Icon(Icons.open_in_new, size: 16, color: AppColors.textMid),
                  onTap: () => launchUrl(
                    Uri.parse(AppConfig.termsUrl),
                    mode: LaunchMode.externalApplication,
                  ),
                  showDivider: false,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.label);
  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(start: 2),
      child: Text(
        label.toUpperCase(),
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          letterSpacing: 1.0,
          color: AppColors.textMid,
        ),
      ),
    );
  }
}

class _Group extends StatelessWidget {
  const _Group({required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.bgPanel,
        borderRadius: Radii.xlAll,
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 14,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(children: children),
    );
  }
}

class _RowItem extends StatelessWidget {
  const _RowItem({
    required this.icon,
    required this.tint,
    required this.title,
    this.subtitle,
    this.trailing,
    this.onTap,
    this.showDivider = true,
  });

  final IconData icon;
  final Color tint;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  final bool showDivider;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Container(
                  width: 36,
                  height: 36,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: tint.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(icon, size: 19, color: tint),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: TextStyle(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w600,
                          color: AppColors.textHi,
                        ),
                      ),
                      if (subtitle != null) ...[
                        const SizedBox(height: 2),
                        Text(
                          subtitle!,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 12,
                            height: 1.3,
                            color: AppColors.textMid,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                if (trailing != null) ...[
                  const SizedBox(width: 8),
                  trailing!,
                ],
              ],
            ),
          ),
        ),
        if (showDivider)
          Divider(height: 1, indent: 62, color: AppColors.border),
      ],
    );
  }
}
