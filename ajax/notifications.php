<?php
require_once '../config/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'unread_count' => 0, 'items' => []]);
    exit;
}

$user_id = (string)$_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? '';
$unread_count = 0;
$items = [];

try {
    $mine = [];
    foreach (fb_all('notifications') as $nid => $n) {
        if ((string)($n['user_id'] ?? '') === $user_id && ($n['user_type'] ?? '') === $user_type && !(int)($n['is_read'] ?? 0)) {
            $n['id'] = $nid;
            $mine[] = $n;
        }
    }
    $unread_count = count($mine);
    usort($mine, function ($a, $b) { return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });
    $mine = array_slice($mine, 0, 10);

    foreach ($mine as $n) {
        $createdAt = strtotime($n['created_at'] ?? 'now');
        $diff = time() - $createdAt;
        if ($diff < 60) $timeLabel = 'Just now';
        elseif ($diff < 3600) $timeLabel = floor($diff / 60) . 'm ago';
        elseif ($diff < 86400) $timeLabel = floor($diff / 3600) . 'h ago';
        else $timeLabel = date('M j', $createdAt);

        $items[] = [
            'id' => (int)$n['id'],
            'icon' => $n['icon'] ?? 'bi-bell',
            'title' => $n['title'],
            'message' => $n['message'],
            'link' => BASE_URL . '/auth/view_notification.php?id=' . $n['id'],
            'time' => $timeLabel,
            'is_read' => (bool)$n['is_read']
        ];
    }

    echo json_encode(['success' => true, 'unread_count' => $unread_count, 'items' => $items]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'unread_count' => 0, 'items' => []]);
}
