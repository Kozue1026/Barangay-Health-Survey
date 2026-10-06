<?php
// GET ?resident_id= — tiny version poll so phone auto-detects web changes.
// Returns surveys_version (bumped on admin survey/question/choice writes)
// + profile_updated_at for that resident.
require_once __DIR__ . '/_common.php';
$in = api_input();
try {
  $sv = 0;
  try {
    $m = fb_get('/_meta/surveys_updated');
    if (is_array($m) && isset($m['v'])) $sv = (int)$m['v'];
  } catch (Exception $e) {}
  // Fallback when _meta not yet bumped (older data): max surveys updated_at hash.
  if ($sv === 0) {
    $max = '';
    foreach (fb_all('surveys') as $s) { $u = $s['updated_at'] ?? ''; if ($u > $max) $max = $u; }
    $sv = $max !== '' ? crc32($max . count(fb_all('surveys'))) : 0;
  }
  $out = ['surveys_version' => $sv];
  if (!empty($in['resident_id'])) {
    $r = fb_get_rec('residents', (string)$in['resident_id']);
    if (!$r) {
      api_fail('Account not found. Please contact the administrator.', 403);
    }
    $out['profile_updated_at'] = $r['updated_at'] ?? null;
    $out['status'] = $r['status'] ?? 'active';
    if (($r['status'] ?? '') !== 'active') {
      api_fail('Your account is currently disabled. Please contact the administrator.', 403);
    }
  }
  api_ok($out);
} catch (Exception $e) { api_fail('Sync check failed.', 500); }
