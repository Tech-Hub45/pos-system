<?php
// Database credentials
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    // 1. Connect to MySQL without specifying database first
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<h3>NexusPOS Installer</h3>";
    echo "Connected to MySQL successfully...<br>";

    // 2. Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `pos_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "Database `pos_system` verified/created successfully...<br>";
    
    // 3. Connect to the specific database
    $pdo->exec("USE `pos_system`");

    // 4. Create Tables
    $queries = [
        "Users Table" => "CREATE TABLE IF NOT EXISTS `users` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `username` VARCHAR(50) NOT NULL UNIQUE,
          `password` VARCHAR(255) NOT NULL,
          `role` ENUM('Admin', 'Manager', 'Cashier') NOT NULL,
          `full_name` VARCHAR(100) NOT NULL,
          `email` VARCHAR(100) NOT NULL UNIQUE,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "Suppliers Table" => "CREATE TABLE IF NOT EXISTS `suppliers` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `name` VARCHAR(100) NOT NULL,
          `contact_name` VARCHAR(100),
          `phone` VARCHAR(20),
          `email` VARCHAR(100),
          `address` TEXT,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",

        "Products Table" => "CREATE TABLE IF NOT EXISTS `products` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `barcode` VARCHAR(50) NOT NULL UNIQUE,
          `name` VARCHAR(100) NOT NULL,
          `description` TEXT,
          `category` VARCHAR(50) NOT NULL,
          `buy_price` DECIMAL(10,2) NOT NULL,
          `sell_price` DECIMAL(10,2) NOT NULL,
          `stock_qty` INT NOT NULL DEFAULT 0,
          `min_stock_qty` INT NOT NULL DEFAULT 5,
          `supplier_id` INT,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB",

        "Sales Table" => "CREATE TABLE IF NOT EXISTS `sales` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
          `user_id` INT,
          `total_amount` DECIMAL(10,2) NOT NULL,
          `amount_paid` DECIMAL(10,2) NOT NULL,
          `change_amount` DECIMAL(10,2) NOT NULL,
          `payment_method` VARCHAR(50) NOT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB",

        "Sale Items Table" => "CREATE TABLE IF NOT EXISTS `sale_items` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `sale_id` INT NOT NULL,
          `product_id` INT,
          `quantity` INT NOT NULL,
          `unit_price` DECIMAL(10,2) NOT NULL,
          `total_price` DECIMAL(10,2) NOT NULL,
          FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB",

        "Audit Logs Table" => "CREATE TABLE IF NOT EXISTS `audit_logs` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT,
          `action` VARCHAR(100) NOT NULL,
          `details` TEXT,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB",

        "System Settings Table" => "CREATE TABLE IF NOT EXISTS `system_settings` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `setting_key` VARCHAR(100) NOT NULL UNIQUE,
          `setting_value` VARCHAR(255) NOT NULL,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    ];

    foreach ($queries as $tableName => $sql) {
        $pdo->exec($sql);
        echo "Table: $tableName verified/created successfully...<br>";
    }

    // 5. Seed Users
    $passwordHash = password_hash('password123', PASSWORD_BCRYPT);
    
    // Check and insert Admin
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name, email) VALUES (?, ?, 'Admin', 'System Administrator', 'admin@pos.com')");
        $stmt->execute(['admin', $passwordHash]);
        echo "Seeded User: admin (password: password123)...<br>";
    } else {
        echo "User 'admin' already exists...<br>";
    }

    // Check and insert Manager
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'manager'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name, email) VALUES (?, ?, 'Manager', 'Store Manager', 'manager@pos.com')");
        $stmt->execute(['manager', $passwordHash]);
        echo "Seeded User: manager (password: password123)...<br>";
    } else {
        echo "User 'manager' already exists...<br>";
    }

    // Check and insert Cashier
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'cashier'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name, email) VALUES (?, ?, 'Cashier', 'Jane Doe', 'cashier@pos.com')");
        $stmt->execute(['cashier', $passwordHash]);
        echo "Seeded User: cashier (password: password123)...<br>";
    } else {
        echo "User 'cashier' already exists...<br>";
    }

    // Seed Suppliers
    $pdo->exec("INSERT IGNORE INTO `suppliers` (`id`, `name`, `contact_name`, `phone`, `email`, `address`) VALUES
        (1, 'Tech Distributors Inc.', 'Alice Johnson', '555-0199', 'alice@techdist.com', '123 Tech Way, Silicon Valley'),
        (2, 'Global Goods Wholesale', 'Bob Smith', '555-0244', 'bob@globalgoods.com', '456 Bulk Ave, Logistics City')");
    echo "Seeded suppliers table...<br>";

    // Seed Products
    $pdo->exec("INSERT IGNORE INTO `products` (`barcode`, `name`, `description`, `category`, `buy_price`, `sell_price`, `stock_qty`, `min_stock_qty`, `supplier_id`) VALUES
        ('8801234567890', 'Wireless Mouse', 'Ergonomic 2.4GHz wireless office mouse', 'Electronics', 10.00, 19.99, 50, 5, 1),
        ('8801234567891', 'Mechanical Keyboard', 'RGB Backlit Blue Switch Keyboard', 'Electronics', 25.00, 49.99, 15, 3, 1),
        ('8801234567892', 'Stainless Water Bottle', 'Double-wall vacuum insulated 1L bottle', 'Home & Kitchen', 8.00, 15.49, 4, 5, 2),
        ('8801234567893', 'Notebook A5', '120 pages dotted grid notebook', 'Stationery', 2.00, 4.99, 100, 10, 2),
        ('8801234567894', 'Gel Pen Pack', '10-pack black ink fine point pens', 'Stationery', 1.50, 3.49, 3, 5, 2)");
    echo "Seeded default products table...<br>";

    $pdo->exec("INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('tax_rate', '8.00') ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)");
    echo "Seeded default tax rate...<br>";

    echo "<br><strong style='color: green;'>Setup Complete! You can now log in at <a href='login.php'>login.php</a>.</strong>";

} catch (PDOException $e) {
    echo "<br><strong style='color: red;'>Database Error: " . $e->getMessage() . "</strong>";
    echo "<br>Please make sure XAMPP MySQL module is turned ON in your XAMPP Control Panel.";
}
?>
