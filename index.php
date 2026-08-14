<?php
require_once __DIR__ . '/includes/header.php';

$taxRate = (float)get_system_setting($pdo, 'tax_rate', '8.00');

// Fetch all categories for filtering
try {
    $categoriesStmt = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category ASC");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch all products with supplier details for the catalog
    $productsStmt = $pdo->query("SELECT p.*, s.name as supplier_name FROM products p LEFT JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.name ASC");
    $products = $productsStmt->fetchAll();
} catch (PDOException $e) {
    die("Error loading POS: " . $e->getMessage());
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>POS Register</h2>
        <p>Scan barcodes or select products to build a customer transaction</p>
    </div>
    <div class="header-actions" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <button type="button" id="toggle-barcode-scanner-btn" class="btn btn-secondary" style="padding: 12px 16px; width: auto;">
            <i class="fa-solid fa-barcode"></i> Barcode Scanner
        </button>
        <!-- Barcode Scanning Simulation Input -->
        <div style="position: relative;">
            <i class="fa-solid fa-barcode" style="position: absolute; left: 14px; top: 14px; color: var(--text-muted);"></i>
            <input type="text" id="barcode-scan-input" class="input-field" placeholder="Scan product barcode..." style="padding-left: 40px; width: 260px;" autocomplete="off">
        </div>
    </div>
</div>

<div class="pos-layout">
    <!-- Left panel: Product Catalog -->
    <div class="products-panel">
        <div class="pos-search-bar">
            <!-- Product Text Search -->
            <div style="position: relative; flex: 1;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; top: 14px; color: var(--text-muted);"></i>
                <input type="text" id="search-product" class="input-field" placeholder="Search by name or category..." style="padding-left: 40px;">
            </div>
            <!-- Category Filter Dropdown -->
            <select id="category-filter" class="input-field" style="width: 180px;">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Product Grid View -->
        <div class="product-grid" id="product-grid-container">
            <?php foreach ($products as $prod): ?>
                <div class="product-card" 
                     data-id="<?= $prod['id'] ?>" 
                     data-barcode="<?= htmlspecialchars($prod['barcode']) ?>" 
                     data-name="<?= htmlspecialchars($prod['name']) ?>" 
                     data-price="<?= $prod['sell_price'] ?>" 
                     data-stock="<?= $prod['stock_qty'] ?>"
                     data-category="<?= htmlspecialchars($prod['category']) ?>">
                    <span class="product-card-category"><?= htmlspecialchars($prod['category']) ?></span>
                    <span class="product-card-title"><?= htmlspecialchars($prod['name']) ?></span>
                    <span class="product-card-stock <?= ($prod['stock_qty'] <= $prod['min_stock_qty']) ? 'low-stock' : '' ?>">
                        <?= ($prod['stock_qty'] <= 0) ? 'Out of Stock' : 'Stock: ' . $prod['stock_qty'] ?>
                    </span>
                    <span class="product-card-price"><?= format_currency($prod['sell_price']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right panel: Checkout register cart -->
    <div class="checkout-panel">
        <h3 class="checkout-header">
            <span>Current Order</span>
            <?php if ($currentUser['role'] === 'Cashier'): ?>
                <button id="clear-cart-btn" class="btn btn-secondary cart-clear-btn">
                    <i class="fa-solid fa-trash-can"></i> Clear All
                </button>
            <?php else: ?>
                <span class="role-view-badge"><?= htmlspecialchars($currentUser['role']) ?> View</span>
            <?php endif; ?>
        </h3>

        <!-- Cart items list (loaded dynamically via Javascript) -->
        <div class="cart-items" id="cart-items-container">
            <!-- Dynamic items go here -->
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); gap: 10px;">
                <i class="fa-solid fa-basket-shopping" style="font-size: 40px;"></i>
                <p>Order Cart is empty</p>
            </div>
        </div>

        <!-- Totals & Payment Actions -->
        <div class="cart-totals">
            <div class="cart-total-row">
                <span>Subtotal</span>
                <span id="cart-subtotal">$0.00</span>
            </div>
            <div class="cart-total-row">
                <span>Tax (<span id="tax-rate-label"><?= number_format($taxRate, 2) ?>%</span>)</span>
                <span id="cart-tax">$0.00</span>
            </div>
            <?php if (in_array($currentUser['role'], ['Manager', 'Admin'])): ?>
                <div class="form-group" style="margin: 6px 0 0;">
                    <label for="tax-rate-input">Manager Tax Setup</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="number" id="tax-rate-input" class="input-field" min="0" max="100" step="0.01" value="<?= htmlspecialchars($taxRate) ?>" style="min-width: 0; flex: 1;">
                        <button type="button" id="save-tax-rate-btn" class="btn btn-primary" style="width: auto; padding: 10px 12px;">Save</button>
                    </div>
                </div>
            <?php endif; ?>
            <div class="cart-total-row grand-total">
                <span>Total</span>
                <span id="cart-grand-total">$0.00</span>
            </div>
        </div>

        <div class="checkout-actions">
            <?php if ($currentUser['role'] === 'Cashier'): ?>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="payment-method">Payment Method</label>
                    <select id="payment-method" class="input-field">
                        <option value="Cash">Cash</option>
                        <option value="Card">Credit/Debit Card</option>
                        <option value="Mobile">Mobile Wallet</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="amount-paid">Amount Paid</label>
                    <input type="number" step="0.01" id="amount-paid" class="input-field" placeholder="Enter Cash Amount Paid">
                </div>

                <div class="cart-total-row" style="font-weight: 700; font-size: 15px;">
                    <span>Change Due</span>
                    <span id="change-due" style="color: var(--success); font-size: 18px;">$0.00</span>
                </div>

                <button id="checkout-btn" class="btn btn-primary" style="padding: 14px;">
                    <i class="fa-solid fa-cash-register"></i> Complete Transaction
                </button>
            <?php else: ?>
                <div class="alert alert-warning" style="margin-bottom: 0; font-size: 13px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Checkout operations are reserved for Cashiers. You are logged in as <strong><?= htmlspecialchars($currentUser['role']) ?></strong> (View Mode).</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Receipt Modal Dialogue -->
<div class="modal" id="receipt-modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Transaction Invoice</h3>
            <span class="modal-close" id="close-receipt-modal">&times;</span>
        </div>
        <hr style="border: 0; border-top: 1px dashed var(--border-color);">
        
        <!-- Printable area -->
        <div id="receipt-print-area" style="padding: 10px 0;">
            <!-- Content built dynamically by JS -->
        </div>

        <div style="display: flex; gap: 12px;">
            <button id="print-receipt-btn" class="btn btn-primary" style="flex: 1;">
                <i class="fa-solid fa-print"></i> Print Receipt
            </button>
            <button id="new-sale-btn" class="btn btn-secondary" style="flex: 1;">
                <i class="fa-solid fa-rotate-left"></i> New Sale
            </button>
        </div>
    </div>
</div>

<?php
// Embed product inventory details to JS
echo "<script>const productCatalog = " . json_encode($products) . ";
window.systemTaxRate = " . json_encode((float)$taxRate) . ";</script>";
require_once __DIR__ . '/includes/footer.php';
?>
