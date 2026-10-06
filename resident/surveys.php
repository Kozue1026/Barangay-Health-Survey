<?php
$pageTitle = "Available Surveys";
require_once '../config/session.php';
requireResident();

$user_id = (string)$_SESSION['user_id'];
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$today = fb_today();

$surveys = [];
$search_error = '';

try {
    $admins = fb_all('admins');
    $responses = fb_all('responses');
    $questions = fb_all('survey_questions');
    $qcount = [];
    foreach ($questions as $qq) { $sid = (string)($qq['survey_id'] ?? ''); $qcount[$sid] = ($qcount[$sid] ?? 0) + 1; }
    $myResp = [];
    foreach ($responses as $rid => $rr) {
        if ((string)($rr['resident_id'] ?? '') === $user_id) $myResp[(string)($rr['survey_id'] ?? '')] = $rid;
    }
    foreach (fb_all('surveys') as $sid => $s) {
        if (!fb_survey_open($s)) continue;
        if (($qcount[(string)$sid] ?? 0) <= 0) continue;
        if ($search !== '' && stripos($s['title'] ?? '', $search) === false && stripos($s['description'] ?? '', $search) === false) continue;
        $s['id'] = $sid;
        $s['response_id'] = $myResp[(string)$sid] ?? null;
        $creator = $admins[(string)($s['created_by'] ?? '')] ?? [];
        $s['creator_name'] = $creator['full_name'] ?? null;
        $s['creator_role'] = $creator['role'] ?? null;
        if ($filter === 'completed' && empty($s['response_id'])) continue;
        if ($filter === 'active' && !empty($s['response_id'])) continue;
        $surveys[] = $s;
    }
    usort($surveys, function ($a, $b) { return strcmp($a['closing_date'] ?? '', $b['closing_date'] ?? ''); });
} catch (Exception $e) {
    $search_error = "We encountered a temporary issue searching for \"" . htmlspecialchars($search) . "\". Please try resetting your search or using a different keyword.";
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';

$flash_error = '';
if (isset($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
?>

<div class="main-content">
    <div class="content-wrapper">
        <h2 class="mb-4">Community Health Surveys</h2>

        <?php if ($flash_error): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($flash_error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($search_error)): ?>
            <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-warning"></i>
                <div class="flex-grow-1">
                    <strong>Search Notice:</strong> <?= htmlspecialchars($search_error) ?>
                </div>
                <a href="surveys.php" class="btn btn-sm btn-outline-dark ms-3">Reset Search &amp; Try Again</a>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Bar Row -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body py-3">
                <form method="GET" class="row g-3 align-items-center">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    
                    <div class="col-md-7">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search surveys by title or description..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-search me-1"></i> Search</button>
                        </div>
                    </div>
                    
                    <div class="col-md-5 d-flex justify-content-md-end">
                        <div class="btn-group w-100 w-md-auto" role="group">
                            <a href="surveys.php?filter=all<?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                            <a href="surveys.php?filter=active<?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-outline-primary <?= $filter === 'active' ? 'active' : '' ?>">Pending</a>
                            <a href="surveys.php?filter=completed<?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-outline-primary <?= $filter === 'completed' ? 'active' : '' ?>">Completed</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <?php if (empty($surveys)): ?>
                <div class="col-12 text-center py-5">
                    <div class="mb-3">
                        <i class="bi bi-inbox text-muted opacity-50" style="font-size: 3.5rem;"></i>
                    </div>
                    <h5 class="text-muted fw-bold mb-2">
                        <?php 
                        if (!empty($search)) {
                            echo 'No surveys match your search "' . htmlspecialchars($search) . '".';
                        } elseif ($filter === 'completed') {
                            echo "You haven't completed any surveys yet.";
                        } elseif ($filter === 'active') {
                            echo "You have no pending surveys to take at this time.";
                        } else {
                            echo "No available surveys found at the moment.";
                        }
                        ?>
                    </h5>
                    <p class="text-muted small mb-0">
                        <?php if (!empty($search)): ?>
                            Try adjusting your search keywords or clear the search field.
                        <?php elseif ($filter === 'completed'): ?>
                            When you complete surveys, they will appear here for your records.
                        <?php elseif ($filter === 'active'): ?>
                            Check back later for new survey announcements from the health center.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($surveys as $survey): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm card-hover">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-primary"><?= htmlspecialchars($survey['category']) ?></span>
                                    <?php if ($survey['response_id']): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-lg"></i> Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </div>
                                <h5 class="card-title"><?= htmlspecialchars($survey['title']) ?></h5>
                                <p class="card-text flex-grow-1 text-muted" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= htmlspecialchars($survey['description']) ?>
                                </p>
                                <div class="mt-3">
                                    <small class="text-muted d-block mb-1">
                                        <i class="bi bi-calendar-event me-1"></i> Closes: <?= date('M d, Y', strtotime($survey['closing_date'])) ?>
                                    </small>
                                    <small class="text-muted d-block mb-3">
                                        <i class="bi bi-person-circle me-1"></i> Created by: <?= htmlspecialchars(!empty($survey['creator_name']) ? ($survey['creator_role'] === 'super_admin' ? 'Admin' : 'Staff') . ' (' . $survey['creator_name'] . ')' : 'System') ?>
                                    </small>
                                    <?php if ($survey['response_id']): ?>
                                        <button class="btn btn-success w-100" disabled>Completed ✓</button>
                                    <?php else: ?>
                                        <a href="take_survey.php?id=<?= $survey['id'] ?>" class="btn btn-primary w-100">Take Survey</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
