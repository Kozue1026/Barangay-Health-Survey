import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config.dart';
import '../models/resident.dart';
import '../models/survey.dart';
import 'api_interface.dart';

/// Phase 2 implementation — drop-in replacement for MockApiService.
/// Expects PHP endpoints (to be created in Build step 2):
/// POST login.php, register.php, submit.php, profile_update.php
/// GET surveys.php, survey_detail.php, profile_get.php, dashboard.php
class PhpApiService implements ApiInterface {
  final String base = AppConfig.apiBaseUrl;
  String? _residentId;

  static const _timeout = Duration(seconds: 30);

  void _throwIfBlocked(String body, int status) {
    final t = body.trimLeft().toLowerCase();
    if (t.startsWith('<html') || body.contains('aes.js')) {
      throw Exception(
          'Server blocked app request (bot check HTML). Use the Koyeb API host, not InfinityFree.');
    }
  }

  Future<dynamic> _get(String path) async {
    final r =
        await http.get(Uri.parse('$base/$path')).timeout(_timeout);
    _throwIfBlocked(r.body, r.statusCode);
    dynamic d;
    try {
      d = jsonDecode(r.body);
    } catch (_) {
      d = null;
    }
    if (r.statusCode != 200 || (d is Map && d['ok'] == false)) {
      final msg = d is Map && d['error'] != null
          ? '${d['error']}'
          : 'Server error ${r.statusCode}';
      throw Exception(msg);
    }
    return d;
  }

  Future<dynamic> _post(String path, Map<String, dynamic> body) async {
    final r = await http
        .post(Uri.parse('$base/$path'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode(body))
        .timeout(_timeout);
    _throwIfBlocked(r.body, r.statusCode);
    dynamic d;
    try {
      d = jsonDecode(r.body);
    } catch (_) {
      throw Exception(
          'Invalid server response (${r.statusCode}). Check API host URL.');
    }
    if (r.statusCode != 200 || (d is Map && d['ok'] == false)) {
      throw Exception(d is Map ? '${d['error'] ?? 'Request failed'}' : 'Request failed');
    }
    return d;
  }

  @override
  Future<Resident> login(String residentNumber, String password) async {
    final d = await _post('login.php', {'resident_number': residentNumber, 'password': password});
    _residentId = '${d['user_id'] ?? ''}';
    return Resident.fromJson(Map<String, dynamic>.from(d['resident']));
  }

  @override
  Future<Resident> register({
    required String firstName,
    required String lastName,
    required String phone,
    required String address,
    required String birthdate,
    required String password,
    String middleName = '',
    String extensionName = '',
    String email = '',
    String gender = 'Male',
    String civilStatus = 'Single',
    String occupation = '',
    String employer = '',
    String employerAddress = '',
    String spouseName = '',
    String spouseOccupation = '',
    String spouseEmployer = '',
    List<Child> children = const [],
    String fatherName = '',
    String motherName = '',
    String reference1Name = '',
    String reference1Contact = '',
    String reference2Name = '',
    String reference2Contact = '',
    String signature = '',
    String profilePhotoPath = '',
  }) async {
    final d = await _post('register.php', {
      'first_name': firstName,
      'last_name': lastName,
      'middle_name': middleName,
      'extension_name': extensionName,
      'phone': phone,
      'email': email,
      'address': address,
      'gender': gender,
      'civil_status': civilStatus,
      'birthdate': birthdate,
      'occupation': occupation,
      'employer': employer,
      'employer_address': employerAddress,
      'spouse_name': spouseName,
      'spouse_occupation': spouseOccupation,
      'spouse_employer': spouseEmployer,
      'children': children.map((c) => c.toJson()).toList(),
      'father_name': fatherName,
      'mother_name': motherName,
      'reference1_name': reference1Name,
      'reference1_contact': reference1Contact,
      'reference2_name': reference2Name,
      'reference2_contact': reference2Contact,
      'signature': signature,
      'profile_photo': profilePhotoPath,
      'password': password,
    });
    return Resident.fromJson(Map<String, dynamic>.from(d['resident']));
  }

  @override
  Future<Map<String, int>> dashboardCounts(String residentId) async {
    final d = await _get('dashboard.php?resident_id=$residentId');
    return {'active': (d['active'] ?? 0) as int, 'completed': (d['completed'] ?? 0) as int, 'pending': (d['pending'] ?? 0) as int};
  }

  @override
  Future<List<Survey>> surveys({String search = '', String filter = 'all'}) async {
    final d = await _get('surveys.php?resident_id=${_residentId ?? ''}&search=$search&filter=$filter');
    return (d['surveys'] as List).map((e) => Survey.fromJson(Map<String, dynamic>.from(e))).toList();
  }

  @override
  Future<List<SurveyQuestion>> surveyDetail(String surveyId) async {
    final d = await _get('survey_detail.php?id=$surveyId&resident_id=${_residentId ?? ''}');
    return (d['questions'] as List).map((e) => SurveyQuestion.fromJson(Map<String, dynamic>.from(e))).toList();
  }

  @override
  Future<void> submitSurvey(String surveyId, Map<String, String> answers) async {
    await _post('submit.php', {'survey_id': surveyId, 'resident_id': _residentId, 'answers': answers});
  }

  @override
  Future<Resident> getProfile() async {
    final d = await _get('profile_get.php?resident_id=${_residentId ?? ''}');
    return Resident.fromJson(Map<String, dynamic>.from(d['resident']));
  }

  @override
  Future<void> updateProfile(Resident r) async {
    await _post('profile_update.php', {'resident_id': _residentId, ...r.toJson()});
  }

  @override
  Future<void> changePassword({required String oldPassword, required String newPassword}) async {
    await _post('change_password.php', {
      'resident_id': _residentId,
      'old_password': oldPassword,
      'new_password': newPassword,
    });
  }

  @override
  Future<Map<String, dynamic>> syncCheck(String residentId) async {
    final d = await _get('sync_check.php?resident_id=$residentId');
    return Map<String, dynamic>.from(d as Map);
  }

  @override
  Future<String> uploadPhoto(String residentId, String filePath) async {
    final req = http.MultipartRequest('POST', Uri.parse('$base/upload_photo.php'));
    req.fields['resident_id'] = residentId;
    req.files.add(await http.MultipartFile.fromPath('profile_pic', filePath));
    final streamed = await req.send();
    final body = await streamed.stream.bytesToString();
    dynamic d;
    try {
      d = jsonDecode(body);
    } catch (_) {
      d = null;
    }
    if (streamed.statusCode != 200 || (d is Map && d['ok'] == false)) {
      throw Exception(d is Map && d['error'] != null ? '${d['error']}' : 'Upload failed (${streamed.statusCode})');
    }
    return '${(d as Map)['profile_picture'] ?? ''}';
  }
}
