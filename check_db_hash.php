<?php
require_once __DIR__ . '/config/db.php';

echo "<h3>Database Password Hashing Verification</h3>";

try {
    $stmt = $pdo->query("SELECT id, username, password FROM users");
    $users = $stmt->fetchAll();

    if (empty($users)) {
        echo "<p style='color:red;'>No users found in database!</p>";
    }

    foreach ($users as $user) {
        $dbHash = $user['password'];
        $testPassword = 'password123';
        
        // Run verification test
        $verify = password_verify($testPassword, $dbHash);
        
        echo "<p>";
        echo "<strong>User:</strong> " . htmlspecialchars($user['username']) . "<br>";
        echo "<strong>Hash in DB:</strong> <code>" . htmlspecialchars($dbHash) . "</code> (Length: " . strlen($dbHash) . ")<br>";
        echo "<strong>Verify with 'password123':</strong> " . ($verify ? "<span style='color:green;font-weight:bold;'>MATCHES (SUCCESS)</span>" : "<span style='color:red;font-weight:bold;'>DOES NOT MATCH (FAIL)</span>") . "<br>";
        echo "</p><hr>";
    }
} catch (PDOException $e) {
    echo "Query error: " . $e->getMessage();
}
?>
