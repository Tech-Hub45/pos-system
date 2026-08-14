<?php
require_once __DIR__ . '/includes/header.php';
require_roles(['Cashier', 'Admin', 'Manager']); // All roles can view their session stats if needed, but primary is Cashier

$cashierId = $currentUser['id'];

try {
    // 1. Personal metrics today
    $mySalesStmt = $pdo->prepare("SELECT SUM(total_amount) FROM sales WHERE user_id = ? AND DATE(created_at) = CURDATE()");
    $mySalesStmt->execute([$cashierId]);
    $mySalesToday = (float)$mySalesStmt->fetchColumn();

    $myOrdersStmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE user_id = ? AND DATE(created_at) = CURDATE()");
    $myOrdersStmt->execute([$cashierId]);
    $myOrdersToday = (int)$myOrdersStmt->fetchColumn();

    // 2. Total active products count
    $productsCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

    // 3. Low stock alerts (Read-only notice for cashiers)
    $lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= min_stock_qty")->fetchColumn();

    // 4. Personal recent sales transactions
    $myRecentSalesStmt = $pdo->prepare("
        SELECT * FROM sales 
        WHERE user_id = ? 
        ORDER BY id DESC LIMIT 5
    ");
    $myRecentSalesStmt->execute([$cashierId]);
    $myRecentSales = $myRecentSalesStmt->fetchAll();

} catch (PDOException $e) {
    die("Cashier dashboard query error: " . $e->getMessage());
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Cashier Dashboard</h2>
        <p>Welcome back, <?= htmlspecialchars($currentUser['full_name']) ?>! Track your personal daily registers and open checkout terminals</p>
    </div>
    <div class="header-actions">
        <a href="index.php" class="btn btn-primary" style="padding: 12px 24px; font-size: 15px; width: auto; box-shadow: var(--shadow-md);">
            <i class="fa-solid fa-cart-plus"></i> Open Sales Register (POS)
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card" style="border-top: 4px solid var(--success);">
        <div class="stat-header">
            <span class="stat-title">My Sales Today</span>
            <div class="stat-icon success"><i class="fa-solid fa-sack-dollar"></i></div>
        </div>
        <span class="stat-value"><?= format_currency($mySalesToday) ?></span>
    </div>

    <div class="stat-card" style="border-top: 4px solid var(--primary);">
        <div class="stat-header">
            <span class="stat-title">My Transactions Today</span>
            <div class="stat-icon primary"><i class="fa-solid fa-receipt"></i></div>
        </div>
        <span class="stat-value"><?= $myOrdersToday ?> sales</span>
    </div>

    <div class="stat-card" style="border-top: 4px solid var(--warning);">
        <div class="stat-header">
            <span class="stat-title">Store Products catalog</span>
            <div class="stat-icon warning"><i class="fa-solid fa-boxes-stacked"></i></div>
        </div>
        <span class="stat-value"><?= $productsCount ?> items</span>
    </div>

    <div class="stat-card" style="border-top: 4px solid var(--danger);">
        <div class="stat-header">
            <span class="stat-title">Low Stock Alert Items</span>
            <div class="stat-icon danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <span class="stat-value" style="color: <?= $lowStockCount > 0 ? 'var(--danger)' : 'inherit' ?>;"><?= $lowStockCount ?> warnings</span>
    </div>
</div>

<!-- Main Section Quick Navigation -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
    <!-- Cashier Actions Box -->
    <div class="data-card" style="display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 40px; gap: 20px;">
        <div style="width: 70px; height: 70px; border-radius: 50%; background-color: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 32px;">
            <i class="fa-solid fa-cash-register"></i>
        </div>
        <div>
            <h3 style="font-weight: 700; margin-bottom: 8px;">POS Checkout Terminal</h3>
            <p style="color: var(--text-secondary); font-size: 14px; max-width: 320px; margin: 0 auto;">
                Start processing customer items, scan product barcodes, calculate billing totals, and issue printed invoices.
            </p>
        </div>
        <a href="index.php" class="btn btn-primary" style="width: auto; padding: 14px 32px;">
            <i class="fa-solid fa-circle-play"></i> Launch Sales Register
        </a>
    </div>

    <!-- Personal Sales Logs -->
    <div class="data-card">
        <h3 style="margin-bottom: 15px; font-weight: 700;">My Recent Transactions Today</h3>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Time</th>
                        <th>Method</th>
                        <th style="text-align: right;">Total Bill</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myRecentSales)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">You have not processed any sales today yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($myRecentSales as $row): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($row['invoice_no']) ?></code></td>
                                <td><?= date('H:i:s', strtotime($row['created_at'])) ?></td>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($row['payment_method']) ?></span></td>
                                <td style="text-align: right; font-weight: 700; color: var(--text-primary);">
                                    <?= format_currency($row['total_amount']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
