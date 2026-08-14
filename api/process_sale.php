<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login.']);
    exit();
}

// Only Cashiers are authorized to write sales transactions
if ($_SESSION['role'] !== 'Cashier') {
    echo json_encode(['success' => false, 'message' => 'Access Denied: Checkout operations are restricted to Cashiers only.']);
    exit();
}


// Read raw JSON post data
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input || empty($input['cart'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction payload.']);
    exit();
}

$cart = $input['cart'];
$totalAmount = (float)$input['total_amount'];
$amountPaid = (float)$input['amount_paid'];
$changeAmount = (float)$input['change_amount'];
$paymentMethod = htmlspecialchars($input['payment_method']);
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$fullName = $_SESSION['full_name'];

// Unique Invoice Number Generator
$invoiceNo = 'INV-' . date('YmdHis') . '-' . rand(100, 999);

try {
    // Start ACID transaction
    $pdo->beginTransaction();

    // 1. Insert into Sales record
    $saleStmt = $pdo->prepare("INSERT INTO sales (invoice_no, user_id, total_amount, amount_paid, change_amount, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
    $saleStmt->execute([$invoiceNo, $userId, $totalAmount, $amountPaid, $changeAmount, $paymentMethod]);
    $saleId = $pdo->lastInsertId();

    $receiptItems = [];
    $subtotal = 0;

    // 2. Loop through cart items and log sale detail
    foreach ($cart as $item) {
        $productId = (int)$item['id'];
        $qty = (int)$item['quantity'];
        $unitPrice = (float)$item['price'];
        $totalPrice = $unitPrice * $qty;
        $subtotal += $totalPrice;

        // Verify stock levels before writing transaction
        $prodStmt = $pdo->prepare("SELECT name, stock_qty, min_stock_qty FROM products WHERE id = ? FOR UPDATE");
        $prodStmt->execute([$productId]);
        $prod = $prodStmt->fetch();

        if (!$prod) {
            throw new Exception("Product ID {$productId} does not exist in catalog.");
        }

        if ($prod['stock_qty'] < $qty) {
            throw new Exception("Insufficient stock for product '{$prod['name']}'. Available: {$prod['stock_qty']}.");
        }

        // Write sale item line
        $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
        $itemStmt->execute([$saleId, $productId, $qty, $unitPrice, $totalPrice]);

        // Reduce stock inventory level
        $newStock = $prod['stock_qty'] - $qty;
        $updateStockStmt = $pdo->prepare("UPDATE products SET stock_qty = ? WHERE id = ?");
        $updateStockStmt->execute([$newStock, $productId]);

        // If stock is now low, create low-stock warning in audit logs
        if ($newStock <= $prod['min_stock_qty']) {
            log_action($pdo, 'LOW_STOCK_ALERT', "Product '{$prod['name']}' has low stock levels ({$newStock} units left).");
        }

        $receiptItems[] = [
            'name' => $prod['name'],
            'quantity' => $qty,
            'price' => $unitPrice
        ];
    }

    // 3. Log user activity
    log_action($pdo, 'CREATE_SALE', "Processed sale {$invoiceNo} by {$username}. Total: " . format_currency($totalAmount));

    // Commit changes
    $pdo->commit();

    // Respond with success and receipt structures
    echo json_encode([
        'success' => true,
        'invoice' => [
            'invoice_no' => $invoiceNo,
            'cashier' => $fullName,
            'payment_method' => $paymentMethod,
            'subtotal' => $subtotal,
            'tax' => $totalAmount - $subtotal,
            'total' => $totalAmount,
            'paid' => $amountPaid,
            'change' => $changeAmount,
            'items' => $receiptItems
        ]
    ]);

} catch (Exception $e) {
    // Rollback changes on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
