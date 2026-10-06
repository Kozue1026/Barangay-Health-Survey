<?php
// GET ?resident_id=&search=&filter=all|active|completed — mirrors resident/surveys.php.
require_once __DIR__ . '/_common.php';
$in = api_input();
api_require($in, ['resident_id']);
try {
  $uid = (string)$in['resident_id'];
  api_require_active($uid);
  $search = trim($in['search'] ?? '');
  $filter = $in['filter'] ?? 'all';
  $questions = fb_all('survey_questions');
  $qcount = [];
  foreach ($questions as $qq) { $sid = (string)($qq['survey_id'] ?? ''); $qcount[$sid] = ($qcount[$sid] ?? 0) + 1; }
  $myResp = [];
  foreach (fb_all('responses') as $rid => $rr) {
    if ((string)($rr['resident_id'] ?? '') === $uid) $myResp[(string)($rr['survey_id'] ?? '')] = (string)$rid;
  }
  $out = [];
  foreach (fb_all('surveys') as $sid => $s) {
    if (!fb_survey_open($s)) continue;
    if (($qcount[(string)$sid] ?? 0) <= 0) continue;
    if ($search !== '' && stripos($s['title'] ?? '', $search) === false && stripos($s['description'] ?? '', $search) === false) continue;
    $rid = $myResp[(string)$sid] ?? null;
    if ($filter === 'completed' && empty($rid)) continue;
    if ($filter === 'active' && !empty($rid)) continue;
    $out[] = [
      'id' => (string)$sid, 'title' => $s['title'] ?? '', 'description' => $s['description'] ?? '',
      'category' => $s['category'] ?? '', 'opening_date' => $s['opening_date'] ?? '',
      'closing_date' => $s['closing_date'] ?? '', 'question_count' => $qcount[(string)$sid] ?? 0,
      'response_id' => $rid, 'completed' => !empty($rid),
    ];
  }
  usort($out, function ($a, $b) { return strcmp($a['closing_date'] ?? '', $b['closing_date'] ?? ''); });
  api_ok(['surveys' => $out]);
} catch (Exception $e) { api_fail('Could not load surveys.', 500); }
