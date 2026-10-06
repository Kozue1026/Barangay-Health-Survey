<?php
// POST {resident_id, old_password, new_password} — mirrors auth/change_password.php (8+ letters+numbers).
require_once __DIR__ . '/_common.php';
$in = api_input();
api_require($in, ['resident_id', 'old_password', 'new_password']);
try {
  $uid = (string)$in['resident_id'];
  $user = api_require_active($uid);
  if (!password_verify($in['old_password'], fb_pass_hash($user))) api_fail('Current password is incorrect.');
  $nw = $in['new_password'] ?? '';
  if (strlen($nw) < 8 || !preg_match('/[A-Za-z]/', $nw) || !preg_match('/[0-9]/', $nw)) {
    api_fail('Password must be at least 8 characters long and contain both letters and numbers.');
  }
  fb_update_rec('residents', $uid, ['password' => password_hash($nw, PASSWORD_DEFAULT), 'first_login' => 0, 'updated_at' => fb_now()]);
  fb_log($uid, 'resident', 'Change Password', 'Mobile password change');
  try {
    notifyAdmins(null, 'Resident Password Changed', 'Resident ' . ($user['resident_number'] ?? '') . ' changed their account password (mobile).', '/admin/manage_residents.php', 'bi-key text-warning', $uid, 'resident');
  } catch (Exception $e) {}
  api_ok([]);
} catch (Exception $e) {
  if (http_response_code() === 200) api_fail('Could not change password.', 500);
  throw $e;
}
