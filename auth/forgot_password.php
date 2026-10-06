<?php
session_start();
require_once '../config/session.php';

// If already logged in, redirect
if (isLoggedIn()) {
    if ($_SESSION['user_type'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../resident/dashboard.php");
    }
    exit;
}

$pageTitle = "Forgot Password";
$bodyClass = "auth-page";
$step = (int)($_POST['step'] ?? 1);
$account_type = $_POST['account_type'] ?? 'resident';
$identifier = trim($_POST['identifier'] ?? '');
$error = '';
$success = '';

$question = '';
$verify_mode = 'question'; // 'question', 'details', 'staff_email'

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($step === 1) {
            // STEP 1: Identify Account
            if (empty($identifier)) {
                $error = "Please enter your " . ($account_type === 'admin' ? "Username" : "Resident Number") . ".";
            } else {
                if ($account_type === 'admin') {
                    $adminUser = fb_find_one('admins', 'username', $identifier);
                    if ($adminUser) {
                        if (($adminUser['status'] ?? 'active') !== 'active') {
                            $error = "This account is currently disabled. Please contact the system administrator.";
                        } else {
                            $verify_mode = 'staff_email';
                            $step = 2;
                        }
                    } else {
                        $error = "Account not found with that username.";
                    }
                } else {
                    $resUser = fb_find_one('residents', 'resident_number', $identifier);
                    if ($resUser) {
                        if (($resUser['status'] ?? 'active') !== 'active') {
                            $error = "This account is currently disabled. Please contact the barangay administrator.";
                        } else {
                            if (!empty($resUser['security_question'])) {
                                $question = $resUser['security_question'];
                                $verify_mode = 'question';
                            } else {
                                $verify_mode = 'details';
                            }
                            $step = 2;
                        }
                    } else {
                        $error = "No resident account found with that Resident Number.";
                    }
                }
            }
        } elseif ($step === 2) {
            // STEP 2: Verify Identity
            $verify_mode = $_POST['verify_mode'] ?? 'question';
            $is_verified = false;

            if ($account_type === 'admin') {
                $verify_email = trim($_POST['verify_email'] ?? '');
                $found = false;
                foreach (fb_all('admins') as $a) {
                    if (($a['username'] ?? '') === $identifier && strtolower($a['email'] ?? '') === strtolower($verify_email) && ($a['status'] ?? '') === 'active') { $found = true; break; }
                }
                if ($found) {
                    $is_verified = true;
                } else {
                    $error = "Verification failed. The email address does not match our records.";
                }
            } else {
                if ($verify_mode === 'question') {
                    $answer = trim($_POST['answer'] ?? '');
                    $question = $_POST['question'] ?? '';
                    $found = false;
                    foreach (fb_all('residents') as $r) {
                        if (($r['resident_number'] ?? '') === $identifier && strtolower($r['security_answer'] ?? '') === strtolower($answer) && ($r['status'] ?? '') === 'active') { $found = true; break; }
                    }
                    if ($found) {
                        $is_verified = true;
                    } else {
                        $error = "Incorrect answer to the security question.";
                    }
                } else {
                    // Fallback verification via registered birthdate and phone
                    $verify_birthdate = trim($_POST['verify_birthdate'] ?? '');
                    $verify_phone = trim($_POST['verify_phone'] ?? '');
                    $found = false;
                    foreach (fb_all('residents') as $r) {
                        if (($r['resident_number'] ?? '') === $identifier && ($r['birthdate'] ?? '') === $verify_birthdate && ($r['phone'] ?? '') === $verify_phone && ($r['status'] ?? '') === 'active') { $found = true; break; }
                    }
                    if ($found) {
                        $is_verified = true;
                    } else {
                        $error = "Verification failed. The birthdate or contact number does not match our records.";
                    }
                }
            }

            if ($is_verified) {
                $step = 3;
            }
        } elseif ($step === 3) {
            // STEP 3: Reset Password
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (strlen($new_password) < 8 || !preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                $error = "Password must be at least 8 characters long and contain both letters and numbers.";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);

                if ($account_type === 'admin') {
                    $adm = fb_find_one('admins', 'username', $identifier);
                    if ($adm) fb_update_rec('admins', $adm['_id'], ['password' => $hashed, 'updated_at' => fb_now()]);

                    $admInfo = fb_find_one('admins', 'username', $identifier);
                    if ($admInfo) {
                        fb_log($admInfo['_id'], 'admin', 'Password Recovery', 'Staff password recovered successfully');
                    }
                } else {
                    $res = fb_find_one('residents', 'resident_number', $identifier);
                    if ($res) fb_update_rec('residents', $res['_id'], ['password' => $hashed, 'first_login' => 0, 'updated_at' => fb_now()]);

                    $resInfo = fb_find_one('residents', 'resident_number', $identifier);
                    if ($resInfo) {
                        $resName = trim(($resInfo['first_name'] ?? '') . ' ' . ($resInfo['last_name'] ?? ''));
                        fb_log($resInfo['_id'], 'resident', 'Password Recovery', 'Resident recovered password via security verification');

                        notifyAdmins(
                            null,
                            "Resident Password Recovered",
                            "Resident $identifier ($resName) successfully reset their password via recovery verification.",
                            "/admin/manage_residents.php",
                            "bi-shield-lock text-warning",
                            $resInfo['_id'],
                            "resident"
                        );
                    }
                }

                $success = "Your password has been reset successfully. You can now log in with your new password.";
                $step = 4;
            }
        }
    }
} catch (Exception $e) {
    $error = "A system error occurred. Please try again later.";
}

