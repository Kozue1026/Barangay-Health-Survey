<?php
// Firebase Realtime Database backend (dependency-free REST + service-account OAuth2)
// DB: https://barangay-health-center-db-default-rtdb.firebaseio.com/
// Preserves MySQL IDs as string keys. No composer required.

date_default_timezone_set('Asia/Manila');

if (!defined('FIREBASE_DB_URL')) {
    define('FIREBASE_DB_URL', 'https://barangay-health-center-db-default-rtdb.firebaseio.com');
}
if (!defined('FIREBASE_CREDENTIALS_PATH')) {
    define('FIREBASE_CREDENTIALS_PATH', __DIR__ . '/firebase_credentials.json');
}
if (!defined('FIREBASE_TOKEN_CACHE')) {
    // Prefer env override (Koyeb secret mount), then sys temp if writable,
    // else persistent uploads dir so token survives across requests.
    $envCache = getenv('FIREBASE_TOKEN_CACHE');
    if ($envCache !== false && $envCache !== '') {
        define('FIREBASE_TOKEN_CACHE', $envCache);
    } else {
        $tmp = sys_get_temp_dir() . '/fb_rtdb_token_cache.json';
        if (@is_writable(sys_get_temp_dir())) {
            define('FIREBASE_TOKEN_CACHE', $tmp);
        } else {
            define('FIREBASE_TOKEN_CACHE', __DIR__ . '/../uploads/.fb_token_cache.json');
        }
    }
}

function fb_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function fb_service_account() {
    static $sa = null;
    if ($sa === null) {
        $path = FIREBASE_CREDENTIALS_PATH;
        if (!file_exists($path)) {
            throw new Exception('Firebase credentials not found at ' . $path);
        }
        $sa = json_decode(file_get_contents($path), true);
        if (!is_array($sa) || ($sa['type'] ?? '') !== 'service_account') {
            throw new Exception('Invalid Firebase service account file.');
        }
    }
    return $sa;
}

