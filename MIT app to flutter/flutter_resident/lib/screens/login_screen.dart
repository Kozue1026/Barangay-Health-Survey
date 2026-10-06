import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/resident.dart';
import '../services/api_interface.dart';
import '../theme.dart';
import '../widgets/chrome.dart';
import 'register_screen.dart';
import 'home_screen.dart';

class Session extends ChangeNotifier {
  final ApiInterface api;
  Resident? resident;
  Session(this.api);
}

/// True when the server says the account was disabled/deleted by admin.
bool isAccountDisabled(Object e) {
  final m = '$e'.toLowerCase();
  return m.contains('disabled') || m.contains('account not found');
}

void forceLogoutToLogin(BuildContext context, String message) {
  try {
    context.read<Session>().resident = null;
  } catch (_) {}
  Navigator.pushAndRemoveUntil(
    context,
    MaterialPageRoute(builder: (_) => LoginScreen(notice: message)),
    (_) => false,
  );
}

class LoginScreen extends StatefulWidget {
  final String? notice;
  const LoginScreen({super.key, this.notice});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _id = TextEditingController();
  final _pw = TextEditingController();
  bool _obscure = true;
  bool _busy = false;
  String? _err;

  @override
  void initState() {
    super.initState();
    _err = widget.notice;
  }

  Future<void> _doLogin() async {
    final api = context.read<Session>().api;
    setState(() { _busy = true; _err = null; });
    try {
      // Phase 1 mock accepts RES-0001 / password123
      final r = await api.login(_id.text.trim(), _pw.text);
      context.read<Session>().resident = r;
      if (!mounted) return;
      Navigator.pushReplacement(
          context, MaterialPageRoute(builder: (_) => const HomeScreen()));
    } catch (e) {
      setState(() => _err = '$e'.replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return NavyScaffold(
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(vertical: 30),
        child: Column(
          children: [
            Image.asset('assets/logo_bhc.png', width: 96, height: 96,
                errorBuilder: (_, _, _) => const Icon(Icons.local_hospital, size: 80, color: Colors.white)),
            const SizedBox(height: 12),
            const Text('Barangay Health Center',
                style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold, color: Colors.white)),
            const Text('Survey Management System',
                style: TextStyle(fontSize: 15, color: AppTheme.lightBlue)),
            const SizedBox(height: 6),
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 40),
              child: Text('Welcome. Manage community health surveys and support better care for barangay residents.',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 13, color: AppTheme.mutedBlue)),
            ),
            const SizedBox(height: 18),
            WhiteCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text('Sign in', textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 4),
                  const Text('Resident login (RES-0001 format)',
                      textAlign: TextAlign.center, style: TextStyle(color: Colors.grey)),
                  const SizedBox(height: 16),
                  if (_err != null)
                    Container(
                      padding: const EdgeInsets.all(10),
                      margin: const EdgeInsets.only(bottom: 12),
                      decoration: BoxDecoration(
                          color: Colors.red.shade50,
                          borderRadius: BorderRadius.circular(8)),
                      child: Text(_err!, style: const TextStyle(color: Colors.red)),
                    ),
                  TextField(
                    controller: _id,
                    decoration: InputDecoration(
                        labelText: 'Resident Number (e.g. RES-0001)',
                        prefixIcon: const Icon(Icons.badge),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(10),
                          borderSide: BorderSide(color: Colors.grey.shade400),
                        ),
                        focusedBorder: const OutlineInputBorder(
                          borderRadius: BorderRadius.all(Radius.circular(10)),
                          borderSide:
                              BorderSide(color: AppTheme.primary, width: 1.6),
                        )),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _pw,
                    obscureText: _obscure,
                    decoration: InputDecoration(
                      labelText: 'Password',
                      prefixIcon: const Icon(Icons.lock),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(10),
                        borderSide: BorderSide(color: Colors.grey.shade400),
                      ),
                      focusedBorder: const OutlineInputBorder(
                        borderRadius: BorderRadius.all(Radius.circular(10)),
                        borderSide:
                            BorderSide(color: AppTheme.primary, width: 1.6),
                      ),
                      suffixIcon: IconButton(
                        icon: Icon(_obscure ? Icons.visibility_off : Icons.visibility),
                        onPressed: () => setState(() => _obscure = !_obscure),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  ElevatedButton(
                    onPressed: _busy ? null : _doLogin,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primary,
                      foregroundColor: Colors.white,
                      minimumSize: const Size.fromHeight(52),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                      textStyle: const TextStyle(
                          fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                    child: Text(_busy ? 'SIGNING IN...' : 'LOGIN'),
                  ),
                  const SizedBox(height: 12),
                  const Text("Don't have an account?",
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.grey, fontSize: 13)),
                  const SizedBox(height: 8),
                  ElevatedButton(
                    onPressed: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                            builder: (_) => const RegisterScreen())),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFE6EEFF),
                      foregroundColor: AppTheme.primary,
                      minimumSize: const Size.fromHeight(52),
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                      textStyle: const TextStyle(
                          fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                    child: const Text('SIGN UP'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
