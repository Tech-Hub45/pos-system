<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/role_check.php';
// Only Managers can access this dashboard
enforce_role(['Manager']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <script src="../assets/js/theme.js" defer></script>
</head>
<body>
    <main class="dashboard">
        <h1 class="fade-in">Manager Dashboard</h1>
        <section class="card-grid">
            <div class="card glass" id="sales-overview">
                <h2>Sales Overview</h2>
                <canvas id="salesChart"></canvas>
            </div>
            <div class="card glass" id="stock-alerts">
                <h2>Low Stock Alerts</h2>
                <ul id="lowStockList"></ul>
            </div>
            <div class="card glass" id="activity-log">
                <h2>Recent Activity</h2>
                <ul id="activityLog"></ul>
            </div>
        </section>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
