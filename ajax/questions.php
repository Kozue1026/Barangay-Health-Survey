<?php
require_once '../config/session.php';
requireLogin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $survey_id = (string)($_GET['survey_id'] ?? 0);
            $questions = [];
            $choices = fb_all('survey_choices');
            foreach (fb_all('survey_questions') as $qid => $q) {
                if ((string)($q['survey_id'] ?? '') !== $survey_id) continue;
                $cc = 0;
                foreach ($choices as $c) { if ((string)($c['question_id'] ?? '') === (string)$qid) $cc++; }
                $q['choice_count'] = $cc;
                $questions[] = $q;
            }
            usort($questions, function ($a, $b) { return ((int)($a['question_order'] ?? 0)) - ((int)($b['question_order'] ?? 0)); });
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $questions]);
            break;

        case 'get':
            $id = (string)($_GET['id'] ?? 0);
            $question = fb_get_rec('survey_questions', $id);
            if ($question) {
                $ch = [];
                foreach (fb_all('survey_choices') as $c) {
                    if ((string)($c['question_id'] ?? '') === $id) $ch[] = $c;
                }
                usort($ch, function ($a, $b) { return ((int)($a['choice_order'] ?? 0)) - ((int)($b['choice_order'] ?? 0)); });
                $question['choices'] = $ch;
            }
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $question]);
            break;

        case 'create':
            requireAdmin();
            $survey_id = (string)($_POST['survey_id'] ?? 0);
            $question_text = trim(htmlspecialchars($_POST['question_text'] ?? ''));
            $question_type = trim(htmlspecialchars($_POST['question_type'] ?? 'multiple_choice'));
            $question_order = (int)($_POST['question_order'] ?? 0);
            $required = isset($_POST['required']) && $_POST['required'] == '1' ? 1 : 0;

            if (empty($question_text)) {
                echo json_encode(['success' => false, 'message' => 'Question text is required', 'data' => null]);
                break;
            }
            $rawChoices = $_POST['choices'] ?? [];
            if (is_string($rawChoices)) $rawChoices = json_decode($rawChoices, true) ?? [];
            $validChoices = [];
            if (is_array($rawChoices)) {
                foreach ($rawChoices as $c) {
                    $cText = is_array($c) ? trim(htmlspecialchars($c['choice_text'] ?? '')) : trim(htmlspecialchars($c));
                    if ($cText !== '') $validChoices[] = is_array($c) ? array_merge($c, ['choice_text' => $cText]) : ['choice_text' => $cText];
                }
            }
            if ($question_type === 'multiple_choice' && count($validChoices) < 2) {
                echo json_encode(['success' => false, 'message' => 'Multiple Choice questions must have at least 2 choices. Please add at least 2 choices.', 'data' => null]);
                break;
            }
            $new_id = fb_next_id('survey_questions');
            fb_set_rec('survey_questions', $new_id, [
                'survey_id' => $survey_id, 'question_text' => $question_text,
                'question_type' => $question_type, 'question_order' => $question_order,
                'required' => $required, 'created_at' => fb_now(),
            ]);
            if ($question_type === 'multiple_choice' && !empty($validChoices)) {
                $order = 1;
                foreach ($validChoices as $c) {
                    $cOrder = isset($c['choice_order']) && $c['choice_order'] !== '' ? (int)$c['choice_order'] : $order;
                    $cid = fb_next_id('survey_choices');
                    fb_set_rec('survey_choices', $cid, ['question_id' => $new_id, 'choice_text' => $c['choice_text'], 'choice_order' => $cOrder, 'created_at' => fb_now()]);
                    $order++;
                }
            }
            echo json_encode(['success' => true, 'message' => 'Question created successfully', 'data' => ['id' => $new_id]]);
            break;

        case 'update':
            requireAdmin();
            $id = (string)($_POST['id'] ?? 0);
            $question_text = trim(htmlspecialchars($_POST['question_text'] ?? ''));
            $question_type = trim(htmlspecialchars($_POST['question_type'] ?? 'multiple_choice'));
            $question_order = (int)($_POST['question_order'] ?? 0);
            $required = isset($_POST['required']) && $_POST['required'] == '1' ? 1 : 0;
            if (empty($question_text)) {
                echo json_encode(['success' => false, 'message' => 'Question text is required', 'data' => null]);
                break;
            }
            $hasChoicesPayload = isset($_POST['choices']);
            $rawChoices = $_POST['choices'] ?? [];
            if (is_string($rawChoices)) $rawChoices = json_decode($rawChoices, true) ?? [];
            $validChoices = [];
            if (is_array($rawChoices)) {
                foreach ($rawChoices as $c) {
                    $cText = is_array($c) ? trim(htmlspecialchars($c['choice_text'] ?? '')) : trim(htmlspecialchars($c));
                    if ($cText !== '') $validChoices[] = is_array($c) ? array_merge($c, ['choice_text' => $cText]) : ['choice_text' => $cText];
                }
            }
            if ($question_type === 'multiple_choice') {
                if ($hasChoicesPayload) {
                    if (count($validChoices) < 2) {
                        echo json_encode(['success' => false, 'message' => 'Multiple Choice questions must have at least 2 choices. Please add at least 2 choices.', 'data' => null]);
                        break;
                    }
                } else {
                    $cnt = 0;
                    foreach (fb_all('survey_choices') as $c) { if ((string)($c['question_id'] ?? '') === $id) $cnt++; }
                    if ($cnt < 2) {
                        echo json_encode(['success' => false, 'message' => 'Multiple Choice questions must have at least 2 choices. Please add at least 2 choices.', 'data' => null]);
                        break;
                    }
                }
            }
            $upd = ['question_text' => $question_text, 'question_type' => $question_type, 'required' => $required];
            if ($question_order > 0) $upd['question_order'] = $question_order;
            fb_update_rec('survey_questions', $id, $upd);
            if ($question_type === 'multiple_choice' && $hasChoicesPayload) {
                $submittedIds = [];
                $order = 1;
                foreach ($validChoices as $c) {
                    $cid = isset($c['id']) ? (string)$c['id'] : '';
                    $cOrder = isset($c['choice_order']) && $c['choice_order'] !== '' ? (int)$c['choice_order'] : $order;
                    if ($cid !== '' && $cid !== '0' && fb_get_rec('survey_choices', $cid)) {
                        fb_update_rec('survey_choices', $cid, ['choice_text' => $c['choice_text'], 'choice_order' => $cOrder]);
                        $submittedIds[] = $cid;
                    } else {
                        $ncid = fb_next_id('survey_choices');
                        fb_set_rec('survey_choices', $ncid, ['question_id' => $id, 'choice_text' => $c['choice_text'], 'choice_order' => $cOrder, 'created_at' => fb_now()]);
                        $submittedIds[] = $ncid;
                    }
                    $order++;
                }
                if (!empty($submittedIds)) {
                    foreach (fb_all('survey_choices') as $ecid => $ec) {
                        if ((string)($ec['question_id'] ?? '') === $id && !in_array((string)$ecid, $submittedIds, true)) {
                            fb_delete_rec('survey_choices', $ecid);
                        }
                    }
                }
            }
            echo json_encode(['success' => true, 'message' => 'Question updated successfully', 'data' => null]);
            break;

        case 'delete':
            requireAdmin();
            $id = (string)$_POST['id'];
            fb_delete_rec('survey_questions', $id);
            // cascade choices
            foreach (fb_all('survey_choices') as $cid => $c) {
                if ((string)($c['question_id'] ?? '') === $id) fb_delete_rec('survey_choices', $cid);
            }
            echo json_encode(['success' => true, 'message' => 'Question deleted successfully', 'data' => null]);
            break;

        case 'reorder':
            requireAdmin();
            $items = $_POST['items'] ?? [];
            if (is_string($items)) $items = json_decode($items, true);
            foreach ((array)$items as $item) {
                fb_update_rec('survey_questions', (string)$item['id'], ['question_order' => (int)$item['order']]);
            }
            echo json_encode(['success' => true, 'message' => 'Questions reordered successfully', 'data' => null]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action', 'data' => null]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'data' => null]);
}
