<?php
require_once __DIR__ . '/includes/header.php';
require_roles(['Admin']);

// Pagination and Limits
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Filter logs by action type
$filterAction = $_GET['action_type'] ?? '';

// Build Query
$queryStr = "
    SELECT a.*, u.username, u.role
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($filterAction)) {
    $queryStr .= " AND a.action = ?";
    $params[] = $filterAction;
}

$queryStr .= " ORDER BY a.id DESC LIMIT ? OFFSET ?";

try {
    // Total count for pagination
    $countQuery = "SELECT COUNT(*) FROM audit_logs WHERE 1=1";
    if (!empty($filterAction)) {
        $countQuery .= " AND action = ?";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute([$filterAction]);
    } else {
        $countStmt = $pdo->query($countQuery);
    }
    $totalLogs = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $limit);

    // Fetch Logs
    $stmt = $pdo->prepare($queryStr);
    // Bind parameters manually to handle limits correctly
    if (!empty($filterAction)) {
        $stmt->bindValue(1, $filterAction, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    $logs = $stmt->fetchAll();

    // Fetch distinct actions for filter dropdown
    $actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Audit log load failure: " . $e->getMessage());
}
?>

<div class="page-header">
    <div class="page-title">
        <h2>System Audit Logs</h2>
        <p>Review system events, employee login sequences, product modifications, and transaction history</p>
    </div>
</div>

<!-- Filters -->
<div class="data-card" style="margin-bottom: 24px; padding: 18px 24px;">
    <form action="audit_logs.php" method="GET" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
            <label for="filter-action" style="margin-bottom: 6px;">Filter Action Type</label>
            <select name="action_type" id="filter-action" class="input-field">
                <option value="">All Action Types</option>
                <?php foreach ($actions as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>" <?= ($filterAction === $act) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($act) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="width: auto;">
                <i class="fa-solid fa-filter"></i> Filter Logs
            </button>
            <a href="audit_logs.php" class="btn btn-secondary" style="width: auto;">Clear</a>
        </div>
    </form>
</div>

<!-- Audit Log Table -->
<div class="data-card">
    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No system event logs found.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><code>LOG-<?= str_pad($log['id'], 5, '0', STR_PAD_LEFT) ?></code></td>
                            <td><?= $log['created_at'] ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                            <td>
                                <?php if ($log['role'] === 'Admin'): ?>
                                    <span class="badge badge-danger">Admin</span>
                                <?php elseif ($log['role'] === 'Manager'): ?>
                                    <span class="badge badge-warning">Manager</span>
                                <?php elseif ($log['role'] === 'Cashier'): ?>
                                    <span class="badge badge-success">Cashier</span>
                                <?php else: ?>
                                    <span class="badge" style="background-color: var(--bg-accent); color: var(--text-secondary);">System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= in_array($log['action'], ['LOGIN_FAILED', 'LOW_STOCK_ALERT', 'DELETE_PRODUCT']) ? 'badge-danger' : 'badge-primary' ?>">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td><small><?= htmlspecialchars($log['details']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination controls -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 20px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="audit_logs.php?page=<?= $i ?>&action_type=<?= urlencode($filterAction) ?>" 
                   class="btn <?= ($page === $i) ? 'btn-primary' : 'btn-secondary' ?>" 
                   style="width: auto; padding: 6px 12px; font-size: 13px;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
