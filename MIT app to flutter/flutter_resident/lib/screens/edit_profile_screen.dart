import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../models/resident.dart';
import '../widgets/chrome.dart';
import 'login_screen.dart';

/// Edit form with web-parity validation (required: first/last/phone).
class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({super.key});
  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _map = <String, TextEditingController>{};
  String _gender = 'Male';
  String _civil = 'Single';
  bool _busy = false;
  bool _uploadingPhoto = false;
  String? _photoMsg;
  String? _err;
  String? _ok;

  @override
  void initState() {
    super.initState();
    for (final k in ['first_name','middle_name','last_name','extension_name','email','phone','address','birthdate','occupation','employer','employer_address','spouse_name','spouse_occupation','spouse_employer','father_name','mother_name','reference1_name','reference1_contact','reference2_name','reference2_contact','signature','security_question','security_answer']) {
      _map[k] = TextEditingController();
    }
    _fillFromSession();
  }

  void _fillFromSession() {
    final me = context.read<Session>().resident;
    if (me == null) return;
    _map['first_name']!.text = me.firstName;
      _map['middle_name']!.text = me.middleName;
      _map['last_name']!.text = me.lastName;
      _map['extension_name']!.text = me.extensionName;
      _map['email']!.text = me.email;
      _map['phone']!.text = me.phone;
      _map['address']!.text = me.address;
      _map['birthdate']!.text = me.birthdate;
      _map['occupation']!.text = me.occupation;
      _map['employer']!.text = me.employer;
      _map['employer_address']!.text = me.employerAddress;
      _map['spouse_name']!.text = me.spouseName;
      _map['spouse_occupation']!.text = me.spouseOccupation;
      _map['spouse_employer']!.text = me.spouseEmployer;
      _map['father_name']!.text = me.fatherName;
      _map['mother_name']!.text = me.motherName;
      _map['reference1_name']!.text = me.reference1Name;
      _map['reference1_contact']!.text = me.reference1Contact;
      _map['reference2_name']!.text = me.reference2Name;
      _map['reference2_contact']!.text = me.reference2Contact;
      _map['signature']!.text = me.signature;
      _map['security_question']!.text = me.securityQuestion;
      _map['security_answer']!.text = me.securityAnswer;
      _gender = me.gender.isNotEmpty ? me.gender : 'Male';
      _civil = me.civilStatus.isNotEmpty ? me.civilStatus : 'Single';
  }

  /// Pull-to-refresh: fetch latest web data into the form.
  Future<void> _refresh() async {
    try {
      final s = context.read<Session>();
      final r = await s.api.getProfile();
      s.resident = r;
      if (!mounted) return;
      setState(() {
        _map['first_name']!.text = r.firstName;
        _map['middle_name']!.text = r.middleName;
        _map['last_name']!.text = r.lastName;
        _map['extension_name']!.text = r.extensionName;
        _map['email']!.text = r.email;
        _map['phone']!.text = r.phone;
        _map['address']!.text = r.address;
        _map['birthdate']!.text = r.birthdate;
        _map['occupation']!.text = r.occupation;
        _map['employer']!.text = r.employer;
        _map['employer_address']!.text = r.employerAddress;
        _map['spouse_name']!.text = r.spouseName;
        _map['spouse_occupation']!.text = r.spouseOccupation;
        _map['spouse_employer']!.text = r.spouseEmployer;
        _map['father_name']!.text = r.fatherName;
        _map['mother_name']!.text = r.motherName;
        _map['reference1_name']!.text = r.reference1Name;
        _map['reference1_contact']!.text = r.reference1Contact;
        _map['reference2_name']!.text = r.reference2Name;
        _map['reference2_contact']!.text = r.reference2Contact;
        _map['signature']!.text = r.signature;
        _map['security_question']!.text = r.securityQuestion;
        _map['security_answer']!.text = r.securityAnswer;
        _gender = r.gender.isNotEmpty ? r.gender : 'Male';
        _civil = r.civilStatus.isNotEmpty ? r.civilStatus : 'Single';
      });
    } catch (e) {
      if (isAccountDisabled(e) && mounted) {
        forceLogoutToLogin(context, '$e'.replaceFirst('Exception: ', ''));
      }
    }
  }

  /// Pick + upload immediately so web sees the same file in realtime.
  Future<void> _changePhoto() async {
    setState(() { _uploadingPhoto = true; _photoMsg = null; _err = null; });
    try {
      final f = await ImagePicker().pickImage(source: ImageSource.gallery);
      if (f == null) return;
      final s = context.read<Session>();
      final id = s.resident?.id ?? '';
      if (id.isEmpty) throw Exception('Please login again first.');
      final filename = await s.api.uploadPhoto(id, f.path);
      final r = await s.api.getProfile();
      s.resident = r;
      if (mounted) {
        setState(() => _photoMsg = 'Photo updated ($filename) — visible on web too.');
      }
    } catch (e) {
      if (isAccountDisabled(e) && mounted) {
        forceLogoutToLogin(context, '$e'.replaceFirst('Exception: ', ''));
        return;
      }
      if (mounted) setState(() => _err = '$e'.replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _uploadingPhoto = false);
    }
  }

  Future<void> _save() async {    setState(() { _busy = true; _err = null; _ok = null; });
    try {
      final s = context.read<Session>();
      final old = s.resident!;
      final updated = Resident(
        id: old.id,
        residentNumber: old.residentNumber,
        firstName: _map['first_name']!.text.trim(),
        middleName: _map['middle_name']!.text.trim(),
        lastName: _map['last_name']!.text.trim(),
        extensionName: _map['extension_name']!.text.trim(),
        email: _map['email']!.text.trim(),
        phone: _map['phone']!.text.trim(),
        address: _map['address']!.text.trim(),
        gender: _gender,
        civilStatus: _civil,
        birthdate: _map['birthdate']!.text.trim(),
        occupation: _map['occupation']!.text.trim(),
        employer: _map['employer']!.text.trim(),
        employerAddress: _map['employer_address']!.text.trim(),
        spouseName: _map['spouse_name']!.text.trim(),
        spouseOccupation: _map['spouse_occupation']!.text.trim(),
        spouseEmployer: _map['spouse_employer']!.text.trim(),
        fatherName: _map['father_name']!.text.trim(),
        motherName: _map['mother_name']!.text.trim(),
        reference1Name: _map['reference1_name']!.text.trim(),
        reference1Contact: _map['reference1_contact']!.text.trim(),
        reference2Name: _map['reference2_name']!.text.trim(),
        reference2Contact: _map['reference2_contact']!.text.trim(),
        signature: _map['signature']!.text.trim(),
        securityQuestion: _map['security_question']!.text.trim(),
        securityAnswer: _map['security_answer']!.text.trim().toLowerCase(),
      );
      await s.api.updateProfile(updated);
      s.resident = updated;
      setState(() => _ok = 'Profile Updated — your profile has been updated successfully.');
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
      appBar: const AppHeader(title: 'Edit Profile', showBack: true),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.symmetric(vertical: 14),
          child: WhiteCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            if (_err != null) _banner(_err!, Colors.red),
            if (_ok != null) _banner(_ok!, Colors.green),
            OutlinedButton.icon(
              onPressed: _busy ? null : _changePhoto,
              icon: const Icon(Icons.camera_alt),
              label: Text(_uploadingPhoto ? 'Uploading photo...' : 'Change Profile Photo'),
            ),
            if (_photoMsg != null)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(_photoMsg!, style: const TextStyle(color: Colors.green, fontSize: 13)),
              ),
            const SizedBox(height: 6),
            const Text('1. Personal Information', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2F6BFF))),
            _f('first_name', 'First Name *'),
            _f('middle_name', 'Middle Name (optional)'),
            _f('last_name', 'Last Name *'),
            _f('extension_name', 'Extension (Jr., Sr., III)'),
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
              Expanded(child: DropdownButtonFormField<String>(initialValue: _gender,
                  isExpanded: true,
                  items: const ['Male','Female','Prefer not to say'].map((e) => DropdownMenuItem(value: e, child: Text(e, overflow: TextOverflow.ellipsis))).toList(),
                  onChanged: (v) => setState(() => _gender = v ?? 'Male'), decoration: const InputDecoration(labelText: 'Gender', contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 12)))),
              const SizedBox(width: 10),
              Expanded(child: DropdownButtonFormField<String>(initialValue: _civil,
                  isExpanded: true,
                  items: const ['Single','Married','Widowed','Separated','Divorced'].map((e) => DropdownMenuItem(value: e, child: Text(e, overflow: TextOverflow.ellipsis))).toList(),
                  onChanged: (v) => setState(() => _civil = v ?? 'Single'), decoration: const InputDecoration(labelText: 'Civil Status', contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 12)))),
                ],
              ),
            ),
            _f('birthdate', 'Birthdate (yyyy-MM-dd)'),
            _f('phone', 'Contact Number * (11 digits)', num: true),
            _f('email', 'Email'),
            _f('address', 'Residential Address'),
            _f('occupation', 'Occupation'),
            _f('employer', 'Employer / Business'),
            _f('employer_address', 'Employer Address'),
            const SizedBox(height: 10),
            const Text('2. Spouse Information', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2F6BFF))),
            _f('spouse_name', 'Spouse Full Name'),
            _f('spouse_occupation', 'Spouse Occupation'),
            _f('spouse_employer', 'Spouse Employer'),
            const SizedBox(height: 10),
            const Text('4. Parents Information', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2F6BFF))),
            _f('father_name', "Father's Full Name"),
            _f('mother_name', "Mother's Full Name"),
            const SizedBox(height: 10),
            const Text('5. References & Signature', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2F6BFF))),
            _f('reference1_name', 'Reference 1 Name'),
            _f('reference1_contact', 'Reference 1 Contact'),
            _f('reference2_name', 'Reference 2 Name'),
            _f('reference2_contact', 'Reference 2 Contact'),
            _f('signature', 'Signature (type full name)'),
            const SizedBox(height: 10),
            const Text('6. Security Question', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2F6BFF))),
            _f('security_question', 'Security Question'),
            _f('security_answer', 'Security Answer'),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _busy ? null : _save, child: Text(_busy ? 'Saving...' : 'Save Changes')),
          ]),
        ),
        ),
      ),
    );
  }

  Widget _f(String key, String label, {bool num = false}) => Padding(
        padding: const EdgeInsets.only(top: 10),
        child: TextField(controller: _map[key], keyboardType: num ? TextInputType.number : TextInputType.text,
            decoration: InputDecoration(labelText: label)),
      );

  Widget _banner(String t, Color c) => Container(
      padding: const EdgeInsets.all(10), margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(color: c.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
      child: Text(t, style: TextStyle(color: c, fontWeight: FontWeight.bold)));
}
