import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import 'app_button.dart';

class AppSkeletonList extends StatelessWidget {
  const AppSkeletonList({super.key, this.count = 4});

  final int count;

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(Space.md, Space.sm, Space.md, Space.xl),
      itemCount: count,
      separatorBuilder: (_, __) => const SizedBox(height: Space.sm),
      itemBuilder: (_, __) => const _SkeletonCard(),
    );
  }
}

class _SkeletonCard extends StatefulWidget {
  const _SkeletonCard();

  @override
  State<_SkeletonCard> createState() => _SkeletonCardState();
}

class _SkeletonCardState extends State<_SkeletonCard>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c;

  @override
  void initState() {
    super.initState();
    _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1400))..repeat();
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _c,
      builder: (context, _) {
        final alignX = -2.0 + (_c.value * 4.0);
        return Container(
          height: 112,
          decoration: BoxDecoration(
            borderRadius: Radii.lgAll,
            border: Border.all(color: AppColors.border),
            gradient: LinearGradient(
              begin: Alignment(alignX - 1, 0),
              end: Alignment(alignX + 1, 0),
              colors: [
                AppColors.bgPanel,
                AppColors.bgPanelAlt,
                AppColors.bgPanel,
              ],
            ),
          ),
        );
      },
    );
  }
}

class AppEmptyState extends StatelessWidget {
  const AppEmptyState({
    super.key,
    required this.title,
    this.message,
    this.actionLabel,
    this.onAction,
    this.icon = Icons.inbox_outlined,
  });

  final String title;
  final String? message;
  final String? actionLabel;
  final VoidCallback? onAction;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Space.xl),
        child: Container(
          padding: const EdgeInsets.all(Space.lg),
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
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.amber.withValues(alpha: 0.12),
                  border: Border.all(color: AppColors.amber.withValues(alpha: 0.3)),
                ),
                child: Icon(icon, size: 28, color: AppColors.amber),
              ),
              const SizedBox(height: Space.md),
              Text(
                title,
                textAlign: TextAlign.center,
                style: TypeTokens.title.copyWith(color: AppColors.textHi),
              ),
              if (message != null) ...[
                const SizedBox(height: Space.xs),
                Text(
                  message!,
                  textAlign: TextAlign.center,
                  style: TypeTokens.caption.copyWith(color: AppColors.textSecondary),
                ),
              ],
              if (actionLabel != null && onAction != null) ...[
                const SizedBox(height: Space.lg),
                AppButton(label: actionLabel!, onPressed: onAction),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class AppErrorState extends StatelessWidget {
  const AppErrorState({
    super.key,
    required this.title,
    this.message,
    this.onRetry,
    this.retryLabel = 'Retry',
  });

  final String title;
  final String? message;
  final VoidCallback? onRetry;
  final String retryLabel;

  @override
  Widget build(BuildContext context) {
    return AppEmptyState(
      icon: Icons.error_outline,
      title: title,
      message: message,
      actionLabel: onRetry == null ? null : retryLabel,
      onAction: onRetry,
    );
  }
}
