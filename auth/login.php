<?php
require_once '../config/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if ($_SESSION['user_type'] === 'admin') {
        header("Location: " . BASE_URL . "/admin/dashboard.php");
    } else {
        header("Location: " . BASE_URL . "/resident/dashboard.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'resident';
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = "Please enter all required fields.";
    } else {
        try {
            if ($role === 'admin') {
                $user = fb_find_one('admins', 'username', $identifier);
            } else {
                $user = fb_find_one('residents', 'resident_number', $identifier);
            }

            if ($user && password_verify($password, fb_pass_hash($user))) {
                if (isset($user['status']) && $user['status'] !== 'active') {
                    $error = "Your account is currently disabled. Please contact the administrator.";
                } else {
                    session_regenerate_id(true);
                    $uid = $user['_id'];
                    $_SESSION['user_id'] = $uid;
                    $_SESSION['user_type'] = $role;
                    $_SESSION['role'] = $user['role'] ?? ($role === 'admin' ? 'admin' : 'resident');

                    if ($role === 'admin') {
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                    } else {
                        $_SESSION['username'] = $user['resident_number'];
                        $_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                        $_SESSION['first_login'] = $user['first_login'] ?? 0;
                        initializeResidentNotifications($uid, $user['first_name'] ?? '');
                    }

                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                    fb_log($uid, $role, 'Login', 'User logged in successfully');
                    $hid = fb_next_id('login_history');
                    fb_set_rec('login_history', $hid, ['user_id' => $uid, 'user_type' => $role, 'login_time' => fb_now(), 'logout_time' => null, 'ip_address' => $ip_address]);
                    $_SESSION['login_history_id'] = $hid;

                    if ($role === 'resident' && ($user['first_login'] ?? 0) == 1) {
                        header("Location: change_password.php");
                    } else {
                        if ($role === 'admin') {
                            header("Location: ../admin/dashboard.php");
                        } else {
                            header("Location: ../resident/dashboard.php");
                        }
                    }
                    exit;
                }
            } else {
                $error = "Invalid credentials. Please try again.";
            }
        } catch (Exception $e) {
            $error = "System error occurred. Please try again later.";
        }
    }
}

$pageTitle = "Login";
$bodyClass = "auth-page";
require_once '../includes/header.php';
?>

<div class="login-container">
    <div class="login-left d-none d-md-flex">
        <span class="brand-mark mb-4" style="width:52px;height:52px;font-size:1.5rem;"><i class="bi bi-heart-pulse-fill"></i></span>
        <h2>Barangay Health Center</h2>
        <h4>Survey Management System</h4>
        <p class="mt-4">Welcome back. Manage community health surveys and support better care for barangay residents.</p>
        <div class="mt-auto pt-4 small login-side-note">Secure access for residents and staff</div>
    </div>
    <div class="login-right">
        <h3 class="mb-1 text-center login-heading">Sign in</h3>
        <p class="text-muted text-center mb-4">Choose your role to continue</p>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <ul class="nav nav-tabs role-tabs mb-4 justify-content-center" id="roleTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="resident-tab" data-bs-toggle="tab" data-bs-target="#resident-pane" type="button" role="tab" onclick="switchRole('resident')">Resident</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin-pane" type="button" role="tab" onclick="switchRole('admin')">Admin</button>
                </li>
            </ul>

            <input type="hidden" name="role" id="role_input" value="resident">

            <div class="mb-3">
                <label for="identifier" id="identifier_label" class="form-label">Resident Number</label>
                <input type="text" class="form-control" id="identifier" name="identifier" required value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required>
                    <span class="pw-toggle-btn" data-pw-toggle="password" title="Show/hide password" role="button" aria-label="Toggle password visibility">
                        <i class="bi bi-eye-slash" id="toggleIcon"></i>
                    </span>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="rememberMe">
                    <label class="form-check-label" for="rememberMe">Remember me</label>
                </div>
                <a href="forgot_password.php" id="forgot_link" class="text-decoration-none">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-gradient w-100 py-2 fs-5 rounded-pill">Login</button>
        </form>
    </div>
</div>

<script>
    function switchRole(role) {
        document.getElementById('role_input').value = role;
        const identifierLabel = document.getElementById('identifier_label');
        const forgotLink = document.getElementById('forgot_link');

        if (role === 'admin') {
            identifierLabel.innerText = 'Username';
        } else {
            identifierLabel.innerText = 'Resident Number';
        }
        if (forgotLink) forgotLink.style.display = 'inline';
    }
</script>
<?php require_once '../includes/footer.php'; ?>
