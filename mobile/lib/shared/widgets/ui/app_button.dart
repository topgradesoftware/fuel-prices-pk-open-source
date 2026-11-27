import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

enum AppButtonVariant { primary, secondary, outlined, ghost, danger }

class AppButton extends StatefulWidget {
  const AppButton({
    super.key,
    required this.label,
    this.onPressed,
    this.variant = AppButtonVariant.primary,
    this.icon,
    this.expanded = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final AppButtonVariant variant;
  final IconData? icon;
  final bool expanded;

  @override
  State<AppButton> createState() => _AppButtonState();
}

class _AppButtonState extends State<AppButton> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final child = widget.icon == null
        ? Text(widget.label, style: const TextStyle(fontWeight: FontWeight.w600))
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(widget.icon, size: 18),
              const SizedBox(width: Space.xs),
              Text(widget.label, style: const TextStyle(fontWeight: FontWeight.w600)),
            ],
          );

    final button = switch (widget.variant) {
      AppButtonVariant.primary => Container(
          decoration: BoxDecoration(
            borderRadius: Radii.mdAll,
            gradient: LinearGradient(
              colors: [AppColors.amber, AppColors.amberDim],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.amber.withValues(alpha: 0.3),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: FilledButton(
            onPressed: widget.onPressed,
            style: FilledButton.styleFrom(
              backgroundColor: Colors.transparent,
              shadowColor: Colors.transparent,
              foregroundColor: AppColors.onPrimary,
              shape: RoundedRectangleBorder(borderRadius: Radii.mdAll),
              minimumSize: const Size(64, Space.tapTarget),
            ),
            child: child,
          ),
        ),
      AppButtonVariant.secondary => FilledButton(
          onPressed: widget.onPressed,
          style: FilledButton.styleFrom(
            backgroundColor: AppColors.bgPanelAlt,
            foregroundColor: AppColors.textHi,
            shape: RoundedRectangleBorder(
              borderRadius: Radii.mdAll,
              side: BorderSide(color: AppColors.border),
            ),
            minimumSize: const Size(64, Space.tapTarget),
          ),
          child: child,
        ),
      AppButtonVariant.outlined => OutlinedButton(
          onPressed: widget.onPressed,
          style: OutlinedButton.styleFrom(
            side: BorderSide(color: AppColors.amber.withValues(alpha: 0.5)),
            foregroundColor: AppColors.amber,
            shape: RoundedRectangleBorder(borderRadius: Radii.mdAll),
            minimumSize: const Size(64, Space.tapTarget),
          ),
          child: child,
        ),
      AppButtonVariant.ghost => TextButton(
          onPressed: widget.onPressed,
          style: TextButton.styleFrom(
            foregroundColor: AppColors.amber,
            minimumSize: const Size(48, Space.tapTarget),
          ),
          child: child,
        ),
      AppButtonVariant.danger => FilledButton(
          onPressed: widget.onPressed,
          style: FilledButton.styleFrom(
            backgroundColor: AppColors.danger,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: Radii.mdAll),
            minimumSize: const Size(64, Space.tapTarget),
          ),
          child: child,
        ),
    };

    final animated = GestureDetector(
      onTapDown: (_) => setState(() => _pressed = true),
      onTapUp: (_) => setState(() => _pressed = false),
      onTapCancel: () => setState(() => _pressed = false),
      child: AnimatedScale(
        scale: _pressed ? 0.97 : 1.0,
        duration: const Duration(milliseconds: 100),
        curve: Curves.easeOutCubic,
        child: button,
      ),
    );

    if (!widget.expanded) return animated;
    return SizedBox(width: double.infinity, child: animated);
  }
}

class AppFab extends StatelessWidget {
  const AppFab({super.key, required this.onPressed, this.icon = Icons.add});

  final VoidCallback onPressed;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: LinearGradient(
          colors: [AppColors.amber, AppColors.amberDim],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.amber.withValues(alpha: 0.35),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: FloatingActionButton(
        onPressed: onPressed,
        elevation: 0,
        highlightElevation: 0,
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.onPrimary,
        child: Icon(icon, size: 26),
      ),
    );
  }
}
