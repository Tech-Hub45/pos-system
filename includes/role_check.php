<?php
// includes/role_check.php
// Usage: require_once __DIR__ . '/role_check.php';
// Call enforce_role(['Admin', 'Manager']); // allowed roles array

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Enforce that the currently logged‑in user has one of the allowed roles.
 * If not, send a 403 response and exit.
 */
function enforce_role(array $allowedRoles) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        echo "Unauthorized – please log in.";
        exit();
    }
    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo "Forbidden – you do not have permission to view this page.";
        exit();
    }
}
?>
