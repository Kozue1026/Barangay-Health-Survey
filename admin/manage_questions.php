<?php
$pageTitle = "Manage Questions";
require_once '../config/session.php';
requireAdmin();

if (!isset($_GET['survey_id']) || empty($_GET['survey_id'])) {
    header("Location: manage_surveys.php");
    exit;
}

$survey_id = (string)$_GET['survey_id'];

// Fetch survey details
$survey = fb_get_rec('surveys', $survey_id);
if ($survey) $survey['id'] = $survey_id;

if (!$survey) {
    header("Location: manage_surveys.php");
    exit;
}

$message = '';
$error = '';

if (isset($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = $_SESSION['user_id'];
        
        if ($action === 'create' || $action === 'edit') {
            $question_text = trim($_POST['question_text']);
            $question_type = $_POST['question_type'];
            $question_order = (int)$_POST['question_order'];
            $required = isset($_POST['required']) ? 1 : 0;
            
            if (empty($question_text) || empty($question_type)) {
                $error = "Question text and type are required.";
            } else {
                if ($action === 'create') {
                    try {
                        $new_q_id = fb_next_id('survey_questions');
                        fb_set_rec('survey_questions', $new_q_id, [
                            'survey_id' => $survey_id, 'question_text' => $question_text,
                            'question_type' => $question_type, 'question_order' => $question_order,
                            'required' => $required, 'created_at' => fb_now(),
                        ]);
                        $message = "Question added successfully.";
                        fb_log($user_id, 'admin', 'create_question', "Added question: $question_text to survey ID: $survey_id");
                        fb_bump_sync('surveys');
                        
                        if ($question_type === 'multiple_choice') {
                            // Check if choices were submitted
                            $rawChoices = $_POST['choices'] ?? [];
                            if (is_string($rawChoices)) $rawChoices = json_decode($rawChoices, true) ?? [];
                            $validC = [];
                            foreach ($rawChoices as $c) {
                                $txt = is_array($c) ? trim($c['choice_text'] ?? '') : trim($c);
                                if ($txt !== '') $validC[] = $txt;
                            }
                            if (count($validC) >= 2) {
                                $cOrd = 1;
                                foreach ($validC as $txt) {
                                    $ncid = fb_next_id('survey_choices');
                                    fb_set_rec('survey_choices', $ncid, [
                                        'question_id' => $new_q_id, 'choice_text' => $txt,
                                        'choice_order' => $cOrd++, 'created_at' => fb_now(),
                                    ]);
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $error = "Error adding question.";
                    }
                } elseif ($action === 'edit') {
                    $question_id = (string)$_POST['question_id'];
                    try {
                        $existingQ = fb_get_rec('survey_questions', $question_id);
                        if ($existingQ && (string)($existingQ['survey_id'] ?? '') === $survey_id) {
                            fb_update_rec('survey_questions', $question_id, [
                                'question_text' => $question_text, 'question_type' => $question_type,
                                'question_order' => $question_order, 'required' => $required,
                            ]);
                            $message = "Question updated successfully.";
                            fb_log($user_id, 'admin', 'update_question', "Updated question ID: $question_id");
                            fb_bump_sync('surveys');
                        } else {
                            $error = "Error updating question.";
                        }
                    } catch (Exception $e) {
                        $error = "Error updating question.";
                    }
                }
            }
        } elseif ($action === 'delete') {
            $question_id = (string)$_POST['question_id'];
            $question = fb_get_rec('survey_questions', $question_id);
            
            if ($question && (string)($question['survey_id'] ?? '') === $survey_id) {
                try {
                    // Cascade: delete choices of this question
                    foreach (fb_all('survey_choices') as $cid => $c) {
                        if ((string)($c['question_id'] ?? '') === $question_id) fb_delete_rec('survey_choices', $cid);
                    }
                    fb_delete_rec('survey_questions', $question_id);
                    $message = "Question deleted successfully.";
                    fb_log($user_id, 'admin', 'delete_question', "Deleted question: {$question['question_text']}");
                    fb_bump_sync('surveys');
                } catch (Exception $e) {
                    $error = "Error deleting question.";
                }
            }
        }
    }
}

// Fetch all questions for this survey
$allChoices = fb_all('survey_choices');
$questions = [];
foreach (fb_all('survey_questions') as $qid => $q) {
    if ((string)($q['survey_id'] ?? '') !== $survey_id) continue;
    $cc = 0;
    foreach ($allChoices as $c) { if ((string)($c['question_id'] ?? '') === (string)$qid) $cc++; }
    $q['id'] = (string)$qid;
    $q['choices_count'] = $cc;
    $questions[] = $q;
}
usort($questions, function ($a, $b) { return ((int)($a['question_order'] ?? 0)) - ((int)($b['question_order'] ?? 0)); });

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="manage_surveys.php">Surveys</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($survey['title']) ?> > Questions</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Questions</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#questionModal" onclick="openAddModal()">
                <i class="bi bi-plus-circle me-2"></i>Add New Question
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($questions)): ?>
            <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-warning"></i>
                <div>
                    <strong>Survey Needs Questions:</strong> This survey currently has 0 questions. Please add at least 1 question below so residents can participate in this survey.
                </div>
            </div>
        <?php else: ?>
            <?php
            $incompleteMcqs = 0;
            foreach ($questions as $q) {
                if ($q['question_type'] === 'multiple_choice' && (int)$q['choices_count'] < 2) {
                    $incompleteMcqs++;
                }
            }
            ?>
            <?php if ($incompleteMcqs > 0): ?>
                <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4">
                    <i class="bi bi-exclamation-circle-fill fs-3 me-3 text-warning"></i>
                    <div>
                        <strong>Choices Verification Needed:</strong> <?= $incompleteMcqs ?> Multiple Choice question(s) currently have fewer than 2 choices. Please click <strong>Manage Choices</strong> or edit the question to add at least 2 choices before activating this survey.
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card stat-card table-container">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Question Text</th>
                                <th>Type</th>
                                <th>Order</th>
                                <th>Required</th>
                                <th>Choices</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($questions as $index => $q): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($q['question_text']) ?></td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?= htmlspecialchars(str_replace('_', ' ', ucwords($q['question_type'], '_'))) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($q['question_order']) ?></td>
                                    <td>
                                        <?= $q['required'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ($q['question_type'] === 'multiple_choice'): ?>
                                            <?php if ((int)$q['choices_count'] < 2): ?>
                                                <span class="badge bg-danger" title="Multiple Choice questions must have at least 2 choices">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= $q['choices_count'] ?> choices (Needs ≥ 2)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle me-1"></i><?= $q['choices_count'] ?> choices
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($q)) ?>)" title="Edit question">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php $qSnippet = htmlspecialchars(addslashes(mb_strimwidth($q['question_text'], 0, 45, '...')), ENT_QUOTES); ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete question &quot;<?= $qSnippet ?>&quot;? This will also delete any associated choices and responses.');">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete question">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        <?php if ($q['question_type'] === 'multiple_choice'): ?>
                                            <a href="manage_choices.php?question_id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-list-check me-1"></i>Manage Choices
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($questions)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No questions found for this survey.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Question Modal -->
<div class="modal fade" id="questionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" id="modalAction" value="create">
                    <input type="hidden" name="question_id" id="question_id">

                    <div class="mb-3">
                        <label class="form-label">Question Text <span class="text-danger">*</span></label>
                        <textarea name="question_text" id="question_text" class="form-control" rows="3" placeholder="Enter your survey question..." required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Question Type <span class="text-danger">*</span></label>
                            <select name="question_type" id="question_type" class="form-select" required>
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="yes_no">Yes/No</option>
                                <option value="rating">Rating Scale (1-5)</option>
                                <option value="short_answer">Short Answer</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Question Order <span class="text-danger">*</span></label>
                            <input type="number" name="question_order" id="question_order" class="form-control" value="0" required>
                        </div>
                    </div>

                    <!-- Choices (visible only for multiple choice) -->
                    <div class="mb-3" id="choices_section" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Choices <span class="text-danger">*</span> <small class="text-muted">(At least 2 choices required)</small></label>
                            <span class="badge bg-secondary" id="choices_counter">2 choices</span>
                        </div>
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-info-circle me-1"></i>Multiple Choice questions require a minimum of 2 choices for respondents to select from.
                        </div>
                        <div id="choices_container"></div>
                        <div id="choices_error" class="alert alert-danger py-2 small mt-2" style="display: none;"></div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                            <button type="button" class="btn btn-sm btn-outline-primary px-3 py-2 fw-semibold" id="add_choice_btn">
                                <i class="bi bi-plus-circle me-2"></i> Add Another Choice
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary px-3 py-2 fw-semibold" id="add_other_btn" title="Add an 'Other' option where respondents can type custom short answer text">
                                <i class="bi bi-plus-circle me-2"></i> Add "Other"
                            </button>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" name="required" id="required" class="form-check-input" value="1">
                        <label class="form-check-label" for="required">Required Question</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="save_question_btn">Save Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const surveyId = <?= (int)$survey_id ?>;

function updateChoicesUI() {
    const rows = document.querySelectorAll('#choices_container .choice-row');
    const counter = document.getElementById('choices_counter');
    if (counter) {
        counter.textContent = `${rows.length} choice${rows.length === 1 ? '' : 's'}`;
        if (rows.length < 2) {
            counter.className = 'badge bg-danger';
        } else {
            counter.className = 'badge bg-success';
        }
    }
    
    rows.forEach((row, idx) => {
        const input = row.querySelector('.choice-input');
        if (!input.value && (!input.placeholder || input.placeholder.startsWith('Choice '))) {
            input.placeholder = `Choice ${idx + 1}`;
        }
        const orderInput = row.querySelector('.choice-order');
        if (!orderInput.value) {
            orderInput.value = idx + 1;
        }
    });
}

function ensureMinChoices(minCount = 2) {
    const container = document.getElementById('choices_container');
    while (container.querySelectorAll('.choice-row').length < minCount) {
        const idx = container.querySelectorAll('.choice-row').length;
        container.appendChild(createChoiceRow({ choice_order: idx + 1 }));
    }
    updateChoicesUI();
}

function createChoiceRow(choice = {}) {
    const id = choice.id || '';
    const text = choice.choice_text || '';
    const order = typeof choice.choice_order !== 'undefined' ? choice.choice_order : '';

    const row = document.createElement('div');
    row.className = 'd-flex align-items-center mb-2 choice-row';
    const escapedText = text ? String(text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';
    
    row.innerHTML = `
        <input type="hidden" class="choice-id" value="${id}">
        <input type="text" class="form-control choice-input me-2" placeholder="Choice text" value="${escapedText}">
        <input type="number" class="form-control choice-order me-2" style="width:85px; min-width: 85px;" placeholder="Order" value="${order}">
        <button type="button" class="btn btn-sm btn-outline-danger remove-choice-btn d-inline-flex align-items-center justify-content-center p-0" style="width: 34px; height: 34px; min-width: 34px; border-radius: 8px;" title="Remove choice"><i class="bi bi-x-lg" style="font-size: 0.8rem;"></i></button>
    `;

    row.querySelector('.remove-choice-btn').addEventListener('click', () => {
        const currentRows = document.querySelectorAll('#choices_container .choice-row');
        if (currentRows.length <= 2) {
            alert('A Multiple Choice question must have at least 2 choices. You cannot remove more choices.');
            return;
        }
        row.remove();
        updateChoicesUI();
    });

    row.querySelector('.choice-input').addEventListener('input', () => {
        const errDiv = document.getElementById('choices_error');
        if (errDiv) errDiv.style.display = 'none';
    });

    return row;
}

function showChoicesSection(show) {
    document.getElementById('choices_section').style.display = show ? '' : 'none';
    if (show) {
        ensureMinChoices(2);
    }
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Question';
    document.getElementById('modalAction').value = 'create';
    document.getElementById('question_id').value = '';
    document.getElementById('question_text').value = '';
    document.getElementById('question_type').value = 'multiple_choice';
    document.getElementById('question_order').value = '<?= count($questions) + 1 ?>';
    document.getElementById('required').checked = true;
    
    // reset choices
    document.getElementById('choices_container').innerHTML = '';
    const errDiv = document.getElementById('choices_error');
    if (errDiv) errDiv.style.display = 'none';

    // show choices UI with at least 2 choices for multiple choice
    showChoicesSection(true);
    ensureMinChoices(2);

    new bootstrap.Modal(document.getElementById('questionModal')).show();
}

async function openEditModal(question) {
    document.getElementById('modalTitle').textContent = 'Edit Question';
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('question_id').value = question.id;
    document.getElementById('question_text').value = question.question_text;
    document.getElementById('question_type').value = question.question_type;
    document.getElementById('question_order').value = question.question_order;
    document.getElementById('required').checked = question.required == 1;

    document.getElementById('choices_container').innerHTML = '';
    const errDiv = document.getElementById('choices_error');
    if (errDiv) errDiv.style.display = 'none';

    // load choices if multiple choice
    if (question.question_type === 'multiple_choice') {
        try {
            const res = await fetch(`../ajax/questions.php?action=get&id=${question.id}`);
            const data = await res.json();
            if (data.success && data.data && Array.isArray(data.data.choices) && data.data.choices.length > 0) {
                data.data.choices.forEach(c => {
                    document.getElementById('choices_container').appendChild(createChoiceRow(c));
                });
            }
            ensureMinChoices(2);
            showChoicesSection(true);
        } catch (err) {
            console.error(err);
            ensureMinChoices(2);
            showChoicesSection(true);
        }
    } else {
        showChoicesSection(false);
    }

    new bootstrap.Modal(document.getElementById('questionModal')).show();
}

// Toggle choices section when question type changes
document.getElementById('question_type').addEventListener('change', function() {
    showChoicesSection(this.value === 'multiple_choice');
});

// Add choice button
document.getElementById('add_choice_btn').addEventListener('click', function() {
    const idx = document.querySelectorAll('#choices_container .choice-row').length;
    document.getElementById('choices_container').appendChild(createChoiceRow({ choice_order: idx + 1 }));
    updateChoicesUI();
});

// Add "Other" choice button (Short Answer option)
document.getElementById('add_other_btn').addEventListener('click', function() {
    const existingRows = document.querySelectorAll('#choices_container .choice-row');
    let hasOther = false;
    existingRows.forEach(r => {
        const val = r.querySelector('.choice-input').value.trim().toLowerCase();
        if (val.startsWith('other')) hasOther = true;
    });
    if (hasOther) {
        alert('An "Other" choice option has already been added to this question.');
        return;
    }
    const idx = existingRows.length;
    const newRow = createChoiceRow({
        choice_text: 'Other (Please specify)',
        choice_order: idx + 1
    });
    document.getElementById('choices_container').appendChild(newRow);
    updateChoicesUI();
});

// Handle form submit via AJAX to support creating/updating choices atomically along with question
document.querySelector('#questionModal form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const action = document.getElementById('modalAction').value;
    const qText = document.getElementById('question_text').value.trim();
    const qType = document.getElementById('question_type').value;
    const qOrder = document.getElementById('question_order').value;
    const qRequired = document.getElementById('required').checked ? '1' : '0';
    const qId = document.getElementById('question_id').value;

    if (!qText) {
        alert('Question text is required.');
        document.getElementById('question_text').focus();
        return;
    }

    // Validation for Multiple Choice: must have at least 2 non-empty choices
    const choicesPayload = [];
    if (qType === 'multiple_choice') {
        const choiceRows = document.querySelectorAll('#choices_container .choice-row');
        choiceRows.forEach((row, idx) => {
            const cid = row.querySelector('.choice-id').value;
            const text = row.querySelector('.choice-input').value.trim();
            const order = row.querySelector('.choice-order').value || (idx + 1);
            if (text) {
                choicesPayload.push({
                    id: cid ? parseInt(cid) : 0,
                    choice_text: text,
                    choice_order: parseInt(order)
                });
            }
        });

        if (choicesPayload.length < 2) {
            const errDiv = document.getElementById('choices_error');
            if (errDiv) {
                errDiv.textContent = 'Multiple Choice questions must have at least 2 choices. Please enter at least 2 choices.';
                errDiv.style.display = 'block';
            }
            alert('Verification Error: Multiple Choice questions must have at least 2 choices. Please enter at least 2 choices before saving.');
            
            // Focus the first empty choice input
            const emptyInput = Array.from(choiceRows).map(r => r.querySelector('.choice-input')).find(inp => !inp.value.trim());
            if (emptyInput) emptyInput.focus();
            return;
        }
    }

    try {
        const saveBtn = document.getElementById('save_question_btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving...';
        }

        const form = new FormData();
        form.append('survey_id', surveyId);
        form.append('question_text', qText);
        form.append('question_type', qType);
        form.append('question_order', qOrder);
        form.append('required', qRequired);
        if (qType === 'multiple_choice') {
            form.append('choices', JSON.stringify(choicesPayload));
        }

        let url = action === 'create' ? '../ajax/questions.php?action=create' : '../ajax/questions.php?action=update';
        if (action === 'edit') {
            form.append('id', qId);
        }

        const resp = await fetch(url, { method: 'POST', body: form });
        const json = await resp.json();

        if (!json.success) {
            throw new Error(json.message || 'Failed to save question');
        }

        // reload to reflect changes
        window.location.reload();
    } catch (err) {
        console.error(err);
        alert('An error occurred: ' + (err.message || err));
        const saveBtn = document.getElementById('save_question_btn');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = 'Save Question';
        }
    }
});

// Auto open Add Question modal if redirected from survey creation
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('auto_add') === '1') {
        openAddModal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
