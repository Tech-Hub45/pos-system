<?php
require_once __DIR__ . '/includes/header.php';
require_roles(['Admin', 'Manager']);

// Handle Forms (Create, Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. ADD / CREATE SUPPLIER
    if ($action === 'create') {
        $name = trim($_POST['name']);
        $contact_name = trim($_POST['contact_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);

        if (empty($name)) {
            $_SESSION['flash_error'] = "Supplier name is required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_name, phone, email, address) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $contact_name, $phone, $email, $address]);
                log_action($pdo, 'ADD_SUPPLIER', "Added new supplier '{$name}'.");
                $_SESSION['flash_success'] = "Supplier '{$name}' added successfully.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
            }
        }
    }

    // 2. UPDATE SUPPLIER
    elseif ($action === 'update') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $contact_name = trim($_POST['contact_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);

        if (empty($id) || empty($name)) {
            $_SESSION['flash_error'] = "Supplier ID and Name are required.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, contact_name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $contact_name, $phone, $email, $address, $id]);
                log_action($pdo, 'UPDATE_SUPPLIER', "Updated supplier details for '{$name}' (ID: {$id}).");
                $_SESSION['flash_success'] = "Supplier details updated successfully.";
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
            }
        }
    }

    header("Location: suppliers.php");
    exit();
}

// Fetch all suppliers
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC")->fetchAll();
?>

<div class="page-header">
    <div class="page-title">
        <h2>Supplier Directory</h2>
        <p>Manage product distribution contracts, corporate contacts, and warehouse procurement channels</p>
    </div>
    <div class="header-actions">
        <button id="add-supplier-btn" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Add Supplier
        </button>
    </div>
</div>

<!-- Suppliers Table -->
<div class="data-card">
    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Supplier ID</th>
                    <th>Company Name</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>Email Address</th>
                    <th>Physical Address</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted);">No suppliers registered.</td></tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td><code>SPL-<?= str_pad($s['id'], 3, '0', STR_PAD_LEFT) ?></code></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars($s['contact_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($s['phone'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($s['email'] ?? 'N/A') ?></td>
                            <td><small><?= htmlspecialchars($s['address'] ?? 'N/A') ?></small></td>
                            <td style="text-align: center;">
                                <button class="btn btn-secondary edit-supplier-btn" 
                                        style="padding: 6px 12px; width: auto; font-size: 13px;"
                                        data-supplier='<?= json_encode($s, JSON_HEX_APOS) ?>'>
                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal dialog for Adding Supplier -->
<div class="modal" id="add-supplier-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Register New Supplier</h3>
            <span class="modal-close" id="close-add-modal">&times;</span>
        </div>
        <hr style="border:0; border-top: 1.5px solid var(--border-color);">
        <form action="suppliers.php" method="POST">
            <input type="hidden" name="action" value="create">
            <div class="product-form-shell">
                <div class="form-group">
                    <label for="add-name">Company Name *</label>
                    <input type="text" id="add-name" name="name" class="input-field" required>
                </div>
                <div class="form-group">
                    <label for="add-contact">Contact Person</label>
                    <input type="text" id="add-contact" name="contact_name" class="input-field">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add-phone">Phone Number</label>
                        <input type="text" id="add-phone" name="phone" class="input-field">
                    </div>
                    <div class="form-group">
                        <label for="add-email">Email Address</label>
                        <input type="email" id="add-email" name="email" class="input-field">
                    </div>
                </div>
                <div class="form-group">
                    <label for="add-address">Office Address</label>
                    <textarea id="add-address" name="address" class="input-field" rows="2"></textarea>
                </div>
            </div>
            <div class="product-form-actions modal-footer">
                <button type="button" class="btn btn-secondary" id="cancel-add-supplier">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal dialog for Editing Supplier -->
<div class="modal" id="edit-supplier-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Supplier details</h3>
            <span class="modal-close" id="close-edit-modal">&times;</span>
        </div>
        <hr style="border:0; border-top: 1.5px solid var(--border-color);">
        <form action="suppliers.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            <div class="product-form-shell">
                <div class="form-group">
                    <label for="edit-name">Company Name *</label>
                    <input type="text" id="edit-name" name="name" class="input-field" required>
                </div>
                <div class="form-group">
                    <label for="edit-contact">Contact Person</label>
                    <input type="text" id="edit-contact" name="contact_name" class="input-field">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-phone">Phone Number</label>
                        <input type="text" id="edit-phone" name="phone" class="input-field">
                    </div>
                    <div class="form-group">
                        <label for="edit-email">Email Address</label>
                        <input type="email" id="edit-email" name="email" class="input-field">
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit-address">Office Address</label>
                    <textarea id="edit-address" name="address" class="input-field" rows="2"></textarea>
                </div>
            </div>
            <div class="product-form-actions modal-footer">
                <button type="button" class="btn btn-secondary" id="cancel-edit-supplier">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Supplier</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const addModal = document.getElementById('add-supplier-modal');
    const editModal = document.getElementById('edit-supplier-modal');
    const addBtn = document.getElementById('add-supplier-btn');
    const closeAddModal = document.getElementById('close-add-modal');
    const cancelAddSupplierBtn = document.getElementById('cancel-add-supplier');
    const closeEditModal = document.getElementById('close-edit-modal');
    const cancelEditSupplierBtn = document.getElementById('cancel-edit-supplier');

    addBtn.addEventListener('click', () => addModal.classList.add('active'));
    closeAddModal.addEventListener('click', () => addModal.classList.remove('active'));
    if (cancelAddSupplierBtn) {
        cancelAddSupplierBtn.addEventListener('click', () => addModal.classList.remove('active'));
    }
    closeEditModal.addEventListener('click', () => editModal.classList.remove('active'));
    if (cancelEditSupplierBtn) {
        cancelEditSupplierBtn.addEventListener('click', () => editModal.classList.remove('active'));
    }

    document.querySelectorAll('.edit-supplier-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const supplier = JSON.parse(btn.getAttribute('data-supplier'));

            document.getElementById('edit-id').value = supplier.id;
            document.getElementById('edit-name').value = supplier.name;
            document.getElementById('edit-contact').value = supplier.contact_name || '';
            document.getElementById('edit-phone').value = supplier.phone || '';
            document.getElementById('edit-email').value = supplier.email || '';
            document.getElementById('edit-address').value = supplier.address || '';

            editModal.classList.add('active');
        });
    });

    window.addEventListener('click', (event) => {
        if (event.target === addModal) {
            addModal.classList.remove('active');
        }
        if (event.target === editModal) {
            editModal.classList.remove('active');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
