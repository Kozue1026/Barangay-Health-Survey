<?php
$pageTitle = "Manage Surveys";
require_once '../config/session.php';
requireAdmin();

$success_msg = '';
$error_msg = '';
$current_admin_id = $_SESSION['user_id'];

// Helper: count questions of a survey + find MCQ questions with <2 choices
function survey_activation_check($survey_id) {
    $sid = (string)$survey_id;
    $qCount = 0;
    $choiceCounts = [];
    foreach (fb_all('survey_questions') as $qid => $q) {
        if ((string)($q['survey_id'] ?? '') !== $sid) continue;
        $qCount++;
        if (($q['question_type'] ?? '') === 'multiple_choice') $choiceCounts[(string)$qid] = 0;
    }
    if (!empty($choiceCounts)) {
        foreach (fb_all('survey_choices') as $c) {
            $qid = (string)($c['question_id'] ?? '');
            if (isset($choiceCounts[$qid])) $choiceCounts[$qid]++;
        }
    }
    $invalid = [];
    foreach ($choiceCounts as $qid => $cnt) { if ($cnt < 2) $invalid[] = $qid; }
    return [$qCount, $invalid];
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Invalid request token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'edit') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $status = trim($_POST['status'] ?? 'active');
            $opening_date = trim($_POST['opening_date'] ?? '');
            $closing_date = trim($_POST['closing_date'] ?? '');
            
            if (empty($title) || empty($category) || empty($opening_date) || empty($closing_date)) {
                $error_msg = "Please fill in all required fields.";
            } elseif (strtotime($closing_date) < strtotime($opening_date . ' +30 minutes')) {
                $error_msg = "Closing date/time must be at least 30 minutes after opening date/time.";
            } elseif ($action === 'create' && strtotime($opening_date) < time() - 60) {
                $error_msg = "Opening date/time cannot be in the past.";
            } else {
                if ($action === 'create') {
                    try {
                        $new_survey_id = fb_next_id('surveys');
                        fb_set_rec('surveys', $new_survey_id, [
                            'title' => $title, 'description' => $description, 'category' => $category,
                            'status' => $status, 'opening_date' => $opening_date, 'closing_date' => $closing_date,
                            'created_by' => $current_admin_id, 'created_at' => fb_now(), 'updated_at' => fb_now(),
                        ]);
                    } catch (Exception $e) {
                        $error_msg = "Failed to create survey. Please try again.";
                        $new_survey_id = null;
                    }

                    if (!empty($new_survey_id)) {
                    if ($status === 'active') {
                        notifyAllResidents(null, "New Survey Available", $title, "/resident/preview_survey.php?id=" . $new_survey_id, "bi-clipboard-check text-primary", $new_survey_id, "survey");
                    }

                    fb_log($current_admin_id, 'admin', 'create_survey', "Created survey: $title");
                    fb_bump_sync('surveys');
                    
                    $_SESSION['flash_success'] = "Survey created successfully! Add your first question below to complete your survey setup.";
                    header("Location: manage_questions.php?survey_id=" . $new_survey_id . "&auto_add=1");
                    exit;
                    }
                } elseif ($action === 'edit') {
                    $survey_id = (string)$_POST['survey_id'];
                    
                    if ($status === 'active') {
                        list($qCount, $invalidMcqs) = survey_activation_check($survey_id);

                        if ($qCount === 0) {
                            $error_msg = "Cannot set survey to active because it has 0 questions. Please add at least 1 question first.";
                        } elseif (!empty($invalidMcqs)) {
                            $error_msg = "Cannot set survey to active because one or more Multiple Choice questions have fewer than 2 choices. Please add at least 2 choices for all Multiple Choice questions.";
                        }
                    }

                    if (empty($error_msg)) {
                        try {
                            fb_update_rec('surveys', $survey_id, [
                                'title' => $title, 'description' => $description, 'category' => $category,
                                'status' => $status, 'opening_date' => $opening_date,
                                'closing_date' => $closing_date, 'updated_at' => fb_now(),
                            ]);
                            fb_log($current_admin_id, 'admin', 'update_survey', "Updated survey: $title");
                            fb_bump_sync('surveys');
                            $success_msg = "Survey updated successfully.";
                        } catch (Exception $e) {
                            $error_msg = "Failed to update survey. Please try again.";
                        }
                    }
                }
            }
        } elseif ($action === 'delete') { // Soft Delete / Archive
            $survey_id = (string)$_POST['survey_id'];
            
            $survey = fb_get_rec('surveys', $survey_id);
            
            if ($survey) {
                fb_update_rec('surveys', $survey_id, ['status' => 'archived', 'updated_at' => fb_now()]);
                
                fb_log($current_admin_id, 'admin', 'archive_survey', "Archived survey: " . $survey['title']);
                fb_bump_sync('surveys');
                
                $success_msg = "Survey \"" . htmlspecialchars($survey['title']) . "\" archived successfully. All questions and response records are preserved.";
            }
        } elseif ($action === 'permanent_delete') { // Permanent Delete from Archive
            $survey_id = (string)$_POST['survey_id'];
            
            $survey = fb_get_rec('surveys', $survey_id);
            
            if ($survey) {
                try {
                    // Get all questions of this survey
                    $question_ids = [];
                    foreach (fb_all('survey_questions') as $qid => $q) {
                        if ((string)($q['survey_id'] ?? '') === $survey_id) $question_ids[] = (string)$qid;
                    }

                    // Delete survey choices + response answers for those questions
                    foreach (fb_all('survey_choices') as $cid => $c) {
                        if (in_array((string)($c['question_id'] ?? ''), $question_ids, true)) fb_delete_rec('survey_choices', $cid);
                    }
                    foreach (fb_all('response_answers') as $aid => $a) {
                        if (in_array((string)($a['question_id'] ?? ''), $question_ids, true)) fb_delete_rec('response_answers', $aid);
                    }

                    // Delete responses
                    foreach (fb_all('responses') as $rid => $r) {
                        if ((string)($r['survey_id'] ?? '') === $survey_id) fb_delete_rec('responses', $rid);
                    }
                    // Delete survey questions
                    foreach ($question_ids as $qid) fb_delete_rec('survey_questions', $qid);
                    // Delete notifications related to this survey
                    foreach (fb_all('notifications') as $nid => $n) {
                        if ((string)($n['related_id'] ?? '') === $survey_id && ($n['related_type'] ?? '') === 'survey') fb_delete_rec('notifications', $nid);
                    }
                    // Delete the survey itself
                    fb_delete_rec('surveys', $survey_id);

                    fb_log($current_admin_id, 'admin', 'permanent_delete_survey', "Permanently deleted survey: " . $survey['title']);
                    fb_bump_sync('surveys');

                    $success_msg = "Survey \"" . htmlspecialchars($survey['title']) . "\" has been permanently deleted.";
                } catch (Exception $e) {
                    $error_msg = "Failed to permanently delete survey. Please try again.";
                }
            }
        } elseif ($action === 'restore') { // Restore from Archive
            $survey_id = (string)$_POST['survey_id'];
            
            $survey = fb_get_rec('surveys', $survey_id);
            
            if ($survey) {
                fb_update_rec('surveys', $survey_id, ['status' => 'active', 'updated_at' => fb_now()]);
                
                fb_log($current_admin_id, 'admin', 'restore_survey', "Restored survey: " . $survey['title']);
                fb_bump_sync('surveys');
                
                $success_msg = "Survey \"" . htmlspecialchars($survey['title']) . "\" restored to active status successfully.";
            }
        } elseif ($action === 'toggle_status') {
            $survey_id = (string)$_POST['survey_id'];
            $survey = fb_get_rec('surveys', $survey_id);
            
            if ($survey) {
                $new_status = (($survey['status'] ?? '') === 'active') ? 'inactive' : 'active';
                
                // Check question count and MCQ choices count before activating
                list($qCount, $invalidMcqs) = survey_activation_check($survey_id);

                if ($new_status === 'active' && $qCount === 0) {
                    $error_msg = "Cannot activate survey \"" . htmlspecialchars($survey['title']) . "\" because it has 0 questions. Please add at least 1 question before activating.";
                } elseif ($new_status === 'active' && !empty($invalidMcqs)) {
                    $error_msg = "Cannot activate survey \"" . htmlspecialchars($survey['title']) . "\" because one or more Multiple Choice questions have fewer than 2 choices. Please add at least 2 choices for all Multiple Choice questions.";
                } else {
                    fb_update_rec('surveys', $survey_id, ['status' => $new_status, 'updated_at' => fb_now()]);
                    
                    fb_log($current_admin_id, 'admin', 'toggle_survey_status', "Toggled status to $new_status for survey: " . $survey['title']);
                    fb_bump_sync('surveys');
                    
                    $success_msg = "Survey status updated to " . ucfirst($new_status) . ".";
                }
            }
        }
    }
}

