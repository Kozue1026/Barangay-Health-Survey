<?php
require_once '../config/session.php';

if (!isLoggedIn()) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

$notif_id = (string)($_GET['id'] ?? '');
$user_id = (string)$_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? '';

if ($notif_id !== '') {
    try {
        $n = fb_get_rec('notifications', $notif_id);
        if ($n && (string)($n['user_id'] ?? '') === $user_id && ($n['user_type'] ?? '') === $user_type) {
            $link = $n['link'] ?? '';
            if ($link) {
                fb_update_rec('notifications', $notif_id, ['is_read' => 1]);
                $targetUrl = (strpos($link, 'http://') === 0 || strpos($link, 'https://') === 0)
                    ? $link
                    : (strpos($link, BASE_URL) === 0 ? $link : BASE_URL . '/' . ltrim($link, '/'));
                header("Location: " . $targetUrl);
                exit;
            }
        }
    } catch (Exception $e) {
        // Fallback
    }
}

if ($user_type === 'admin') {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
} else {
    header("Location: " . BASE_URL . "/resident/dashboard.php");
}
exit;
