/// Mirrors the `fuel_prices` entity from Spec §9.1, joined against
/// company/city/fuel_type rather than carrying a single global price.
class FuelPrice {
  final String id;
  final String companyId;
  final String cityId;
  final String fuelTypeId;
  final double price;
  final double? previousPrice;
  final String currency;
  final String unit;
  final DateTime effectiveDate;
  final String source; // e.g. "trackmate", "manual", "pso_api"
  final String? sourceUrl;
  final bool verified;

  const FuelPrice({
    required this.id,
    required this.companyId,
    required this.cityId,
    required this.fuelTypeId,
    required this.price,
    this.previousPrice,
    this.currency = 'PKR',
    required this.unit,
    required this.effectiveDate,
    this.source = 'trackmate',
    this.sourceUrl,
    this.verified = false,
  });

  double get changeAmount => previousPrice == null ? 0 : price - previousPrice!;

  double get changePercentage =>
      previousPrice == null || previousPrice == 0 ? 0 : (changeAmount / previousPrice!) * 100;

  bool get isIncrease => changeAmount > 0.001;
  bool get isDecrease => changeAmount < -0.001;
  bool get isUnchanged => !isIncrease && !isDecrease;

  factory FuelPrice.fromJson(Map<String, dynamic> json) => FuelPrice(
        id: json['id'].toString(),
        companyId: json['company_id'].toString(),
        cityId: json['city_id'].toString(),
        fuelTypeId: json['fuel_type_id'].toString(),
        price: (json['price'] as num).toDouble(),
        previousPrice: (json['previous_price'] as num?)?.toDouble(),
        currency: json['currency'] as String? ?? 'PKR',
        unit: json['unit'] as String? ?? 'Rs/L',
        effectiveDate: DateTime.parse(json['effective_date'] as String),
        source: json['source'] as String? ?? 'trackmate',
        sourceUrl: json['source_url'] as String?,
        verified: json['verified'] as bool? ?? false,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'company_id': companyId,
        'city_id': cityId,
        'fuel_type_id': fuelTypeId,
        'price': price,
        'previous_price': previousPrice,
        'currency': currency,
        'unit': unit,
        'effective_date': effectiveDate.toIso8601String(),
        'source': source,
        'source_url': sourceUrl,
        'verified': verified,
      };
}

/// One point on a Price History chart (Spec §3.5 / §9.1 fuel_price_history).
class FuelPricePoint {
  final DateTime date;
  final double price;
  const FuelPricePoint({required this.date, required this.price});
}
