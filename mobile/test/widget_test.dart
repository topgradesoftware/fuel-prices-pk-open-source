import 'package:flutter_test/flutter_test.dart';
import 'package:oil_price_calculator/core/config/app_config.dart';

void main() {
  test('AppConfig exposes production API base by default', () {
    expect(AppConfig.apiBaseUrl, 'https://oilprice.topgradesoftware.com/api/v1');
    expect(AppConfig.privacyUrl, 'https://oilprice.topgradesoftware.com/privacy');
    expect(AppConfig.termsUrl, 'https://oilprice.topgradesoftware.com/terms');
  });
}
