import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../config.dart';
import '../widgets/chrome.dart';
import 'login_screen.dart';
import 'edit_profile_screen.dart';

/// Profile — always fetches fresh (web edits appear without re-login).
/// Pull-to-refresh + reloads after Edit + on appear.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> with WidgetsBindingObserver {
  bool _loading = true;
  String? _err;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _load(silent: true);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _load(silent: true);
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent && mounted) setState(() => _loading = true);
    try {
      final s = context.read<Session>();
      final r = await s.api.getProfile();
      s.resident = r;
      _err = null;
    } catch (e) {
      if (isAccountDisabled(e) && mounted) {
        forceLogoutToLogin(context, '$e'.replaceFirst('Exception: ', ''));
        return;
      }
      _err = '$e'.replaceFirst('Exception: ', '');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final me = context.watch<Session>().resident;
    return NavyScaffold(
      appBar: const AppHeader(title: 'Member Profile', showBack: true),
      body: _loading && me == null
          ? const Center(child: CircularProgressIndicator(color: Colors.white))
          : RefreshIndicator(
              onRefresh: () => _load(),
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(vertical: 14),
                child: Column(
                children: [
                  Builder(builder: (context) {
                    final url = AppConfig.photoUrl(me?.profilePhoto ?? '');
                    if (url == null) {
                      return CircleAvatar(
                        radius: 56,
                        backgroundColor: Colors.white,
                        child: Text(
                          '${(me?.firstName.isNotEmpty == true ? me!.firstName[0] : 'R')}${(me?.lastName.isNotEmpty == true ? me!.lastName[0] : 'S')}',
                          style: const TextStyle(fontSize: 36, fontWeight: FontWeight.bold, color: Color(0xFF2F6BFF)),
                        ),
                      );
                    }
                    return CircleAvatar(
                      radius: 56,
                      backgroundColor: Colors.white,
                      backgroundImage: NetworkImage(url),
                      onBackgroundImageError: (_, __) {},
                      child: null,
                    );
                  }),
                  const SizedBox(height: 10),
                  Text(me?.fullName ?? '', textAlign: TextAlign.center,
                      style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white)),
                  Text('Resident Number: ${me?.residentNumber ?? ''}',
                      style: const TextStyle(color: Color(0xFFB6C6E6))),
                  const SizedBox(height: 12),
                  if (_err != null)
                    Padding(padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Text(_err!, style: const TextStyle(color: Colors.redAccent))),
                  WhiteCard(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      _section('Main Information'),
                      _row('Contact Number *', me?.phone),
                      _row('Email', me?.email),
                      _row('Address', me?.address),
                      _row('Civil Status', me?.civilStatus),
                      _row('Children', '${me?.children.length ?? 0}'),
                      ExpansionTile(
                        title: const Text('View Full Information',
                            style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2F6BFF))),
                        tilePadding: EdgeInsets.zero,
                        childrenPadding: EdgeInsets.zero,
                        children: [
                      _section('1. Personal Information'),
                      _row('First Name *', me?.firstName),
                      _row('Middle Name', me?.middleName),
                      _row('Last Name *', me?.lastName),
                      _row('Extension', me?.extensionName),
                      _row('Gender', me?.gender),
                      _row('Civil Status', me?.civilStatus),
                      _row('Birthdate', me?.birthdate),
                      _row('Contact Number *', me?.phone),
                      _row('Email', me?.email),
                      _row('Address', me?.address),
                      _row('Occupation', me?.occupation),
                      _row('Employer', me?.employer),
                      _row('Employer Address', me?.employerAddress),
                      _section('2. Spouse Information'),
                      _row('Spouse Name', me?.spouseName),
                      _row('Spouse Occupation', me?.spouseOccupation),
                      _row('Spouse Employer', me?.spouseEmployer),
                      _section('3. Children Information'),
                      if (me == null || me.children.isEmpty)
                        const Text('No children recorded.',
                            style: TextStyle(color: Colors.grey, fontSize: 13)),
                      if (me != null)
                        for (var i = 0; i < me.children.length; i++)
                          _row('Child ${i + 1}',
                              '${me.children[i].name}${me.children[i].age != null ? ' (${me.children[i].age} y/o)' : ''}'),
                      _section('4. Parents Information'),
                      _row('Father', me?.fatherName),
                      _row('Mother', me?.motherName),
                      _section('5. References & Signature'),
                      _row('Reference 1', me?.reference1Name),
                      _row('Reference 1 Contact', me?.reference1Contact),
                      _row('Reference 2', me?.reference2Name),
                      _row('Reference 2 Contact', me?.reference2Contact),
                      _row('Signature', me?.signature),
                      _section('6. Security (recovery)'),
                      _row('Security Question', me?.securityQuestion),
                        ],
                      ),
                      const SizedBox(height: 16),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: () => Navigator.push(context,
                              MaterialPageRoute(builder: (_) => const EditProfileScreen())).then((_) => _load(silent: true)),
                          child: const Text('Edit Profile'),
                        ),
                      ),
                    ]),
                  ),
                ],
                ),
              ),
            ),
    );
  }

  Widget _section(String t) => Padding(
        padding: const EdgeInsets.only(top: 14, bottom: 6),
        child: Text(t, style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2F6BFF), fontSize: 15)),
      );

  Widget _row(String label, String? value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 150, child: Text(label, style: const TextStyle(color: Colors.grey, fontSize: 13))),
            Expanded(child: Text((value == null || value.isEmpty) ? 'Not set' : value,
                style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))),
          ],
        ),
      );
}
