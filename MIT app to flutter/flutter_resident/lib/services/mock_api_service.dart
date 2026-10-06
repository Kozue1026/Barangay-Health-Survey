import '../models/resident.dart';
import '../models/survey.dart';
import 'api_interface.dart';

/// Phase 1 mock — same JSON shape as the PHP api/ will return in Phase 2.
/// RES-0001 format (4-digit pad) to match fb_next_resident_number().
class MockApiService implements ApiInterface {
  int _counter = 2; // next new registration => RES-0002

  final Resident _me = Resident(
    id: '1',
    residentNumber: 'RES-0001',
    firstName: 'Juan',
    lastName: 'Dela Cruz',
    middleName: 'Santos',
    email: 'juan@example.com',
    phone: '09171234567',
    address: 'Purok 1, Barangay Health Center',
    gender: 'Male',
    civilStatus: 'Married',
    birthdate: '1990-05-10',
    occupation: 'Driver',
    employer: 'Self-employed',
    spouseName: 'Maria Dela Cruz',
    fatherName: 'Pedro Dela Cruz',
    motherName: 'Rosa Santos',
    reference1Name: 'Kapitan Reyes',
    reference1Contact: '09170000001',
    securityQuestion: 'Favorite teacher?',
    securityAnswer: 'santos',
  );

  String _nextResNumber() =>
      'RES-${_counter.toString().padLeft(4, '0')}';

  @override
  Future<Resident> login(String residentNumber, String password) async {
    await Future.delayed(const Duration(milliseconds: 500));
    if (residentNumber.trim().isEmpty) throw Exception('Please enter your Resident Number.');
    if (password.isEmpty) throw Exception('Please enter your password.');
    if (residentNumber.trim().toUpperCase() != 'RES-0001') {
      throw Exception('No account was found with that Resident Number.');
    }
    if (password != 'password123') {
      throw Exception('Incorrect password. Please try again.');
    }
    return _me;
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
    await Future.delayed(const Duration(milliseconds: 500));
    final err = Resident.validateRegistration(
      firstName: firstName,
      lastName: lastName,
      phone: phone,
      birthdate: birthdate,
      gender: gender,
      address: address,
      middleName: middleName,
      email: email,
    );
    if (err != null) throw Exception(err);
    if (password.length < 8 || !RegExp(r'[A-Za-z]').hasMatch(password) || !RegExp(r'[0-9]').hasMatch(password)) {
      throw Exception('Password must be at least 8 characters long and contain both letters and numbers.');
    }
    final res = _nextResNumber();
    _counter++;
    return Resident(
      id: '99',
      residentNumber: res,
      firstName: firstName,
      lastName: lastName,
      middleName: middleName,
      extensionName: extensionName,
      email: email,
      phone: phone,
      address: address,
      gender: gender,
      civilStatus: civilStatus,
      birthdate: birthdate,
      occupation: occupation,
      employer: employer,
      employerAddress: employerAddress,
      spouseName: spouseName,
      spouseOccupation: spouseOccupation,
      spouseEmployer: spouseEmployer,
      children: children,
      fatherName: fatherName,
      motherName: motherName,
      reference1Name: reference1Name,
      reference1Contact: reference1Contact,
      reference2Name: reference2Name,
      reference2Contact: reference2Contact,
      signature: signature,
      profilePhoto: profilePhotoPath,
    );
  }

  @override
  Future<Map<String, int>> dashboardCounts(String residentId) async {
    return {'active': 3, 'completed': 1, 'pending': 2};
  }

  final List<Survey> _surveys = [
    Survey(id: '1', title: 'Community Health Needs', description: 'Help us plan health programs for your family.', category: 'Health', openingDate: '2026-01-01', closingDate: '2026-12-31', questionCount: 4, completed: true),
    Survey(id: '2', title: 'Dengue Awareness', description: '5-minute survey on dengue prevention in your purok.', category: 'Sanitation', openingDate: '2026-01-01', closingDate: '2026-12-31', questionCount: 3),
    Survey(id: '3', title: 'Maternal Health', description: 'For mothers and guardians — immunization and checkups.', category: 'Maternal', openingDate: '2026-01-01', closingDate: '2026-12-31', questionCount: 3),
  ];

  @override
  Future<List<Survey>> surveys({String search = '', String filter = 'all'}) async {
    await Future.delayed(const Duration(milliseconds: 300));
    var list = _surveys.where((s) {
      if (search.isNotEmpty &&
          !s.title.toLowerCase().contains(search.toLowerCase()) &&
          !s.description.toLowerCase().contains(search.toLowerCase())) {
        return false;
      }
      if (filter == 'completed' && !s.completed) return false;
      if (filter == 'active' && s.completed) return false;
      return true;
    }).toList();
    return list;
  }

  @override
  Future<List<SurveyQuestion>> surveyDetail(String surveyId) async {
    await Future.delayed(const Duration(milliseconds: 300));
    return [
      SurveyQuestion(id: '11', text: 'How would you rate our health center service?', type: 'rating', required: true),
      SurveyQuestion(id: '12', text: 'Did you receive free medicine this quarter?', type: 'yes_no', required: true),
      SurveyQuestion(
        id: '13',
        text: 'What program do you need most?',
        type: 'multiple_choice',
        required: true,
        choices: [
          SurveyChoice(id: '101', text: 'Check-up'),
          SurveyChoice(id: '102', text: 'Medicine assistance'),
          SurveyChoice(id: '103', text: 'Other'),
        ],
      ),
      SurveyQuestion(id: '14', text: 'Any other concern?', type: 'short_answer', required: false),
    ];
  }

  @override
  Future<void> submitSurvey(String surveyId, Map<String, String> answers) async {
    await Future.delayed(const Duration(milliseconds: 500));
    if (surveyId == '1') throw Exception('You have already completed this survey.');
  }

  @override
  Future<Resident> getProfile() async => _me;

  @override
  Future<void> updateProfile(Resident r) async {
    await Future.delayed(const Duration(milliseconds: 500));
    final err = Resident.validateRequired(
        firstName: r.firstName, lastName: r.lastName, phone: r.phone,
        middleName: r.middleName, birthdate: r.birthdate);
    if (err != null) throw Exception(err);
  }

  @override
  Future<void> changePassword({required String oldPassword, required String newPassword}) async {
    await Future.delayed(const Duration(milliseconds: 500));
    if (oldPassword != 'password123') throw Exception('Current password is incorrect.');
    if (newPassword.length < 8 || !RegExp(r'[A-Za-z]').hasMatch(newPassword) || !RegExp(r'[0-9]').hasMatch(newPassword)) {
      throw Exception('Password must be at least 8 characters long and contain both letters and numbers.');
    }
  }

  @override
  Future<Map<String, dynamic>> syncCheck(String residentId) async {
    return {'surveys_version': 1, 'profile_updated_at': null};
  }

  @override
  Future<String> uploadPhoto(String residentId, String filePath) async {
    // Mock: pretend upload, return fake server filename.
    await Future.delayed(const Duration(milliseconds: 500));
    return 'mock_${residentId}_${DateTime.now().millisecondsSinceEpoch}.jpg';
  }
}
