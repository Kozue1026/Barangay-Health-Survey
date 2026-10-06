<?php
// GET ?id= — survey + ordered questions + ordered choices. Mirrors take_survey.php.
require_once __DIR__ . '/_common.php';
$in = api_input();
api_require($in, ['id']);
try {
  $sid = (string)$in['id'];
  if (!empty($in['resident_id'])) api_require_active((string)$in['resident_id']);
  $survey = fb_get_rec('surveys', $sid);
  if (!$survey) api_fail('Survey not found.', 404);
  if (($survey['status'] ?? '') !== 'active') api_fail('This survey is no longer available.', 410);
  $qs = [];
  foreach (fb_all('survey_questions') as $qid => $q) {
    if ((string)($q['survey_id'] ?? '') !== $sid) continue;
    $qs[] = [
      'id' => (string)$qid, 'question_text' => $q['question_text'] ?? '',
      'question_type' => $q['question_type'] ?? 'short_answer',
      'required' => (int)($q['required'] ?? 1),
      'question_order' => (int)($q['question_order'] ?? 0),
    ];
  }
  usort($qs, function ($a, $b) { return $a['question_order'] - $b['question_order']; });
  $chByQ = [];
  foreach (fb_all('survey_choices') as $cid => $c) {
    $qid = (string)($c['question_id'] ?? '');
    $chByQ[$qid][] = [
      'id' => (string)$cid, 'choice_text' => $c['choice_text'] ?? '',
      'choice_order' => (int)($c['choice_order'] ?? 0),
    ];
  }
  foreach ($chByQ as &$list) { usort($list, function ($a, $b) { return $a['choice_order'] - $b['choice_order']; }); }
  unset($list);
  foreach ($qs as &$q) { $q['choices'] = $chByQ[(string)$q['id']] ?? []; unset($q['question_order']); foreach ($q['choices'] as &$c) unset($c['choice_order']); unset($c); }
  unset($q);
  api_ok(['survey' => [
    'id' => $sid, 'title' => $survey['title'] ?? '', 'description' => $survey['description'] ?? '',
    'category' => $survey['category'] ?? '', 'opening_date' => $survey['opening_date'] ?? '',
    'closing_date' => $survey['closing_date'] ?? '',
  ], 'questions' => $qs]);
} catch (Exception $e) {
  if (http_response_code() === 200) api_fail('Could not load survey.', 500);
  throw $e;
}
