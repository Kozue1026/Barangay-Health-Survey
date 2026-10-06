<?php
require_once '../config/session.php';
requireLogin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function fb_is_active_now($s) {
    return fb_survey_open($s);
}

try {
    switch ($action) {
        case 'admin_stats':
            requireAdmin();
            $residents = fb_all('residents');
            $surveys = fb_all('surveys');
            $activeRes = 0; foreach ($residents as $r) { if (($r['status'] ?? '') === 'active') $activeRes++; }
            $activeSurv = 0; foreach ($surveys as $s) { if (($s['status'] ?? '') === 'active') $activeSurv++; }
            $stats = [
                'total_residents' => count($residents),
                'active_residents' => $activeRes,
                'total_surveys' => count($surveys),
                'active_surveys' => $activeSurv,
                'total_questions' => count(fb_all('survey_questions')),
                'total_responses' => count(fb_all('responses')),
            ];
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $stats]);
            break;

        case 'resident_stats':
            $resident_id = (string)$_SESSION['user_id'];
            $active = 0;
            foreach (fb_all('surveys') as $s) { if (fb_is_active_now($s)) $active++; }
            $completed = 0;
            foreach (fb_all('responses') as $r) { if ((string)($r['resident_id'] ?? '') === $resident_id) $completed++; }
            $stats = ['active_surveys' => $active, 'completed_surveys' => $completed, 'pending_surveys' => max(0, $active - $completed)];
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $stats]);
            break;

        case 'chart_data':
            requireAdmin();
            $data = [];
            $surveys = fb_all('surveys');
            $responses = fb_all('responses');
            $residents = fb_all('residents');
            $activeResCount = 0; foreach ($residents as $r) { if (($r['status'] ?? '') === 'active') $activeResCount++; }
            $comp = [];
            $i = 0;
            foreach ($surveys as $sid => $s) {
                if (($s['status'] ?? '') !== 'active' || $i >= 5) continue;
                $rc = 0; foreach ($responses as $r) { if ((string)($r['survey_id'] ?? '') === (string)$sid) $rc++; }
                $comp[] = ['survey_title' => $s['title'] ?? '', 'response_count' => $rc, 'total_residents' => $activeResCount];
                $i++;
            }
            $data['survey_completion'] = $comp;
            // monthly last 12 months
            $cutoff = date('Y-m', strtotime('-12 months'));
            $months = [];
            foreach ($responses as $r) {
                $m = substr($r['submitted_at'] ?? $r['created_at'] ?? '', 0, 7);
                if ($m >= $cutoff && $m !== '') $months[$m] = ($months[$m] ?? 0) + 1;
            }
            ksort($months);
            $mr = []; foreach ($months as $m => $c) $mr[] = ['month' => $m, 'count' => $c];
            $data['monthly_responses'] = $mr;
            $cats = []; foreach ($surveys as $s) { $c = $s['category'] ?? 'Uncategorized'; $cats[$c] = ($cats[$c] ?? 0) + 1; }
            $catArr = []; foreach ($cats as $c => $n) $catArr[] = ['category' => $c, 'count' => $n];
            $data['categories'] = $catArr;
            $dist = ['active' => 0, 'inactive' => 0];
            foreach ($surveys as $s) {
                if (($s['status'] ?? '') === 'active') $dist['active']++;
                else $dist['inactive']++;
            }
            $data['status_distribution'] = $dist;
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $data]);
            break;

        case 'recent_activity':
            requireAdmin();
            $logs = array_values(fb_all('activity_logs'));
            $admins = fb_all('admins'); $residents = fb_all('residents');
            foreach ($logs as &$al) {
                if (($al['user_type'] ?? '') === 'admin') $al['user_name'] = $admins[(string)($al['user_id'] ?? '')]['username'] ?? null;
                else $al['user_name'] = $residents[(string)($al['user_id'] ?? '')]['first_name'] ?? null;
            }
            usort($logs, function ($a, $b) { return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => array_slice($logs, 0, 10)]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action', 'data' => null]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'data' => null]);
}
