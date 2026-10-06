import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../widgets/chrome.dart';
import 'login_screen.dart';

/// Change password only (no forgot-flow). Old + new + confirm.
/// Server does bcrypt verify + hash — phone never sees hashes.
class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});
  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _old = TextEditingController();
  final _nw = TextEditingController();
  final _cf = TextEditingController();
  bool _busy = false;
  String? _err;
  String? _ok;

  Future<void> _save() async {
    setState(() { _busy = true; _err = null; _ok = null; });
    try {
      if (_nw.text.length < 8 || !RegExp(r'[A-Za-z]').hasMatch(_nw.text) || !RegExp(r'[0-9]').hasMatch(_nw.text)) {
        throw Exception('Password must be at least 8 characters long and contain both letters and numbers.');
      }
      if (_nw.text != _cf.text) throw Exception('New passwords do not match.');
      final api = context.read<Session>().api;
      // ApiInterface extension: use dynamic to avoid breaking mock signature.
      final dynamic d = api;
      await d.changePassword(oldPassword: _old.text, newPassword: _nw.text);
      setState(() => _ok = 'Password changed successfully. Use it on web and mobile.');
      _old.clear(); _nw.clear(); _cf.clear();
    } catch (e) {
      if (isAccountDisabled(e) && mounted) {
        forceLogoutToLogin(context, '$e'.replaceFirst('Exception: ', ''));
        return;
      }
      setState(() => _err = '$e'.replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return NavyScaffold(
      appBar: const AppHeader(title: 'Change Password', showBack: true),
      body: RefreshIndicator(
        onRefresh: () async {
          await Future.delayed(const Duration(milliseconds: 400));
          if (mounted) setState(() { _err = null; });
        },
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.symmetric(vertical: 14),
          child: WhiteCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Text('Resident Number stays the same (RES-XXXX). Only the password changes, synced to web via Firebase.',
                style: TextStyle(color: Colors.grey, fontSize: 13)),
            const SizedBox(height: 12),
            if (_err != null)
              Container(padding: const EdgeInsets.all(10), margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(8)),
                  child: Text(_err!, style: const TextStyle(color: Colors.red))),
            if (_ok != null)
              Container(padding: const EdgeInsets.all(10), margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(color: Colors.green.shade50, borderRadius: BorderRadius.circular(8)),
                  child: Text(_ok!, style: const TextStyle(color: Colors.green, fontWeight: FontWeight.bold))),
            TextField(controller: _old, obscureText: true, decoration: const InputDecoration(labelText: 'Current Password *')),
            const SizedBox(height: 10),
            TextField(controller: _nw, obscureText: true, decoration: const InputDecoration(labelText: 'New Password (8+ letters & numbers) *')),
            const SizedBox(height: 10),
            TextField(controller: _cf, obscureText: true, decoration: const InputDecoration(labelText: 'Confirm New Password *')),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Saving...' : 'Change Password')),
          ]),
        ),
        ),
      ),
    );
  }
}
