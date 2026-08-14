-- Create Database
CREATE DATABASE IF NOT EXISTS `pos_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `pos_system`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('Admin', 'Manager', 'Cashier') NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Suppliers Table
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `contact_name` VARCHAR(100),
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `address` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Products Table
CREATE TABLE IF NOT EXISTS `products` (
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
) ENGINE=InnoDB;

-- 4. Sales Table
CREATE TABLE IF NOT EXISTS `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL,
  `change_amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Sale Items Table
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `product_id` INT,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. Audit Logs Table
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 7. System Settings Table
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` VARCHAR(255) NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed Data (Default password for all seeded users is 'password123' hashed with bcrypt)
-- Hashed password: $2y$10$wKqK.L8TWhmP.oGv9GkSReMefF3uR5rD0QyJbMee7n5rI3tA0y2C2
INSERT INTO `users` (`username`, `password`, `role`, `full_name`, `email`) VALUES
('admin', '$2y$10$wKqK.L8TWhmP.oGv9GkSReMefF3uR5rD0QyJbMee7n5rI3tA0y2C2', 'Admin', 'System Administrator', 'admin@pos.com'),
('manager', '$2y$10$wKqK.L8TWhmP.oGv9GkSReMefF3uR5rD0QyJbMee7n5rI3tA0y2C2', 'Manager', 'Store Manager', 'manager@pos.com'),
('cashier', '$2y$10$wKqK.L8TWhmP.oGv9GkSReMefF3uR5rD0QyJbMee7n5rI3tA0y2C2', 'Cashier', 'Jane Doe', 'cashier@pos.com')
ON DUPLICATE KEY UPDATE `username`=`username`;

INSERT INTO `suppliers` (`id`, `name`, `contact_name`, `phone`, `email`, `address`) VALUES
(1, 'Tech Distributors Inc.', 'Alice Johnson', '555-0199', 'alice@techdist.com', '123 Tech Way, Silicon Valley'),
(2, 'Global Goods Wholesale', 'Bob Smith', '555-0244', 'bob@globalgoods.com', '456 Bulk Ave, Logistics City')
ON DUPLICATE KEY UPDATE `name`=`name`;

INSERT INTO `products` (`barcode`, `name`, `description`, `category`, `buy_price`, `sell_price`, `stock_qty`, `min_stock_qty`, `supplier_id`) VALUES
('8801234567890', 'Wireless Mouse', 'Ergonomic 2.4GHz wireless office mouse', 'Electronics', 10.00, 19.99, 50, 5, 1),
('8801234567891', 'Mechanical Keyboard', 'RGB Backlit Blue Switch Keyboard', 'Electronics', 25.00, 49.99, 15, 3, 1),
('8801234567892', 'Stainless Water Bottle', 'Double-wall vacuum insulated 1L bottle', 'Home & Kitchen', 8.00, 15.49, 4, 5, 2),
('8801234567893', 'Notebook A5', '120 pages dotted grid notebook', 'Stationery', 2.00, 4.99, 100, 10, 2),
('8801234567894', 'Gel Pen Pack', '10-pack black ink fine point pens', 'Stationery', 1.50, 3.49, 3, 5, 2)
ON DUPLICATE KEY UPDATE `barcode`=`barcode`;

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('tax_rate', '8.00')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
