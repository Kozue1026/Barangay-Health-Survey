<?php
$pageTitle = "Manage Choices";
require_once '../config/session.php';
requireAdmin();

if (!isset($_GET['question_id']) || empty($_GET['question_id'])) {
    header("Location: manage_surveys.php");
    exit;
}

$question_id = (string)$_GET['question_id'];

// Fetch question details and parent survey
$question = fb_get_rec('survey_questions', $question_id);
if ($question) {
    $parentSurvey = fb_get_rec('surveys', (string)($question['survey_id'] ?? ''));
    $question['survey_title'] = $parentSurvey['title'] ?? '';
    $question['id'] = $question_id;
}

if (!$question || ($question['question_type'] ?? '') !== 'multiple_choice') {
    header("Location: manage_surveys.php");
    exit;
}

$message = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = $_SESSION['user_id'];
        
        if ($action === 'create' || $action === 'edit') {
            $choice_text = trim($_POST['choice_text']);
            $choice_order = (int)$_POST['choice_order'];
            
            if (empty($choice_text)) {
                $error = "Choice text is required.";
            } elseif (preg_match('/^(.)\1{3,}$/', $choice_text)) {
                $error = "Invalid choice text. Test data or repeating characters (e.g. '11111') are not allowed.";
            } else {
                if ($action === 'create') {
                    try {
                        $new_id = fb_next_id('survey_choices');
                        fb_set_rec('survey_choices', $new_id, [
                            'question_id' => $question_id, 'choice_text' => $choice_text,
                            'choice_order' => $choice_order, 'created_at' => fb_now(),
                        ]);
                        $message = "Choice added successfully.";
                        fb_log($user_id, 'admin', 'create_choice', "Added choice to question ID: $question_id");
                        fb_bump_sync('surveys');
                    } catch (Exception $e) {
                        $error = "Error adding choice.";
                    }
                } elseif ($action === 'edit') {
                    $choice_id = (string)$_POST['choice_id'];
                    try {
                        $existing = fb_get_rec('survey_choices', $choice_id);
                        if ($existing && (string)($existing['question_id'] ?? '') === $question_id) {
                            fb_update_rec('survey_choices', $choice_id, ['choice_text' => $choice_text, 'choice_order' => $choice_order]);
                            $message = "Choice updated successfully.";
                            fb_log($user_id, 'admin', 'update_choice', "Updated choice ID: $choice_id");
                            fb_bump_sync('surveys');
                        } else {
                            $error = "Error updating choice.";
                        }
                    } catch (Exception $e) {
                        $error = "Error updating choice.";
                    }
                }
            }
        } elseif ($action === 'delete') {
            $choice_id = (string)$_POST['choice_id'];

            // Check current choice count
            $currentChoiceCount = 0;
            foreach (fb_all('survey_choices') as $c) {
                if ((string)($c['question_id'] ?? '') === $question_id) $currentChoiceCount++;
            }

            if ($currentChoiceCount <= 2) {
                $error = "Cannot delete choice. Multiple Choice questions must have at least 2 choices. Please add another choice before deleting this one.";
            } else {
                $choice = fb_get_rec('survey_choices', $choice_id);
                
                if ($choice && (string)($choice['question_id'] ?? '') === $question_id) {
                    try {
                        fb_delete_rec('survey_choices', $choice_id);
                        $message = "Choice deleted successfully.";
                        fb_log($user_id, 'admin', 'delete_choice', "Deleted choice: {$choice['choice_text']}");
                        fb_bump_sync('surveys');
                    } catch (Exception $e) {
                        $error = "Error deleting choice.";
                    }
                }
            }
        }
    }
}

// Fetch all choices for this question
$choices = [];
foreach (fb_all('survey_choices') as $cid => $c) {
    if ((string)($c['question_id'] ?? '') !== $question_id) continue;
    $c['id'] = (string)$cid;
    $choices[] = $c;
}
usort($choices, function ($a, $b) { return ((int)($a['choice_order'] ?? 0)) - ((int)($b['choice_order'] ?? 0)); });

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';

// Helper to truncate long question text for breadcrumb
$short_q = strlen($question['question_text']) > 30 ? substr($question['question_text'], 0, 27) . '...' : $question['question_text'];
?>

<div class="main-content">
    <div class="content-wrapper">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="manage_surveys.php">Surveys</a></li>
                <li class="breadcrumb-item"><a href="manage_questions.php?survey_id=<?= $question['survey_id'] ?>"><?= htmlspecialchars($question['survey_title']) ?></a></li>
                <li class="breadcrumb-item"><a href="manage_questions.php?survey_id=<?= $question['survey_id'] ?>">Questions</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($short_q) ?> > Choices</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Manage Choices</h2>
            <div class="d-flex gap-2">
                <button class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#choiceModal" onclick="openAddModal()">
                    <i class="bi bi-plus-circle"></i>
                    <span>Add New Choice</span>
                </button>
                <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#choiceModal" onclick="openAddOtherModal()">
                    <i class="bi bi-plus-circle"></i>
                    <span>Add "Other"</span>
                </button>
            </div>
        </div>

        <div class="mb-4">
            <strong>Question:</strong> <?= htmlspecialchars($question['question_text']) ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (count($choices) <= 2): ?>
            <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-3">
                <i class="bi bi-info-circle-fill fs-4 me-3 text-info"></i>
                <div>
                    <strong>Minimum 2 choices required:</strong> Multiple Choice questions must have at least 2 choices so respondents have options to choose from. To delete or replace an existing choice, please add a new choice first.
                </div>
            </div>
        <?php endif; ?>

        <div class="card stat-card table-container">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Choice Text</th>
                                <th>Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($choices as $index => $c): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($c['choice_text']) ?></td>
                                    <td><?= htmlspecialchars($c['choice_order']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($c)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php $cText = htmlspecialchars(addslashes($c['choice_text']), ENT_QUOTES); ?>
                                        <?php if (count($choices) <= 2): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="alert('Cannot delete this choice. Multiple Choice questions must have at least 2 choices. Please add another choice first.');" title="At least 2 choices required">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete choice &quot;<?= $cText ?>&quot;?');">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="choice_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($choices)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">No choices found for this question.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Choice Modal -->
<div class="modal fade" id="choiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Choice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" id="modalAction" value="create">
                    <input type="hidden" name="choice_id" id="choice_id">

                    <div class="mb-3">
                        <label class="form-label">Choice Text <span class="text-danger">*</span></label>
                        <input type="text" name="choice_text" id="choice_text" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Order <span class="text-danger">*</span></label>
                        <input type="number" name="choice_order" id="choice_order" class="form-control" value="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Choice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Choice';
    document.getElementById('modalAction').value = 'create';
    document.getElementById('choice_id').value = '';
    document.getElementById('choice_text').value = '';
    document.getElementById('choice_order').value = '<?= count($choices) + 1 ?>';
}

function openAddOtherModal() {
    document.getElementById('modalTitle').textContent = 'Add "Other" Choice (Short Answer)';
    document.getElementById('modalAction').value = 'create';
    document.getElementById('choice_id').value = '';
    document.getElementById('choice_text').value = 'Other (Please specify)';
    document.getElementById('choice_order').value = '<?= count($choices) + 1 ?>';
}

function openEditModal(choice) {
    document.getElementById('modalTitle').textContent = 'Edit Choice';
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('choice_id').value = choice.id;
    document.getElementById('choice_text').value = choice.choice_text;
    document.getElementById('choice_order').value = choice.choice_order;
    
    new bootstrap.Modal(document.getElementById('choiceModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
