<?php
// POST {survey_id, resident_id, answers:{questionId: value}} — mirrors resident/submit_survey.php.
// multiple_choice value = choice_id (or "Other: text"), yes_no = Yes/No, rating = 1-5, short = text.
require_once __DIR__ . '/_common.php';
$in = api_input();
api_require($in, ['survey_id', 'resident_id']);
try {
  $sid = (string)$in['survey_id'];
  $uid = (string)$in['resident_id'];
  api_require_active($uid);
  $answers = $in['answers'] ?? [];
  if (!is_array($answers)) $answers = [];

  $survey = fb_get_rec('surveys', $sid);
  $valid = $survey && fb_survey_open($survey);
  if (!$valid) api_fail('Survey is not available.');
  foreach (fb_all('responses') as $rr) {
    if ((string)($rr['survey_id'] ?? '') === $sid && (string)($rr['resident_id'] ?? '') === $uid) {
      api_fail('You have already submitted this survey.');
    }
  }
  $questions = [];
  foreach (fb_all('survey_questions') as $qid => $qq) {
    if ((string)($qq['survey_id'] ?? '') === $sid) { $qq['id'] = (string)$qid; $questions[] = $qq; }
  }
  if (empty($questions)) api_fail('This survey has no questions configured.');
  $choiceMap = fb_all('survey_choices');

  $response_id = fb_next_id('responses');
  fb_set_rec('responses', $response_id, [
    'survey_id' => $sid, 'resident_id' => $uid, 'status' => 'completed',
    'submitted_at' => fb_now(), 'created_at' => fb_now(),
  ]);
  foreach ($questions as $q) {
    $qid = (string)$q['id'];
    if (!array_key_exists($qid, $answers)) continue;
    $val = is_string($answers[$qid]) ? $answers[$qid] : (string)$answers[$qid];
    if ($val === '') continue;
    $answer_text = null; $choice_id = null; $rating_value = null;
    if (($q['question_type'] ?? '') === 'multiple_choice') {
      if (stripos($val, 'Other:') === 0) {
        $answer_text = trim($val);
      } else {
        $choice_id = $val;
        $answer_text = $choiceMap[$choice_id]['choice_text'] ?? '';
      }
    } elseif (($q['question_type'] ?? '') === 'yes_no') {
      if ($val !== 'Yes' && $val !== 'No') continue;
      $answer_text = $val;
    } elseif (($q['question_type'] ?? '') === 'rating') {
      $rating_value = (int)$val;
      if ($rating_value < 1 || $rating_value > 5) continue;
      $answer_text = (string)$rating_value;
    } else {
      $answer_text = trim($val);
      if ($answer_text === '') continue;
    }
    $aid = fb_next_id('response_answers');
    fb_set_rec('response_answers', $aid, [
      'response_id' => (string)$response_id, 'question_id' => $qid,
      'answer_text' => $answer_text, 'choice_id' => $choice_id,
      'rating_value' => $rating_value, 'created_at' => fb_now(),
    ]);
  }
  fb_log($uid, 'resident', 'Submit Survey', 'Completed survey (mobile): ' . ($survey['title'] ?? $sid));
  try {
    $me = fb_get_rec('residents', $uid);
    $nm = trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''));
    notifyAdmins(null, 'New Survey Response', "$nm completed the survey: " . ($survey['title'] ?? ''), '/admin/survey_results.php', 'bi-chat-dots text-success', $sid, 'survey');
  } catch (Exception $e) {}
  api_ok(['response_id' => (string)$response_id]);
} catch (Exception $e) {
  if (http_response_code() === 200) api_fail('Submit failed. Please try again.', 500);
  throw $e;
}
