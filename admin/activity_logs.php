<?php
$pageTitle = "Activity Logs";
require_once '../config/session.php';
requireAdmin();

$message = '';
$error = '';

// Handle POST actions (Clear Logs)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
    } else {
        try {
            foreach (fb_all('activity_logs') as $lid => $l) {
                fb_delete_rec('activity_logs', (string)$lid);
            }
            $message = "All activity logs have been cleared successfully.";
            fb_log($_SESSION['user_id'], 'admin', 'clear_logs', 'Cleared all system activity logs');
        } catch (Exception $e) {
            $error = "Error clearing logs: " . $e->getMessage();
        }
    }
}

// Build Filter Query (applied in PHP over Firebase rows)
$filter_from = $_GET['date_from'] ?? '';
$filter_to = $_GET['date_to'] ?? '';
$filter_type = $_GET['user_type'] ?? 'all';
$filter_action = $_GET['action_type'] ?? 'all';
$search = $_GET['search'] ?? '';

// Fetch Logs (Firebase) with user-name resolution equivalent to the old LEFT JOINs
$logs = [];
try {
    $adminNames = [];
    foreach (fb_all('admins') as $aid => $a) {
        if (is_array($a)) $adminNames[(string)$aid] = $a['full_name'] ?? '';
    }
    $residentNames = [];
    foreach (fb_all('residents') as $rid => $r) {
        if (is_array($r)) $residentNames[(string)$rid] = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
    }
    foreach (fb_all('activity_logs') as $lid => $log) {
        if (!is_array($log)) continue;
        $log['id'] = (string)$lid;
        $uid = (string)($log['user_id'] ?? '');
        if (($log['user_type'] ?? '') === 'admin') {
            $log['user_name'] = $adminNames[$uid] ?? null;
        } elseif (($log['user_type'] ?? '') === 'resident') {
            $log['user_name'] = $residentNames[$uid] ?? null;
        } else {
            $log['user_name'] = null;
        }
        $createdDate = substr((string)($log['created_at'] ?? ''), 0, 10);
        if ($filter_from !== '' && $createdDate < $filter_from) continue;
        if ($filter_to !== '' && $createdDate > $filter_to) continue;
        if ($filter_type !== 'all' && ($log['user_type'] ?? '') !== $filter_type) continue;
        if ($filter_action !== 'all' && stripos((string)($log['action'] ?? ''), $filter_action) === false) continue;
        if ($search !== '') {
            $hay = strtolower((string)($log['description'] ?? '') . ' ' . (string)($log['user_name'] ?? ''));
            if (stripos($hay, strtolower($search)) === false) continue;
        }
        $logs[] = $log;
    }
    usort($logs, function ($a, $b) { return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')); });
    $logs = array_slice($logs, 0, 500);
} catch (Exception $e) {
    $logs = [];
    $error = "Error loading logs: " . $e->getMessage();
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1 fw-bold">Activity Logs</h2>
                <p class="text-muted mb-0">System audit trail and user activity records</p>
            </div>
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

        <!-- Filters Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label fw-medium small">From Date</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_from) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-medium small">To Date</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_to) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-medium small">User Type</label>
                        <select name="user_type" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Users</option>
                            <option value="admin" <?= $filter_type === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="resident" <?= $filter_type === 'resident' ? 'selected' : '' ?>>Resident</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-medium small">Action</label>
                        <select name="action_type" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" <?= $filter_action === 'all' ? 'selected' : '' ?>>All Actions</option>
                            <option value="login" <?= $filter_action === 'login' ? 'selected' : '' ?>>Login/Logout</option>
                            <option value="create" <?= $filter_action === 'create' ? 'selected' : '' ?>>Create</option>
                            <option value="update" <?= $filter_action === 'update' ? 'selected' : '' ?>>Update</option>
                            <option value="delete" <?= $filter_action === 'delete' ? 'selected' : '' ?>>Delete</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium small">Search Description or User</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" id="search_input" class="form-control" placeholder="Search activity..." value="<?= htmlspecialchars($search) ?>" onkeyup="realtimeSearchLogs(this.value)" oninput="realtimeSearchLogs(this.value)">
                            <button type="submit" class="btn btn-primary px-3" style="flex-shrink: 0;"><i class="bi bi-search me-1"></i> Apply Filter</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Table Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="logs_table">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4" style="width: 230px; font-size: 0.8rem; font-weight: 700; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">DATE &amp; TIME</th>
                                <th style="min-width: 180px; font-size: 0.8rem; font-weight: 700; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">USER</th>
                                <th style="width: 130px; font-size: 0.8rem; font-weight: 700; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">TYPE</th>
                                <th style="width: 160px; font-size: 0.8rem; font-weight: 700; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">ACTION</th>
                                <th class="pe-4" style="font-size: 0.8rem; font-weight: 700; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">DESCRIPTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="ps-4 text-nowrap text-muted" style="font-size: 0.88rem;">
                                        <i class="bi bi-clock me-2 text-primary"></i><?= date('M j, Y, g:i A', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></span>
                                    </td>
                                    <td>
                                        <?php if ($log['user_type'] === 'admin'): ?>
                                            <span class="badge bg-primary rounded-pill px-3 py-1 fw-semibold" style="font-size: 0.78rem;">Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-success rounded-pill px-3 py-1 fw-semibold" style="font-size: 0.78rem;">Resident</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill px-3 py-1 fw-bold text-uppercase text-white" style="background-color: #64748b; font-size: 0.72rem; letter-spacing: 0.5px;">
                                            <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $log['action']))) ?>
                                        </span>
                                    </td>
                                    <td class="pe-4 text-dark" style="font-size: 0.92rem;">
                                        <?= htmlspecialchars($log['description']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="bi bi-journal-x fs-3 d-block mb-2 text-muted"></i>
                                        No activity logs found matching criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card-footer bg-white d-flex flex-column align-items-center gap-2 py-3">
                <form method="POST" id="clearLogsForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="clear_logs">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmClearLogs()">
                        <i class="bi bi-trash me-1"></i> Clear All Logs
                    </button>
                </form>
                <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Showing up to 500 recent activity logs</small>
            </div>
        </div>

    </div>
</div>

<script>
// Real-Time Search Filter
function realtimeSearchLogs(query) {
    const filter = query.toLowerCase().trim();
    const rows = document.querySelectorAll('#logs_table tbody tr');
    rows.forEach(row => {
        if (row.cells.length < 2) return;
        const text = row.innerText.toLowerCase();
        row.style.display = (filter === '' || text.includes(filter)) ? '' : 'none';
    });
}

function confirmClearLogs() {
    App.confirmAction('WARNING: Are you sure you want to delete ALL activity logs? This action will clear the entire system audit history and cannot be undone.', function() {
        document.getElementById('clearLogsForm').submit();
    }, { title: 'Clear Activity Logs', heading: 'Confirm Deletion of All Logs', confirmText: 'Yes, Clear All Logs', confirmClass: 'btn-danger', iconClass: 'bi bi-exclamation-triangle text-danger fs-1' });
}
</script>

<?php require_once '../includes/footer.php'; ?>
