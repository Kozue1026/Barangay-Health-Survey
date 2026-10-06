<?php
require_once '../config/session.php';
requireLogin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'submit':
            $csrf_token = $_POST['csrf_token'] ?? '';
            if (!validateCSRFToken($csrf_token)) {
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token', 'data' => null]);
                break;
            }
            $survey_id = (string)$_POST['survey_id'];
            $resident_id = (string)$_SESSION['user_id'];
            foreach (fb_all('responses') as $r) {
                if ((string)($r['survey_id'] ?? '') === $survey_id && (string)($r['resident_id'] ?? '') === $resident_id) {
                    echo json_encode(['success' => false, 'message' => 'You have already submitted this survey', 'data' => null]);
                    break 2;
                }
            }
            $response_id = fb_next_id('responses');
            fb_set_rec('responses', $response_id, ['survey_id' => $survey_id, 'resident_id' => $resident_id, 'status' => 'completed', 'submitted_at' => fb_now(), 'created_at' => fb_now()]);
            $answers = $_POST['answers'] ?? [];
            $questions = fb_all('survey_questions');
            foreach ($answers as $q_id => $val) {
                $qType = $questions[(string)$q_id]['question_type'] ?? null;
                if (!$qType) continue;
                if ($qType === 'multiple_choice') {
                    $vals = is_array($val) ? $val : [$val];
                    foreach ($vals as $v) {
                        $aid = fb_next_id('response_answers');
                        fb_set_rec('response_answers', $aid, ['response_id' => $response_id, 'question_id' => (string)$q_id, 'answer_text' => null, 'choice_id' => (string)(int)$v, 'rating_value' => null, 'created_at' => fb_now()]);
                    }
                } elseif ($qType === 'yes_no') {
                    $aid = fb_next_id('response_answers');
                    fb_set_rec('response_answers', $aid, ['response_id' => $response_id, 'question_id' => (string)$q_id, 'answer_text' => trim(htmlspecialchars($val)), 'choice_id' => null, 'rating_value' => null, 'created_at' => fb_now()]);
                } elseif ($qType === 'rating') {
                    $aid = fb_next_id('response_answers');
                    fb_set_rec('response_answers', $aid, ['response_id' => $response_id, 'question_id' => (string)$q_id, 'answer_text' => null, 'choice_id' => null, 'rating_value' => (int)$val, 'created_at' => fb_now()]);
                } elseif ($qType === 'short_answer') {
                    $aid = fb_next_id('response_answers');
                    fb_set_rec('response_answers', $aid, ['response_id' => $response_id, 'question_id' => (string)$q_id, 'answer_text' => trim(htmlspecialchars($val)), 'choice_id' => null, 'rating_value' => null, 'created_at' => fb_now()]);
                }
            }
            fb_log($resident_id, 'resident', 'submit_survey', "Submitted survey ID $survey_id");
            $survey = fb_get_rec('surveys', $survey_id);
            $survey_title = $survey['title'] ?? 'Survey';
            $resident_name = $_SESSION['full_name'] ?? 'A resident';
            notifyAdmins(null, "New Survey Response", $resident_name . " completed the survey: " . $survey_title, "/admin/survey_results.php", "bi-chat-dots text-success", $survey_id, "survey");
            echo json_encode(['success' => true, 'message' => 'Survey submitted successfully', 'data' => ['response_id' => $response_id]]);
            break;

        case 'get_results':
            requireAdmin();
            $survey_id = (string)($_GET['survey_id'] ?? 0);
            $total_respondents = fb_survey_respondent_count($survey_id);
            list(, $results) = fb_question_results($survey_id);
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => ['total_respondents' => $total_respondents, 'results' => $results]]);
            break;

        case 'check_submission':
            $survey_id = (string)($_GET['survey_id'] ?? 0);
            $resident_id = (string)$_SESSION['user_id'];
            $submitted = false;
            foreach (fb_all('responses') as $r) {
                if ((string)($r['survey_id'] ?? '') === $survey_id && (string)($r['resident_id'] ?? '') === $resident_id) { $submitted = true; break; }
            }
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => ['submitted' => $submitted]]);
            break;

        case 'get_respondents':
            requireAdmin();
            $survey_id = (string)($_GET['survey_id'] ?? 0);
            $residents = fb_all('residents');
            $respondents = [];
            foreach (fb_all('responses') as $rid => $r) {
                if ((string)($r['survey_id'] ?? '') !== $survey_id) continue;
                $res = $residents[(string)($r['resident_id'] ?? '')] ?? [];
                if (!$res) continue;
                $respondents[] = ['response_id' => $rid, 'resident_number' => $res['resident_number'] ?? '', 'first_name' => $res['first_name'] ?? '', 'last_name' => $res['last_name'] ?? '', 'submitted_at' => $r['submitted_at'] ?? ''];
            }
            usort($respondents, function ($a, $b) { return strcmp($b['submitted_at'] ?? '', $a['submitted_at'] ?? ''); });
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $respondents]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action', 'data' => null]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'data' => null]);
}
