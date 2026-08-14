<?php
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    log_action($pdo, 'LOGOUT', "User {$_SESSION['username']} logged out.");
}

// Clear all session variables
$_SESSION = [];

// Destroy session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login
header("Location: login.php");
exit();
?>