if (isset($_SESSION['flash_success'])) {
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Stats Calculation
$allSurveysFb = fb_all('surveys');
$totalSurveys = 0; $activeSurveys = 0; $archivedSurveys = 0;
foreach ($allSurveysFb as $s) {
    $st = $s['status'] ?? '';
    if ($st === 'archived') $archivedSurveys++;
    else { $totalSurveys++; if ($st === 'active') $activeSurveys++; }
}
$inactiveSurveys = $totalSurveys - $activeSurveys;

$totalQuestions = count(fb_all('survey_questions'));
$totalResponses = count(fb_all('responses'));

// Search, Filter and Pagination
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter in PHP + two-step counts (no JOINs)
$allQuestionsFb = fb_all('survey_questions');
$allResponsesFb = fb_all('responses');
$qCountBySurvey = []; $rCountBySurvey = [];
foreach ($allQuestionsFb as $q) { $sid = (string)($q['survey_id'] ?? ''); $qCountBySurvey[$sid] = ($qCountBySurvey[$sid] ?? 0) + 1; }
foreach ($allResponsesFb as $r) { $sid = (string)($r['survey_id'] ?? ''); $rCountBySurvey[$sid] = ($rCountBySurvey[$sid] ?? 0) + 1; }

$filtered = [];
foreach ($allSurveysFb as $sid => $s) {
    $st = $s['status'] ?? '';
    if ($filter_status === 'archived') { if ($st !== 'archived') continue; }
    elseif ($filter_status !== 'all') { if ($st !== $filter_status) continue; }
    else { if ($st === 'archived') continue; }
    if ($search !== '') {
        $hay = strtolower(($s['title'] ?? '') . ' ' . ($s['description'] ?? '') . ' ' . ($s['category'] ?? ''));
        if (stripos($hay, strtolower($search)) === false) continue;
    }
    $s['id'] = (string)$sid;
    $s['question_count'] = $qCountBySurvey[(string)$sid] ?? 0;
    $s['response_count'] = $rCountBySurvey[(string)$sid] ?? 0;
    $filtered[] = $s;
}
usort($filtered, function ($a, $b) { return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')); });

$total_records = count($filtered);
$total_pages = max(1, (int)ceil($total_records / $limit));
$surveys = array_slice($filtered, $offset, $limit);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-wrapper p-4">
        
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1 fw-bold">Manage Surveys</h2>
                <p class="text-muted mb-0">Create, customize questions, and monitor community health surveys</p>
            </div>
            <button class="btn btn-primary px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#surveyModal" onclick="openCreateModal()">
                <i class="bi bi-plus-circle me-1"></i> Create New Survey
            </button>
        </div>

        <!-- Quick Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm bg-primary text-white card-hover">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-uppercase opacity-75 fw-bold">Total Surveys</small>
                            <h4 class="mb-0 fw-bold"><?= $totalSurveys ?> Total</h4>
                            <small class="opacity-90">(<?= $activeSurveys ?> Active, <?= $inactiveSurveys ?> Inactive)</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center ms-3" style="width: 52px; height: 52px; flex-shrink: 0; background-color: rgba(255,255,255,0.25);">
                            <i class="bi bi-clipboard2-data-fill" style="font-size: 1.6rem; color: #fff;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm bg-info text-dark card-hover">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-uppercase opacity-75 fw-bold text-dark">Survey Questions</small>
                            <h4 class="mb-0 fw-bold text-dark"><?= $totalQuestions ?> Total Questions</h4>
                            <small class="opacity-90 text-dark">Configured across surveys</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center ms-3" style="width: 52px; height: 52px; flex-shrink: 0; background-color: rgba(0,0,0,0.12);">
                            <i class="bi bi-question-circle-fill" style="font-size: 1.6rem; color: #164e63;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm bg-success text-white card-hover">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-uppercase opacity-75 fw-bold">Resident Responses</small>
                            <h4 class="mb-0 fw-bold"><?= $totalResponses ?> Responses</h4>
                            <small class="opacity-90">Preserved in analytics database</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center ms-3" style="width: 52px; height: 52px; flex-shrink: 0; background-color: rgba(255,255,255,0.25);">
                            <i class="bi bi-chat-dots-fill" style="font-size: 1.6rem; color: #fff;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-7">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" id="search_input" class="form-control" placeholder="Search by title, description, category... (Live filtering as you type)" value="<?= htmlspecialchars($search) ?>" onkeyup="realtimeSearchSurveys(this.value)" oninput="realtimeSearchSurveys(this.value)">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>All Statuses (Active &amp; Inactive)</option>
                            <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $filter_status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="archived" <?= $filter_status == 'archived' ? 'selected' : '' ?>>Archived (Deleted Surveys)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Apply Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Surveys Table Card -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="surveys_table">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">#</th>
                                <th>Title &amp; Category</th>
                                <th>Timeline</th>
                                <th>Questions &amp; Responses</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($surveys)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-clipboard-x fs-3 d-block mb-2 text-muted"></i>
                                        No surveys found matching criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($surveys as $i => $survey): ?>
                                    <?php $survTitle = htmlspecialchars($survey['title']); ?>
                                    <tr>
                                        <td class="ps-4"><?= $offset + $i + 1 ?></td>
                                        <td>
                                            <strong class="fs-6 d-block mb-1"><?= $survTitle ?></strong>
                                            <span class="badge bg-secondary-subtle text-dark border">
                                                <i class="bi bi-tag-fill me-1 text-primary"></i><?= htmlspecialchars($survey['category']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="d-block text-success fw-medium"><i class="bi bi-calendar-check me-1"></i>Opens: <?= htmlspecialchars(fb_fmt_dt($survey['opening_date'] ?? '')) ?></small>
                                            <small class="d-block text-danger fw-medium"><i class="bi bi-calendar-x me-1"></i>Closes: <?= htmlspecialchars(fb_fmt_dt($survey['closing_date'] ?? '')) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($survey['question_count'] == 0): ?>
                                                <a href="manage_questions.php?survey_id=<?= $survey['id'] ?>&auto_add=1" class="badge bg-warning text-dark text-decoration-none p-2 me-1" title="Add Questions to Complete Setup">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>0 Questions (Needs Questions)
                                                </a>
                                            <?php else: ?>
                                                <a href="manage_questions.php?survey_id=<?= $survey['id'] ?>" class="badge bg-info text-dark text-decoration-none p-2 me-1" title="Manage Questions">
                                                    <i class="bi bi-question-circle me-1"></i><?= $survey['question_count'] ?> Questions
                                                </a>
                                            <?php endif; ?>
                                            <a href="survey_results.php?survey_id=<?= $survey['id'] ?>" class="badge bg-primary text-decoration-none p-2" title="View Results">
                                                <i class="bi bi-chat-dots me-1"></i><?= $survey['response_count'] ?> Responses
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($survey['status'] == 'active' && $survey['question_count'] == 0): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>Draft (Needs Questions)</span>
                                            <?php elseif ($survey['status'] == 'active'): ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active (Ready for Residents)</span>
                                            <?php elseif ($survey['status'] == 'inactive'): ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><i class="bi bi-archive me-1"></i>Archived</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4" style="min-width: 210px;">
                                            <div class="d-flex flex-nowrap justify-content-end align-items-center gap-1" style="margin-left: auto;">
                                                <!-- Manage Questions (icon-only, badge in Questions column links here too) -->
                                                <a href="manage_questions.php?survey_id=<?= $survey['id'] ?>" class="btn btn-sm btn-outline-info" title="Manage Questions (Add / Edit Questions)">
                                                    <i class="bi bi-list-check"></i>
                                                </a>

                                                <!-- View Results Button -->
                                                <a href="survey_results.php?survey_id=<?= $survey['id'] ?>" class="btn btn-sm btn-outline-success" title="View Analytics &amp; Results">
                                                    <i class="bi bi-bar-chart-line"></i>
                                                </a>

                                                <!-- Edit Survey Modal Button -->
                                                <button type="button" class="btn btn-sm btn-outline-primary" title="Edit Survey Details" onclick='openEditModal(<?= json_encode($survey) ?>)'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                
                                                <?php if ($survey['status'] !== 'archived'): ?>
                                                    <!-- Toggle Status with System Modal -->
                                                    <form method="POST" id="toggleSurveyForm_<?= $survey['id'] ?>" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="survey_id" value="<?= $survey['id'] ?>">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Toggle Status" onclick="confirmToggleSurveyStatus(<?= $survey['id'] ?>, '<?= addslashes($survTitle) ?>', '<?= $survey['status'] ?>', <?= $survey['question_count'] ?>)">
                                                            <i class="bi bi-arrow-repeat"></i>
                                                        </button>
                                                    </form>

                                                    <!-- Archive Survey with System Modal -->
                                                    <form method="POST" id="archiveSurveyForm_<?= $survey['id'] ?>" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="survey_id" value="<?= $survey['id'] ?>">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Archive Survey" onclick="confirmArchiveSurvey(<?= $survey['id'] ?>, '<?= addslashes($survTitle) ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <!-- Restore Survey Button -->
                                                    <form method="POST" id="restoreSurveyForm_<?= $survey['id'] ?>" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="restore">
                                                        <input type="hidden" name="survey_id" value="<?= $survey['id'] ?>">
                                                        <button type="button" class="btn btn-sm btn-outline-success" title="Restore Survey to Active" onclick="confirmRestoreSurvey(<?= $survey['id'] ?>, '<?= addslashes($survTitle) ?>')">
                                                            <i class="bi bi-arrow-counterclockwise"></i>
                                                        </button>
                                                    </form>

                                                    <!-- Permanently Delete Survey Button -->
                                                    <form method="POST" id="permDeleteSurveyForm_<?= $survey['id'] ?>" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="permanent_delete">
                                                        <input type="hidden" name="survey_id" value="<?= $survey['id'] ?>">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Permanently Delete Survey" onclick="confirmPermanentDeleteSurvey(<?= $survey['id'] ?>, '<?= addslashes($survTitle) ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white pt-3 pb-0">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-end">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>">Previous</a>
                        </li>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Create / Edit Survey -->
<div class="modal fade" id="surveyModal" tabindex="-1" aria-labelledby="surveyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="surveyModalLabel">Create New Survey</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="surveyForm">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" id="formAction" value="create">
        <input type="hidden" name="survey_id" id="survey_id" value="">
        
        <div class="modal-body">
            <div id="surveyStepBanner" class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-3">
                <i class="bi bi-info-circle-fill fs-4 me-3 text-info"></i>
                <div>
                    <strong>Step 1 of 2: Basic Information</strong><br>
                    Submitting this form saves your survey details and automatically forwards you to <strong>Step 2 (Question Builder)</strong> to add questions and choices.
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label fw-bold">Survey Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="title" id="title" required placeholder="e.g. Maternal Health &amp; Child Immunization Survey 2026">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">Description</label>
                    <textarea class="form-control" name="description" id="description" rows="3" placeholder="Explain the purpose of this survey for barangay residents..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                    <select class="form-select" name="category" id="category" required>
                        <option value="">Select Category...</option>
                        <option value="Health">Health</option>
                        <option value="Vaccination">Vaccination</option>
                        <option value="Nutrition">Nutrition</option>
                        <option value="Sanitation">Sanitation</option>
                        <option value="General">General</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" id="status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-12">
                    <div class="p-3 rounded border-start border-4 border-success bg-light mb-2">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-success"><i class="bi bi-door-open me-1"></i>Opens</span>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Opening presets">
                                <button type="button" class="btn btn-outline-success" onclick="presetOpening('now')">Now</button>
                                <button type="button" class="btn btn-outline-success" onclick="presetOpening('plus1h')">+1h</button>
                                <button type="button" class="btn btn-outline-success" onclick="presetOpening('tomorrow8')">Tomorrow 8am</button>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-7">
                                <label class="form-label small fw-bold mb-1">Date</label>
                                <input type="date" class="form-control form-control-lg" id="opening_date_d" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-bold mb-1">Time</label>
                                <input type="time" class="form-control form-control-lg" id="opening_date_t" required>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 rounded border-start border-4 border-danger bg-light">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-danger"><i class="bi bi-door-closed me-1"></i>Closes</span>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Closing presets">
                                <button type="button" class="btn btn-outline-danger" onclick="presetClosing('plus1h')">+1h</button>
                                <button type="button" class="btn btn-outline-danger" onclick="presetClosing('plus1d')">+1 day</button>
                                <button type="button" class="btn btn-outline-danger" onclick="presetClosing('plus1w')">+1 week</button>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-7">
                                <label class="form-label small fw-bold mb-1">Date</label>
                                <input type="date" class="form-control form-control-lg" id="closing_date_d" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-bold mb-1">Time</label>
                                <input type="time" class="form-control form-control-lg" id="closing_date_t" required>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>At least 30 mins gap for opening and closing.</small>
                        <div id="gapMeter" class="fw-bold mt-1 small"></div>
                        <div id="dtError" class="text-danger small mt-1" style="display:none;"></div>
                    </div>
                    <input type="hidden" name="opening_date" id="opening_date">
                    <input type="hidden" name="closing_date" id="closing_date">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary px-4" id="saveBtn">Save Survey &amp; Add Questions</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Real-Time Live Search
function realtimeSearchSurveys(query) {
    const filter = query.toLowerCase().trim();
    const rows = document.querySelectorAll('#surveys_table tbody tr');
    rows.forEach(row => {
        if (row.cells.length < 2) return;
        const text = row.innerText.toLowerCase();
        row.style.display = (filter === '' || text.includes(filter)) ? '' : 'none';
    });
}

// Survey date+time: split Date + Time boxes with presets and live gap meter.
// Hidden #opening_date / #closing_date carry 'Y-m-dTH:i' to the server.
function surveyPad(n) { return String(n).padStart(2, '0'); }
function surveyFmtLocal(d) {
    return d.getFullYear() + '-' + surveyPad(d.getMonth() + 1) + '-' + surveyPad(d.getDate()) + 'T' + surveyPad(d.getHours()) + ':' + surveyPad(d.getMinutes());
}
function surveyNowLocal() { const d = new Date(); d.setSeconds(0, 0); return surveyFmtLocal(d); }
function surveySplitLocal(v) {
    v = (v || '').trim().replace(' ', 'T');
    if (/^\d{4}-\d{2}-\d{2}$/.test(v)) v += 'T08:00';
    return { d: v.slice(0, 10), t: v.slice(11, 16) };
}
function surveyGetOpening() {
    const d = document.getElementById('opening_date_d').value;
    const t = document.getElementById('opening_date_t').value || '08:00';
    return d ? (d + 'T' + t) : '';
}
function surveyGetClosing() {
    const d = document.getElementById('closing_date_d').value;
    const t = document.getElementById('closing_date_t').value || '17:00';
    return d ? (d + 'T' + t) : '';
}
function surveyFmtGap(mins) {
    if (mins < 0) mins = 0;
    const h = Math.floor(mins / 60), m = mins % 60;
    return h > 0 ? (h + 'h ' + surveyPad(m) + 'm') : (m + 'm');
}
function surveySyncHidden() {
    document.getElementById('opening_date').value = surveyGetOpening();
    document.getElementById('closing_date').value = surveyGetClosing();
}
function surveyValidate(showMsg) {
    const o = surveyGetOpening(), c = surveyGetClosing();
    const meter = document.getElementById('gapMeter');
    const err = document.getElementById('dtError');
    const saveBtn = document.getElementById('saveBtn');
    const isCreate = (document.getElementById('formAction').value === 'create');
    let msg = '';
    let gapOk = false;
    if (o && c) {
        const gap = Math.round((new Date(c) - new Date(o)) / 60000);
        if (gap < 30) {
            msg = 'Gap: ' + surveyFmtGap(gap) + ' — need +' + (30 - gap) + ' more mins.';
        } else {
            gapOk = true;
            meter.textContent = 'Gap: ' + surveyFmtGap(gap) + ' ✓ OK';
            meter.className = 'fw-bold mt-1 small text-success';
        }
    }
    if (!msg && isCreate && o && new Date(o) < new Date(surveyNowLocal())) {
        msg = 'Opening cannot be in the past.';
    }
    if (!msg && (!o || !c)) {
        msg = 'Pick both opening and closing date + time.';
    }
    if (msg) {
        meter.textContent = '';
        if (showMsg) {
            err.textContent = msg;
            err.style.display = 'block';
        }
        saveBtn.disabled = true;
        return false;
    }
    err.style.display = 'none';
    saveBtn.disabled = false;
    return gapOk;
}
function surveyBindDt() {
    ['opening_date_d', 'opening_date_t', 'closing_date_d', 'closing_date_t'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el && !el.dataset.bound) {
            el.dataset.bound = '1';
            el.addEventListener('change', function () {
                // Keep closing >= opening + 30min automatically.
                const o = surveyGetOpening();
                let c = surveyGetClosing();
                if (o && c && new Date(c) < new Date(surveyAddMinutes(o, 30))) {
                    const parts = surveyAddMinutes(o, 30).split('T');
                    document.getElementById('closing_date_d').value = parts[0];
                    document.getElementById('closing_date_t').value = parts[1];
                }
                surveySyncHidden();
                surveyValidate(true);
            });
        }
    });
}
function surveyAddMinutes(localStr, mins) {
    const d = new Date(localStr);
    d.setMinutes(d.getMinutes() + mins);
    return surveyFmtLocal(d);
}
function presetOpening(which) {
    const now = new Date(); now.setSeconds(0, 0);
    let d;
    if (which === 'now') d = now;
    else if (which === 'plus1h') { d = new Date(now.getTime() + 3600000); }
    else { d = new Date(now); d.setDate(d.getDate() + 1); d.setHours(8, 0, 0, 0); }
    const s = surveyFmtLocal(d).split('T');
    document.getElementById('opening_date_d').value = s[0];
    document.getElementById('opening_date_t').value = s[1];
    // Nudge closing forward if needed.
    const c = surveyGetClosing();
    const o = surveyGetOpening();
    if (!c || new Date(c) < new Date(surveyAddMinutes(o, 30))) {
        const p = surveyAddMinutes(o, 30).split('T');
        document.getElementById('closing_date_d').value = p[0];
        document.getElementById('closing_date_t').value = p[1];
    }
    surveySyncHidden();
    surveyValidate(true);
}
function presetClosing(which) {
    const o = surveyGetOpening() || surveyNowLocal();
    let ms = 3600000;
    if (which === 'plus1d') ms = 86400000;
    if (which === 'plus1w') ms = 7 * 86400000;
    const d = new Date(new Date(o).getTime() + ms);
    const s = surveyFmtLocal(d).split('T');
    document.getElementById('closing_date_d').value = s[0];
    document.getElementById('closing_date_t').value = s[1];
    surveySyncHidden();
    surveyValidate(true);
}
function applySurveyDateRules() {
    surveyBindDt();
    const o = surveyGetOpening();
    if (!document.getElementById('opening_date_d').value) {
        const now = surveyNowLocal().split('T');
        document.getElementById('opening_date_d').value = now[0];
        document.getElementById('opening_date_t').value = '08:00';
    }
    if (!document.getElementById('closing_date_d').value) {
        const base = surveyGetOpening() || surveyNowLocal();
        const p = surveyAddMinutes(base, 60 * 24).split('T');
        document.getElementById('closing_date_d').value = p[0];
        document.getElementById('closing_date_t').value = '17:00';
    }
    surveySyncHidden();
    surveyValidate(false);
    document.getElementById('saveBtn').disabled = false;
}
document.getElementById('surveyForm').addEventListener('submit', function (e) {
    surveySyncHidden();
    if (!surveyValidate(true)) e.preventDefault();
});

// Modal Handlers
function openCreateModal() {
    document.getElementById('surveyModalLabel').innerText = 'Create New Survey (Step 1 of 2)';
    document.getElementById('formAction').value = 'create';
    document.getElementById('surveyForm').reset();
    applySurveyDateRules();
    if (document.getElementById('surveyStepBanner')) {
        document.getElementById('surveyStepBanner').style.display = 'flex';
    }
    document.getElementById('saveBtn').innerHTML = '<i class="bi bi-arrow-right-circle me-1"></i> Save &amp; Add Questions (Step 2)';
}

function openEditModal(survey) {
    document.getElementById('surveyModalLabel').innerText = 'Edit Survey Details';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('survey_id').value = survey.id;
    if (document.getElementById('surveyStepBanner')) {
        document.getElementById('surveyStepBanner').style.display = 'none';
    }
    
    document.getElementById('title').value = survey.title;
    document.getElementById('description').value = survey.description;
    document.getElementById('category').value = survey.category;
    document.getElementById('status').value = survey.status;
    const so = surveySplitLocal(survey.opening_date);
    const sc = surveySplitLocal(survey.closing_date);
    document.getElementById('opening_date_d').value = so.d;
    document.getElementById('opening_date_t').value = so.t;
    document.getElementById('closing_date_d').value = sc.d;
    document.getElementById('closing_date_t').value = sc.t;
    surveySyncHidden();
    surveyValidate(false);
    document.getElementById('saveBtn').disabled = false;
    
    document.getElementById('saveBtn').innerHTML = 'Update Survey Details';
    
    var myModal = new bootstrap.Modal(document.getElementById('surveyModal'));
    myModal.show();
}

// Confirmation Modals via System App.confirmAction
function confirmToggleSurveyStatus(id, title, currentStatus, questionCount) {
    const newStatus = currentStatus === 'active' ? 'Inactive' : 'Active';
    if (currentStatus !== 'active' && questionCount === 0) {
        App.confirmAction(`Cannot activate survey "${title}" because it has 0 questions. Please add at least 1 question before activating it for residents.`, function() {
            window.location.href = `manage_questions.php?survey_id=${id}&auto_add=1`;
        }, { title: 'Cannot Activate Survey', heading: '0 Questions Configured', confirmText: 'Add Questions Now', confirmClass: 'btn-warning', iconClass: 'bi bi-exclamation-triangle text-warning fs-1' });
    } else {
        App.confirmAction(`Are you sure you want to change status for survey "${title}" to ${newStatus}?`, function() {
            document.getElementById('toggleSurveyForm_' + id).submit();
        }, { title: 'Toggle Survey Status', heading: `Set Status to ${newStatus}?`, confirmText: `Set to ${newStatus}`, confirmClass: 'btn-primary', iconClass: 'bi bi-arrow-repeat text-primary fs-1' });
    }
}

function confirmArchiveSurvey(id, title) {
    App.confirmAction(`Are you sure you want to archive survey "${title}"? The survey will be safely moved to Archives and all questions, choices, and resident responses will be preserved.`, function() {
        document.getElementById('archiveSurveyForm_' + id).submit();
    }, { title: 'Archive Survey', heading: 'Confirm Survey Archival', confirmText: 'Yes, Archive Survey', confirmClass: 'btn-danger', iconClass: 'bi bi-archive text-danger fs-1' });
}

function confirmRestoreSurvey(id, title) {
    App.confirmAction(`Are you sure you want to restore archived survey "${title}" back to active status?`, function() {
        document.getElementById('restoreSurveyForm_' + id).submit();
    }, { title: 'Restore Survey', heading: 'Restore to Active Status', confirmText: 'Yes, Restore Survey', confirmClass: 'btn-success', iconClass: 'bi bi-arrow-counterclockwise text-success fs-1' });
}

function confirmPermanentDeleteSurvey(id, title) {
    App.confirmAction(`Are you sure you want to permanently delete survey "${title}"? This action cannot be undone and will permanently remove all associated questions, choices, responses, and analytics data.`, function() {
        document.getElementById('permDeleteSurveyForm_' + id).submit();
    }, { title: 'Permanently Delete Survey', heading: 'Are you sure you want to permanently delete?', confirmText: 'Yes, Permanently Delete', confirmClass: 'btn-danger', iconClass: 'bi bi-exclamation-triangle text-danger fs-1' });
}
</script>

<?php require_once '../includes/footer.php'; ?>
