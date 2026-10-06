<?php
require_once '../config/session.php';
requireLogin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $question_id = (string)($_GET['question_id'] ?? 0);
            $choices = array_values(array_filter(fb_all('survey_choices'), function ($c) use ($question_id) {
                return (string)($c['question_id'] ?? '') === (string)$question_id;
            }));
            usort($choices, function ($a, $b) { return ((int)($a['choice_order'] ?? 0)) - ((int)($b['choice_order'] ?? 0)); });
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $choices]);
            break;

        case 'get':
            $id = (string)($_GET['id'] ?? 0);
            $choice = fb_get_rec('survey_choices', $id);
            if ($choice) $choice['_id'] = $id;
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $choice]);
            break;

        case 'create':
            requireAdmin();
            $question_id = (string)$_POST['question_id'];
            $choice_text = trim(htmlspecialchars($_POST['choice_text'] ?? ''));
            $choice_order = (int)($_POST['choice_order'] ?? 0);

            if (empty($choice_text) || preg_match('/^(.)\1{3,}$/', $choice_text)) {
                echo json_encode(['success' => false, 'message' => 'Invalid choice text. Test data or repeating characters (e.g. 11111) are not allowed.', 'data' => null]);
                break;
            }

            $newId = fb_next_id('survey_choices');
            fb_set_rec('survey_choices', $newId, [
                'question_id' => $question_id, 'choice_text' => $choice_text,
                'choice_order' => $choice_order, 'created_at' => fb_now(),
            ]);

            echo json_encode(['success' => true, 'message' => 'Choice created successfully', 'data' => ['id' => $newId]]);
            break;

        case 'update':
            requireAdmin();
            $id = (string)$_POST['id'];
            $choice_text = trim(htmlspecialchars($_POST['choice_text'] ?? ''));

            if (empty($choice_text) || preg_match('/^(.)\1{3,}$/', $choice_text)) {
                echo json_encode(['success' => false, 'message' => 'Invalid choice text. Test data or repeating characters (e.g. 11111) are not allowed.', 'data' => null]);
                break;
            }

            fb_update_rec('survey_choices', $id, ['choice_text' => $choice_text]);

            echo json_encode(['success' => true, 'message' => 'Choice updated successfully', 'data' => null]);
            break;

        case 'delete':
            requireAdmin();
            $id = (string)$_POST['id'];
            $choice = fb_get_rec('survey_choices', $id);

            if ($choice) {
                $qid = (string)($choice['question_id'] ?? '');
                $totalChoices = 0;
                foreach (fb_all('survey_choices') as $c) {
                    if ((string)($c['question_id'] ?? '') === $qid) $totalChoices++;
                }
                if ($totalChoices <= 2) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Cannot delete choice. Multiple Choice questions must have at least 2 choices.',
                        'data' => null
                    ]);
                    break;
                }
                fb_delete_rec('survey_choices', $id);
                echo json_encode(['success' => true, 'message' => 'Choice deleted successfully', 'data' => null]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Choice not found', 'data' => null]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action', 'data' => null]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'data' => null]);
}
