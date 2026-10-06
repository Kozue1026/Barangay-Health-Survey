import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../theme.dart';
import '../widgets/chrome.dart';
import 'login_screen.dart';
import 'profile_screen.dart';
import 'edit_profile_screen.dart';
import 'survey_list_screen.dart';
import 'change_password_screen.dart';

/// Home dashboard. Auto-refreshes counts + profile name on appear,
/// on resume, every 15s via sync_check, and via pull-to-refresh.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with WidgetsBindingObserver {
  Map<String, int>? _counts;
  Timer? _poll;
  int? _surveysVersion;
  String? _profileVersion;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
    _poll = Timer.periodic(const Duration(seconds: 15), (_) => _checkSync());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _poll?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _load();
  }

  Future<void> _load() async {
    final s = context.read<Session>();
    try {
      final c = await s.api.dashboardCounts(s.resident?.id ?? '1');
      // Show counts immediately so Pending moves the moment Active does,
      // then refresh display name without blocking the cards.
      if (mounted) setState(() => _counts = Map<String, int>.from(c));
      try {
        final r = await s.api.getProfile();
        s.resident = r;
        if (mounted) setState(() {});
      } catch (e) {
        if (isAccountDisabled(e) && mounted) {
          forceLogoutToLogin(context, '$e'.replaceFirst('Exception: ', ''));
          return;
        }
      }
    } catch (e) {
      if (isAccountDisabled(e) && mounted) {
        forceLogoutToLogin(context, '$e'.replaceFirst('Exception: ', ''));
      }
    }
  }

  Future<void> _checkSync() async {
    if (!mounted) return;
    try {
      final s = context.read<Session>();
      final v = await s.api.syncCheck(s.resident?.id ?? '');
      final sv = v['surveys_version'];
      final pv = '${v['profile_updated_at'] ?? ''}';
      final svi = sv is int ? sv : int.tryParse('$sv') ?? 0;
      if (_surveysVersion != null && svi != _surveysVersion) {
        await _load();
      } else if (_profileVersion != null && pv != _profileVersion && pv.isNotEmpty) {
        await _load();
      }
      _surveysVersion = svi;
      _profileVersion = pv;
    } catch (e) {
      if (isAccountDisabled(e) && mounted) {
        forceLogoutToLogin(context, '$e'.replaceFirst('Exception: ', ''));
      }
    }
  }

  void _go(Widget page) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => page)).then((_) {
      _load();
      _checkSync();
    });
  }

  @override
  Widget build(BuildContext context) {
    final me = context.watch<Session>().resident;
    return NavyScaffold(
      appBar: const AppHeader(title: 'Barangay Health Center'),
      body: RefreshIndicator(
        onRefresh: _load,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.symmetric(vertical: 14),
          child: Column(
            children: [
            Container(
              margin: const EdgeInsets.symmetric(horizontal: 16),
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(16),
                image: const DecorationImage(
                    image: AssetImage('assets/card_hero.png'), fit: BoxFit.cover),
                color: AppTheme.primary,
              ),
              child: Column(
                children: [
                  Text('Welcome, ${me?.firstName ?? 'Member'}!',
                      textAlign: TextAlign.center,
                      style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white)),
                  const SizedBox(height: 4),
                  Text('Resident Number: ${me?.residentNumber ?? ''}',
                      style: const TextStyle(color: Color(0xFFDCE8FF))),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: [
                  _statCard('Active', '${_counts?['active'] ?? '-'}', Colors.blue),
                  const SizedBox(width: 10),
                  _statCard('Completed', '${_counts?['completed'] ?? '-'}', Colors.green),
                  const SizedBox(width: 10),
                  _statCard('Pending', '${_counts?['pending'] ?? '-'}', Colors.orange),
                ],
              ),
            ),
            const SizedBox(height: 14),
            WhiteCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text('MENU', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey)),
                  const SizedBox(height: 10),
                  _menuBtn(context, Icons.person, 'My Profile', 'View your resident information',
                      () => _go(const ProfileScreen())),
                  _menuBtn(context, Icons.edit, 'Edit Profile', 'Update personal and family records',
                      () => _go(const EditProfileScreen())),
                  _menuBtn(context, Icons.assignment, 'Survey', 'Answer health surveys from the center',
                      () => _go(const SurveyListScreen())),
                  _menuBtn(context, Icons.lock_outline, 'Change Password', 'Update login password',
                      () => _go(const ChangePasswordScreen())),
                  _menuBtn(context, Icons.logout, 'Logout', 'Sign out of this device', () {
                    context.read<Session>().resident = null;
                    Navigator.pushAndRemoveUntil(context,
                        MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
                  }),
                ],
              ),
            ),
            const SizedBox(height: 12),
            const Text('Barangay Health Center members receive free health check-ups and priority assistance.',
                textAlign: TextAlign.center, style: TextStyle(color: AppTheme.mutedBlue, fontSize: 12)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _statCard(String label, String value, Color c) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
        child: Column(children: [
          Text(value, style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: c)),
          Text(label, style: const TextStyle(color: Colors.grey, fontSize: 12)),
        ]),
      ),
    );
  }

  Widget _menuBtn(BuildContext ctx, IconData icon, String title, String sub, VoidCallback onTap) {
    return Card(
      elevation: 0,
      color: const Color(0xFFF4F7FF),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: ListTile(
        leading: Icon(icon, color: AppTheme.primary),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Text(sub, style: const TextStyle(fontSize: 12)),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }
}
