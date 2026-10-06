<?php
// GET ?resident_id= — active/completed/pending counts, mirrors resident/dashboard.php.
require_once __DIR__ . '/_common.php';
$in = api_input();
api_require($in, ['resident_id']);
try {
  $uid = (string)$in['resident_id'];
  api_require_active($uid);
  $activeIds = [];
  foreach (fb_all('surveys') as $sid => $s) {
    if (fb_survey_open($s)) {
      $activeIds[(string)$sid] = true;
    }
  }
  $active = count($activeIds);
  $completed = 0;
  foreach (fb_all('responses') as $r) {
    if ((string)($r['resident_id'] ?? '') === $uid && isset($activeIds[(string)($r['survey_id'] ?? '')])) $completed++;
  }
  api_ok(['active' => $active, 'completed' => $completed, 'pending' => max(0, $active - $completed)]);
} catch (Exception $e) { api_fail('Could not load dashboard.', 500); }