require_once '../includes/header.php';
?>

<div class="login-container">
    <div class="login-left d-none d-md-flex">
        <span class="brand-mark mb-4" style="width:52px;height:52px;font-size:1.5rem;"><i class="bi bi-shield-check"></i></span>
        <h2>Barangay Health Center</h2>
        <h4>Password Recovery</h4>
        <p class="mt-4">Verify your identity to securely set a new password and regain access to your account.</p>
        <div class="mt-auto pt-3 small login-side-note">
            <i class="bi bi-shield-check me-1"></i> Secure identity verification
        </div>
    </div>
    <div class="login-right">
        <h3 class="mb-3 text-center login-heading"><i class="bi bi-key text-primary me-2"></i>Forgot Password</h3>
        
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <p class="text-muted small text-center mb-3">Select your account type and enter your identifier to start the recovery process.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="step" value="1">
                
                <ul class="nav nav-tabs role-tabs mb-3 justify-content-center" id="recoveryRoleTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $account_type === 'resident' ? 'active' : '' ?>" type="button" onclick="switchRecoveryRole('resident')">Resident</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $account_type === 'admin' ? 'active' : '' ?>" type="button" onclick="switchRecoveryRole('admin')">Staff / Admin</button>
                    </li>
                </ul>
                
                <input type="hidden" name="account_type" id="recovery_account_type" value="<?= htmlspecialchars($account_type) ?>">

                <div class="mb-3">
                    <label for="identifier" id="recovery_label" class="form-label fw-medium"><?= $account_type === 'admin' ? 'Username' : 'Resident Number' ?></label>
                    <input type="text" class="form-control" id="identifier" name="identifier" required value="<?= htmlspecialchars($identifier) ?>" placeholder="<?= $account_type === 'admin' ? 'e.g. admin' : 'e.g. RES-2026-0001' ?>">
                </div>
                
                <button type="submit" class="btn btn-gradient w-100 py-2 rounded-pill mt-2">Next Step <i class="bi bi-arrow-right ms-1"></i></button>
                
                <div class="text-center mt-3">
                    <a href="login.php" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left me-1"></i> Back to Login</a>
                </div>
            </form>

        <?php elseif ($step === 2): ?>
            <p class="text-muted small text-center mb-3">Please verify your identity to proceed with resetting your password.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="step" value="2">
                <input type="hidden" name="account_type" value="<?= htmlspecialchars($account_type) ?>">
                <input type="hidden" name="identifier" value="<?= htmlspecialchars($identifier) ?>">
                <input type="hidden" name="verify_mode" value="<?= htmlspecialchars($verify_mode) ?>">
                <input type="hidden" name="question" value="<?= htmlspecialchars($question) ?>">

                <?php if ($account_type === 'admin'): ?>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Registered Email Address</label>
                        <input type="email" name="verify_email" class="form-control" required placeholder="Enter the email associated with your account">
                        <small class="text-muted">Enter the email registered with username <strong><?= htmlspecialchars($identifier) ?></strong></small>
                    </div>
                <?php else: ?>
                    <?php if ($verify_mode === 'question'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-medium text-primary"><i class="bi bi-question-circle me-1"></i> Security Question</label>
                            <div class="p-2 bg-light rounded border mb-2 fw-semibold text-dark">
                                <?= htmlspecialchars($question) ?>
                            </div>
                            <label class="form-label fw-medium">Your Answer</label>
                            <input type="text" name="answer" class="form-control" required placeholder="Enter your answer" autofocus>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-info-circle me-1"></i> Please verify using your registered birthdate and contact number.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Birthdate</label>
                            <input type="date" name="verify_birthdate" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Registered Contact Number (11 digits)</label>
                            <input type="tel" name="verify_phone" class="form-control" required placeholder="09171234567" pattern="[0-9]{11}">
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <button type="submit" class="btn btn-gradient w-100 py-2 rounded-pill mt-2">Verify Identity <i class="bi bi-shield-check ms-1"></i></button>
                
                <div class="text-center mt-3">
                    <a href="forgot_password.php" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left me-1"></i> Start Over</a>
                </div>
            </form>

        <?php elseif ($step === 3): ?>
            <p class="text-muted small text-center mb-3">Identity verified! Set a new password for account <strong><?= htmlspecialchars($identifier) ?></strong>.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="step" value="3">
                <input type="hidden" name="account_type" value="<?= htmlspecialchars($account_type) ?>">
                <input type="hidden" name="identifier" value="<?= htmlspecialchars($identifier) ?>">

                <div class="mb-3">
                    <label class="form-label fw-medium">New Password</label>
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
                    <label class="form-label fw-medium">Confirm Password</label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required onkeyup="checkMatch()">
                        <span class="pw-toggle-btn" data-pw-toggle="confirm_password" title="Show/hide password" role="button" aria-label="Toggle password visibility">
                            <i class="bi bi-eye-slash"></i>
                        </span>
                    </div>
                    <small id="match_text" class="text-muted d-block mt-1"></small>
                </div>

                <button type="submit" class="btn btn-gradient w-100 py-2 rounded-pill mt-2" id="submit_btn">Reset Password</button>
            </form>

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
                        text.textContent = 'Fair (Meets requirements)';
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

        <?php elseif ($step === 4): ?>
            <div class="text-center py-3">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                <h4 class="mt-3 mb-2 fw-bold text-success">Password Reset Successful!</h4>
                <p class="text-muted small mb-4"><?= htmlspecialchars($success) ?></p>
                <a href="login.php" class="btn btn-gradient px-4 py-2 rounded-pill fs-6"><i class="bi bi-box-arrow-in-right me-1"></i> Proceed to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchRecoveryRole(role) {
    document.getElementById('recovery_account_type').value = role;
    const label = document.getElementById('recovery_label');
    const identifierInput = document.getElementById('identifier');
    if (role === 'admin') {
        label.innerText = 'Username';
        identifierInput.placeholder = 'e.g. admin';
    } else {
        label.innerText = 'Resident Number';
        identifierInput.placeholder = 'e.g. RES-2026-0001';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
