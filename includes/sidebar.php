<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$userType = $_SESSION['user_type'] ?? '';
?>
<aside class="sidebar" id="sidebarMenu">
    <div class="sidebar-header">
        <a href="<?= $userType === 'resident' ? BASE_URL . '/resident/dashboard.php' : BASE_URL . '/admin/dashboard.php' ?>" class="sidebar-brand">
            <span class="brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
            <span class="brand-copy">
                <span class="brand-name">Barangay Health</span>
                <span class="brand-sub">Survey System</span>
            </span>
        </a>
    </div>

    <ul class="sidebar-nav">
        <?php if ($userType === 'admin'): ?>
            <li class="nav-title">Overview</li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-grid-1x2-fill"></i> Dashboard
                </a>
            </li>
            <li class="nav-title">People</li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/admin/manage_residents.php" class="nav-link <?php echo $currentPage == 'manage_residents.php' ? 'active' : ''; ?>">
                    <i class="bi bi-people-fill"></i> Manage Residents
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/admin/manage_staff.php" class="nav-link <?php echo $currentPage == 'manage_staff.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-badge-fill"></i> Manage Staff
                </a>
            </li>
            <li class="nav-title">Surveys</li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/admin/manage_surveys.php" class="nav-link <?php echo in_array($currentPage, ['manage_surveys.php','manage_questions.php','manage_choices.php']) ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard2-pulse-fill"></i> Manage Surveys
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/admin/survey_results.php" class="nav-link <?php echo $currentPage == 'survey_results.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bar-chart-fill"></i> Survey Results
                </a>
            </li>
            <li class="nav-title">Records</li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/admin/reports.php" class="nav-link <?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-bar-graph-fill"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/admin/activity_logs.php" class="nav-link <?php echo $currentPage == 'activity_logs.php' ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history"></i> Activity Logs
                </a>
            </li>
        <?php elseif ($userType === 'resident'): ?>
            <li class="nav-title">Menu</li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/resident/dashboard.php" class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-grid-1x2-fill"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/resident/surveys.php" class="nav-link <?php echo in_array($currentPage, ['surveys.php','take_survey.php','preview_survey.php','submit_survey.php']) ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard2-pulse-fill"></i> Surveys
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/resident/profile.php" class="nav-link <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-fill"></i> My Profile
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <a href="#" class="nav-link nav-link-logout" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</aside>
