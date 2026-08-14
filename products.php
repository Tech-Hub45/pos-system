<?php
require_once __DIR__ . '/includes/header.php';
require_roles(['Admin', 'Manager']);

// Fetch suppliers list for dropdown menus
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

// Handle Form Submissions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. ADD / CREATE PRODUCT
    if ($action === 'create') {
        $barcode = trim($_POST['barcode']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        $buy_price = (float)$_POST['buy_price'];
        $sell_price = (float)$_POST['sell_price'];
        $stock_qty = (int)$_POST['stock_qty'];
        $min_stock_qty = (int)$_POST['min_stock_qty'];
        $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;

        if (empty($barcode) || empty($name)) {
            $_SESSION['flash_error'] = "Barcode and Name are required fields.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO products (barcode, name, description, category, buy_price, sell_price, stock_qty, min_stock_qty, supplier_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$barcode, $name, $description, $category, $buy_price, $sell_price, $stock_qty, $min_stock_qty, $supplier_id]);
                log_action($pdo, 'ADD_PRODUCT', "Added new product '{$name}' with barcode {$barcode}.");
                $_SESSION['flash_success'] = "Product '{$name}' added successfully.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $_SESSION['flash_error'] = "Duplicate Barcode: A product with barcode '{$barcode}' already exists.";
                } else {
                    $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
                }
            }
        }
    }

    // 2. UPDATE PRODUCT
    elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $barcode = trim($_POST['barcode']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        $buy_price = (float)$_POST['buy_price'];
        $sell_price = (float)$_POST['sell_price'];
        $stock_qty = (int)$_POST['stock_qty'];
        $min_stock_qty = (int)$_POST['min_stock_qty'];
        $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;

        if (empty($id) || empty($barcode) || empty($name)) {
            $_SESSION['flash_error'] = "Product ID, Barcode, and Name are required fields.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE products SET barcode = ?, name = ?, description = ?, category = ?, buy_price = ?, sell_price = ?, stock_qty = ?, min_stock_qty = ?, supplier_id = ? WHERE id = ?");
                $stmt->execute([$barcode, $name, $description, $category, $buy_price, $sell_price, $stock_qty, $min_stock_qty, $supplier_id, $id]);
                log_action($pdo, 'UPDATE_PRODUCT', "Updated product details for '{$name}' (ID: {$id}).");
                $_SESSION['flash_success'] = "Product updated successfully.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $_SESSION['flash_error'] = "Duplicate Barcode: A product with barcode '{$barcode}' already exists.";
                } else {
                    $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
                }
            }
        }
    }

    // 3. DELETE PRODUCT
    elseif ($action === 'delete') {
        if ($currentUser['role'] !== 'Admin') {
            $_SESSION['flash_error'] = "Access Denied: Only Administrators are permitted to delete products.";
        } else {
            $id = (int)$_POST['id'];
            try {
                // Get product name first for audit log
                $nameStmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
                $nameStmt->execute([$id]);
                $prodName = $nameStmt->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
                log_action($pdo, 'DELETE_PRODUCT', "Deleted product '{$prodName}' (ID: {$id}).");
                $_SESSION['flash_success'] = "Product deleted successfully.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Cannot delete product. It is linked to sales transactions.";
            }
        }
    }

    header("Location: products.php");
    exit();
}

// Fetch all products with supplier details
$products = $pdo->query("
    SELECT p.*, s.name as supplier_name 
    FROM products p 
    LEFT JOIN suppliers s ON p.supplier_id = s.id 
    ORDER BY p.id DESC
")->fetchAll();
?>

<div class="page-header">
    <div class="page-title">
        <h2>Product Management</h2>
        <p>Maintain your store product list, prices, barcodes, and inventory thresholds</p>
    </div>
    <div class="header-actions">
        <button id="add-product-btn" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Add Product
        </button>
    </div>
</div>

<!-- Product Table -->
<div class="data-card">
    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Barcode</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Buy Price</th>
                    <th>Sell Price</th>
                    <th>Stock</th>
                    <th>Supplier</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="8" style="text-align: center; color: var(--text-muted);">No products registered.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($p['barcode']) ?></code></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['category']) ?></td>
                            <td><?= format_currency($p['buy_price']) ?></td>
                            <td style="color: var(--primary); font-weight: 600;"><?= format_currency($p['sell_price']) ?></td>
                            <td>
                                <span class="badge <?= ($p['stock_qty'] <= $p['min_stock_qty']) ? 'badge-danger' : 'badge-success' ?>">
                                    <?= $p['stock_qty'] ?> units
                                </span>
                            </td>
                            <td><?= htmlspecialchars($p['supplier_name'] ?? 'N/A') ?></td>
                            <td style="text-align: center; display: flex; justify-content: center; gap: 8px;">
                                <button class="btn btn-secondary edit-product-btn" 
                                        style="padding: 6px 10px; width: auto; font-size: 13px;"
                                        data-product='<?= json_encode($p, JSON_HEX_APOS) ?>'>
                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                </button>
                                <?php if ($currentUser['role'] === 'Admin'): ?>
                                    <form action="products.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 10px; width: auto; font-size: 13px;">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal dialog for Adding Product -->
