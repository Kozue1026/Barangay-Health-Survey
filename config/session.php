<?php
date_default_timezone_set('Asia/Manila');

// Detect API requests (mobile app, stateless JSON) — must skip sessions/redirects.
if (!defined('IS_API_REQUEST')) {
    $___script = $_SERVER['SCRIPT_NAME'] ?? '';
    define('IS_API_REQUEST', strpos(str_replace('\\', '/', $___script), '/api/') !== false);
}

// Auto-detect base URL path for subfolder installations (e.g., /Group-5)
// Env override for hosted deploys (Koyeb/Render): set BASE_URL env, e.g. '' for root.
if (!defined('BASE_URL')) {
    $envBase = getenv('BASE_URL');
    if ($envBase !== false && $envBase !== '') {
        define('BASE_URL', rtrim($envBase, '/'));
    } else {
        $configDir = str_replace('\\', '/', __DIR__);
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
        $projectRoot = str_replace('\\', '/', dirname($configDir)); // one level up from /config
        $basePath = ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0) ? str_replace($docRoot, '', $projectRoot) : '';
        define('BASE_URL', rtrim($basePath, '/'));
    }
}

if (!IS_API_REQUEST && session_status() === PHP_SESSION_NONE) {
    session_start();
}

$timeout_duration = 1800; // 30 minutes

if (!IS_API_REQUEST) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: " . BASE_URL . "/auth/login.php?timeout=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function setNoCacheHeaders() {
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function requireLogin() {
    setNoCacheHeaders();
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "/auth/login.php");
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_type'] !== 'admin') {
        header("Location: " . BASE_URL . "/resident/dashboard.php");
        exit;
    }
}

function requireResident() {
    requireLogin();
    if ($_SESSION['user_type'] !== 'resident') {
        header("Location: " . BASE_URL . "/admin/dashboard.php");
        exit;
    }
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'user_id' => $_SESSION['user_id'],
            'user_type' => $_SESSION['user_type'],
            'username' => $_SESSION['username'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? ''
        ];
    }
    return null;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

require_once __DIR__ . '/firebase.php';

function fb_log($user_id, $user_type, $action, $description) {
    try {
        $id = fb_next_id('activity_logs');
        fb_set_rec('activity_logs', $id, [
            'user_id' => $user_id, 'user_type' => $user_type, 'action' => $action,
            'description' => $description, 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => fb_now(),
        ]);
        return true;
    } catch (Exception $e) { return false; }
}

function logActivity($pdo, $action = null, $description = null) {
    // Backward compat: logActivity($pdo, $action, $desc) or logActivity($action, $desc)
    if ($action === null && is_string($pdo)) { $action = $pdo; $description = $action; }
    if (is_string($pdo) && $action !== null && $description === null && func_num_args() === 2) {
        // called as logActivity($action, $description)
        $description = $action; $action = $pdo;
    }
    if (!isLoggedIn()) return;
    fb_log($_SESSION['user_id'], $_SESSION['user_type'], $action, $description);
}

function fb_create_notification($user_id, $user_type, $title, $message, $link, $icon = 'bi-bell', $related_id = null, $related_type = null) {
    try {
        $id = fb_next_id('notifications');
        fb_set_rec('notifications', $id, [
            'user_id' => $user_id, 'user_type' => $user_type, 'title' => $title,
            'message' => $message, 'link' => $link, 'icon' => $icon ?: 'bi-bell',
            'is_read' => 0, 'related_id' => $related_id, 'related_type' => $related_type,
            'created_at' => fb_now(),
        ]);
        return true;
    } catch (Exception $e) { return false; }
}

function createNotification($pdo, $user_id = null, $user_type = null, $title = null, $message = null, $link = null, $icon = 'bi-bell', $related_id = null, $related_type = null) {
    if (is_string($pdo) || is_numeric($pdo)) {
        // new-style: createNotification($user_id, $user_type, ...)
        return fb_create_notification($pdo, $user_id, $user_type, $title, $message, $link, $icon, $related_id);
    }
    return fb_create_notification($user_id, $user_type, $title, $message, $link, $icon, $related_id, $related_type);
}

function notifyAllResidents($pdo, $title = null, $message = null, $link = null, $icon = 'bi-bell', $related_id = null, $related_type = null) {
    if (is_string($pdo)) { // new-style first arg is title
        $related_type = $link; $related_id = $icon; $icon = $message; $link = $title; $message = $pdo; $title = $pdo;
    }
    // Detect new-style call notifyAllResidents($title,...) where $pdo is actually title
    if ($title === null && is_string($pdo)) { $title = $pdo; }
    try {
        foreach (fb_all('residents') as $id => $r) {
            if (($r['status'] ?? '') !== 'active') continue;
            fb_create_notification($id, 'resident', $title, $message, $link, $icon, $related_id, $related_type);
        }
        return true;
    } catch (Exception $e) { return false; }
}

