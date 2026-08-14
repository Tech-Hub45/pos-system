<?php
require_once __DIR__ . '/config/db.php';

// Redirect to role-specific dashboard page
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] === 'Admin') {
    header("Location: admin_dashboard.php");
} elseif ($_SESSION['role'] === 'Manager') {
    header("Location: manager_dashboard.php");
} else {
    header("Location: cashier_dashboard.php");
}
exit();
?>
