<?php
$title = "Activity Log";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['admin']);

// Handle acknowledge action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge_log'])) {
    $log_id = intval($_POST['log_id']);
    $stmt = $mysqli->prepare("UPDATE activity_logs SET is_acknowledged = 1, acknowledged_by = ?, acknowledged_at = NOW() WHERE id = ?");
    $stmt->bind_param("ii", $_SESSION['user_id'], $log_id);
    $stmt->execute();
    $stmt->close();
    header("Location: activity_log.php?success=1");
    exit();
}

// Handle toggle system settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_setting'])) {
    $key = $_POST['setting_key'];
    $new_val = intval($_POST['setting_value']);
    $stmt = $mysqli->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->bind_param("is", $new_val, $key);
    $stmt->execute();
    $stmt->close();
    header("Location: activity_log.php?success=2");
    exit();
}

// Include layout AFTER all operations
require_once __DIR__ . '/../helper/layout.php';

// Build filter query
$filterWhere = "1=1";
$status_filter = $_GET['status'] ?? 'unacknowledged';
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

if ($status_filter === 'unacknowledged') {
    $filterWhere .= " AND is_acknowledged = 0";
} elseif ($status_filter === 'acknowledged') {
    $filterWhere .= " AND is_acknowledged = 1";
}

if ($action_filter) {
    $filterWhere .= " AND action = '" . $mysqli->real_escape_string($action_filter) . "'";
}

if ($date_from) {
    $filterWhere .= " AND DATE(created_at) >= '" . $mysqli->real_escape_string($date_from) . "'";
}

if ($date_to) {
    $filterWhere .= " AND DATE(created_at) <= '" . $mysqli->real_escape_string($date_to) . "'";
}

// Get activity logs
$logsQuery = "SELECT 
    id,
    user_name,
    user_role,
    action,
    entity_type,
    entity_name,
    details,
    ip_address,
    is_acknowledged,
    created_at
FROM activity_logs
WHERE $filterWhere
ORDER BY created_at DESC";

$logsResult = $mysqli->query($logsQuery);
$logs = $logsResult->fetch_all(MYSQLI_ASSOC);

// Get unack count
$unackCountQuery = "SELECT COUNT(*) as count FROM activity_logs WHERE is_acknowledged = 0";
$unackCountResult = $mysqli->query($unackCountQuery);
$unackCount = $unackCountResult->fetch_assoc()['count'] ?? 0;

// Get current system settings
$settingsRes = $mysqli->query("SELECT setting_key, setting_value, description FROM system_settings");
$sys_settings = [];
while ($row = $settingsRes->fetch_assoc()) {
    $sys_settings[$row['setting_key']] = $row;
}
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <h4 class="mb-0">
        <i class="bi bi-clock-history"></i> Activity Log
        <?php if ($unackCount > 0): ?>
            <span class="badge bg-danger"><?= $unackCount ?> New</span>
        <?php endif; ?>
    </h4>
    <?php if ($unackCount > 0): ?>
        <form method="POST" style="display: inline;">
            <button type="submit" name="acknowledge_all" class="btn btn-success btn-sm" onclick="return confirm('Acknowledge all activities?')">
                <i class="bi bi-check-all"></i> Acknowledge All
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> 
        <?= ($_GET['success'] == '2') ? 'System permissions updated successfully!' : 'Activity acknowledged successfully!' ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Permission Control Panel -->
<div class="card mb-4 shadow-sm border-0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden;">
    <div class="card-header d-flex align-items-center" style="background-color: #17a2b8; color: #ffffff; padding: 12px 20px; border-bottom: none;">
        <i class="bi bi-shield-lock-fill me-2"></i>
        <h5 class="mb-0" style="font-weight: 600; letter-spacing: 0.5px;">Feature Access Control (Master Switches)</h5>
    </div>
    <div class="card-body" style="background-color: #f8f9fa;">
        <div class="row g-3">
            <?php foreach ($sys_settings as $key => $s): 
                $isOn = (int)$s['setting_value'] === 1;
            ?>
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-white d-flex justify-content-between align-items-center shadow-sm" style="border-color: #e9ecef !important;">
                        <div>
                            <h6 class="mb-1" style="font-weight: 600; color: #2c3e50;"><?= str_replace('_', ' ', ucfirst($key)) ?></h6>
                            <p class="small mb-0" style="color: #6c757d;"><?= htmlspecialchars($s['description']) ?></p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="setting_key" value="<?= $key ?>">
                            <input type="hidden" name="setting_value" value="<?= $isOn ? 0 : 1 ?>">
                            <button type="submit" name="toggle_setting" class="btn <?= $isOn ? 'btn-danger' : 'btn-success' ?> btn-sm px-4" style="border-radius: 20px; font-weight: 500;">
                                <i class="bi <?= $isOn ? 'bi-lock-fill' : 'bi-unlock-fill' ?> me-1"></i>
                                <?= $isOn ? 'Lock' : 'Unlock' ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="card filter-card">
    <div class="card-body">
        <form method="GET">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="unacknowledged" <?= $status_filter === 'unacknowledged' ? 'selected' : '' ?>>Unacknowledged</option>
                        <option value="acknowledged" <?= $status_filter === 'acknowledged' ? 'selected' : '' ?>>Acknowledged</option>
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Action</label>
                    <select name="action" class="form-control">
                        <option value="">All Actions</option>
                        <option value="edit" <?= $action_filter === 'edit' ? 'selected' : '' ?>>Edit</option>
                        <option value="delete" <?= $action_filter === 'delete' ? 'selected' : '' ?>>Delete</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>

                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>

                <div class="filter-group">
                    <div class="filter-buttons">
                        <button type="submit" class="btn-filter">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="activity_log.php" class="btn-reset">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Activity Logs Table -->
<div class="card shadow-sm border-0">
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No activity logs found.
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr class="<?= $log['is_acknowledged'] ? '' : 'table-warning' ?>">
                                <td><?= date('Y-m-d H:i', strtotime($log['created_at'])) ?></td>
                                <td><?= htmlspecialchars($log['user_name']) ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?= ucfirst(htmlspecialchars($log['user_role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['action'] === 'edit'): ?>
                                        <span class="badge bg-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </span>
                                    <?php elseif ($log['action'] === 'delete'): ?>
                                        <span class="badge bg-danger">
                                            <i class="bi bi-trash"></i> Delete
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['entity_name']) ?></td>
                                <td><?= htmlspecialchars($log['details']) ?></td>
                                <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                <td>
                                    <?php if ($log['is_acknowledged']): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle"></i> Acknowledged
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">
                                            <i class="bi bi-exclamation-triangle"></i> New
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$log['is_acknowledged']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                            <button type="submit" name="acknowledge_log" class="btn btn-success btn-sm" title="Acknowledge">
                                                <i class="bi bi-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="../../assets/css/activityLog.css">

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
