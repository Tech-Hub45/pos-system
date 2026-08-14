<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/role_check.php';
// Only Admins can access this dashboard
enforce_role(['Admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>
    <main class="dashboard">
        <h1 class="fade-in">Admin Dashboard</h1>
        <section class="card-grid">
            <a href="admin/users.php" class="card glass">
                <h2>User Management</h2>
                <p>Create, edit, and deactivate user accounts.</p>
            </a>
            <a href="admin/products.php" class="card glass">
                <h2>Product Management</h2>
                <p>Add, update, delete, and categorize products.</p>
            </a>
            <a href="manager/inventory.php" class="card glass">
                <h2>Inventory Overview</h2>
                <p>Track stock levels and receive low‑stock alerts.</p>
            </a>
            <a href="api/report_sales.php" class="card glass" target="_blank">
                <h2>Sales Reports</h2>
                <p>View daily, weekly and monthly sales analytics.</p>
            </a>
        </section>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
