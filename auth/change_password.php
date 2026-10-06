<?php
$pageTitle = "Change Password";
require_once '../config/session.php';
requireLogin();

$is_first_login = isset($_SESSION['first_login']) && $_SESSION['first_login'] == 1;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request token.";
    } elseif ($is_first_login && isset($_POST['skip_first'])) {
        // Optional: keep default password, clear first-login flag and continue.
        try {
            $uid = (string)$_SESSION['user_id'];
            $utype = $_SESSION['user_type'];
            $node = $utype === 'admin' ? 'admins' : 'residents';
            fb_update_rec($node, $uid, ['first_login' => 0]);
            $_SESSION['first_login'] = 0;
            fb_log($uid, $utype, 'Skip First Password Change', 'User kept default password for now');
            header("Location: " . BASE_URL . "/resident/dashboard.php");
            exit;
        } catch (Exception $e) {
            $error = "Database error occurred.";
        }
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $user_id = (string)$_SESSION['user_id'];
        $user_type = $_SESSION['user_type'];

        try {
            $node = $user_type === 'admin' ? 'admins' : 'residents';
            $user = fb_get_rec($node, $user_id);

            if (!$user || !password_verify($current_password, fb_pass_hash($user))) {
                $error = "Current password is incorrect.";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } elseif (strlen($new_password) < 8 || !preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                $error = "Password must be at least 8 characters long and contain both letters and numbers.";
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                if ($user_type === 'resident') {
                    fb_update_rec('residents', $user_id, ['password' => $hashed, 'first_login' => 0]);
                    $_SESSION['first_login'] = 0;

                    // Notify admins
                    $resInfo = fb_get_rec('residents', $user_id);
                    $resNum = $resInfo['resident_number'] ?? '';
                    $resName = trim(($resInfo['first_name'] ?? '') . ' ' . ($resInfo['last_name'] ?? ''));
                    notifyAdmins(
                        null,
                        "Resident Password Changed",
                        "Resident $resNum ($resName) changed their account password.",
                        "/admin/manage_residents.php",
                        "bi-key text-warning",
                        $user_id,
                        "resident"
                    );
                } else {
                    fb_update_rec('admins', $user_id, ['password' => $hashed]);
                }

                fb_log($user_id, $user_type, 'Change Password', 'User changed their password');

                $success = "Password changed successfully.";
                if ($is_first_login) {
                    header("Location: " . BASE_URL . "/resident/dashboard.php");
                    exit;
                }
            }
        } catch (Exception $e) {
            $error = "Database error occurred.";
        }
    }
}

$bodyClass = $is_first_login ? 'auth-page' : '';
require_once '../includes/header.php';
if (!$is_first_login) {
    require_once '../includes/sidebar.php';
    require_once '../includes/navbar.php';
}
?>

<div class="<?= !$is_first_login ? 'main-content' : 'container py-5 d-flex align-items-center justify-content-center min-vh-100' ?>">
    <div class="<?= !$is_first_login ? 'content-wrapper p-4 d-flex align-items-center justify-content-center' : 'w-100' ?>" style="<?= !$is_first_login ? 'min-height: calc(100vh - 120px);' : '' ?>">
        <div class="row justify-content-center w-100">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <div class="card shadow-sm border-0 my-auto">
                    <div class="card-body p-4">
                        <h4 class="card-title text-center mb-4"><i class="bi bi-key me-2 text-primary"></i>Change Password</h4>
                    <?php if ($is_first_login): ?>
                        <div class="alert alert-info">
                            Welcome! You may change your default password now, or skip and keep it until you're ready.
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" name="current_password" id="current_password" class="form-control" required>
                                <span class="pw-toggle-btn" data-pw-toggle="current_password" title="Show/hide password" role="button" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye-slash"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="new_password" class="form-control" required onkeyup="checkStrength()">
                                <span class="pw-toggle-btn" data-pw-toggle="new_password" title="Show/hide password" role="button" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye-slash"></i>
                                </span>
                            </div>
                            <div class="progress mt-2" style="height: 5px;">
                                <div id="strength_meter" class="progress-bar" role="progressbar" style="width: 0%;"></div>
                            </div>
                            <small id="strength_text" class="text-muted d-block mt-1"></small>
                            <small class="text-muted d-block mt-1"><i class="bi bi-info-circle me-1"></i>Must be at least 8 characters long and contain both letters and numbers.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required onkeyup="checkMatch()">
                                <span class="pw-toggle-btn" data-pw-toggle="confirm_password" title="Show/hide password" role="button" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye-slash"></i>
                                </span>
                            </div>
                            <small id="match_text" class="text-muted d-block mt-1"></small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100" id="submit_btn">Change Password</button>
                    </form>
                    <?php if ($is_first_login): ?>
                    <form method="POST" action="" class="mt-2">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="skip_first" value="1">
                        <button type="submit" class="btn btn-outline-secondary w-100">Skip for now</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
