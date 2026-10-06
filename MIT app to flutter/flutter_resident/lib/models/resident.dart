// Mirrors `resident_children` node: {child_name, age} — web format.
// Accepts legacy {name, birthdate} keys for backward compat with old mocks.
class Child {
  final String name;
  final int? age;

  const Child({this.name = '', this.age});

  factory Child.fromJson(Map<String, dynamic> j) {
    int? a;
    final raw = j['age'] ?? j['child_age'];
    if (raw != null && '${raw}'.trim().isNotEmpty) {
      a = int.tryParse('${raw}'.trim());
    }
    return Child(
      name: '${j['child_name'] ?? j['name'] ?? ''}',
      age: a,
    );
  }

  Map<String, dynamic> toJson() => {'child_name': name, 'age': age};
}

class Resident {
  final String id; // Firebase key
  final String residentNumber; // RES-0001 format
  final String status; // Active (autofilled on self-registration)
  final String profilePhoto; // local path/filename, optional
  final String firstName;
  final String lastName;
  final String middleName;
  final String extensionName;
  final String email;
  final String phone;
  final String address;
  final String gender;
  final String civilStatus;
  final String birthdate; // yyyy-MM-dd
  final String occupation;
  final String employer;
  final String employerAddress;
  final String spouseName;
  final String spouseOccupation;
  final String spouseEmployer;
  final List<Child> children;
  final String fatherName;
  final String motherName;
  final String reference1Name;
  final String reference1Contact;
  final String reference2Name;
  final String reference2Contact;
  final String signature;
  final String securityQuestion;
  final String securityAnswer;

  Resident({
    required this.id,
    required this.residentNumber,
    this.status = 'Active',
    this.profilePhoto = '',
    required this.firstName,
    required this.lastName,
    this.middleName = '',
    this.extensionName = '',
    this.email = '',
    required this.phone,
    this.address = '',
    this.gender = 'Male',
    this.civilStatus = 'Single',
    this.birthdate = '',
    this.occupation = '',
    this.employer = '',
    this.employerAddress = '',
    this.spouseName = '',
    this.spouseOccupation = '',
    this.spouseEmployer = '',
    this.children = const [],
    this.fatherName = '',
    this.motherName = '',
    this.reference1Name = '',
    this.reference1Contact = '',
    this.reference2Name = '',
    this.reference2Contact = '',
    this.signature = '',
    this.securityQuestion = '',
    this.securityAnswer = '',
  });

  String get fullName =>
      '$firstName ${middleName.isNotEmpty ? '$middleName ' : ''}$lastName${extensionName.isNotEmpty ? ' $extensionName' : ''}'.trim();

  /// Age auto-calculated from birthdate, null when birthdate is empty/invalid.
  int? get age {
    if (birthdate.isEmpty) return null;
    final d = DateTime.tryParse(birthdate);
    if (d == null) return null;
    final now = DateTime.now();
    var a = now.year - d.year;
    if (now.month < d.month || (now.month == d.month && now.day < d.day)) a--;
    return a < 0 ? null : a;
  }

