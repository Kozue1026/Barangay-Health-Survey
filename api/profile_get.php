<?php
// GET ?resident_id= — own profile + children (web format). Password stripped.
require_once __DIR__ . '/_common.php';
$in = api_input();
api_require($in, ['resident_id']);
try {
  $uid = (string)$in['resident_id'];
  $r = api_require_active($uid);
  $kids = [];
  foreach (fb_all('resident_children') as $cid => $cc) {
    if ((string)($cc['resident_id'] ?? '') === $uid) {
      $kids[] = ['id' => (string)$cid, 'child_name' => $cc['child_name'] ?? '', 'age' => $cc['age'] ?? null];
    }
  }
  $r = fb_public_resident($r);
  $r['id'] = $uid;
  $r['children'] = $kids;
  api_ok(['resident' => $r]);
} catch (Exception $e) {
  if (http_response_code() === 200) api_fail('Could not load profile.', 500);
  throw $e;
}