function fb_access_token() {
    // Return cached token if still valid (60s buffer)
    if (file_exists(FIREBASE_TOKEN_CACHE)) {
        $c = @json_decode(@file_get_contents(FIREBASE_TOKEN_CACHE), true);
        if (is_array($c) && !empty($c['access_token']) && !empty($c['expires_at']) && $c['expires_at'] > time() + 60) {
            return $c['access_token'];
        }
    }
    $sa = fb_service_account();
    $header = fb_base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $now = time();
    $claims = [
        'iss' => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.database https://www.googleapis.com/auth/userinfo.email',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ];
    $payload = fb_base64url_encode(json_encode($claims));
    $sigInput = $header . '.' . $payload;
    $pkey = openssl_pkey_get_private($sa['private_key']);
    if (!$pkey) throw new Exception('Invalid Firebase private key.');
    $sig = '';
    if (!openssl_sign($sigInput, $sig, $pkey, OPENSSL_ALGO_SHA256)) {
        throw new Exception('Failed to sign Firebase JWT.');
    }
    $jwt = $sigInput . '.' . fb_base64url_encode($sig);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($resp, true);
    if ($code !== 200 || empty($data['access_token'])) {
        throw new Exception('Firebase OAuth failed: ' . substr($resp, 0, 300));
    }
    @file_put_contents(FIREBASE_TOKEN_CACHE, json_encode([
        'access_token' => $data['access_token'],
        'expires_at' => time() + (int)($data['expires_in'] ?? 3600),
    ]));
    return $data['access_token'];
}

function fb_request($method, $path, $data = null) {
    $token = fb_access_token();
    $path = '/' . trim($path, '/');
    $url = FIREBASE_DB_URL . $path . '.json?access_token=' . urlencode($token);
    $ch = curl_init($url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $method];
    if ($data !== null) {
        $json = json_encode($data);
        $opts[CURLOPT_POSTFIELDS] = $json;
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) throw new Exception('Firebase request failed: ' . $err);
    if ($code >= 400) throw new Exception('Firebase HTTP ' . $code . ': ' . substr($resp, 0, 500));
    if ($resp === '' || $resp === 'null') return null;
    return json_decode($resp, true);
}

function fb_get($path) { return fb_request('GET', $path); }
function fb_set($path, $data) { return fb_request('PUT', $path, $data); }
function fb_update($path, $data) { return fb_request('PATCH', $path, $data); }
function fb_remove($path) { return fb_request('DELETE', $path); }
function fb_push($path, $data) {
    $res = fb_request('POST', $path, $data);
    return $res['name'] ?? null;
}

function fb_now() { return date('Y-m-d H:i:s'); }
function fb_today() { return date('Y-m-d'); }

// Survey window helpers (datetime-aware, backward compatible with date-only rows).
// Date-only opening => 00:00 start of day; date-only closing => 23:59 end of day.
function fb_norm_open($v) {
    $v = trim((string)($v ?? ''));
    if ($v === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) $v .= ' 00:00';
    return $v;
}
function fb_norm_close($v) {
    $v = trim((string)($v ?? ''));
    if ($v === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) $v .= ' 23:59';
    return $v;
}
// True when a survey row is currently answerable (status + window).
function fb_survey_open($survey, $nowTs = null) {
    if (($survey['status'] ?? '') !== 'active') return false;
    $nowTs = $nowTs ?? time();
    $od = fb_norm_open($survey['opening_date'] ?? null);
    $cd = fb_norm_close($survey['closing_date'] ?? null);
    if ($od !== null && strtotime($od) > $nowTs) return false;
    if ($cd !== null && strtotime($cd) < $nowTs) return false;
    return true;
}
// Human display that keeps working for both date-only and datetime rows.
function fb_fmt_dt($v) {
    $v = trim((string)($v ?? ''));
    if ($v === '') return '';
    $hasTime = (bool)preg_match('/\d{2}:\d{2}/', $v);
    return date($hasTime ? 'M d, Y g:i A' : 'M d, Y', strtotime($v));
}

// MySQL-shaped defaults: Firebase drops null values, MySQL returns NULL columns.
// Back-fill missing keys so templates ($row['col']) never raise "Undefined array key".
function fb_schema_defaults($node) {
    static $schemas = [
        'admins' => ['id' => null, 'username' => null, 'password' => null, 'full_name' => null, 'email' => null, 'role' => 'admin', 'status' => 'active', 'created_at' => null, 'updated_at' => null],
        'residents' => ['id' => null, 'resident_number' => null, 'password' => null, 'first_name' => null, 'last_name' => null, 'middle_name' => null, 'extension_name' => null, 'email' => null, 'phone' => null, 'address' => null, 'occupation' => null, 'employer' => null, 'employer_address' => null, 'spouse_name' => null, 'spouse_occupation' => null, 'spouse_employer' => null, 'father_name' => null, 'mother_name' => null, 'reference1_name' => null, 'reference1_contact' => null, 'reference2_name' => null, 'reference2_contact' => null, 'signature' => null, 'gender' => null, 'civil_status' => null, 'birthdate' => null, 'profile_picture' => null, 'status' => 'active', 'first_login' => 0, 'security_question' => null, 'security_answer' => null, 'created_at' => null, 'updated_at' => null],
        'resident_children' => ['id' => null, 'resident_id' => null, 'child_name' => null, 'age' => null, 'created_at' => null],
        'surveys' => ['id' => null, 'title' => null, 'description' => null, 'category' => null, 'status' => 'active', 'opening_date' => null, 'closing_date' => null, 'created_by' => null, 'created_at' => null, 'updated_at' => null],
        'survey_questions' => ['id' => null, 'survey_id' => null, 'question_text' => null, 'question_type' => null, 'question_order' => 0, 'required' => 1, 'created_at' => null],
        'survey_choices' => ['id' => null, 'question_id' => null, 'choice_text' => null, 'choice_order' => 0, 'created_at' => null],
        'responses' => ['id' => null, 'survey_id' => null, 'resident_id' => null, 'status' => 'completed', 'submitted_at' => null, 'created_at' => null],
        'response_answers' => ['id' => null, 'response_id' => null, 'question_id' => null, 'answer_text' => null, 'choice_id' => null, 'rating_value' => null, 'created_at' => null],
        'notifications' => ['id' => null, 'user_id' => null, 'user_type' => null, 'title' => null, 'message' => null, 'link' => null, 'icon' => 'bi-bell', 'is_read' => 0, 'related_id' => null, 'related_type' => null, 'created_at' => null],
        'activity_logs' => ['id' => null, 'user_id' => null, 'user_type' => null, 'action' => null, 'description' => null, 'ip_address' => null, 'created_at' => null],
        'login_history' => ['id' => null, 'user_id' => null, 'user_type' => null, 'login_time' => null, 'logout_time' => null, 'ip_address' => null],
    ];
    $node = trim($node, '/');
    return $schemas[$node] ?? [];
}
function fb_normalize($node, $row) {
    if (!is_array($row)) return $row;
    $defaults = fb_schema_defaults($node);
    if (!$defaults) return $row;
    return array_merge($defaults, $row);
}

// ---- Convenience helpers over flat nodes (keys = preserved MySQL IDs as strings) ----
function fb_all($node) {
    $v = fb_get('/' . trim($node, '/'));
    if (!is_array($v)) return [];
    $out = [];
    foreach ($v as $k => $row) {
        if ($row === null || $row === '' || $row === []) continue;
        if (!is_array($row)) continue;
        // RTDB returns numeric-keyed nodes as JSON arrays (index == id). Skip index 0 placeholder.
        if ((string)$k === '0' && !isset($row['id'])) continue;
        $out[(string)$k] = fb_normalize($node, $row);
    }
    return $out;
}
function fb_get_rec($node, $id) {
    if ($id === null || $id === '') return null;
    $v = fb_get('/' . trim($node, '/') . '/' . $id);
    if (!is_array($v)) return null;
    $rec = fb_normalize($node, $v);
    // Always expose the key as id for templates that use $row['id']
    if (!isset($rec['id']) || $rec['id'] === null || $rec['id'] === '') $rec['id'] = (string)$id;
    return $rec;
}
function fb_set_rec($node, $id, $data) {
    return fb_set('/' . trim($node, '/') . '/' . $id, $data);
}
function fb_update_rec($node, $id, $data) {
    return fb_update('/' . trim($node, '/') . '/' . $id, $data);
}
function fb_delete_rec($node, $id) {
    return fb_remove('/' . trim($node, '/') . '/' . $id);
}
function fb_next_id($node) {
    // Atomic-ish counter stored at _counters/{node}. Initialized from max existing id.
    $node = trim($node, '/');
    try {
        $c = fb_get('/_counters/' . $node);
        if (is_array($c) && isset($c['next_id'])) {
            $next = (int)$c['next_id'];
            fb_set('/_counters/' . $node, ['next_id' => $next + 1]);
            return (string)$next;
        }
    } catch (Exception $e) { /* fall through */ }
    $all = fb_all($node);
    $max = 0;
    foreach ($all as $k => $v) { if (ctype_digit((string)$k)) $max = max($max, (int)$k); }
    $next = $max + 1;
    try { fb_set('/_counters/' . $node, ['next_id' => $next + 1]); } catch (Exception $e) {}
    return (string)$next;
}
function fb_find_one($node, $field, $value, $caseInsensitive = false) {
    foreach (fb_all($node) as $id => $row) {
        if (!is_array($row) || !array_key_exists($field, $row)) continue;
        $rv = $row[$field];
        if ($caseInsensitive && is_string($rv) && is_string($value)) {
            if (strtolower($rv) === strtolower($value)) { $row['_id'] = (string)$id; return $row; }
        } else {
            if ($rv == $value) { $row['_id'] = (string)$id; return $row; }
        }
    }
    return null;
}
function fb_where($node, callable $fn) {
    $out = [];
    foreach (fb_all($node) as $id => $row) {
        if (!is_array($row)) continue;
        $row['_id'] = (string)$id;
        if ($fn($row)) $out[(string)$id] = $row;
    }
    return $out;
}
function fb_search_rows($rows, $q, $fields) {
    if ($q === '') return $rows;
    $ql = strtolower($q);
    return array_filter($rows, function ($r) use ($ql, $fields) {
        foreach ($fields as $f) {
            if (isset($r[$f]) && stripos((string)$r[$f], $ql) !== false) return true;
        }
        return false;
    });
}
function fb_sort_paginate($rows, $sort_by, $sort_order, $page, $limit) {
    $rows = array_values($rows);
    usort($rows, function ($a, $b) use ($sort_by, $sort_order) {
        $av = $a[$sort_by] ?? $a['_id'] ?? '';
        $bv = $b[$sort_by] ?? $b['_id'] ?? '';
        if (is_numeric($av) && is_numeric($bv)) $c = $av - $bv;
        else $c = strcmp((string)$av, (string)$bv);
        return strtoupper($sort_order) === 'ASC' ? $c : -$c;
    });
    $total = count($rows);
    $offset = ($page - 1) * $limit;
    return [array_slice($rows, $offset, $limit), $total];
}
function fb_next_resident_number() {
    $max = 0;
    foreach (fb_all('residents') as $id => $r) {
        if (preg_match('/^RES-(\d+)$/', $r['resident_number'] ?? '', $m)) $max = max($max, (int)$m[1]);
    }
    return 'RES-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
}
// Bump lightweight sync version so mobile can poll tiny _meta instead of full lists.
function fb_bump_sync($key = 'surveys') {
    try {
        fb_set('/_meta/' . trim($key, '/') . '_updated', ['v' => time()]);
    } catch (Exception $e) {}
}
// Strip password hashes before sending to browser
function fb_public_resident($r) {
    if (is_array($r)) { unset($r['password']); unset($r['password_hash']); unset($r['security_answer']); }
    return $r;
}
function fb_public_admin($a) {
    if (is_array($a)) { unset($a['password']); unset($a['password_hash']); }
    return $a;
}
function fb_pass_hash($row) {
    return $row['password'] ?? $row['password_hash'] ?? null;
}
// Aggregated survey results (shared by ajax/reports, ajax/responses, admin pages)
function fb_question_results($survey_id) {
    $sid = (string)$survey_id;
    $qs = [];
    foreach (fb_all('survey_questions') as $qid => $q) {
        if ((string)($q['survey_id'] ?? '') !== $sid) continue;
        $q['_id'] = (string)$qid;
        $qs[] = $q;
    }
    usort($qs, function ($a, $b) { return ((int)($a['question_order'] ?? 0)) - ((int)($b['question_order'] ?? 0)); });
    $answers = fb_all('response_answers');
    $choices = fb_all('survey_choices');
    $results = [];
    foreach ($qs as $q) {
        $qid = (string)$q['_id'];
        $qd = ['question_text' => $q['question_text'] ?? '', 'question_type' => $q['question_type'] ?? '', 'data' => []];
        if ($q['question_type'] === 'multiple_choice') {
            $map = [];
            foreach ($choices as $cid => $c) {
                if ((string)($c['question_id'] ?? '') === $qid) $map[(string)$cid] = ['choice_text' => $c['choice_text'] ?? '', 'count' => 0, 'order' => (int)($c['choice_order'] ?? 0)];
            }
            foreach ($answers as $a) {
                if ((string)($a['question_id'] ?? '') === $qid && !empty($a['choice_id']) && isset($map[(string)$a['choice_id']])) {
                    $map[(string)$a['choice_id']]['count']++;
                }
            }
            $list = array_values($map);
            usort($list, function ($a, $b) { return $a['order'] - $b['order']; });
            $qd['data'] = array_map(function ($x) { return ['choice_text' => $x['choice_text'], 'count' => $x['count']]; }, $list);
        } elseif ($q['question_type'] === 'yes_no') {
            $cnt = [];
            foreach ($answers as $a) {
                if ((string)($a['question_id'] ?? '') !== $qid) continue;
                $t = $a['answer_text'] ?? '';
                if ($t !== 'Yes' && $t !== 'No') continue;
                if (!isset($cnt[$t])) $cnt[$t] = 0;
                $cnt[$t]++;
            }
            foreach ($cnt as $label => $c) $qd['data'][] = ['label' => $label, 'count' => $c];
        } elseif ($q['question_type'] === 'rating') {
            $cnt = []; $sum = 0; $n = 0;
            foreach ($answers as $a) {
                if ((string)($a['question_id'] ?? '') !== $qid || !isset($a['rating_value']) || $a['rating_value'] === null || $a['rating_value'] === '') continue;
                $rv = (string)$a['rating_value'];
                $cnt[$rv] = ($cnt[$rv] ?? 0) + 1;
                $sum += (int)$a['rating_value']; $n++;
            }
            ksort($cnt, SORT_NUMERIC);
            foreach ($cnt as $label => $c) $qd['data'][] = ['label' => $label, 'count' => $c];
            $qd['average'] = $n ? round($sum / $n, 2) : 0;
        } else {
            foreach ($answers as $a) {
                if ((string)($a['question_id'] ?? '') !== $qid) continue;
                if (($a['answer_text'] ?? '') !== '') $qd['data'][] = $a['answer_text'];
            }
        }
        $results[] = $qd;
    }
    return [$qs, $results];
}
function fb_survey_respondent_count($survey_id) {
    $c = 0;
    foreach (fb_all('responses') as $r) { if ((string)($r['survey_id'] ?? '') === (string)$survey_id) $c++; }
    return $c;
}
