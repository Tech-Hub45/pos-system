<?php
require_once __DIR__ . '/includes/header.php';
require_roles(['Manager', 'Admin']); // Manager (and Admin as backup) can view this

$taxRate = (float)get_system_setting($pdo, 'tax_rate', '8.00');

try {
    // 1. Manager metrics
    $todaySales = (float)$pdo->query("SELECT SUM(total_amount) FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $productsCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= min_stock_qty")->fetchColumn();
    $totalSuppliers = (int)$pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();

    // 2. Critical Stock Levels
    $criticalItems = $pdo->query("
        SELECT name, stock_qty, min_stock_qty, category 
        FROM products 
        WHERE stock_qty <= min_stock_qty 
        ORDER BY stock_qty ASC LIMIT 5
    ")->fetchAll();

    // 3. Recent Sales Transactions
    $recentSales = $pdo->query("
        SELECT s.*, u.full_name as cashier_name 
        FROM sales s 
        LEFT JOIN users u ON s.user_id = u.id 
        ORDER BY s.id DESC LIMIT 5
    ")->fetchAll();

    // 4. Sales Trend Line Chart (Last 7 Days)
    $weeklyTrend = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%b %d') as sale_date, SUM(total_amount) as daily_total
        FROM sales
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY created_at ASC
    ")->fetchAll();
    $trendLabels = [];
    $trendValues = [];
    foreach ($weeklyTrend as $row) {
        $trendLabels[] = $row['sale_date'];
        $trendValues[] = (float)$row['daily_total'];
    }

} catch (PDOException $e) {
    die("Manager dashboard query error: " . $e->getMessage());
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Manager Dashboard</h2>
        <p>Operational control panel: inventory monitors, procurement logs, and sales performance analysis</p>
    </div>
</div>

<div class="data-card" style="margin-bottom: 24px; max-width: 520px;">
    <h3 style="margin-bottom: 12px; font-weight: 700;">Tax Configuration</h3>
    <form id="tax-settings-form">
        <div class="form-group" style="margin-bottom: 0;">
            <label for="manager-tax-rate">Current Tax %</label>
            <div style="display: flex; gap: 12px; align-items: center;">
                <input type="number" id="manager-tax-rate" class="input-field" min="0" max="100" step="0.01" value="<?= htmlspecialchars($taxRate) ?>" style="flex: 1;">
                <button type="submit" class="btn btn-primary" style="width: auto; padding: 12px 18px;">Update Tax</button>
            </div>
        </div>
    </form>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card" style="border-top: 4px solid var(--success);">
        <div class="stat-header">
            <span class="stat-title">Store Sales Today</span>
            <div class="stat-icon success"><i class="fa-solid fa-sack-dollar"></i></div>
        </div>
        <span class="stat-value"><?= format_currency($todaySales) ?></span>
    </div>

    <div class="stat-card" style="border-top: 4px solid var(--primary);">
        <div class="stat-header">
            <span class="stat-title">Monitored Products</span>
            <div class="stat-icon primary"><i class="fa-solid fa-boxes-stacked"></i></div>
        </div>
        <span class="stat-value"><?= $productsCount ?></span>
    </div>

    <div class="stat-card" style="border-top: 4px solid var(--warning);">
        <div class="stat-header">
            <span class="stat-title">Active Suppliers</span>
            <div class="stat-icon warning"><i class="fa-solid fa-truck-field"></i></div>
        </div>
        <span class="stat-value"><?= $totalSuppliers ?></span>
    </div>

    <div class="stat-card" style="border-top: 4px solid var(--danger);">
        <div class="stat-header">
            <span class="stat-title">Low Stock Warnings</span>
            <div class="stat-icon danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <span class="stat-value" style="color: <?= $lowStockCount > 0 ? 'var(--danger)' : 'inherit' ?>;"><?= $lowStockCount ?></span>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
    <!-- Sales Chart -->
    <div class="data-card">
        <h3 style="margin-bottom: 20px; font-weight: 700;">Sales Trend (Last 7 Days)</h3>
        <div class="chart-wrapper">
            <canvas id="managerSalesChart"></canvas>
        </div>
    </div>

    <!-- Critical Stocks alerts -->
    <div class="data-card">
        <h3 style="margin-bottom: 15px; font-weight: 700; color: var(--danger); display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-triangle-exclamation"></i> Inventory Shortage Warnings
        </h3>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Available Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($criticalItems)): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--success); font-weight: 600;">Stock levels are normal.</td></tr>
                    <?php else: ?>
                        <?php foreach ($criticalItems as $row): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['category']) ?></td>
                                <td><span class="badge badge-danger"><?= $row['stock_qty'] ?> units remaining</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent sales entries -->
<div class="data-card">
    <h3 style="margin-bottom: 15px; font-weight: 700;">Recent Sales Log</h3>
    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Invoice No</th>
                    <th>Date</th>
                    <th>Processed By</th>
                    <th>Payment Method</th>
                    <th>Total Bill</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentSales)): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No sales recorded today.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentSales as $row): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($row['invoice_no']) ?></code></td>
                            <td><?= $row['created_at'] ?></td>
                            <td><?= htmlspecialchars($row['cashier_name'] ?? 'System') ?></td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($row['payment_method']) ?></span></td>
                            <td style="font-weight: 700;"><?= format_currency($row['total_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const taxForm = document.getElementById('tax-settings-form');
    const managerTaxRateInput = document.getElementById('manager-tax-rate');

    if (taxForm && managerTaxRateInput) {
        taxForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const taxRate = parseFloat(managerTaxRateInput.value);

            if (Number.isNaN(taxRate) || taxRate < 0 || taxRate > 100) {
                alert('Tax percentage must be between 0 and 100.');
                return;
            }

            try {
                const response = await fetch('api/update_tax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tax_rate: taxRate })
                });

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.message || 'Unable to update tax rate.');
                }

                alert('Tax rate updated successfully.');
                window.location.reload();
            } catch (error) {
                alert(error.message);
            }
        });
    }

    new Chart(document.getElementById('managerSalesChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [{
                label: 'Sales Revenue ($)',
                data: <?= json_encode($trendValues) ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.3
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
