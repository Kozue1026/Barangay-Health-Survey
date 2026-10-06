// Mirrors surveys / survey_questions / survey_choices nodes.
// Availability is server-driven (fb_survey_open): status=active,
// opening_datetime <= now <= closing_datetime, questionCount > 0.
// Dates may be 'Y-m-d' (legacy) or 'Y-m-d H:i' (with time).
class Survey {
  final String id;
  final String title;
  final String description;
  final String category;
  final String openingDate;
  final String closingDate;
  final int questionCount;
  final bool completed;

  Survey({
    required this.id,
    required this.title,
    required this.description,
    required this.category,
    required this.openingDate,
    required this.closingDate,
    this.questionCount = 0,
    this.completed = false,
  });

  factory Survey.fromJson(Map<String, dynamic> j) => Survey(
        id: '${j['id'] ?? ''}',
        title: '${j['title'] ?? ''}',
        description: '${j['description'] ?? ''}',
        category: '${j['category'] ?? ''}',
        openingDate: '${j['opening_date'] ?? ''}',
        closingDate: '${j['closing_date'] ?? ''}',
        questionCount: int.tryParse('${j['question_count'] ?? 0}') ?? 0,
        completed: j['completed'] == true || '${j['response_id'] ?? ''}'.isNotEmpty,
      );
}

class SurveyChoice {
  final String id;
  final String text;
  SurveyChoice({required this.id, required this.text});
  factory SurveyChoice.fromJson(Map<String, dynamic> j) =>
      SurveyChoice(id: '${j['id'] ?? ''}', text: '${j['choice_text'] ?? ''}');
}

class SurveyQuestion {
  final String id;
  final String text;
  final String type; // multiple_choice, yes_no, rating, short_answer
  final bool required;
  final List<SurveyChoice> choices;
  SurveyQuestion({
    required this.id,
    required this.text,
    required this.type,
    required this.required,
    this.choices = const [],
  });
  factory SurveyQuestion.fromJson(Map<String, dynamic> j) {
    final ch = (j['choices'] as List? ?? [])
        .map((e) => SurveyChoice.fromJson(Map<String, dynamic>.from(e)))
        .toList();
    return SurveyQuestion(
      id: '${j['id'] ?? ''}',
      text: '${j['question_text'] ?? ''}',
      type: '${j['question_type'] ?? 'short_answer'}',
      required: '${j['required'] ?? '1'}' == '1' || j['required'] == true,
      choices: ch,
    );
  }
}
