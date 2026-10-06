<?php
$pageTitle = "Resident Dashboard";
require_once '../config/session.php';
requireResident();

$user_id = (string)$_SESSION['user_id'];
$today = fb_today();

// Get counts (pending = active surveys not yet completed by this resident)
$activeIds = [];
foreach (fb_all('surveys') as $sid => $s) {
    if (fb_survey_open($s)) $activeIds[(string)$sid] = true;
}
$total_active = count($activeIds);
$completed = 0;
foreach (fb_all('responses') as $r) {
    if ((string)($r['resident_id'] ?? '') === $user_id && isset($activeIds[(string)($r['survey_id'] ?? '')])) $completed++;
}

$pending = max(0, $total_active - $completed);

// Get resident info
$resident = fb_get_rec('residents', $user_id);

// Get last login (second most recent)
$logins = [];
foreach (fb_all('login_history') as $h) {
    if ((string)($h['user_id'] ?? '') === $user_id && ($h['user_type'] ?? '') === 'resident' && !empty($h['login_time'])) $logins[] = $h['login_time'];
}
rsort($logins);
$last_login = $logins[1] ?? 'First time login';

// Get available surveys
$admins = fb_all('admins');
$responses = fb_all('responses');
$active_surveys = [];
foreach (fb_all('surveys') as $sid => $s) {
    if (!fb_survey_open($s)) continue;
    $s['id'] = $sid;
    $s['response_id'] = null;
    foreach ($responses as $rid => $r) {
        if ((string)($r['survey_id'] ?? '') === (string)$sid && (string)($r['resident_id'] ?? '') === $user_id) { $s['response_id'] = $rid; break; }
    }
    $creator = $admins[(string)($s['created_by'] ?? '')] ?? [];
    $s['creator_name'] = $creator['full_name'] ?? null;
    $s['creator_role'] = $creator['role'] ?? null;
    $active_surveys[] = $s;
}
usort($active_surveys, function ($a, $b) { return strcmp($a['closing_date'] ?? '', $b['closing_date'] ?? ''); });
$active_surveys = array_slice($active_surveys, 0, 6);

// Get recent activity
$activities = [];
foreach (fb_all('activity_logs') as $lid => $l) {
    if ((string)($l['user_id'] ?? '') === $user_id && ($l['user_type'] ?? '') === 'resident') { $l['id'] = $lid; $activities[] = $l; }
}
usort($activities, function ($a, $b) { return strcmp($b['id'], $a['id']); });
$activities = array_slice($activities, 0, 5);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <h2 class="mb-4">Welcome, <?= htmlspecialchars($resident['first_name']) ?>!</h2>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stat-card shadow-sm border-0 bg-primary text-white card-hover">
                    <div class="card-body d-flex align-items-center justify-content-between p-4">
                        <div>
                            <h5 class="card-title fw-bold fs-4 mb-2"><i class="bi bi-journal-text me-2"></i>Active Surveys</h5>
                            <h2 class="mb-0 display-6 fw-bold"><?= $total_active ?></h2>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center ms-3" style="width: 56px; height: 56px; flex-shrink: 0; background-color: rgba(255,255,255,0.25);">
                            <i class="bi bi-journal-text" style="font-size: 1.75rem; color: #fff;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card shadow-sm border-0 bg-success text-white card-hover">
                    <div class="card-body d-flex align-items-center justify-content-between p-4">
                        <div>
                            <h5 class="card-title fw-bold fs-4 mb-2"><i class="bi bi-check-circle-fill me-2"></i>Completed</h5>
                            <h2 class="mb-0 display-6 fw-bold"><?= $completed ?></h2>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center ms-3" style="width: 56px; height: 56px; flex-shrink: 0; background-color: rgba(255,255,255,0.25);">
                            <i class="bi bi-check-circle-fill" style="font-size: 1.75rem; color: #fff;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card shadow-sm border-0 bg-warning text-dark card-hover">
                    <div class="card-body d-flex align-items-center justify-content-between p-4">
                        <div>
                            <h5 class="card-title fw-bold fs-4 mb-2 text-dark"><i class="bi bi-clock-fill me-2"></i>Pending</h5>
                            <h2 class="mb-0 display-6 fw-bold text-dark"><?= $pending ?></h2>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center ms-3" style="width: 56px; height: 56px; flex-shrink: 0; background-color: rgba(0,0,0,0.12);">
                            <i class="bi bi-clock-fill" style="font-size: 1.75rem; color: #854d0e;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Available Surveys</h5>
                        <a href="surveys.php" class="btn btn-sm btn-outline-primary px-3 text-center justify-content-center">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($active_surveys)): ?>
                            <p class="text-muted text-center py-4">No active surveys available at the moment.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($active_surveys as $survey): ?>
                                    <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?= htmlspecialchars($survey['title']) ?></h6>
                                            <span class="badge bg-secondary mb-2"><?= htmlspecialchars($survey['category']) ?></span>
                                            <small class="d-block text-muted mb-1">Closes on: <?= date('M d, Y', strtotime($survey['closing_date'])) ?></small>
                                            <small class="d-block text-muted"><i class="bi bi-person-circle me-1"></i>Created by: <?= htmlspecialchars(!empty($survey['creator_name']) ? ($survey['creator_role'] === 'super_admin' ? 'Admin' : 'Staff') . ' (' . $survey['creator_name'] . ')' : 'System') ?></small>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($survey['response_id']): ?>
                                                <button class="btn btn-success btn-sm px-3 text-center justify-content-center" disabled><i class="fas fa-check me-1"></i> Completed</button>
                                            <?php else: ?>
                                                <a href="take_survey.php?id=<?= $survey['id'] ?>" class="btn btn-primary btn-sm px-3 text-center justify-content-center">Take Survey</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">Profile Summary</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-3">
                                <small class="text-muted d-block">Full Name</small>
                                <strong><?= htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']) ?></strong>
                            </li>
                            <li class="mb-3">
                                <small class="text-muted d-block">Resident Number</small>
                                <strong><?= htmlspecialchars($resident['resident_number']) ?></strong>
                            </li>
                            <li class="mb-3">
                                <small class="text-muted d-block">Email</small>
                                <strong><?= htmlspecialchars($resident['email']) ?></strong>
                            </li>
                            <li>
                                <small class="text-muted d-block">Last Login</small>
                                <strong><?= $last_login !== 'First time login' ? date('M j, Y, g:i A', strtotime($last_login)) : $last_login ?></strong>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card shadow-sm border-0 border-top border-3 border-primary">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                        <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1 small">Activity Feed</span>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($activities)): ?>
                            <p class="text-muted text-center py-3 mb-0">No recent activity.</p>
                        <?php else: ?>
                        <div class="activity-timeline py-2">
                            <?php foreach ($activities as $log): ?>
                                <div class="activity-timeline-item">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted small fw-medium"><i class="bi bi-clock me-1 text-primary"></i><?= date('M j, Y, g:i A', strtotime($log['created_at'])) ?></span>
                                        <span class="badge bg-primary-subtle text-primary text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($log['action']) ?></span>
                                    </div>
                                    <p class="mb-0 text-dark small fw-semibold"><?= htmlspecialchars($log['description']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
