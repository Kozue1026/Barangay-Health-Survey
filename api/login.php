<?php
// POST {resident_number, password} — mirrors auth/login.php resident branch.
require_once __DIR__ . '/_common.php';
$in = api_input();
api_require($in, ['resident_number', 'password']);
try {
  $user = fb_find_one('residents', 'resident_number', trim($in['resident_number']));
  if (!$user || !password_verify($in['password'], fb_pass_hash($user))) {
    api_fail('Invalid credentials. Please try again.', 401);
  }
  if (($user['status'] ?? '') !== 'active') {
    api_fail('Your account is currently disabled. Please contact the administrator.', 403);
  }
  $uid = (string)$user['_id'];
  fb_log($uid, 'resident', 'Login', 'Mobile login successful');
  $hid = fb_next_id('login_history');
  try {
    fb_set_rec('login_history', $hid, [
      'user_id' => $uid, 'user_type' => 'resident',
      'login_time' => fb_now(), 'logout_time' => null,
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
  } catch (Exception $e) {}
  try { initializeResidentNotifications($uid, $user['first_name'] ?? ''); } catch (Exception $e) {}
  api_ok([
    'user_id' => $uid,
    'first_login' => (int)($user['first_login'] ?? 0),
    'resident' => fb_public_resident(fb_get_rec('residents', $uid)),
  ]);
} catch (Exception $e) {
  if (http_response_code() === 200) api_fail('System error occurred. Please try again later.', 500);
  throw $e;
}
