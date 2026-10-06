<?php
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = (string)$_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'mark_read') {
        $notif_id = (string)($_POST['id'] ?? $_GET['id'] ?? 0);
        $n = fb_get_rec('notifications', $notif_id);
        if ($n && (string)($n['user_id'] ?? '') === $user_id && ($n['user_type'] ?? '') === $user_type) {
            fb_update_rec('notifications', $notif_id, ['is_read' => 1]);
        }
        echo json_encode(['success' => true]);
    } elseif ($action === 'mark_all_read') {
        $marked = 0;
        foreach (fb_all('notifications') as $nid => $n) {
            if ((string)($n['user_id'] ?? '') === $user_id && ($n['user_type'] ?? '') === $user_type && !(int)($n['is_read'] ?? 0)) {
                fb_update_rec('notifications', $nid, ['is_read' => 1]);
                $marked++;
            }
        }
        echo json_encode(['success' => true, 'marked' => $marked]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
