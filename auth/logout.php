<?php
require_once '../config/session.php';

if (isLoggedIn()) {
    try {
        if (isset($_SESSION['login_history_id'])) {
            $h = fb_get_rec('login_history', (string)$_SESSION['login_history_id']);
            if ($h) fb_update_rec('login_history', (string)$_SESSION['login_history_id'], ['logout_time' => fb_now()]);
        }
        fb_log($_SESSION['user_id'], $_SESSION['user_type'], 'Logout', 'User logged out');
    } catch (Exception $e) {
        // Continue with logout even if db fails
    }
}

// Clear all session variables
$_SESSION = [];

// Invalidate session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_unset();
session_destroy();

setNoCacheHeaders();
header("Location: " . BASE_URL . "/auth/login.php");
exit;
