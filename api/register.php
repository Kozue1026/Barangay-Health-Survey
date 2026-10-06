<?php
// POST mobile self-registration only. Creates residents + resident_children.
// Required (draft as-is): first_name, last_name, phone(11), birthdate, gender, address, password(8+letters+numbers).
require_once __DIR__ . '/_common.php';
$in = api_input();
api_require($in, ['first_name', 'last_name', 'phone', 'birthdate', 'gender', 'address', 'password']);
try {
  $first = trim($in['first_name'] ?? '');
  $last = trim($in['last_name'] ?? '');
  $middle = trim($in['middle_name'] ?? '');
  $phone = trim($in['phone'] ?? '');
  $birthdate = trim($in['birthdate'] ?? '');
  $gender = trim($in['gender'] ?? '');
  $address = trim($in['address'] ?? '');
  $email = trim($in['email'] ?? '');
  $pw = $in['password'] ?? '';

  $nameRe = '/^[\p{L}\s\-\'\.]+$/u';
  if ($first === '' || !preg_match($nameRe, $first)) api_fail('First Name is required (letters only).');
  if ($last === '' || !preg_match($nameRe, $last)) api_fail('Last Name is required (letters only).');
  if ($middle !== '' && !preg_match($nameRe, $middle)) api_fail('Middle Name must be letters only.');
  if (!preg_match('/^[0-9]{11}$/', $phone)) api_fail('Phone must be exactly 11 digits (e.g. 09171234567).');
  if ($birthdate === '' || !strtotime($birthdate)) api_fail('Birthdate is required.');
  if (strtotime($birthdate) > time()) api_fail('Birthdate cannot be in the future.');
  if ($gender === '') api_fail('Gender is required.');
  if ($address === '') api_fail('Residential Address is required.');
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) api_fail('Please enter a valid email address.');
  if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
    api_fail('Password must be at least 8 characters long and contain both letters and numbers.');
  }
  // Duplicate phone/email guard (resident_number is auto-assigned, always unique)
  foreach (fb_all('residents') as $r) {
    if (($r['phone'] ?? '') === $phone) api_fail('That mobile number is already registered.');
    if ($email !== '' && strcasecmp($r['email'] ?? '', $email) === 0) api_fail('An account already exists for that email address.');
  }

  $resNum = fb_next_resident_number(); // RES-0001 format
  $id = fb_next_id('residents');
  $now = fb_now();
  $rec = [
    'resident_number' => $resNum,
    'password' => password_hash($pw, PASSWORD_DEFAULT),
    'first_name' => $first, 'last_name' => $last, 'middle_name' => $middle,
    'extension_name' => trim($in['extension_name'] ?? ''),
    'email' => $email, 'phone' => $phone, 'address' => $address,
    'gender' => $gender, 'civil_status' => trim($in['civil_status'] ?? 'Single'),
    'birthdate' => date('Y-m-d', strtotime($birthdate)),
    'occupation' => trim($in['occupation'] ?? ''), 'employer' => trim($in['employer'] ?? ''),
    'employer_address' => trim($in['employer_address'] ?? ''),
    'spouse_name' => trim($in['spouse_name'] ?? ''),
    'spouse_occupation' => trim($in['spouse_occupation'] ?? ''),
    'spouse_employer' => trim($in['spouse_employer'] ?? ''),
    'father_name' => trim($in['father_name'] ?? ''), 'mother_name' => trim($in['mother_name'] ?? ''),
    'reference1_name' => trim($in['reference1_name'] ?? ''),
    'reference1_contact' => trim($in['reference1_contact'] ?? ''),
    'reference2_name' => trim($in['reference2_name'] ?? ''),
    'reference2_contact' => trim($in['reference2_contact'] ?? ''),
    'signature' => trim($in['signature'] ?? ''),
    'security_question' => trim($in['security_question'] ?? ''),
    'security_answer' => strtolower(trim($in['security_answer'] ?? '')),
    'profile_picture' => null, 'status' => 'active', 'first_login' => 0,
    'created_at' => $now, 'updated_at' => $now,
  ];
  fb_set_rec('residents', $id, $rec);

  // Children -> separate resident_children node (web format: child_name + age)
  $kids = $in['children'] ?? [];
  if (is_array($kids)) {
    foreach ($kids as $k) {
      if (!is_array($k)) continue;
      $cn = trim($k['child_name'] ?? $k['name'] ?? '');
      if ($cn === '') continue;
      $age = $k['age'] ?? null;
      $age = ($age === '' || $age === null) ? null : (int)$age;
      $cid = fb_next_id('resident_children');
      fb_set_rec('resident_children', $cid, [
        'resident_id' => (string)$id, 'child_name' => $cn, 'age' => $age, 'created_at' => $now,
      ]);
    }
  }

  fb_log((string)$id, 'resident', 'Register', "Mobile self-registration: $resNum");
  try { initializeResidentNotifications((string)$id, $first); } catch (Exception $e) {}
  try {
    notifyAdmins(null, 'New Resident Registered', "Resident $resNum ($first $last) registered via mobile.", '/admin/manage_residents.php', 'bi-person-plus text-success', (string)$id, 'resident');
  } catch (Exception $e) {}

  api_ok(['user_id' => (string)$id, 'resident' => fb_public_resident(fb_get_rec('residents', (string)$id))]);
} catch (Exception $e) {
  if (http_response_code() === 200) api_fail('Registration failed. Please try again.', 500);
  throw $e;
}
