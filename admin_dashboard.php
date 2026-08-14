<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/role_check.php';
// Only Admin can access this dashboard
enforce_role(['Admin']);

try {
    // 1. Admin metrics
    $totalUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $todaySales    = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= min_stock_qty")->fetchColumn();

    // 2. Recent audit logs
    $recentLogs = $pdo->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5")->fetchAll();

    // 3. Recent users
    $recentUsers = $pdo->query("SELECT id, username, full_name, role, created_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll();

} catch (PDOException $e) {
    die("Admin dashboard error: " . $e->getMessage());
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Admin Dashboard</h2>
        <p>System overview, user accounts, and activity monitoring</p>
    </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Total Users</span>
            <div class="stat-icon primary"><i class="fa-solid fa-users"></i></div>
        </div>
        <span class="stat-value"><?= $totalUsers ?></span>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Total Products</span>
            <div class="stat-icon success"><i class="fa-solid fa-box"></i></div>
        </div>
        <span class="stat-value"><?= $totalProducts ?></span>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Today's Revenue</span>
            <div class="stat-icon warning"><i class="fa-solid fa-dollar-sign"></i></div>
        </div>
        <span class="stat-value"><?= format_currency($todaySales) ?></span>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Low Stock Alerts</span>
            <div class="stat-icon danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <span class="stat-value"><?= $lowStockCount ?></span>
    </div>
</div>

<!-- Quick Actions -->
<div class="data-card">
    <h3 style="margin-bottom: 16px; font-weight: 700; font-size: 16px;">Quick Actions</h3>
    <div class="quick-actions-grid">
        <a href="users.php" class="quick-action-btn">
            <i class="fa-solid fa-user-plus"></i>
            Manage Users
        </a>
        <a href="products.php" class="quick-action-btn">
            <i class="fa-solid fa-boxes-stacked"></i>
            Manage Products
        </a>
        <a href="products.php?add=1" class="quick-action-btn">
            <i class="fa-solid fa-plus-circle"></i>
            Add Product
        </a>
        <a href="suppliers.php" class="quick-action-btn">
            <i class="fa-solid fa-truck-field"></i>
            Suppliers
        </a>
        <a href="suppliers.php?action=add" class="quick-action-btn">
            <i class="fa-solid fa-user-plus"></i>
            Add Supplier
        </a>
        <a href="inventory.php" class="quick-action-btn">
            <i class="fa-solid fa-warehouse"></i>
            Inventory
        </a>
        <a href="reports.php" class="quick-action-btn">
            <i class="fa-solid fa-chart-bar"></i>
            Sales Reports
        </a>
    </div>
</div>

<!-- Recent Users -->
<div class="data-card">
    <h3 style="margin-bottom: 16px; font-weight: 700; font-size: 16px;">Recent Users</h3>
    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentUsers as $u): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($u['role']) ?></span></td>
                        <td><?= $u['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Audit Logs -->
<div class="data-card">
    <h3 style="margin-bottom: 16px; font-weight: 700; font-size: 16px;">Recent Activity</h3>
    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Details</th>
                    <th>User</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td><span class="badge badge-warning"><?= htmlspecialchars($log['action']) ?></span></td>
                        <td style="max-width: 300px; word-break: break-word;"><?= htmlspecialchars($log['details']) ?></td>
                        <td><?= htmlspecialchars($log['user_id']) ?></td>
                        <td><?= $log['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
