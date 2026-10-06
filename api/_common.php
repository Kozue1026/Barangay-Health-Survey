<?php
// Shared helpers for Flutter resident API (same Firebase as website).
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/session.php';

function api_input() {
  $raw = file_get_contents('php://input');
  if ($raw !== '' && ($j = json_decode($raw, true)) !== null && is_array($j)) {
    return array_merge($_GET, $_POST, $j);
  }
  return array_merge($_GET, $_POST);
}
function api_ok($data = []) {
  echo json_encode(array_merge(['ok' => true], $data));
  exit;
}
function api_fail($msg, $code = 400) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg]);
  exit;
}
function api_require($in, $keys) {
  foreach ($keys as $k) {
    if (!isset($in[$k]) || (is_string($in[$k]) && trim($in[$k]) === '')) {
      api_fail("Missing required field: $k");
    }
  }
}
// Enforce admin active/inactive live: any resident-scoped call fails fast when disabled.
function api_require_active($resident_id) {
  $r = fb_get_rec('residents', (string)$resident_id);
  if (!$r) api_fail('Account not found. Please contact the administrator.', 403);
  if (($r['status'] ?? '') !== 'active') {
    api_fail('Your account is currently disabled. Please contact the administrator.', 403);
  }
  return $r;
}
