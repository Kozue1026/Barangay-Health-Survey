<?php
$pageTitle = "Survey Submitted";
require_once '../config/session.php';
requireResident();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    die("Invalid CSRF token");
}

$survey_id = (string)($_POST['survey_id'] ?? '');
$user_id = (string)$_SESSION['user_id'];

if (!$survey_id) {
    header("Location: surveys.php");
    exit;
}

try {
    // Validate survey
    $survey = fb_get_rec('surveys', $survey_id);
    $valid = $survey && fb_survey_open($survey);
    if (!$valid) {
        throw new Exception("Survey is not available.");
    }

    // Check duplicate
    foreach (fb_all('responses') as $rr) {
        if ((string)($rr['survey_id'] ?? '') === $survey_id && (string)($rr['resident_id'] ?? '') === $user_id) {
            throw new Exception("You have already submitted this survey.");
        }
    }

    // Insert response
    $response_id = fb_next_id('responses');
    fb_set_rec('responses', $response_id, ['survey_id' => $survey_id, 'resident_id' => $user_id, 'status' => 'completed', 'submitted_at' => fb_now(), 'created_at' => fb_now()]);

    // Get questions to process answers
    $questions = [];
    foreach (fb_all('survey_questions') as $qid => $qq) {
        if ((string)($qq['survey_id'] ?? '') === $survey_id) { $qq['id'] = $qid; $questions[] = $qq; }
    }

    if (empty($questions)) {
        throw new Exception("Cannot submit survey because this survey has no questions configured.");
    }
    $choiceMap = fb_all('survey_choices');

    foreach ($questions as $q) {
        $q_id = (string)$q['id'];
        $answer_val = $_POST["answer_$q_id"] ?? null;

        if ($answer_val !== null && $answer_val !== '') {
            $answer_text = null;
            $choice_id = null;
            $rating_value = null;

            if ($q['question_type'] === 'multiple_choice') {
                $choice_id = (string)$answer_val;
                // get choice text
                $cText = $choiceMap[$choice_id]['choice_text'] ?? '';

                $otherCustom = trim($_POST["answer_other_$q_id"] ?? '');
                if (!empty($otherCustom) && (stripos(trim($cText), 'other') === 0)) {
                    $answer_text = "Other: " . $otherCustom;
                } else {
                    $answer_text = $cText;
                }
            } elseif ($q['question_type'] === 'yes_no') {
                $answer_text = $answer_val;
            } elseif ($q['question_type'] === 'rating') {
                $rating_value = (int)$answer_val;
                $answer_text = (string)$answer_val;
            } else {
                $answer_text = trim($answer_val);
            }

            $aid = fb_next_id('response_answers');
            fb_set_rec('response_answers', $aid, ['response_id' => $response_id, 'question_id' => $q_id, 'answer_text' => $answer_text, 'choice_id' => $choice_id, 'rating_value' => $rating_value, 'created_at' => fb_now()]);
        }
    }

    // Log
    fb_log($user_id, 'resident', 'Submit Survey', "Completed survey: {$survey['title']}");

    // Notify admins of new survey submission
    $resident_name = $_SESSION['full_name'] ?? 'A resident';
    notifyAdmins(null, "New Survey Response", $resident_name . " completed the survey: " . $survey['title'], "/admin/survey_results.php", "bi-chat-dots text-success", $survey_id, "survey");

    require_once '../includes/header.php';
    require_once '../includes/sidebar.php';
    require_once '../includes/navbar.php';
    ?>
    <div class="main-content">
        <div class="content-wrapper d-flex justify-content-center align-items-center" style="min-height: 70vh;">
            <div class="text-center bg-white p-5 rounded shadow-sm">
                <i class="bi bi-check-circle-fill text-success mb-4" style="font-size: 5rem;"></i>
                <h2 class="mb-3">Survey Submitted Successfully!</h2>
                <p class="text-muted mb-4">Thank you for participating in <strong><?= htmlspecialchars($survey['title']) ?></strong>.<br>Your feedback is valuable to us.</p>
                
                <div class="d-flex justify-content-center gap-3">
                    <a href="dashboard.php" class="btn btn-primary px-4">Back to Dashboard</a>
                    <a href="surveys.php" class="btn btn-outline-primary px-4">View Other Surveys</a>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once '../includes/footer.php';

} catch (Exception $e) {
    echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href='surveys.php';</script>";
}
