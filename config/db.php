<?php
// Prevent direct access to config file
if (count(get_included_files()) === 1) {
    exit("Direct access not permitted.");
}

// Session configuration (start session if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pos_system');

try {
    // Establish PDO connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Elegant error display if DB connection fails
    die("Database Connection Failed: " . $e->getMessage() . "<br><br><strong>Please verify that XAMPP Apache and MySQL are running, and that you have imported the schema.sql file.</strong>");
}

/**
 * Log an event to the audit_logs table
 *
 * @param PDO $pdo The database connection instance
 * @param string $action The action name (e.g. 'LOGIN', 'ADD_PRODUCT')
 * @param string $details Detailed description of the action
 * @return bool True on success, false on failure
 */
function log_action($pdo, $action, $details) {
    try {
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $action, $details]);
    } catch (PDOException $e) {
        // Fail silently or log to error log to avoid breaking core flow
        error_log("Audit log failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Format currency values consistently
 *
 * @param float $amount
 * @return string
 */
function format_currency($amount) {
    return '$' . number_format((float)$amount, 2);
}

/**
 * Check if the logged-in user has one of the required roles
 *
 * @param array $allowedRoles Array of allowed role names, e.g. ['Admin', 'Manager']
 */
function require_roles($allowedRoles) {
    if (!isset($_SESSION['role'])) {
        header("Location: login.php");
        exit();
    }
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        // Redirect to POS index if not authorized
        $_SESSION['flash_error'] = "You do not have permission to access that page.";
        header("Location: index.php");
        exit();
    }
}

function get_system_setting($pdo, $key, $default = '0') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    } catch (PDOException $e) {
        error_log("System setting query failed: " . $e->getMessage());
        return $default;
    }
}
?>
