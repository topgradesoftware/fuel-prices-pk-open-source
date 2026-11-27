import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/i18n/strings.dart';
import '../../core/network/api_client.dart';
import '../../core/services/app_providers.dart';
import '../../core/services/app_settings.dart';
import '../../core/services/notification_store.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/ui/app_states.dart';

class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    _syncRemote();
  }

  Future<void> _syncRemote() async {
    try {
      final data = await ApiClient().get('/notifications');
      final items = data['items'] as List? ?? data['value'] as List? ?? const [];
      for (final raw in items.reversed) {
        final m = Map<String, dynamic>.from(raw as Map);
        await NotificationStore.add(AppNotification(
          id: 'srv_${m['id']}',
          title: m['title'] as String? ?? '',
          body: m['body'] as String? ?? '',
          createdAt: DateTime.tryParse(m['created_at'] as String? ?? '') ?? DateTime.now(),
          kind: (m['type'] == 'PRICE_CHANGE') ? 'increase' : 'info',
        ));
      }
      if (mounted) {
        ref.read(notificationFeedProvider.notifier).state = NotificationStore.load();
      }
    } catch (_) {
      // Offline — local Hive feed still shows.
    }
  }

  String _relative(DateTime at, S s) {
    final diff = DateTime.now().difference(at);
    if (diff.inMinutes < 1) return s('justNow');
    if (diff.inHours < 1) return '${diff.inMinutes}${s('minutesAgo')}';
    if (diff.inDays < 1) return '${s('today')} · ${DateFormat('HH:mm').format(at)}';
    if (diff.inDays == 1) return s('yesterday');
    return DateFormat('d MMM').format(at);
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    final items = ref.watch(notificationFeedProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(s('notifications')),
        actions: [
          if (items.isNotEmpty)
            TextButton(
              onPressed: () async {
                await NotificationStore.clear();
                ref.read(notificationFeedProvider.notifier).state = const [];
              },
              child: Text(s('clear')),
            ),
        ],
      ),
      body: items.isEmpty
          ? AppEmptyState(
              title: s('noNotifications'),
              icon: Icons.notifications_off_outlined,
            )
          : RefreshIndicator(
              onRefresh: () async {
                await _syncRemote();
                ref.read(notificationFeedProvider.notifier).state =
                    NotificationStore.load();
              },
              child: ListView.separated(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, Space.navContentClearance),
                itemCount: items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 12),
                itemBuilder: (context, i) {
                  final n = items[i];
                  final (icon, color) = _styleFor(n.kind);
                  final when = _relative(n.createdAt, s);
                  return Container(
                    padding: const EdgeInsets.all(16),
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
                    child: Row(
                      children: [
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            color: color.withValues(alpha: 0.15),
                            shape: BoxShape.circle,
                            border: Border.all(color: color.withValues(alpha: 0.3)),
                          ),
                          child: Icon(icon, color: color, size: 20),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                n.title,
                                style: TextStyle(
                                  fontWeight: FontWeight.w700,
                                  fontSize: 14.5,
                                  color: AppColors.textHi,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                '${n.body} · $when',
                                style: TextStyle(
                                  color: AppColors.textSecondary,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
            ),
    );
  }

  static (IconData, Color) _styleFor(String kind) {
    switch (kind) {
      case 'increase':
        return (Icons.trending_up, AppColors.up);
      case 'decrease':
        return (Icons.trending_down, AppColors.down);
      default:
        return (Icons.campaign_outlined, AppColors.amber);
    }
  }
}
