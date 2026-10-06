<?php
require_once '../config/session.php';
requireLogin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function fb_survey_counts($sid) {
    $qc = 0; $rc = 0;
    foreach (fb_all('survey_questions') as $q) { if ((string)($q['survey_id'] ?? '') === (string)$sid) $qc++; }
    foreach (fb_all('responses') as $r) { if ((string)($r['survey_id'] ?? '') === (string)$sid) $rc++; }
    return [$qc, $rc];
}

try {
    switch ($action) {
        case 'list':
            requireAdmin();
            $q = trim($_GET['q'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? 10));
            $sort_by = in_array($_GET['sort_by'] ?? '', ['id', 'title', 'opening_date', 'closing_date']) ? $_GET['sort_by'] : 'id';
            $sort_order = strtoupper($_GET['sort_order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $rows = [];
            foreach (fb_all('surveys') as $sid => $s) {
                if ($status !== '' && ($s['status'] ?? '') !== $status) continue;
                $s['id'] = $sid;
                list($qc, $rc) = fb_survey_counts($sid);
                $s['question_count'] = $qc; $s['response_count'] = $rc;
                $rows[$sid] = $s;
            }
            if ($q !== '') $rows = fb_search_rows($rows, $q, ['title', 'description']);
            list($pageRows, $total) = fb_sort_paginate($rows, $sort_by, $sort_order, $page, $limit);
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => ['rows' => array_values($pageRows), 'total' => $total, 'total_pages' => ceil($total / $limit)]]);
            break;

        case 'get':
            $id = (string)($_GET['id'] ?? 0);
            $survey = fb_get_rec('surveys', $id);
            if ($survey) {
                $survey['id'] = $id;
                list($qc, $rc) = fb_survey_counts($id);
                $survey['question_count'] = $qc; $survey['response_count'] = $rc;
            }
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $survey]);
            break;

        case 'create':
            requireAdmin();
            $title = trim(htmlspecialchars($_POST['title'] ?? ''));
            $description = trim(htmlspecialchars($_POST['description'] ?? ''));
            $category = trim(htmlspecialchars($_POST['category'] ?? ''));
            $status = trim(htmlspecialchars($_POST['status'] ?? 'draft'));
            $opening_date = trim($_POST['opening_date'] ?? '');
            $closing_date = trim($_POST['closing_date'] ?? '');
            if ($opening_date === '' || $closing_date === '') {
                echo json_encode(['success' => false, 'message' => 'Opening and closing date/time are required.', 'data' => null]); break;
            }
            if (strtotime($closing_date) < strtotime($opening_date . ' +30 minutes')) {
                echo json_encode(['success' => false, 'message' => 'Closing must be at least 30 minutes after opening.', 'data' => null]); break;
            }
            $created_by = (string)$_SESSION['user_id'];
            $nid = fb_next_id('surveys');
            fb_set_rec('surveys', $nid, ['title' => $title, 'description' => $description, 'category' => $category, 'status' => $status, 'opening_date' => $opening_date ?: null, 'closing_date' => $closing_date ?: null, 'created_by' => $created_by, 'created_at' => fb_now(), 'updated_at' => fb_now()]);
            if ($status === 'active') {
                notifyAllResidents(null, "New Survey Available", $title, "/resident/preview_survey.php?id=" . $nid, "bi-clipboard-check text-primary", $nid, "survey");
            }
            echo json_encode(['success' => true, 'message' => 'Survey created successfully', 'data' => ['id' => $nid]]);
            break;

        case 'update':
            requireAdmin();
            $id = (string)$_POST['id'];
            $u_od = trim($_POST['opening_date'] ?? ''); $u_cd = trim($_POST['closing_date'] ?? '');
            if ($u_od !== '' && $u_cd !== '' && strtotime($u_cd) < strtotime($u_od . ' +30 minutes')) {
                echo json_encode(['success' => false, 'message' => 'Closing must be at least 30 minutes after opening.', 'data' => null]); break;
            }
            fb_update_rec('surveys', $id, [
                'title' => trim(htmlspecialchars($_POST['title'] ?? '')),
                'description' => trim(htmlspecialchars($_POST['description'] ?? '')),
                'category' => trim(htmlspecialchars($_POST['category'] ?? '')),
                'status' => trim(htmlspecialchars($_POST['status'] ?? 'draft')),
                'opening_date' => trim($_POST['opening_date'] ?? '') ?: null,
                'closing_date' => trim($_POST['closing_date'] ?? '') ?: null,
            ]);
            echo json_encode(['success' => true, 'message' => 'Survey updated successfully', 'data' => null]);
            break;

        case 'delete':
            requireAdmin();
            $id = (string)$_POST['id'];
            // cascade: questions+choices, responses+answers, survey notifications
            foreach (fb_all('survey_questions') as $qid => $sq) {
                if ((string)($sq['survey_id'] ?? '') === $id) {
                    foreach (fb_all('survey_choices') as $cid => $c) { if ((string)($c['question_id'] ?? '') === (string)$qid) fb_delete_rec('survey_choices', $cid); }
                    foreach (fb_all('response_answers') as $aid => $a) { if ((string)($a['question_id'] ?? '') === (string)$qid) fb_delete_rec('response_answers', $aid); }
                    fb_delete_rec('survey_questions', $qid);
                }
            }
            foreach (fb_all('responses') as $rid => $r) { if ((string)($r['survey_id'] ?? '') === $id) fb_delete_rec('responses', $rid); }
            foreach (fb_all('notifications') as $nid => $n) { if ((string)($n['related_id'] ?? '') === $id && ($n['related_type'] ?? '') === 'survey') fb_delete_rec('notifications', $nid); }
            fb_delete_rec('surveys', $id);
            echo json_encode(['success' => true, 'message' => 'Survey deleted successfully', 'data' => null]);
            break;

        case 'toggle_status':
            requireAdmin();
            $id = (string)$_POST['id'];
            $s = fb_get_rec('surveys', $id);
            $currStatus = $s['status'] ?? 'inactive';
            $newStatus = ($currStatus === 'active') ? 'inactive' : 'active';
            if ($newStatus === 'active') {
                $qCount = 0;
                foreach (fb_all('survey_questions') as $sq) { if ((string)($sq['survey_id'] ?? '') === $id) $qCount++; }
                $invalidMcqs = [];
                foreach (fb_all('survey_questions') as $qid => $sq) {
                    if ((string)($sq['survey_id'] ?? '') === $id && ($sq['question_type'] ?? '') === 'multiple_choice') {
                        $cc = 0;
                        foreach (fb_all('survey_choices') as $c) { if ((string)($c['question_id'] ?? '') === (string)$qid) $cc++; }
                        if ($cc < 2) $invalidMcqs[] = $qid;
                    }
                }
                if ($qCount === 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot activate survey with 0 questions. Please add questions first.', 'data' => null]); break;
                }
                if (!empty($invalidMcqs)) {
                    echo json_encode(['success' => false, 'message' => 'Cannot activate survey: Multiple Choice questions must have at least 2 choices.', 'data' => null]); break;
                }
            }
            fb_update_rec('surveys', $id, ['status' => $newStatus]);
            echo json_encode(['success' => true, 'message' => 'Status updated to ' . $newStatus, 'data' => ['status' => $newStatus]]);
            break;

        case 'get_active':
            $surveys = [];
            foreach (fb_all('surveys') as $sid => $s) {
                if (!fb_survey_open($s)) continue;
                $s['id'] = $sid;
                $qc = 0; foreach (fb_all('survey_questions') as $sq) { if ((string)($sq['survey_id'] ?? '') === (string)$sid) $qc++; }
                $s['question_count'] = $qc;
                $surveys[] = $s;
            }
            usort($surveys, function ($a, $b) { return strcmp($b['id'], $a['id']); });
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $surveys]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action', 'data' => null]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'data' => null]);
}
