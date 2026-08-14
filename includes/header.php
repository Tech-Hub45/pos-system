<?php
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$currentUser = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role'],
    'full_name' => $_SESSION['full_name']
];

$activePage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPOS - Dashboard</title>
    <!-- CSS Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="layout-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-icon">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
                <div class="sidebar-brand-name">NexusPOS</div>
            </div>

            <ul class="sidebar-menu">
                <!-- POS Register: All users -->
                <li class="sidebar-menu-item <?= ($activePage == 'index.php') ? 'active' : '' ?>">
                    <a href="index.php">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span>POS Register</span>
                    </a>
                </li>

                <!-- Role-Specific Dashboards -->
                <?php if ($currentUser['role'] === 'Admin'): ?>
                    <li class="sidebar-menu-item <?= ($activePage == 'admin_dashboard.php') ? 'active' : '' ?>">
                        <a href="admin_dashboard.php">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>Admin Dashboard</span>
                        </a>
                    </li>
                <?php elseif ($currentUser['role'] === 'Manager'): ?>
                    <li class="sidebar-menu-item <?= ($activePage == 'manager_dashboard.php') ? 'active' : '' ?>">
                        <a href="manager_dashboard.php">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>Manager Dashboard</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="sidebar-menu-item <?= ($activePage == 'cashier_dashboard.php') ? 'active' : '' ?>">
                        <a href="cashier_dashboard.php">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>Cashier Dashboard</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (in_array($currentUser['role'], ['Admin', 'Manager'])): ?>
                    <li class="sidebar-menu-item <?= ($activePage == 'products.php') ? 'active' : '' ?>">
                        <a href="products.php">
                            <i class="fa-solid fa-box"></i>
                            <span>Products</span>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="sidebar-menu-item <?= ($activePage == 'inventory.php') ? 'active' : '' ?>">
                    <a href="inventory.php">
                        <i class="fa-solid fa-warehouse"></i>
                        <span>Inventory</span>
                    </a>
                </li>

                <?php if (in_array($currentUser['role'], ['Admin', 'Manager'])): ?>
                    <li class="sidebar-menu-item <?= ($activePage == 'suppliers.php') ? 'active' : '' ?>">
                        <a href="suppliers.php">
                            <i class="fa-solid fa-truck-field"></i>
                            <span>Suppliers</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (in_array($currentUser['role'], ['Admin', 'Manager'])): ?>
                    <li class="sidebar-menu-item <?= ($activePage == 'reports.php') ? 'active' : '' ?>">
                        <a href="reports.php">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($currentUser['role'] === 'Admin'): ?>
                    <li class="sidebar-menu-item <?= ($activePage == 'users.php') ? 'active' : '' ?>">
                        <a href="users.php">
                            <i class="fa-solid fa-users-gear"></i>
                            <span>User Management</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($currentUser['role'] === 'Admin'): ?>
                    <li class="sidebar-menu-item <?= ($activePage == 'audit_logs.php') ? 'active' : '' ?>">
                        <a href="audit_logs.php">
                            <i class="fa-solid fa-clipboard-list"></i>
                            <span>Audit Logs</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Sidebar Footer: user info + theme toggle + logout -->
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                    <span class="user-role"><?= htmlspecialchars($currentUser['role']) ?></span>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button id="theme-toggle" class="theme-toggle-btn" title="Toggle Theme">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                    <a href="logout.php" class="btn btn-secondary" style="padding: 8px 14px; width: auto;">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <?= $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
                </div>
            <?php endif; ?>
