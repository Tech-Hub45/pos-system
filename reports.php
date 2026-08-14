<?php
require_once __DIR__ . '/config/db.php';

// Check roles: Admin, Manager only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Manager'])) {
    $_SESSION['flash_error'] = "You do not have access to reports.";
    header("Location: index.php");
    exit();
}

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// --- DATABASE QUERIES ---

// 1. Profit & Loss Calculations
// Revenue = Sum of sale totals
// Cost of Goods Sold (COGS) = Sum of (items quantity * products buy_price)
// Gross Profit = Revenue - COGS
try {
    // Total Revenue in date range
    $revStmt = $pdo->prepare("SELECT SUM(total_amount) FROM sales WHERE DATE(created_at) BETWEEN ? AND ?");
    $revStmt->execute([$startDate, $endDate]);
    $revenue = (float)$revStmt->fetchColumn();

    // Cost of Goods Sold in date range
    $cogsStmt = $pdo->prepare("
        SELECT SUM(si.quantity * p.buy_price) 
        FROM sale_items si 
        JOIN sales s ON si.sale_id = s.id 
        JOIN products p ON si.product_id = p.id 
        WHERE DATE(s.created_at) BETWEEN ? AND ?
    ");
    $cogsStmt->execute([$startDate, $endDate]);
    $cogs = (float)$cogsStmt->fetchColumn();

    $profit = $revenue - $cogs;

    // 2. Sales records details for Table
    $salesQuery = "
        SELECT s.*, u.full_name as cashier_name, COUNT(si.id) as item_count
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN sale_items si ON si.sale_id = s.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY s.id
        ORDER BY s.created_at DESC
    ";
    $salesStmt = $pdo->prepare($salesQuery);
    $salesStmt->execute([$startDate, $endDate]);
    $sales = $salesStmt->fetchAll();

} catch (PDOException $e) {
    die("Reports database query failure: " . $e->getMessage());
}

// --- CSV EXPORT GENERATOR ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Sales_Report_' . $startDate . '_to_' . $endDate . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Add Report Header
    fputcsv($output, ['NEXUSPOS SALES REPORT']);
    fputcsv($output, ['Period:', $startDate . ' to ' . $endDate]);
    fputcsv($output, []);
    
    // Add Financial Summary
    fputcsv($output, ['FINANCIAL SUMMARY']);
    fputcsv($output, ['Total Revenue', '$' . number_format($revenue, 2)]);
    fputcsv($output, ['Cost of Goods Sold (COGS)', '$' . number_format($cogs, 2)]);
    fputcsv($output, ['Gross Profit', '$' . number_format($profit, 2)]);
    fputcsv($output, []);
    
    // Add Table Header
    fputcsv($output, ['Invoice No', 'Date', 'Cashier', 'Payment Method', 'Items Count', 'Total Paid', 'Total Amount']);
    
    foreach ($sales as $row) {
        fputcsv($output, [
            $row['invoice_no'],
            $row['created_at'],
            $row['cashier_name'] ?? 'N/A',
            $row['payment_method'],
            $row['item_count'],
            '$' . number_format($row['amount_paid'], 2),
            '$' . number_format($row['total_amount'], 2)
        ]);
    }
    
    fclose($output);
    exit();
}

// Include regular HTML view
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h2>Sales & Profitability Reports</h2>
        <p>Analyze business revenue, cost margins, transaction invoices, and download spreadsheet logs</p>
    </div>
    <div class="header-actions">
        <a href="reports.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=csv" class="btn btn-success" style="width: auto;">
            <i class="fa-solid fa-file-excel"></i> Export to Excel (CSV)
        </a>
    </div>
</div>

<!-- Date filters panel -->
<div class="data-card" style="margin-bottom: 24px; padding: 18px 24px;">
    <form action="reports.php" method="GET" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
            <label for="start_date" style="margin-bottom: 6px;">Start Date</label>
            <input type="date" name="start_date" id="start_date" class="input-field" value="<?= $startDate ?>">
        </div>
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
            <label for="end_date" style="margin-bottom: 6px;">End Date</label>
            <input type="date" name="end_date" id="end_date" class="input-field" value="<?= $endDate ?>">
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="width: auto;">
                <i class="fa-solid fa-sync"></i> Generate Report
            </button>
            <a href="reports.php" class="btn btn-secondary" style="width: auto;">Clear</a>
        </div>
    </form>
</div>

<!-- Profit & Loss Summary Card -->
<div class="data-card" style="margin-bottom: 24px; border-left: 5px solid var(--primary);">
    <h3 style="margin-bottom: 16px; font-weight: 700; font-size: 16px;">Profit & Loss Statement (P&L Summary)</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div>
            <span style="font-size: 13px; color: var(--text-muted); font-weight: 600;">Total Revenue</span>
            <h2 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-top: 4px;"><?= format_currency($revenue) ?></h2>
        </div>
        <div>
            <span style="font-size: 13px; color: var(--text-muted); font-weight: 600;">Cost of Goods Sold (COGS)</span>
            <h2 style="font-size: 24px; font-weight: 700; color: var(--text-secondary); margin-top: 4px;"><?= format_currency($cogs) ?></h2>
        </div>
        <div>
            <span style="font-size: 13px; color: var(--text-muted); font-weight: 600;">Gross Profit Margins</span>
            <h2 style="font-size: 24px; font-weight: 700; color: <?= $profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>; margin-top: 4px;">
                <?= format_currency($profit) ?>
            </h2>
        </div>
    </div>
</div>

<!-- Sales Invoices Table -->
<div class="data-card">
    <h3 style="margin-bottom: 16px; font-weight: 700; font-size: 16px;">Detailed Invoices List</h3>
    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Invoice Number</th>
                    <th>Date & Time</th>
                    <th>Cashier</th>
                    <th>Payment Method</th>
                    <th>Items Sold</th>
                    <th>Amount Paid</th>
                    <th style="text-align: right;">Total Bill</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted);">No sales recorded in selected timeframe.</td></tr>
                <?php else: ?>
                    <?php foreach ($sales as $s): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($s['invoice_no']) ?></code></td>
                            <td><?= $s['created_at'] ?></td>
                            <td><?= htmlspecialchars($s['cashier_name'] ?? 'N/A') ?></td>
                            <td>
                                <span class="badge badge-primary"><?= htmlspecialchars($s['payment_method']) ?></span>
                            </td>
                            <td><?= $s['item_count'] ?> items</td>
                            <td><?= format_currency($s['amount_paid']) ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--text-primary);">
                                <?= format_currency($s['total_amount']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
