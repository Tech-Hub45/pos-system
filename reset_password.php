<?php
require_once __DIR__ . '/config/db.php';

try {
    echo "<h3>Password Reset & Diagnostic Tool</h3>";
    
    // 1. Check database connection
    if (!$pdo) {
        die("<strong style='color:red;'>Database connection is not initialized. Check config/db.php</strong>");
    }
    echo "Database connected successfully.<br>";

    // 2. Generate clean password hash for 'password123'
    $newHash = password_hash('password123', PASSWORD_BCRYPT);
    echo "Generated bcrypt hash: <code>$newHash</code><br><br>";

    // 3. Update all seeded users to use this fresh hash
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username IN ('admin', 'manager', 'cashier')");
    $stmt->execute([$newHash]);
    $affected = $stmt->rowCount();
    
    echo "Successfully updated $affected user accounts password to: <strong>password123</strong><br><br>";

    // 4. Print current users in database for validation (omitting password hash details for security)
    echo "<strong>Active Users List:</strong><br>";
    $users = $pdo->query("SELECT id, username, role, full_name, email FROM users")->fetchAll();
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Email</th></tr>";
    foreach ($users as $u) {
        echo "<tr>
            <td>{$u['id']}</td>
            <td><strong>{$u['username']}</strong></td>
            <td>{$u['full_name']}</td>
            <td>{$u['role']}</td>
            <td>{$u['email']}</td>
        </tr>";
    }
    echo "</table>";
    
    echo "<br><strong style='color:green;'>Please go to <a href='login.php'>login.php</a> and try signing in with 'admin' and 'password123' now!</strong>";

} catch (Exception $e) {
    echo "<strong style='color:red;'>Error during reset: " . $e->getMessage() . "</strong>";
}
?>
