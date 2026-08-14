<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if (!in_array($_SESSION['role'], ['Manager', 'Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Only Manager or Admin can update tax settings.']);
    exit();
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input || !isset($input['tax_rate'])) {
    echo json_encode(['success' => false, 'message' => 'Tax rate is required.']);
    exit();
}

$taxRate = (float)$input['tax_rate'];

if ($taxRate < 0 || $taxRate > 100) {
    echo json_encode(['success' => false, 'message' => 'Tax rate must be between 0 and 100.']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('tax_rate', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([(string)$taxRate]);

    log_action($pdo, 'UPDATE_TAX_RATE', "Tax rate changed to {$taxRate}% by {$_SESSION['username']}.");

    echo json_encode(['success' => true, 'tax_rate' => $taxRate]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
