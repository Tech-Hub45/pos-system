<?php
require_once __DIR__ . '/config/db.php';

// If user is already logged in, redirect them
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Admin') {
        header("Location: admin_dashboard.php");
    } elseif ($_SESSION['role'] === 'Manager') {
        header("Location: manager_dashboard.php");
    } else {
        header("Location: cashier_dashboard.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Set Session data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                // Audit log
                log_action($pdo, 'LOGIN', "User {$user['username']} logged in successfully with role {$user['role']}.");

                // Role based redirect
                if ($user['role'] === 'Admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['role'] === 'Manager') {
                    header("Location: manager_dashboard.php");
                } else {
                    header("Location: cashier_dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid username or password.";
                log_action($pdo, 'LOGIN_FAILED', "Failed login attempt for username: " . htmlspecialchars($username));
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NexusPOS System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="overflow: auto; height: auto;">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>NexusPOS</h1>
                <p>Sign in to access your register and inventory dashboard</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="input-field" placeholder="Enter username (e.g. admin)" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="input-field" placeholder="Enter password (e.g. password123)" required>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                    <i class="fa-solid fa-right-to-bracket"></i> Login
                </button>
            </form>

            <div style="margin-top: 25px; text-align: center; font-size: 13px; color: var(--text-muted);">
                <p>Default credentials (password: <strong>password123</strong>):</p>
                <div style="display: flex; justify-content: center; gap: 15px; margin-top: 5px;">
                    <span>Admin: <code>admin</code></span>
                    <span>Manager: <code>manager</code></span>
                    <span>Cashier: <code>cashier</code></span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
