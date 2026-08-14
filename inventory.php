<?php
require_once __DIR__ . '/includes/header.php';

// Handle Stock Adjustments (Restocking)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    // Only Admin & Manager can adjust stock manually
    require_roles(['Admin', 'Manager']);

    $productId = (int)$_POST['product_id'];
    $adjustQty = (int)$_POST['adjust_qty']; // positive or negative adjust

    if ($productId <= 0 || $adjustQty === 0) {
        $_SESSION['flash_error'] = "Invalid stock adjustment quantity.";
    } else {
        try {
            $pdo->beginTransaction();

            // Fetch current product details
            $prodStmt = $pdo->prepare("SELECT name, stock_qty FROM products WHERE id = ? FOR UPDATE");
            $prodStmt->execute([$productId]);
            $prod = $prodStmt->fetch();

            if (!$prod) {
                throw new Exception("Product not found.");
            }

            $newStock = $prod['stock_qty'] + $adjustQty;
            if ($newStock < 0) {
                throw new Exception("Cannot adjust stock. Resulting stock cannot be negative.");
            }

            // Update database
            $updateStmt = $pdo->prepare("UPDATE products SET stock_qty = ? WHERE id = ?");
            $updateStmt->execute([$newStock, $productId]);

            log_action($pdo, 'ADJUST_STOCK', "Manually adjusted stock of '{$prod['name']}' (ID: {$productId}) by {$adjustQty}. New Stock: {$newStock}.");

            $pdo->commit();
            $_SESSION['flash_success'] = "Stock for '{$prod['name']}' successfully adjusted.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = $e->getMessage();
        }
    }
    header("Location: inventory.php");
    exit();
}

// Filter inputs
$filterCategory = $_GET['category'] ?? '';
$filterStockLevel = $_GET['stock_level'] ?? '';

// Build Query
$queryStr = "SELECT p.*, s.name as supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE 1=1";
$params = [];

if (!empty($filterCategory)) {
    $queryStr .= " AND p.category = ?";
    $params[] = $filterCategory;
}

if ($filterStockLevel === 'low') {
    $queryStr .= " AND p.stock_qty <= p.min_stock_qty";
} elseif ($filterStockLevel === 'out') {
    $queryStr .= " AND p.stock_qty = 0";
}

$queryStr .= " ORDER BY p.stock_qty ASC";

try {
    $stmt = $pdo->prepare($queryStr);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Fetch categories for filtering
    $categories = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Error loading inventory: " . $e->getMessage());
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>Inventory Control</h2>
        <p>Monitor warehouse quantity levels, restock products, and review warning alerts</p>
    </div>
</div>

<!-- Filters Bar -->
<div class="data-card" style="margin-bottom: 24px; padding: 18px 24px;">
    <form action="inventory.php" method="GET" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
            <label for="filter-category" style="margin-bottom: 6px;">Filter Category</label>
            <select name="category" id="filter-category" class="input-field">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= ($filterCategory === $cat) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
            <label for="filter-stock" style="margin-bottom: 6px;">Stock Status</label>
            <select name="stock_level" id="filter-stock" class="input-field">
                <option value="">All Stock Levels</option>
                <option value="low" <?= ($filterStockLevel === 'low') ? 'selected' : '' ?>>Low Stock Alerts</option>
                <option value="out" <?= ($filterStockLevel === 'out') ? 'selected' : '' ?>>Out of Stock Only</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="width: auto;">
                <i class="fa-solid fa-filter"></i> Apply Filters
            </button>
            <a href="inventory.php" class="btn btn-secondary" style="width: auto;">Reset</a>
        </div>
    </form>
</div>

<!-- Inventory List Table -->
<div class="data-card">
    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Product Code</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Current Quantity</th>
                    <th>Alert Level</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <?php if (in_array($currentUser['role'], ['Admin', 'Manager'])): ?>
                        <th style="text-align: center;">Restock Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="8" style="text-align: center; color: var(--text-muted);">No products match filter criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($p['barcode']) ?></code></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['category']) ?></td>
                            <td style="font-weight: 700;"><?= $p['stock_qty'] ?> units</td>
                            <td><?= $p['min_stock_qty'] ?> units</td>
                            <td><?= htmlspecialchars($p['supplier_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($p['stock_qty'] <= 0): ?>
                                    <span class="badge badge-danger">Out of Stock</span>
                                <?php elseif ($p['stock_qty'] <= $p['min_stock_qty']): ?>
                                    <span class="badge badge-warning">Low Stock Warning</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Healthy Stock</span>
                                <?php endif; ?>
                            </td>
                            <?php if (in_array($currentUser['role'], ['Admin', 'Manager'])): ?>
                                <td style="text-align: center;">
                                    <button class="btn btn-secondary adjust-stock-btn" 
                                            style="padding: 6px 12px; width: auto; font-size: 13px;"
                                            data-id="<?= $p['id'] ?>"
                                            data-name="<?= htmlspecialchars($p['name']) ?>"
                                            data-qty="<?= $p['stock_qty'] ?>">
                                        <i class="fa-solid fa-truck-ramp-box"></i> Adjust Stock
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Dialog for Stock Adjustment -->
<div class="modal" id="adjust-stock-modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Adjust Stock Qty</h3>
            <span class="modal-close" id="close-adjust-modal">&times;</span>
        </div>
        <hr style="border:0; border-top: 1.5px solid var(--border-color);">
        <form action="inventory.php" method="POST">
            <input type="hidden" name="action" value="adjust_stock">
            <input type="hidden" name="product_id" id="adjust-product-id">

            <p style="font-size: 14px; margin-bottom: 12px;">
                Product: <strong id="adjust-product-name">N/A</strong><br>
                Current Qty: <strong id="adjust-product-current">0</strong> units
            </p>

            <div class="form-group">
                <label for="adjust-qty">Adjustment Count</label>
                <input type="number" id="adjust-qty" name="adjust_qty" class="input-field" placeholder="Example: 10 or -5" required>
                <small style="color: var(--text-muted); font-size: 11px;">Input positive integers to restock, negative integers to reduce.</small>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                <i class="fa-solid fa-floppy-disk"></i> Apply Adjustment
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const adjustModal = document.getElementById('adjust-stock-modal');
    const closeAdjustBtn = document.getElementById('close-adjust-modal');

    if (closeAdjustBtn) {
        closeAdjustBtn.addEventListener('click', () => adjustModal.classList.remove('active'));
    }

    document.querySelectorAll('.adjust-stock-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            const name = btn.getAttribute('data-name');
            const qty = btn.getAttribute('data-qty');

            document.getElementById('adjust-product-id').value = id;
            document.getElementById('adjust-product-name').textContent = name;
            document.getElementById('adjust-product-current').textContent = qty;
            document.getElementById('adjust-qty').value = '';

            adjustModal.classList.add('active');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
