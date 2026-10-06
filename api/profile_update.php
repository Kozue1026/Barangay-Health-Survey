<?php
// POST profile update — mirrors resident/profile.php validation (no resident_number change, no photo here).
require_once __DIR__ . '/_common.php';
$in = api_input();
api_require($in, ['resident_id', 'first_name', 'last_name', 'phone']);
try {
  $uid = (string)$in['resident_id'];
  $old = api_require_active($uid);

  $first = trim($in['first_name'] ?? ''); $last = trim($in['last_name'] ?? '');
  $middle = trim($in['middle_name'] ?? ''); $phone = trim($in['phone'] ?? '');
  $birthdate = trim($in['birthdate'] ?? '');
  $nameRe = '/^[\p{L}\s\-\'\.]+$/u';
  if ($first === '' || !preg_match($nameRe, $first)) api_fail('First Name is required (letters only).');
  if ($last === '' || !preg_match($nameRe, $last)) api_fail('Last Name is required (letters only).');
  if ($middle !== '' && !preg_match($nameRe, $middle)) api_fail('Middle Name must be letters only.');
  if (!preg_match('/^[0-9]{11}$/', $phone)) api_fail('Phone must be exactly 11 digits (e.g. 09171234567).');
  if ($birthdate !== '' && strtotime($birthdate) > time()) api_fail('Birthdate cannot be in the future.');
  // Phone uniqueness (excluding self)
  foreach (fb_all('residents') as $id => $r) {
    if ((string)$id !== $uid && ($r['phone'] ?? '') === $phone) api_fail('That mobile number is already registered.');
  }

  $fields = ['first_name','last_name','middle_name','extension_name','email','phone','address','gender','civil_status','birthdate','occupation','employer','employer_address','spouse_name','spouse_occupation','spouse_employer','father_name','mother_name','reference1_name','reference1_contact','reference2_name','reference2_contact','signature','security_question'];
  $upd = ['updated_at' => fb_now()];
  foreach ($fields as $f) {
    if (array_key_exists($f, $in)) $upd[$f] = is_string($in[$f]) ? trim($in[$f]) : $in[$f];
  }
  if (array_key_exists('security_answer', $in)) $upd['security_answer'] = strtolower(trim($in['security_answer'] ?? ''));
  if (isset($upd['birthdate']) && $upd['birthdate'] !== '') $upd['birthdate'] = date('Y-m-d', strtotime($upd['birthdate']));
  fb_update_rec('residents', $uid, $upd);

  // Sync children (web format) if provided
  if (array_key_exists('children', $in) && is_array($in['children'])) {
    foreach (fb_all('resident_children') as $cid => $cc) {
      if ((string)($cc['resident_id'] ?? '') === $uid) fb_delete_rec('resident_children', (string)$cid);
    }
    foreach ($in['children'] as $k) {
      if (!is_array($k)) continue;
      $cn = trim($k['child_name'] ?? $k['name'] ?? '');
      if ($cn === '') continue;
      $age = $k['age'] ?? null;
      $age = ($age === '' || $age === null) ? null : (int)$age;
      $cid = fb_next_id('resident_children');
      fb_set_rec('resident_children', $cid, ['resident_id' => $uid, 'child_name' => $cn, 'age' => $age, 'created_at' => fb_now()]);
    }
  }

  $new = fb_get_rec('residents', $uid);
  try {
    $changes = getResidentChangesDescription($old, array_merge($old, $upd));
    if (!empty($changes)) {
      notifyAdmins(null, 'Resident Profile Updated (mobile)', 'Resident ' . ($old['resident_number'] ?? '') . " ($first $last) updated: " . implode('; ', $changes), '/admin/manage_residents.php', 'bi-person-lines-fill text-info', $uid, 'resident');
    }
  } catch (Exception $e) {}
  fb_log($uid, 'resident', 'Update Profile', 'Mobile profile update');
  api_ok(['resident' => fb_public_resident($new)]);
} catch (Exception $e) {
  if (http_response_code() === 200) api_fail('Update failed. Please try again.', 500);
  throw $e;
}