function notifyAdmins($pdo, $title = null, $message = null, $link = null, $icon = 'bi-bell', $related_id = null, $related_type = null) {
    try {
        foreach (fb_all('admins') as $id => $a) {
            if (($a['status'] ?? '') !== 'active') continue;
            fb_create_notification($id, 'admin', $title, $message, $link, $icon, $related_id, $related_type);
        }
        return true;
    } catch (Exception $e) { return false; }
}

function initializeResidentNotifications($pdo, $resident_id = null, $first_name = '') {
    if (is_string($pdo) || is_numeric($pdo)) { $first_name = $resident_id ?? ''; $resident_id = $pdo; }
    try {
        if (empty($first_name)) {
            $r = fb_get_rec('residents', $resident_id);
            $first_name = $r['first_name'] ?? 'Resident';
        }

        // 1. Welcome Notification
        $hasWelcome = false;
        foreach (fb_all('notifications') as $n) {
            if (($n['user_id'] ?? '') == $resident_id && ($n['user_type'] ?? '') === 'resident'
                && (($n['related_type'] ?? '') === 'welcome' || stripos($n['title'] ?? '', 'Welcome') !== false)) {
                $hasWelcome = true; break;
            }
        }
        if (!$hasWelcome) {
            fb_create_notification(
                $resident_id,
                'resident',
                'Welcome to Barangay Survey Portal',
                'Welcome, ' . htmlspecialchars($first_name) . '! Your account is active. Please complete your profile and participate in barangay surveys.',
                '/resident/profile.php',
                'bi-person-check text-primary',
                $resident_id,
                'welcome'
            );
        }

        // 2. Notifications for all currently active surveys
        $notifs = fb_all('notifications');
        $resps = fb_all('responses');
        $hasNotif = [];
        foreach ($notifs as $n) {
            if (($n['user_id'] ?? '') == $resident_id && ($n['user_type'] ?? '') === 'resident' && ($n['related_type'] ?? '') === 'survey') {
                $hasNotif[(string)($n['related_id'] ?? '')] = true;
            }
        }
        $hasResp = [];
        foreach ($resps as $r) {
            if (($r['resident_id'] ?? '') == $resident_id) $hasResp[(string)($r['survey_id'] ?? '')] = true;
        }
        foreach (fb_all('surveys') as $sid => $s) {
            if (($s['status'] ?? '') !== 'active') continue;
            if (isset($hasNotif[(string)$sid]) || isset($hasResp[(string)$sid])) continue;
            fb_create_notification(
                $resident_id,
                'resident',
                'New Survey Available',
                $s['title'] ?? 'New survey',
                '/resident/preview_survey.php?id=' . $sid,
                'bi-clipboard-check text-primary',
                $sid,
                'survey'
            );
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function getResidentChangesDescription($old, $new) {
    $fieldLabels = [
        'first_name'         => 'First Name',
        'last_name'          => 'Last Name',
        'middle_name'        => 'Middle Name',
        'extension_name'     => 'Extension Name',
        'email'              => 'Email',
        'phone'              => 'Phone',
        'address'            => 'Address',
        'gender'             => 'Gender',
        'civil_status'       => 'Civil Status',
        'birthdate'          => 'Birthdate',
        'occupation'         => 'Occupation',
        'employer'           => 'Employer',
        'employer_address'   => 'Employer Address',
        'spouse_name'        => 'Spouse Name',
        'spouse_occupation'  => 'Spouse Occupation',
        'spouse_employer'    => 'Spouse Employer',
        'father_name'        => 'Father Name',
        'mother_name'        => 'Mother Name',
        'reference1_name'    => 'Reference 1 Name',
        'reference1_contact' => 'Reference 1 Contact',
        'reference2_name'    => 'Reference 2 Name',
        'reference2_contact' => 'Reference 2 Contact',
        'signature'          => 'Signature',
        'status'             => 'Status',
        'security_question'  => 'Security Question',
        'security_answer'    => 'Security Answer'
    ];
    
    $changes = [];
    foreach ($fieldLabels as $field => $label) {
        if (isset($new[$field]) && isset($old[$field])) {
            $oldVal = trim((string)$old[$field]);
            $newVal = trim((string)$new[$field]);
            if ($oldVal !== $newVal) {
                if ($field === 'security_answer') {
                    $changes[] = "Security Answer updated";
                } elseif ($field === 'address') {
                    $changes[] = "Address updated";
                } elseif ($field === 'security_question') {
                    $changes[] = "Security Question changed to \"$newVal\"";
                } else {
                    $oldDisplay = $oldVal !== '' ? $oldVal : '(empty)';
                    $newDisplay = $newVal !== '' ? $newVal : '(empty)';
                    $changes[] = "$label: $oldDisplay → $newDisplay";
                }
            }
        } elseif (isset($new[$field]) && !isset($old[$field])) {
            $newVal = trim((string)$new[$field]);
            if ($newVal !== '') {
                $changes[] = "$label set to \"$newVal\"";
            }
        }
    }
    return $changes;
}
?>
