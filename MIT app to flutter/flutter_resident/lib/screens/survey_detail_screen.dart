import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/survey.dart';
import '../widgets/chrome.dart';
import 'login_screen.dart';
import 'take_survey_screen.dart';

/// Preview — revalidates availability (admin delete/deactivate locked out).
/// Pull-to-refresh rechecks; gone surveys show non-interactable message.
class SurveyDetailScreen extends StatefulWidget {
  final Survey survey;
  const SurveyDetailScreen({super.key, required this.survey});
  @override
  State<SurveyDetailScreen> createState() => _SurveyDetailScreenState();
}

class _SurveyDetailScreenState extends State<SurveyDetailScreen> {
  bool _gone = false;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  Future<void> _refresh() async {
    try {
      await context.read<Session>().api.surveyDetail(widget.survey.id);
      if (mounted) setState(() => _gone = false);
    } catch (e) {
      if (isAccountDisabled(e) && mounted) {
        forceLogoutToLogin(context, '$e'.replaceFirst('Exception: ', ''));
        return;
      }
      final m = '$e'.toLowerCase();
      if (m.contains('no longer available') || m.contains('not found')) {
        if (mounted) setState(() => _gone = true);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final survey = widget.survey;
    if (_gone) {
      return NavyScaffold(
        appBar: const AppHeader(title: 'Survey Preview', showBack: true),
        body: RefreshIndicator(
          onRefresh: _refresh,
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.symmetric(vertical: 14),
            child: WhiteCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Survey no longer available',
                    style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                const Text('The admin deactivated or deleted this survey. Pull down to check again.',
                    style: TextStyle(color: Colors.grey)),
                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Back to Surveys'),
                  ),
                ),
              ]),
            ),
          ),
        ),
      );
    }
    return NavyScaffold(
      appBar: const AppHeader(title: 'Survey Preview', showBack: true),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.symmetric(vertical: 14),
          child: WhiteCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Chip(label: Text(survey.category)),
            Text(survey.title, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Text('Opening: ${survey.openingDate}\nClosing: ${survey.closingDate}\nQuestions: ${survey.questionCount} items',
                style: const TextStyle(color: Colors.grey)),
            const Divider(height: 24),
            const Text('Description & Instructions',
                style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF2F6BFF))),
            const SizedBox(height: 6),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: const Color(0xFFF4F7FF), borderRadius: BorderRadius.circular(10)),
              child: Text(survey.description.isNotEmpty ? survey.description
                  : 'No additional description. Please answer truthfully to assist in barangay health data collection.'),
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: survey.completed ? null : () => Navigator.push(context,
                    MaterialPageRoute(builder: (_) => TakeSurveyScreen(survey: survey))),
                child: Text(survey.completed ? 'Already Completed' : 'Take Survey Now'),
              ),
            ),
          ]),
        ),
        ),
      ),
    );
  }
}
