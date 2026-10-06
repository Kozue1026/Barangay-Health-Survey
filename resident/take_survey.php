<?php
$pageTitle = "Take Survey";
require_once '../config/session.php';
requireResident();

$survey_id = (string)($_GET['id'] ?? '');
$user_id = (string)$_SESSION['user_id'];

if (!$survey_id) {
    header("Location: surveys.php");
    exit;
}

// Validate survey
$survey = fb_get_rec('surveys', $survey_id);
$survey['id'] = $survey_id;
$valid = $survey && fb_survey_open($survey);
if (!$valid) {
    echo "<script>alert('Survey is not available or has expired.'); window.location.href='surveys.php';</script>";
    exit;
}
$creator = fb_get_rec('admins', (string)($survey['created_by'] ?? ''));
$survey['creator_name'] = $creator['full_name'] ?? null;
$survey['creator_role'] = $creator['role'] ?? null;

// Check if already completed
foreach (fb_all('responses') as $rr) {
    if ((string)($rr['survey_id'] ?? '') === $survey_id && (string)($rr['resident_id'] ?? '') === $user_id) {
        echo "<script>alert('You have already completed this survey.'); window.location.href='surveys.php';</script>";
        exit;
    }
}

// Get questions
$questions = [];
foreach (fb_all('survey_questions') as $qid => $qq) {
    if ((string)($qq['survey_id'] ?? '') !== $survey_id) continue;
    $qq['id'] = $qid;
    $questions[] = $qq;
}
usort($questions, function ($a, $b) { return ((int)($a['question_order'] ?? 0)) - ((int)($b['question_order'] ?? 0)); });

if (empty($questions)) {
    $_SESSION['flash_error'] = "This survey is currently being configured by administrators and has no questions yet. Please check back later.";
    header("Location: surveys.php");
    exit;
}

// Get choices
$choices = [];
foreach (fb_all('survey_choices') as $cid => $cc) {
    $cc['id'] = $cid;
    $choices[(string)($cc['question_id'] ?? '')][] = $cc;
}
foreach ($choices as &$cl) { usort($cl, function ($a, $b) { return ((int)($a['choice_order'] ?? 0)) - ((int)($b['choice_order'] ?? 0)); }); }
unset($cl);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body p-4 bg-primary text-white rounded">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <span class="badge bg-light text-primary"><?= htmlspecialchars($survey['category']) ?></span>
                    <span class="badge bg-light text-primary"><i class="bi bi-person-circle me-1"></i>Created by: <?= htmlspecialchars(!empty($survey['creator_name']) ? ($survey['creator_role'] === 'super_admin' ? 'Admin' : 'Staff') . ' (' . $survey['creator_name'] . ')' : 'System') ?></span>
                </div>
                <h3 class="mb-2"><?= htmlspecialchars($survey['title']) ?></h3>
                <p class="mb-0 opacity-75"><?= htmlspecialchars($survey['description']) ?></p>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form id="surveyForm" action="submit_survey.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="survey_id" value="<?= $survey['id'] ?>">

                    <?php foreach ($questions as $index => $q): ?>
                        <div class="mb-4 pb-3 border-bottom">
                            <h5 class="mb-3">
                                <?= ($index + 1) . '. ' . htmlspecialchars($q['question_text']) ?>
                                <?php if ($q['required']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </h5>

                            <div class="ps-3">
                                <?php if ($q['question_type'] === 'multiple_choice'): ?>
                                    <?php foreach ($choices[$q['id']] ?? [] as $choice): ?>
                                        <?php 
                                            $isOther = (stripos(trim($choice['choice_text']), 'other') === 0);
                                        ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" 
                                                   name="answer_<?= $q['id'] ?>" 
                                                   id="choice_<?= $choice['id'] ?>" 
                                                   value="<?= $choice['id'] ?>"
                                                   onchange="handleChoiceRadioChange(<?= $q['id'] ?>, <?= $choice['id'] ?>, <?= $isOther ? 'true' : 'false' ?>)"
                                                   <?= $q['required'] ? 'required' : '' ?>>
                                            <label class="form-check-label" for="choice_<?= $choice['id'] ?>">
                                                <?= htmlspecialchars($choice['choice_text']) ?>
                                            </label>
                                            <?php if ($isOther): ?>
                                                <div class="mt-2 ms-1 other-input-wrap" id="other_wrap_<?= $choice['id'] ?>" style="display: none;">
                                                    <input type="text" 
                                                           class="form-control form-control-sm" 
                                                           name="answer_other_<?= $q['id'] ?>" 
                                                           id="other_input_<?= $choice['id'] ?>" 
                                                           placeholder="Please specify your answer here..." 
                                                           maxlength="255">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>

                                <?php elseif ($q['question_type'] === 'yes_no'): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="answer_<?= $q['id'] ?>" id="yes_<?= $q['id'] ?>" value="Yes" <?= $q['required'] ? 'required' : '' ?>>
                                        <label class="form-check-label" for="yes_<?= $q['id'] ?>">Yes</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="answer_<?= $q['id'] ?>" id="no_<?= $q['id'] ?>" value="No" <?= $q['required'] ? 'required' : '' ?>>
                                        <label class="form-check-label" for="no_<?= $q['id'] ?>">No</label>
                                    </div>

                                <?php elseif ($q['question_type'] === 'rating'): ?>
                                    <div class="rating-group d-flex gap-2">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <input type="radio" class="btn-check" name="answer_<?= $q['id'] ?>" id="rating_<?= $q['id'] ?>_<?= $i ?>" value="<?= $i ?>" <?= $q['required'] ? 'required' : '' ?>>
                                            <label class="btn btn-outline-primary" for="rating_<?= $q['id'] ?>_<?= $i ?>"><?= $i ?></label>
                                        <?php endfor; ?>
                                    </div>

                                <?php elseif ($q['question_type'] === 'short_answer'): ?>
                                    <textarea class="form-control" name="answer_<?= $q['id'] ?>" rows="3" <?= $q['required'] ? 'required' : '' ?> maxlength="500" onkeyup="document.getElementById('counter_<?= $q['id'] ?>').innerText = this.value.length + '/500'"></textarea>
                                    <small class="text-muted d-block mt-1" id="counter_<?= $q['id'] ?>">0/500</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="surveys.php" class="btn btn-secondary">Cancel</a>
                        <button type="button" class="btn btn-primary px-4" onclick="confirmSubmission()">Submit Survey</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function handleChoiceRadioChange(questionId, choiceId, isOther) {
    const qOtherInputs = document.querySelectorAll(`input[name="answer_other_${questionId}"]`);
    qOtherInputs.forEach(input => {
        const wrap = input.closest('.other-input-wrap');
        if (wrap) wrap.style.display = 'none';
        input.value = '';
    });
    
    if (isOther) {
        const targetWrap = document.getElementById(`other_wrap_${choiceId}`);
        const targetInput = document.getElementById(`other_input_${choiceId}`);
        if (targetWrap && targetInput) {
            targetWrap.style.display = 'block';
            targetInput.focus();
        }
    }
}

function confirmSubmission() {
    const form = document.getElementById('surveyForm');
    if (form.checkValidity()) {
        App.confirmAction(
            'Are you sure you want to submit your responses for this survey? This action cannot be undone.', 
            function() {
                App.showLoading('Submitting your responses...');
                form.submit();
            },
            {
                title: 'Submit Survey',
                heading: 'Confirm Survey Submission',
                confirmText: 'Yes, Submit Survey',
                confirmClass: 'btn-primary',
                iconClass: 'bi bi-send-check text-primary fs-1'
            }
        );
    } else {
        form.reportValidity();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
