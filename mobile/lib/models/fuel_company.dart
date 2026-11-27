/// Mirrors the `fuel_companies` entity from Spec §9.1.
/// New companies can be added purely via backend config —
/// nothing here is hard-coded into a screen.
class FuelCompany {
  final String id;
  final String name;
  final String shortName;
  final String slug;
  final String? logoUrl;
  final String? website;
  final String? customerService;
  final bool isActive;
  final int priority;

  const FuelCompany({
    required this.id,
    required this.name,
    required this.shortName,
    required this.slug,
    this.logoUrl,
    this.website,
    this.customerService,
    this.isActive = true,
    this.priority = 0,
  });

  factory FuelCompany.fromJson(Map<String, dynamic> json) => FuelCompany(
        id: json['id'].toString(),
        name: json['name'] as String,
        shortName: json['short_name'] as String? ?? json['name'] as String,
        slug: json['slug'] as String,
        logoUrl: json['logo'] as String?,
        website: json['website'] as String?,
        customerService: json['customer_service'] as String?,
        isActive: json['is_active'] as bool? ?? true,
        priority: json['priority'] as int? ?? 0,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'short_name': shortName,
        'slug': slug,
        'logo': logoUrl,
        'website': website,
        'customer_service': customerService,
        'is_active': isActive,
        'priority': priority,
      };
}