<div class="modal" id="add-product-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Product</h3>
            <span class="modal-close" id="close-add-modal">&times;</span>
        </div>
        <hr style="border:0; border-top: 1.5px solid var(--border-color);">
        <form action="products.php" method="POST">
            <input type="hidden" name="action" value="create">
            <div class="product-form-shell">
                <div class="form-group">
                    <label for="add-barcode">Barcode *</label>
                    <input type="text" id="add-barcode" name="barcode" class="input-field" required>
                </div>
                <div class="form-group">
                    <label for="add-name">Product Name *</label>
                    <input type="text" id="add-name" name="name" class="input-field" required>
                </div>
                <div class="form-group">
                    <label for="add-description">Description</label>
                    <textarea id="add-description" name="description" class="input-field" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add-category">Category</label>
                        <input type="text" id="add-category" name="category" class="input-field" placeholder="Electronics, Clothing..." required>
                    </div>
                    <div class="form-group">
                        <label for="add-supplier">Supplier</label>
                        <select id="add-supplier" name="supplier_id" class="input-field">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add-buy-price">Cost Price ($)</label>
                        <input type="number" id="add-buy-price" name="buy_price" class="input-field" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="add-sell-price">Selling Price ($)</label>
                        <input type="number" id="add-sell-price" name="sell_price" class="input-field" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add-stock-qty">Stock Quantity *</label>
                        <input type="number" id="add-stock-qty" name="stock_qty" class="input-field" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="add-min-stock-qty">Minimum Stock Quantity</label>
                        <input type="number" id="add-min-stock-qty" name="min_stock_qty" class="input-field" min="0" value="0">
                    </div>
                </div>
            </div>
            <div class="product-form-actions modal-footer">
                <button type="button" class="btn btn-secondary" id="cancel-add-product">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal dialog for Editing Product -->
<div class="modal" id="edit-product-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Product Details</h3>
            <span class="modal-close" id="close-edit-modal">&times;</span>
        </div>
        <hr style="border:0; border-top: 1.5px solid var(--border-color);">
        <form action="products.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            
            <div class="form-group">
                <label for="edit-barcode">Barcode *</label>
                <input type="text" id="edit-barcode" name="barcode" class="input-field" required>
            </div>
            <div class="form-group">
                <label for="edit-name">Product Name *</label>
                <input type="text" id="edit-name" name="name" class="input-field" required>
            </div>
            <div class="form-group">
                <label for="edit-description">Description</label>
                <textarea id="edit-description" name="description" class="input-field" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit-category">Category</label>
                    <input type="text" id="edit-category" name="category" class="input-field" placeholder="Electronics, Clothing..." required>
                </div>
                <div class="form-group">
                    <label for="edit-supplier">Supplier</label>
                    <select id="edit-supplier" name="supplier_id" class="input-field">
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit-buy-price">Cost Price ($)</label>
                    <input type="number" id="edit-buy-price" name="buy_price" class="input-field" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit-sell-price">Selling Price ($)</label>
                    <input type="number" id="edit-sell-price" name="sell_price" class="input-field" step="0.01" min="0" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit-stock-qty">Stock Quantity *</label>
                    <input type="number" id="edit-stock-qty" name="stock_qty" class="input-field" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit-min-stock-qty">Minimum Stock Quantity</label>
                    <input type="number" id="edit-min-stock-qty" name="min_stock_qty" class="input-field" min="0" value="0">
                </div>
            </div>
            <div class="modal-footer" style="margin-top: 20px;">
                <button type="button" class="btn btn-secondary" id="cancel-edit-product">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Product</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addProductBtn = document.getElementById('add-product-btn');
    const addProductModal = document.getElementById('add-product-modal');
    const closeAddModal = document.getElementById('close-add-modal');
    const cancelAddProductBtn = document.getElementById('cancel-add-product');

    addProductBtn.addEventListener('click', () => {
        addProductModal.style.display = 'flex';
    });
    closeAddModal.addEventListener('click', () => {
        addProductModal.style.display = 'none';
    });
    if (cancelAddProductBtn) {
        cancelAddProductBtn.addEventListener('click', () => {
            addProductModal.style.display = 'none';
        });
    }

    const editProductModal = document.getElementById('edit-product-modal');
    const closeEditModal = document.getElementById('close-edit-modal');
    const cancelEditProductBtn = document.getElementById('cancel-edit-product');
    const editProductBtns = document.querySelectorAll('.edit-product-btn');

    editProductBtns.forEach(button => {
        button.addEventListener('click', (e) => {
            const product = JSON.parse(e.currentTarget.dataset.product);
            document.getElementById('edit-id').value = product.id;
            document.getElementById('edit-barcode').value = product.barcode;
            document.getElementById('edit-name').value = product.name;
            document.getElementById('edit-description').value = product.description || '';
            document.getElementById('edit-category').value = product.category;
            document.getElementById('edit-supplier').value = product.supplier_id || '';
            document.getElementById('edit-buy-price').value = product.buy_price;
            document.getElementById('edit-sell-price').value = product.sell_price;
            document.getElementById('edit-stock-qty').value = product.stock_qty;
            document.getElementById('edit-min-stock-qty').value = product.min_stock_qty;
            editProductModal.style.display = 'flex';
        });
    });

    closeEditModal.addEventListener('click', () => {
        editProductModal.style.display = 'none';
    });
    if (cancelEditProductBtn) {
        cancelEditProductBtn.addEventListener('click', () => {
            editProductModal.style.display = 'none';
        });
    }

    window.addEventListener('click', (event) => {
        if (event.target == addProductModal) {
            addProductModal.style.display = 'none';
        }
        if (event.target == editProductModal) {
            editProductModal.style.display = 'none';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
