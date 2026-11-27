import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

class SearchableOption {
  final String id;
  final String label;
  final String? subtitle;

  const SearchableOption({
    required this.id,
    required this.label,
    this.subtitle,
  });
}

Future<T?> showSearchablePicker<T>({
  required BuildContext context,
  required String title,
  required String searchHint,
  required List<T> items,
  required String Function(T) labelOf,
  String? Function(T)? subtitleOf,
  String? Function(T)? idOf,
  T? selected,
  List<String>? pinIds,
}) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: AppColors.bgPanel,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(Radii.xxl)),
    ),
    builder: (context) {
      return _SearchablePickerSheet<T>(
        title: title,
        searchHint: searchHint,
        items: items,
        labelOf: labelOf,
        subtitleOf: subtitleOf,
        idOf: idOf,
        selected: selected,
        pinIds: pinIds,
      );
    },
  );
}

class _SearchablePickerSheet<T> extends StatefulWidget {
  const _SearchablePickerSheet({
    required this.title,
    required this.searchHint,
    required this.items,
    required this.labelOf,
    this.subtitleOf,
    this.idOf,
    this.selected,
    this.pinIds,
  });

  final String title;
  final String searchHint;
  final List<T> items;
  final String Function(T) labelOf;
  final String? Function(T)? subtitleOf;
  final String? Function(T)? idOf;
  final T? selected;
  final List<String>? pinIds;

  @override
  State<_SearchablePickerSheet<T>> createState() => _SearchablePickerSheetState<T>();
}

class _SearchablePickerSheetState<T> extends State<_SearchablePickerSheet<T>> {
  final _controller = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  List<T> get _filtered {
    final q = _query.trim().toLowerCase();
    var list = widget.items;
    if (q.isNotEmpty) {
      list = list.where((item) {
        final label = widget.labelOf(item).toLowerCase();
        final sub = (widget.subtitleOf?.call(item) ?? '').toLowerCase();
        final id = (widget.idOf?.call(item) ?? '').toLowerCase();
        return label.contains(q) || sub.contains(q) || id.contains(q);
      }).toList();
    }

    final pins = widget.pinIds;
    if (pins == null || pins.isEmpty || q.isNotEmpty) return list;

    final rank = {for (var i = 0; i < pins.length; i++) pins[i]: i};
    final copy = [...list];
    copy.sort((a, b) {
      final idA = widget.idOf?.call(a) ?? '';
      final idB = widget.idOf?.call(b) ?? '';
      final pa = rank[idA];
      final pb = rank[idB];
      if (pa != null || pb != null) {
        return (pa ?? 999).compareTo(pb ?? 999);
      }
      return widget.labelOf(a).toLowerCase().compareTo(widget.labelOf(b).toLowerCase());
    });
    return copy;
  }

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    final keyboard = media.viewInsets.bottom;
    // Keep sheet above the keyboard so title + search stay visible.
    final maxHeight = media.size.height - keyboard - media.padding.top;
    final height = (media.size.height * 0.72).clamp(280.0, maxHeight);

    return AnimatedPadding(
      duration: const Duration(milliseconds: 120),
      curve: Curves.easeOut,
      padding: EdgeInsets.only(bottom: keyboard),
      child: SizedBox(
        height: height,
        child: Column(
          children: [
            const SizedBox(height: 10),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
              child: Align(
                alignment: AlignmentDirectional.centerStart,
                child: Text(
                  widget.title,
                  style: TypeTokens.h3.copyWith(color: AppColors.textHi),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: TextField(
                controller: _controller,
                autofocus: true,
                onChanged: (v) => setState(() => _query = v),
                decoration: InputDecoration(
                  hintText: widget.searchHint,
                  prefixIcon: const Icon(Icons.search, size: 20),
                  filled: true,
                  fillColor: AppColors.bgPanelAlt,
                  border: OutlineInputBorder(
                    borderRadius: Radii.mdAll,
                    borderSide: BorderSide(color: AppColors.border),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: Radii.mdAll,
                    borderSide: BorderSide(color: AppColors.border),
                  ),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: _filtered.isEmpty
                  ? Center(
                      child: Text(
                        'No matches',
                        style: TextStyle(color: AppColors.textMid),
                      ),
                    )
                  : ListView.separated(
                      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
                      itemCount: _filtered.length,
                      separatorBuilder: (_, __) => Divider(height: 1, color: AppColors.border),
                      itemBuilder: (context, i) {
                        final item = _filtered[i];
                        final selected = widget.selected != null &&
                            (identical(item, widget.selected) ||
                                (widget.idOf != null &&
                                    widget.idOf!(item) ==
                                        widget.idOf!(widget.selected as T)));
                        final sub = widget.subtitleOf?.call(item);
                        return ListTile(
                          minVerticalPadding: 12,
                          title: Text(widget.labelOf(item)),
                          subtitle: sub == null || sub.isEmpty ? null : Text(sub),
                          trailing: selected
                              ? Icon(Icons.check, color: AppColors.amber)
                              : null,
                          onTap: () => Navigator.of(context).pop(item),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
