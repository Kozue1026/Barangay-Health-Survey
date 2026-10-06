<?php
$userName = $_SESSION['full_name'] ?? 'User';
$profilePic = null;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'resident') {
    try {
        $me = fb_get_rec('residents', (string)$_SESSION['user_id']);
        $profilePic = $me['profile_picture'] ?? null;
    } catch (Exception $e) {
        // Fallback silently if query fails
    }
}
?>
<nav class="top-navbar">
    <div class="navbar-content">
        <div class="navbar-left">
            <button class="navbar-toggler navbar-toggler-btn d-lg-none" type="button" id="sidebarToggle" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            <div class="page-heading">
                <span class="page-kicker">Barangay Health Center</span>
                <h1 class="page-title"><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h1>
            </div>
        </div>

        <div class="navbar-nav-right">
            <?php
            $unread_count = 0;
            $static_notifications = [];
            if (isset($_SESSION['user_id'])) {
                $user_id = (string)$_SESSION['user_id'];
                $user_type = $_SESSION['user_type'] ?? '';
                try {
                    foreach (fb_all('notifications') as $nid => $n) {
                        if ((string)($n['user_id'] ?? '') === $user_id && ($n['user_type'] ?? '') === $user_type && !(int)($n['is_read'] ?? 0)) {
                            $static_notifications[] = ['id' => $nid] + $n;
                        }
                    }
                    usort($static_notifications, function ($a, $b) { return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });
                    $static_notifications = array_slice($static_notifications, 0, 20);
                    $unread_count = count($static_notifications);
                } catch (Exception $e) {
                    // Fallback
                }
            }
            ?>

            <button type="button" class="nav-icon-btn" id="darkModeToggle" aria-label="Toggle theme" title="Toggle theme">
                <i class="bi bi-moon"></i>
            </button>

            <div class="dropdown" id="notificationDropdown">
                <a class="nav-icon-btn" href="#" role="button" id="notifBellBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
                    <?php else: ?>
                        <span class="notification-badge" style="display: none;">0</span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow dropdown-menu-notifications" style="min-width: 340px; max-width: 420px; max-height: 440px; overflow-y: auto;">
                    <li>
                        <h6 class="dropdown-header d-flex justify-content-between align-items-center fw-bold">
                            <span><i class="bi bi-bell me-1"></i>Notifications</span>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;"><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
                            <?php endif; ?>
                        </h6>
                    </li>
                    <?php if ($unread_count > 0): ?>
                        <?php foreach ($static_notifications as $n): ?>
                            <li>
                                <a class="dropdown-item notif-item py-2" href="<?= BASE_URL ?>/auth/view_notification.php?id=<?= $n['id'] ?>">
                                    <div class="d-flex align-items-start gap-2">
                                        <span class="notif-icon-wrap flex-shrink-0">
                                            <i class="bi <?= htmlspecialchars($n['icon']) ?> fs-5"></i>
                                        </span>
                                        <div class="notif-content flex-grow-1">
                                            <div class="notif-title fw-semibold text-wrap"><?= htmlspecialchars($n['title']) ?></div>
                                            <div class="notif-msg text-muted small text-wrap"><?= htmlspecialchars($n['message']) ?></div>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item text-center small text-primary py-2 fw-semibold mark-all-read-btn" href="#" id="markAllReadBtn">
                                <i class="bi bi-check-all me-1"></i>Mark all as read
                            </a>
                        </li>
                    <?php else: ?>
                        <li>
                            <div class="dropdown-item text-muted text-center py-4">
                                <i class="bi bi-check2-circle text-success fs-4 d-block mb-1"></i>
                                <small>No new notifications</small>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="dropdown user-dropdown">
                <a class="user-chip dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if ($profilePic && file_exists(__DIR__ . '/../uploads/profile_pics/' . $profilePic)): ?>
                        <img src="<?= BASE_URL ?>/uploads/profile_pics/<?= htmlspecialchars($profilePic) ?>" class="user-avatar-img" alt="">
                    <?php else: ?>
                        <span class="user-avatar"><?php echo strtoupper(substr($userName, 0, 1)); ?></span>
                    <?php endif; ?>
                    <span class="d-none d-md-inline user-chip-name"><?php echo htmlspecialchars($userName); ?></span>
                    <i class="bi bi-chevron-down d-none d-md-inline user-chip-caret"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><h6 class="dropdown-header">Signed in as <br><strong><?php echo htmlspecialchars($userName); ?></strong></h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/auth/change_password.php"><i class="bi bi-key me-2"></i> Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
