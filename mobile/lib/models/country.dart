class Country {
  final String code;
  final String name;
  final String currency;
  final String currencySymbol;
  final String unitVolume;

  const Country({
    required this.code,
    required this.name,
    required this.currency,
    required this.currencySymbol,
    this.unitVolume = 'L',
  });

  factory Country.fromJson(Map<String, dynamic> json) => Country(
        code: (json['code'] ?? 'PK').toString(),
        name: (json['name'] ?? json['code'] ?? 'Pakistan').toString(),
        currency: (json['currency'] ?? 'PKR').toString(),
        currencySymbol:
            (json['currency_symbol'] ?? json['currencySymbol'] ?? 'Rs').toString(),
        unitVolume: (json['unit_volume'] ?? json['unitVolume'] ?? 'L').toString(),
      );

  Map<String, dynamic> toJson() => {
        'code': code,
        'name': name,
        'currency': currency,
        'currency_symbol': currencySymbol,
        'unit_volume': unitVolume,
      };
}

const Country kDefaultCountry = Country(
  code: 'PK',
  name: 'Pakistan',
  currency: 'PKR',
  currencySymbol: 'Rs',
);

/// Offline fallback only — prefer `GET /api/v1/countries` (full world list).
const List<Country> kDefaultCountries = [
  kDefaultCountry,
  Country(
    code: 'IN',
    name: 'India',
    currency: 'INR',
    currencySymbol: '₹',
  ),
  Country(
    code: 'BD',
    name: 'Bangladesh',
    currency: 'BDT',
    currencySymbol: '৳',
  ),
];
