import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../models/resident.dart';
import '../widgets/chrome.dart';
import 'login_screen.dart';

/// Mobile self-registration matching the website form (5 sections).
/// Section 1 required (first/last/gender/birthdate/contact/address/password).
/// Sections 2-5 optional but submitted when filled.
class _ChildEntry {
  final name = TextEditingController();
  final age = TextEditingController();
  void dispose() {
    name.dispose();
    age.dispose();
  }
}

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  // Section 1
  final _first = TextEditingController();
  final _middle = TextEditingController();
  final _last = TextEditingController();
  final _ext = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _address = TextEditingController();
  final _birthdate = TextEditingController();
  final _occupation = TextEditingController();
  final _employer = TextEditingController();
  final _employerAddr = TextEditingController();
  final _pw = TextEditingController();
  final _pw2 = TextEditingController();
  String _gender = 'Male';
  String _civil = 'Single';
  XFile? _photo;

  // Section 2
  final _spouseName = TextEditingController();
  final _spouseOcc = TextEditingController();
  final _spouseEmp = TextEditingController();

  // Section 3
  final List<_ChildEntry> _children = [];

  // Section 4
  final _father = TextEditingController();
  final _mother = TextEditingController();

  // Section 5
  final _ref1Name = TextEditingController();
  final _ref1Contact = TextEditingController();
  final _ref2Name = TextEditingController();
  final _ref2Contact = TextEditingController();
  final _signature = TextEditingController();

  bool _busy = false;
  String? _err;
  String? _newId;

  @override
  void dispose() {
    for (final c in [
      _first, _middle, _last, _ext, _phone, _email, _address, _birthdate,
      _occupation, _employer, _employerAddr, _pw, _pw2,
      _spouseName, _spouseOcc, _spouseEmp,
      _father, _mother,
      _ref1Name, _ref1Contact, _ref2Name, _ref2Contact, _signature,
    ]) {
      c.dispose();
    }
    for (final e in _children) {
      e.dispose();
    }
    super.dispose();
  }

  String get _ageText {
    final d = DateTime.tryParse(_birthdate.text.trim());
    if (d == null) return '';
    final now = DateTime.now();
    var a = now.year - d.year;
    if (now.month < d.month || (now.month == d.month && now.day < d.day)) a--;
    return a < 0 ? '' : '$a';
  }

  String get _photoName {
    if (_photo == null) return 'No file chosen';
    final p = _photo!.path.replaceAll('\\', '/');
    return p.split('/').last;
  }

  Future<void> _pickPhoto() async {
    try {
      final f = await ImagePicker().pickImage(source: ImageSource.gallery);
      if (f != null) setState(() => _photo = f);
    } catch (e) {
      setState(() => _err = 'Could not open photo picker: $e');
    }
  }

  Future<void> _pickDate(TextEditingController target) async {
    final now = DateTime.now();
    final initial = DateTime.tryParse(target.text.trim()) ?? DateTime(now.year - 20);
    final d = await showDatePicker(
      context: context,
      initialDate: initial.isAfter(now) ? now : initial,
      firstDate: DateTime(1900),
      lastDate: now,
    );
    if (d != null) setState(() => target.text = Resident.formatBirthdate(d));
  }

  String? _photoErr;

  Future<void> _submit() async {
    setState(() { _busy = true; _err = null; _newId = null; _photoErr = null; });
    try {
      if (_pw.text != _pw2.text) throw Exception('The two passwords do not match.');
      final api = context.read<Session>().api;
      final r = await api.register(
        firstName: _first.text.trim(),
        middleName: _middle.text.trim(),
        lastName: _last.text.trim(),
        extensionName: _ext.text.trim(),
        phone: _phone.text.trim(),
        email: _email.text.trim(),
        address: _address.text.trim(),
        gender: _gender,
        civilStatus: _civil,
        birthdate: _birthdate.text.trim(),
        occupation: _occupation.text.trim(),
        employer: _employer.text.trim(),
        employerAddress: _employerAddr.text.trim(),
        spouseName: _spouseName.text.trim(),
        spouseOccupation: _spouseOcc.text.trim(),
        spouseEmployer: _spouseEmp.text.trim(),
        children: _children
            .where((e) =>
                e.name.text.trim().isNotEmpty ||
                e.age.text.trim().isNotEmpty)
            .map((e) => Child(
                name: e.name.text.trim(),
                age: int.tryParse(e.age.text.trim())))
            .toList(),
        fatherName: _father.text.trim(),
        motherName: _mother.text.trim(),
        reference1Name: _ref1Name.text.trim(),
        reference1Contact: _ref1Contact.text.trim(),
        reference2Name: _ref2Name.text.trim(),
        reference2Contact: _ref2Contact.text.trim(),
        signature: _signature.text.trim(),
        profilePhotoPath: '',
        password: _pw.text,
      );
      // Upload photo after account exists so web + mobile share the same file.
      if (_photo != null) {
        try {
          await api.uploadPhoto(r.id, _photo!.path);
        } catch (e) {
          if (mounted) setState(() => _photoErr = 'Account created, but photo upload failed: ${'$e'.replaceFirst('Exception: ', '')}');
        }
      }
      setState(() => _newId = r.residentNumber);
    } catch (e) {
      setState(() => _err = '$e'.replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return NavyScaffold(
      appBar: const AppHeader(title: 'Member Registration', showBack: true),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: WhiteCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (_err != null)
                Container(
                    padding: const EdgeInsets.all(10),
                    margin: const EdgeInsets.only(bottom: 12),
                    decoration: BoxDecoration(
                        color: Colors.red.shade50,
                        borderRadius: BorderRadius.circular(8)),
                    child: Text(_err!,
                        style: const TextStyle(color: Colors.red))),
              if (_newId != null)
                Container(
                    padding: const EdgeInsets.all(12),
                    margin: const EdgeInsets.only(bottom: 12),
                    decoration: BoxDecoration(
                        color: Colors.green.shade50,
                        borderRadius: BorderRadius.circular(8)),
                    child: Text(
                        'Registration successful! Your Resident Number is $_newId. Please write it down.',
                        style: const TextStyle(
                            color: Colors.green,
                            fontWeight: FontWeight.bold))),
              if (_photoErr != null)
                Container(
                    padding: const EdgeInsets.all(10),
                    margin: const EdgeInsets.only(bottom: 12),
                    decoration: BoxDecoration(
                        color: Colors.orange.shade50,
                        borderRadius: BorderRadius.circular(8)),
                    child: Text(_photoErr!,
                        style: const TextStyle(color: Colors.orange))),

              // ---------- 1. Personal Information ----------
              _section('1. Personal Information'),
              const SizedBox(height: 10),
              const TextField(
                  enabled: false,
                  decoration: InputDecoration(
                      labelText: 'Resident Number *',
                      hintText: 'Auto-assigned (e.g. RES-0003)')),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                  initialValue: 'Active',
                  isExpanded: true,
                  items: const [
                    DropdownMenuItem(
                        value: 'Active', child: Text('Active'))
                  ],
                  onChanged: null,
                  decoration:
                      const InputDecoration(labelText: 'Status *')),
              const SizedBox(height: 10),
              const Text('Profile Photo (optional)',
                  style: TextStyle(fontSize: 12, color: Colors.black54)),
              const SizedBox(height: 4),
              Row(children: [
                OutlinedButton(
                    onPressed: _busy ? null : _pickPhoto,
                    child: const Text('Choose File')),
                const SizedBox(width: 10),
                Expanded(
                    child: Text(_photoName,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            color: Colors.grey, fontSize: 13))),
                if (_photo != null)
                  IconButton(
                      icon: const Icon(Icons.clear, size: 18),
                      onPressed: () => setState(() => _photo = null)),
              ]),
              _f(_first, 'First Name *'),
              _f(_middle, 'Middle Name'),
              _f(_last, 'Last Name *'),
              _f(_ext, 'Extension Name', hint: 'Jr., Sr., III'),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                  initialValue: _civil,
                  isExpanded: true,
                  items: const [
                    'Single',
                    'Married',
                    'Widowed',
                    'Separated',
                    'Divorced'
                  ]
                      .map((e) =>
                          DropdownMenuItem(value: e, child: Text(e)))
                      .toList(),
                  onChanged: (v) =>
                      setState(() => _civil = v ?? 'Single'),
                  decoration:
                      const InputDecoration(labelText: 'Civil Status')),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                  initialValue: _gender,
                  isExpanded: true,
                  items: const ['Male', 'Female', 'Prefer not to say']
                      .map((e) =>
                          DropdownMenuItem(value: e, child: Text(e)))
                      .toList(),
                  onChanged: (v) =>
                      setState(() => _gender = v ?? 'Male'),
                  decoration:
                      const InputDecoration(labelText: 'Gender *')),
              const SizedBox(height: 10),
              TextField(
                  controller: _birthdate,
                  readOnly: true,
                  onTap: () => _pickDate(_birthdate),
                  decoration: const InputDecoration(
                      labelText: 'Birthdate *',
                      hintText: 'mm/dd/yyyy',
                      suffixIcon: Icon(Icons.calendar_today))),
              const SizedBox(height: 10),
              TextField(
                  enabled: false,
                  key: ValueKey(_ageText),
                  controller: TextEditingController(
                      text: _ageText.isEmpty
                          ? 'Auto-calculated'
                          : _ageText),
                  decoration: const InputDecoration(labelText: 'Age')),
              _f(_phone, 'Contact Number *',
                  hint: 'e.g. 09171234567',
                  keyboard: TextInputType.number,
                  maxLength: 11),
              _f(_email, 'Email Address',
                  hint: 'name@example.com',
                  keyboard: TextInputType.emailAddress),
              _f(_address, 'Residential Address *',
                  hint: 'House No., Street, Purok, Barangay', maxLines: 3),
              _f(_occupation, 'Occupation',
                  hint: 'e.g. Teacher, Engineer'),
              _f(_employer, 'Employer / Business',
                  hint: 'Company Name'),
              _f(_employerAddr, 'Employer Address',
                  hint: 'Workplace Address'),

              // ---------- 2. Spouse ----------
              _section('2. Spouse Information', optional: true),
              _f(_spouseName, 'Spouse Full Name'),
              _f(_spouseOcc, 'Spouse Occupation'),
              _f(_spouseEmp, 'Spouse Employer'),

              // ---------- 3. Children ----------
              Padding(
                padding: const EdgeInsets.only(top: 16),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    const Expanded(
                      child: Text('3. Children Information',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF2F6BFF))),
                    ),
                    const SizedBox(width: 8),
                    OutlinedButton.icon(
                      onPressed: _busy
                          ? null
                          : () =>
                              setState(() => _children.add(_ChildEntry())),
                      icon: const Icon(Icons.add_circle_outline, size: 14),
                      label: const Text('Add Child',
                          style: TextStyle(fontSize: 12)),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 10, vertical: 6),
                        minimumSize: const Size(0, 32),
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        visualDensity: VisualDensity.compact,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(20),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              if (_children.isEmpty)
                const Padding(
                  padding: EdgeInsets.only(top: 6),
                  child: Text(
                      'No children recorded. Click "Add Child" to record children.',
                      style: TextStyle(color: Colors.grey, fontSize: 13)),
                ),
              for (var i = 0; i < _children.length; i++)
                Container(
                  margin: const EdgeInsets.only(top: 10),
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                      border: Border.all(color: Colors.grey.shade300),
                      borderRadius: BorderRadius.circular(10)),
                  child: Column(children: [
                    Row(children: [
                      Expanded(
                          child: Text('Child ${i + 1}',
                              style: const TextStyle(
                                  fontWeight: FontWeight.bold))),
                      IconButton(
                          icon: const Icon(Icons.delete_outline,
                              color: Colors.red),
                          onPressed: () => setState(() {
                                _children[i].dispose();
                                _children.removeAt(i);
                              })),
                    ]),
                    TextField(
                        controller: _children[i].name,
                        decoration: const InputDecoration(
                            labelText: 'Child Full Name')),
                    const SizedBox(height: 10),
                    TextField(
                        controller: _children[i].age,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                            labelText: 'Child Age',
                            hintText: 'e.g. 5')),
                  ]),
                ),

              // ---------- 4. Parents ----------
              _section('4. Parents Information', optional: true),
              _f(_father, "Father's Full Name"),
              _f(_mother, "Mother's Maiden Name"),

              // ---------- 5. References ----------
              _section('5. Character References & Signature',
                  optional: true),
              _f(_ref1Name, 'Reference 1: Full Name'),
              _f(_ref1Contact, 'Reference 1: Contact / Details'),
              _f(_ref2Name, 'Reference 2: Full Name'),
              _f(_ref2Contact, 'Reference 2: Contact / Details'),
              _f(_signature, 'Signature (Optional)',
                  hint: 'Type full legal name as acknowledgement'),

              // ---------- Account ----------
              _section('6. Account Security'),
              _f(_pw, 'Password (8+ letters & numbers) *', obscure: true),
              _f(_pw2, 'Confirm Password *', obscure: true),

              const SizedBox(height: 16),
              ElevatedButton(
                  onPressed: _busy ? null : _submit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF2F6BFF),
                    foregroundColor: Colors.white,
                    minimumSize: const Size.fromHeight(52),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                    textStyle: const TextStyle(
                        fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                  child: Text(_busy ? 'SIGNING UP...' : 'SIGN UP')),
            ],
          ),
        ),
      ),
    );
  }

  Widget _section(String title, {bool optional = false}) => Padding(
        padding: const EdgeInsets.only(top: 16),
        child: Row(children: [
          Expanded(
              child: Text(title,
                  style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF2F6BFF)))),
          if (optional)
            const Text('Optional',
                style: TextStyle(color: Colors.grey, fontSize: 12)),
        ]),
      );

  Widget _f(TextEditingController c, String label,
      {String? hint,
      TextInputType keyboard = TextInputType.text,
      int? maxLength,
      int maxLines = 1,
      bool obscure = false}) =>
      Padding(
        padding: const EdgeInsets.only(top: 10),
        child: TextField(
            controller: c,
            obscureText: obscure,
            keyboardType: keyboard,
            maxLength: maxLength,
            maxLines: maxLines,
            decoration:
                InputDecoration(labelText: label, hintText: hint)),
      );
}
