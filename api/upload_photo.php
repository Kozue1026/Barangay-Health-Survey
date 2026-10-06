<?php
// POST multipart {resident_id, profile_pic=file} — mirrors resident/profile.php upload_photo action.
// Returns {profile_picture, url}. Same rules: JPG/JPEG/PNG/GIF/WEBP, real image, <=2MB.
require_once __DIR__ . '/_common.php';
// Multipart posts don't arrive as JSON; read fields from $_POST/$_FILES.
$in = array_merge($_GET, $_POST);
$resident_id = (string)($in['resident_id'] ?? '');
if ($resident_id === '') api_fail('Missing required field: resident_id');
try {
  $user = api_require_active($resident_id);
  if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
    api_fail('No file uploaded or error occurred during file transfer.');
  }
  $tmp = $_FILES['profile_pic']['tmp_name'];
  $name = $_FILES['profile_pic']['name'];
  $size = (int)$_FILES['profile_pic']['size'];
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  if (!in_array($ext, $allowed, true) || !@getimagesize($tmp)) {
    api_fail('Upload failed. Please select a valid image file (JPG, PNG, GIF, WEBP).');
  }
  if ($size > 2 * 1024 * 1024) api_fail('File is too large. Maximum size allowed is 2MB.');

  $dir = __DIR__ . '/../uploads/profile_pics/';
  if (!is_dir($dir)) mkdir($dir, 0755, true);
  $new = 'res_' . $resident_id . '_' . time() . '.' . $ext;
  // move_uploaded_file only works for real HTTP uploads; allow rename fallback for tests.
  $moved = @move_uploaded_file($tmp, $dir . $new);
  if (!$moved) $moved = @rename($tmp, $dir . $new);
  if (!$moved) api_fail('There was an error saving the uploaded photo.');

  $old = $user['profile_picture'] ?? null;
  fb_update_rec('residents', $resident_id, ['profile_picture' => $new, 'updated_at' => fb_now()]);
  if ($old && $old !== $new && file_exists($dir . $old)) @unlink($dir . $old);

  fb_log($resident_id, 'resident', 'Upload Profile Picture', 'Mobile photo upload');
  try {
    notifyAdmins(null, 'Resident Photo Updated', 'Resident ' . ($user['resident_number'] ?? '') . ' uploaded a new profile picture (mobile).', '/admin/manage_residents.php', 'bi-image text-info', $resident_id, 'resident');
  } catch (Exception $e) {}

  // Rebuild public URL from this request (works for LAN IP, hosted domains, and proxies like Koyeb/Cloudflare).
  $forwardedProto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0] ?? ''));
  if ($forwardedProto === 'https' || $forwardedProto === 'http') {
    $scheme = $forwardedProto;
  } else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  }
  $host = $_SERVER['HTTP_HOST'] ?? '';
  $basePath = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api')));
  $basePath = rtrim($basePath, '/');
  $url = $scheme . '://' . $host . $basePath . '/uploads/profile_pics/' . $new;
  api_ok(['profile_picture' => $new, 'url' => $url]);
} catch (Exception $e) {
  if (http_response_code() === 200) api_fail('Upload failed. Please try again.', 500);
  throw $e;
}
