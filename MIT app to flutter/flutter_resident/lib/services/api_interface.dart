import '../models/resident.dart';
import '../models/survey.dart';

/// Single interface used by all screens. Phase 1 uses MockApiService.
/// Phase 2 (Firebase connect) swaps to PhpApiService with identical signatures.
abstract class ApiInterface {
  Future<Resident> login(String residentNumber, String password);
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
  });
  Future<Map<String, int>> dashboardCounts(String residentId);
  Future<List<Survey>> surveys({String search = '', String filter = 'all'});
  Future<List<SurveyQuestion>> surveyDetail(String surveyId);
  Future<void> submitSurvey(String surveyId, Map<String, String> answers);
  Future<Resident> getProfile();
  Future<void> updateProfile(Resident r);
  Future<void> changePassword({required String oldPassword, required String newPassword});
  /// Upload picked image file; returns server filename. Syncs to web uploads/.
  Future<String> uploadPhoto(String residentId, String filePath);
  /// Tiny version poll: {surveys_version, profile_updated_at}. Phone calls every 15s.
  Future<Map<String, dynamic>> syncCheck(String residentId);
}