function checkStrength() {
    let val = document.getElementById('new_password').value;
    let meter = document.getElementById('strength_meter');
    let text = document.getElementById('strength_text');
    
    if (val.length === 0) {
        meter.style.width = '0%';
        text.textContent = '';
        checkMatch();
        return;
    }
    
    let hasLetters = /[a-zA-Z]/.test(val);
    let hasNumbers = /[0-9]/.test(val);
    let hasLength = val.length >= 8;
    
    if (!hasLength) {
        meter.style.width = '25%';
        meter.className = 'progress-bar bg-danger';
        text.textContent = 'Weak (Must be at least 8 characters)';
        text.className = 'text-danger small fw-medium mt-1 d-block';
    } else if (!hasLetters || !hasNumbers) {
        meter.style.width = '40%';
        meter.className = 'progress-bar bg-danger';
        text.textContent = 'Weak (Must contain both letters and numbers)';
        text.className = 'text-danger small fw-medium mt-1 d-block';
    } else {
        let extraPoints = 0;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) extraPoints += 15;
        if (/[^a-zA-Z0-9]/.test(val)) extraPoints += 15;
        if (val.length >= 12) extraPoints += 10;
        
        let totalStrength = 60 + extraPoints;
        meter.style.width = totalStrength + '%';
        
        if (totalStrength < 75) {
            meter.className = 'progress-bar bg-info';
            text.textContent = 'Fair (Meets minimum requirements)';
            text.className = 'text-info small fw-medium mt-1 d-block';
        } else if (totalStrength < 90) {
            meter.className = 'progress-bar bg-primary';
            text.textContent = 'Good';
            text.className = 'text-primary small fw-medium mt-1 d-block';
        } else {
            meter.className = 'progress-bar bg-success';
            text.textContent = 'Strong';
            text.className = 'text-success small fw-medium mt-1 d-block';
        }
    }
    checkMatch();
}

function checkMatch() {
    let p1 = document.getElementById('new_password').value;
    let p2 = document.getElementById('confirm_password').value;
    let text = document.getElementById('match_text');
    let btn = document.getElementById('submit_btn');
    
    let hasLetters = /[a-zA-Z]/.test(p1);
    let hasNumbers = /[0-9]/.test(p1);
    let hasLength = p1.length >= 8;
    let isValid = hasLength && hasLetters && hasNumbers;
    
    if (p2 === '') {
        text.textContent = '';
        btn.disabled = !isValid;
    } else if (p1 === p2) {
        if (!isValid) {
            text.textContent = 'Passwords match, but minimum requirements are not met (8+ chars with letters & numbers)';
            text.className = 'text-warning small fw-medium mt-1 d-block';
            btn.disabled = true;
        } else {
            text.textContent = 'Passwords match and meet security requirements';
            text.className = 'text-success small fw-medium mt-1 d-block';
            btn.disabled = false;
        }
    } else {
        text.textContent = 'Passwords do not match';
        text.className = 'text-danger small fw-medium mt-1 d-block';
        btn.disabled = true;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
