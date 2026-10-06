<?php
require_once '../config/session.php';
requireLogin();
requireAdmin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_report_data':
            $survey_id = (string)($_GET['survey_id'] ?? 0);
            $survey = fb_get_rec('surveys', $survey_id);
            if (!$survey) {
                echo json_encode(['success' => false, 'message' => 'Survey not found', 'data' => null]);
                break;
            }
            $total_respondents = fb_survey_respondent_count($survey_id);
            $activeRes = 0; foreach (fb_all('residents') as $r) { if (($r['status'] ?? '') === 'active') $activeRes++; }
            $summary = ['total_respondents' => $total_respondents, 'completion_rate' => $activeRes > 0 ? round(($total_respondents / $activeRes) * 100, 2) : 0];
            list(, $results) = fb_question_results($survey_id);
            $residents = fb_all('residents');
            $respondents = [];
            foreach (fb_all('responses') as $rid => $r) {
                if ((string)($r['survey_id'] ?? '') !== $survey_id) continue;
                $res = $residents[(string)($r['resident_id'] ?? '')] ?? [];
                $respondents[] = ['id' => $rid, 'resident_number' => $res['resident_number'] ?? '', 'first_name' => $res['first_name'] ?? '', 'last_name' => $res['last_name'] ?? '', 'submitted_at' => $r['submitted_at'] ?? ''];
            }
            usort($respondents, function ($a, $b) { return strcmp($b['submitted_at'] ?? '', $a['submitted_at'] ?? ''); });
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => ['survey' => $survey, 'summary' => $summary, 'results' => $results, 'respondents' => $respondents]]);
            break;

        case 'export_data':
            $survey_id = (string)($_GET['survey_id'] ?? 0);
            list($questions, ) = fb_question_results($survey_id);
            $headers = ['Resident Number', 'Name', 'Submitted At'];
            foreach ($questions as $q) $headers[] = $q['question_text'] ?? '';
            $residents = fb_all('residents'); $answers = fb_all('response_answers'); $choices = fb_all('survey_choices');
            $respList = [];
            foreach (fb_all('responses') as $rid => $r) {
                if ((string)($r['survey_id'] ?? '') !== $survey_id) continue;
                $r['_id'] = (string)$rid; $respList[] = $r;
            }
            usort($respList, function ($a, $b) { return strcmp($a['submitted_at'] ?? '', $b['submitted_at'] ?? ''); });
            $rows = [];
            foreach ($respList as $resp) {
                $res = $residents[(string)($resp['resident_id'] ?? '')] ?? [];
                $row = [$res['resident_number'] ?? '', trim(($res['first_name'] ?? '') . ' ' . ($res['last_name'] ?? '')), $resp['submitted_at'] ?? ''];
                foreach ($questions as $q) {
                    $qid = (string)$q['_id'];
                    $ansVal = '';
                    foreach ($answers as $a) {
                        if ((string)($a['response_id'] ?? '') === (string)$resp['_id'] && (string)($a['question_id'] ?? '') === $qid) {
                            if (($q['question_type'] ?? '') === 'multiple_choice' && !empty($a['choice_id'])) {
                                $ansVal = $choices[(string)$a['choice_id']]['choice_text'] ?? '';
                            } elseif (($q['question_type'] ?? '') === 'rating' && isset($a['rating_value'])) {
                                $ansVal = $a['rating_value'];
                            } else $ansVal = $a['answer_text'] ?? '';
                            break;
                        }
                    }
                    $row[] = $ansVal;
                }
                $rows[] = $row;
            }
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => ['headers' => $headers, 'rows' => $rows]]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action', 'data' => null]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'data' => null]);
}
