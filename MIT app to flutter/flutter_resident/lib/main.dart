import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'config.dart';
import 'theme.dart';
import 'services/api_interface.dart';
import 'services/mock_api_service.dart';
import 'services/php_api_service.dart';
import 'screens/login_screen.dart';

void main() {
  final ApiInterface api = AppConfig.useMock ? MockApiService() : PhpApiService();
  runApp(BhcApp(api: api));
}

class BhcApp extends StatelessWidget {
  final ApiInterface api;
  const BhcApp({super.key, required this.api});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => Session(api),
      child: MaterialApp(
        title: 'Barangay Health Survey',
        theme: AppTheme.theme(),
        home: const LoginScreen(),
        debugShowCheckedModeBanner: false,
      ),
    );
  }
}