  static String formatBirthdate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  factory Resident.fromJson(Map<String, dynamic> j) {
    final rawChildren = j['children'];
    List<Child> kids = const [];
    if (rawChildren is List) {
      kids = rawChildren
          .map((e) => Child.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList();
    }
    return Resident(
        id: '${j['id'] ?? ''}',
        residentNumber: '${j['resident_number'] ?? j['residentNumber'] ?? ''}',
        status: '${j['status'] ?? 'Active'}',
        profilePhoto: '${j['profile_picture'] ?? j['profile_photo'] ?? j['profilePhoto'] ?? ''}',
        firstName: '${j['first_name'] ?? j['firstName'] ?? ''}',
        lastName: '${j['last_name'] ?? j['lastName'] ?? ''}',
        middleName: '${j['middle_name'] ?? j['middleName'] ?? ''}',
        extensionName: '${j['extension_name'] ?? ''}',
        email: '${j['email'] ?? ''}',
        phone: '${j['phone'] ?? j['mobileNumber'] ?? ''}',
        address: '${j['address'] ?? ''}',
        gender: '${j['gender'] ?? 'Male'}',
        civilStatus: '${j['civil_status'] ?? 'Single'}',
        birthdate: '${j['birthdate'] ?? ''}',
        occupation: '${j['occupation'] ?? ''}',
        employer: '${j['employer'] ?? ''}',
        employerAddress: '${j['employer_address'] ?? ''}',
        spouseName: '${j['spouse_name'] ?? ''}',
        spouseOccupation: '${j['spouse_occupation'] ?? ''}',
        spouseEmployer: '${j['spouse_employer'] ?? ''}',
        children: kids,
        fatherName: '${j['father_name'] ?? ''}',
        motherName: '${j['mother_name'] ?? ''}',
        reference1Name: '${j['reference1_name'] ?? ''}',
        reference1Contact: '${j['reference1_contact'] ?? ''}',
        reference2Name: '${j['reference2_name'] ?? ''}',
        reference2Contact: '${j['reference2_contact'] ?? ''}',
        signature: '${j['signature'] ?? ''}',
        securityQuestion: '${j['security_question'] ?? ''}',
        securityAnswer: '${j['security_answer'] ?? ''}',
      );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'resident_number': residentNumber,
        'status': status,
        'profile_picture': profilePhoto,
        'first_name': firstName,
        'last_name': lastName,
        'middle_name': middleName,
        'extension_name': extensionName,
        'email': email,
        'phone': phone,
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
        'security_question': securityQuestion,
        'security_answer': securityAnswer,
      };

  /// Same rules as resident/profile.php:107-114 (used by Edit Profile).
  static String? validateRequired({
    required String firstName,
    required String lastName,
    required String phone,
    String middleName = '',
    String birthdate = '',
  }) {
    final nameRe = RegExp(r"^[a-zA-Z\s\-'\.]+$");
    if (firstName.trim().isEmpty || !nameRe.hasMatch(firstName)) {
      return 'First Name is required (letters only).';
    }
    if (lastName.trim().isEmpty || !nameRe.hasMatch(lastName)) {
      return 'Last Name is required (letters only).';
    }
    if (middleName.trim().isNotEmpty && !nameRe.hasMatch(middleName)) {
      return 'Middle Name must be letters only.';
    }
    if (!RegExp(r'^[0-9]{11}$').hasMatch(phone.trim())) {
      return 'Phone must be exactly 11 digits (e.g. 09171234567).';
    }
    if (birthdate.isNotEmpty) {
      final d = DateTime.tryParse(birthdate);
      if (d != null && d.isAfter(DateTime.now())) {
        return 'Birthdate cannot be in the future.';
      }
    }
    return null;
  }

  /// Self-registration rules: section 1 required, sections 2-5 optional
  /// (no validation). Birthdate + gender + address are required here.
  static String? validateRegistration({
    required String firstName,
    required String lastName,
    required String phone,
    required String birthdate,
    required String gender,
    required String address,
    String middleName = '',
    String email = '',
  }) {
    final base = validateRequired(
      firstName: firstName,
      lastName: lastName,
      phone: phone,
      middleName: middleName,
      birthdate: birthdate,
    );
    if (base != null) return base;
    if (birthdate.trim().isEmpty) return 'Birthdate is required.';
    final d = DateTime.tryParse(birthdate.trim());
    if (d == null) return 'Birthdate must be a valid date (yyyy-MM-dd).';
    if (d.isAfter(DateTime.now())) {
      return 'Birthdate cannot be in the future.';
    }
    if (gender.trim().isEmpty) return 'Gender is required.';
    if (address.trim().isEmpty) return 'Residential Address is required.';
    if (email.trim().isNotEmpty && !email.contains('@')) {
      return 'Please enter a valid email address.';
    }
    return null;
  }
}
