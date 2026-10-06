<?php
$pageTitle = "Survey Preview";
require_once '../config/session.php';
requireResident();

$survey_id = (string)($_GET['id'] ?? '');
$user_id = (string)$_SESSION['user_id'];

if (!$survey_id) {
    header("Location: surveys.php");
    exit;
}

// Fetch survey details
$survey = fb_get_rec('surveys', $survey_id);
$valid = $survey && fb_survey_open($survey);
if (!$valid) {
    $_SESSION['flash_error'] = "The requested survey is not available or has expired.";
    header("Location: surveys.php");
    exit;
}
$survey['id'] = $survey_id;
$qc = 0;
foreach (fb_all('survey_questions') as $qq) { if ((string)($qq['survey_id'] ?? '') === $survey_id) $qc++; }
$survey['question_count'] = $qc;
$survey['response_id'] = null;
foreach (fb_all('responses') as $rid => $rr) {
    if ((string)($rr['survey_id'] ?? '') === $survey_id && (string)($rr['resident_id'] ?? '') === $user_id) { $survey['response_id'] = $rid; break; }
}
$creator = fb_get_rec('admins', (string)($survey['created_by'] ?? ''));
$survey['creator_name'] = $creator['full_name'] ?? null;
$survey['creator_role'] = $creator['role'] ?? null;

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-wrapper p-4">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="surveys.php" class="text-decoration-none">Surveys</a></li>
                <li class="breadcrumb-item active" aria-current="page">Preview</li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-light text-primary"><?= htmlspecialchars($survey['category']) ?></span>
                            <?php if ($survey['response_id']): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Completed</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i> Pending Response</span>
                            <?php endif; ?>
                        </div>
                        <h2 class="mb-0 fw-bold"><?= htmlspecialchars($survey['title']) ?></h2>
                    </div>

                    <div class="card-body p-4">
                        <div class="row g-4 mb-4">
                            <div class="col-md-6 border-end">
                                <h6 class="text-muted text-uppercase fw-bold mb-3" style="font-size: 0.75rem;">Survey Details</h6>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th class="ps-0 text-muted fw-normal" style="width: 40%;">Opening Date:</th>
                                        <td class="fw-semibold"><?= htmlspecialchars(fb_fmt_dt($survey['opening_date'] ?? '')) ?></td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0 text-muted fw-normal">Closing Date:</th>
                                        <td class="fw-semibold text-danger"><?= htmlspecialchars(fb_fmt_dt($survey['closing_date'] ?? '')) ?></td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0 text-muted fw-normal">Questions:</th>
                                        <td class="fw-semibold"><?= $survey['question_count'] ?> items</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted text-uppercase fw-bold mb-3" style="font-size: 0.75rem;">Author Information</h6>
                                <p class="mb-1 text-dark fw-semibold">
                                    <i class="bi bi-person-circle me-2 text-primary"></i>
                                    <?= htmlspecialchars($survey['creator_name'] ?? 'System') ?>
                                </p>
                                <p class="text-muted small mb-0">
                                    Role: <?= htmlspecialchars($survey['creator_role'] === 'super_admin' ? 'Health Administrator' : 'Center Staff') ?>
                                </p>
                            </div>
                        </div>

                        <hr class="mb-4">

                        <h6 class="text-muted text-uppercase fw-bold mb-3" style="font-size: 0.75rem;">Survey Description &amp; Instructions</h6>
                        <div class="bg-light p-4 rounded mb-4">
                            <p class="mb-0 text-dark" style="white-space: pre-line; line-height: 1.6;">
                                <?= htmlspecialchars($survey['description'] ?: 'No additional description provided for this survey. Please answer the questions truthfully to assist in barangay health data collection.') ?>
                            </p>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-5">
                            <a href="surveys.php" class="btn btn-outline-secondary px-4">
                                <i class="bi bi-arrow-left"></i> Back to Surveys
                            </a>
                            <?php if ($survey['response_id']): ?>
                                <button class="btn btn-success px-4" disabled>
                                    <i class="bi bi-check2-circle"></i> Survey Already Completed
                                </button>
                            <?php else: ?>
                                <a href="take_survey.php?id=<?= $survey['id'] ?>" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm">
                                    <i class="bi bi-file-earmark-play-fill me-2"></i> Take Survey Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
