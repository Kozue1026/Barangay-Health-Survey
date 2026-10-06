import 'package:flutter_test/flutter_test.dart';
import 'package:bhc_resident/main.dart';
import 'package:bhc_resident/services/mock_api_service.dart';

void main() {
  testWidgets('Login screen shows resident login', (WidgetTester tester) async {
    await tester.pumpWidget(BhcApp(api: MockApiService()));
    expect(find.text('Barangay Health Center'), findsWidgets);
    expect(find.text('LOGIN'), findsOneWidget);
  });
}
