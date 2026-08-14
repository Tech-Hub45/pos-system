<?php
require_once __DIR__ . '/includes/header.php';
require_roles(['Admin']); // Only Admin can manage users

// Handle Form Submissions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. ADD / CREATE USER
    if ($action === 'create') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = $_POST['role'] ?? 'Cashier';
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);

        if (empty($username) || empty($password) || empty($full_name) || empty($email)) {
            $_SESSION['flash_error'] = "All fields are required.";
        } else {
            try {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashedPassword, $role, $full_name, $email]);
                log_action($pdo, 'CREATE_USER', "Registered user '{$username}' with role {$role}.");
                $_SESSION['flash_success'] = "User '{$full_name}' registered successfully.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $_SESSION['flash_error'] = "Username or Email already registered.";
                } else {
                    $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
                }
            }
        }
    }

    // 2. UPDATE USER
    elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $username = trim($_POST['username']);
        $role = $_POST['role'] ?? 'Cashier';
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (empty($id) || empty($username) || empty($full_name) || empty($email)) {
            $_SESSION['flash_error'] = "ID, Username, Full Name, and Email are required.";
        } else {
            try {
                if (!empty($password)) {
                    // Update user including new password
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ?, full_name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$username, $hashedPassword, $role, $full_name, $email, $id]);
                } else {
                    // Update user details without changing password
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, full_name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$username, $role, $full_name, $email, $id]);
                }
                log_action($pdo, 'UPDATE_USER', "Updated user info for '{$username}' (ID: {$id}).");
                $_SESSION['flash_success'] = "User details updated successfully.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
            }
        }
    }

    // 3. DELETE USER
    elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // Prevent deleting oneself
        if ($id === $currentUser['id']) {
            $_SESSION['flash_error'] = "You cannot delete your own active administrator account.";
        } else {
            try {
                $nameStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $nameStmt->execute([$id]);
                $usernameDel = $nameStmt->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                log_action($pdo, 'DELETE_USER', "Deleted user account '{$usernameDel}' (ID: {$id}).");
                $_SESSION['flash_success'] = "User account deleted successfully.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Cannot delete user. They are linked to historical sale logs.";
            }
        }
    }

    header("Location: users.php");
    exit();
}

// Fetch all users
$users = $pdo->query("SELECT id, username, role, full_name, email, created_at FROM users ORDER BY id DESC")->fetchAll();
?>

<div class="page-header">
    <div class="page-title">
        <h2>User Management</h2>
        <p>Register new staff members, update roles, and manage system access permissions</p>
    </div>
    <div class="header-actions">
        <button id="add-user-btn" class="btn btn-primary">
            <i class="fa-solid fa-user-plus"></i> Add User
        </button>
    </div>
</div>

<!-- Users Table -->
<div class="data-card">
    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Created Date</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><code>USR-<?= str_pad($u['id'], 3, '0', STR_PAD_LEFT) ?></code></td>
                        <td style="font-weight: 600;"><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php if ($u['role'] === 'Admin'): ?>
                                <span class="badge badge-danger">Admin</span>
                            <?php elseif ($u['role'] === 'Manager'): ?>
                                <span class="badge badge-warning">Manager</span>
                            <?php else: ?>
                                <span class="badge badge-success">Cashier</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $u['created_at'] ?></td>
                        <td style="text-align: center; display: flex; justify-content: center; gap: 8px;">
                            <button class="btn btn-secondary edit-user-btn" 
                                    style="padding: 6px 10px; width: auto; font-size: 13px;"
                                    data-user='<?= json_encode($u, JSON_HEX_APOS) ?>'>
                                <i class="fa-solid fa-user-pen"></i> Edit
                            </button>
                            <?php if ($u['id'] !== $currentUser['id']): ?>
                                <form action="users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 6px 10px; width: auto; font-size: 13px;">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal dialog for Adding User -->
<div class="modal" id="add-user-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New User</h3>
            <span class="modal-close" id="close-add-modal">&times;</span>
        </div>
        <hr style="border:0; border-top: 1.5px solid var(--border-color);">
        <form action="users.php" method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label for="add-username">Username *</label>
                <input type="text" id="add-username" name="username" class="input-field" required>
            </div>
            <div class="form-group">
                <label for="add-fullname">Full Name *</label>
                <input type="text" id="add-fullname" name="full_name" class="input-field" required>
            </div>
            <div class="form-group">
                <label for="add-email">Email Address *</label>
                <input type="email" id="add-email" name="email" class="input-field" required>
            </div>
            <div class="form-group">
                <label for="add-password">Password *</label>
                <input type="password" id="add-password" name="password" class="input-field" required>
            </div>
            <div class="form-group">
                <label for="add-role">System Role *</label>
                <select id="add-role" name="role" class="input-field" required>
                    <option value="Cashier">Cashier</option>
                    <option value="Manager">Manager</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                <i class="fa-solid fa-circle-check"></i> Register User
            </button>
        </form>
    </div>
</div>

<!-- Modal dialog for Editing User -->
<div class="modal" id="edit-user-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit User Details</h3>
            <span class="modal-close" id="close-edit-modal">&times;</span>
        </div>
        <hr style="border:0; border-top: 1.5px solid var(--border-color);">
        <form action="users.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            
            <div class="form-group">
                <label for="edit-username">Username *</label>
                <input type="text" id="edit-username" name="username" class="input-field" required>
            </div>
            <div class="form-group">
                <label for="edit-fullname">Full Name *</label>
                <input type="text" id="edit-fullname" name="full_name" class="input-field" required>
            </div>
            <div class="form-group">
                <label for="edit-email">Email Address *</label>
                <input type="email" id="edit-email" name="email" class="input-field" required>
            </div>
            <div class="form-group">
                <label for="edit-password">Password (Leave blank to keep current)</label>
                <input type="password" id="edit-password" name="password" class="input-field" placeholder="Enter new password">
            </div>
            <div class="form-group">
                <label for="edit-role">System Role *</label>
                <select id="edit-role" name="role" class="input-field" required>
                    <option value="Cashier">Cashier</option>
                    <option value="Manager">Manager</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                <i class="fa-solid fa-circle-check"></i> Update User
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const addModal = document.getElementById('add-user-modal');
    const editModal = document.getElementById('edit-user-modal');
    const addBtn = document.getElementById('add-user-btn');
    
    addBtn.addEventListener('click', () => addModal.classList.add('active'));
    document.getElementById('close-add-modal').addEventListener('click', () => addModal.classList.remove('active'));
    document.getElementById('close-edit-modal').addEventListener('click', () => editModal.classList.remove('active'));
    
    document.querySelectorAll('.edit-user-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const user = JSON.parse(btn.getAttribute('data-user'));
            
            document.getElementById('edit-id').value = user.id;
            document.getElementById('edit-username').value = user.username;
            document.getElementById('edit-fullname').value = user.full_name;
            document.getElementById('edit-email').value = user.email;
            document.getElementById('edit-role').value = user.role;
            document.getElementById('edit-password').value = ''; // clear password field
            
            editModal.classList.add('active');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
