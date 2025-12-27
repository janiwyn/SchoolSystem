<?php
$title = "Manage User Accounts";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['admin']);

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user'])) {
    $user_id = intval($_POST['user_id']);
    $stmt = $mysqli->prepare("UPDATE users SET status = 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_users.php?success=approved");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_user'])) {
    $user_id = intval($_POST['user_id']);
    $stmt = $mysqli->prepare("UPDATE users SET status = 3 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_users.php?success=rejected");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['suspend_user'])) {
    $user_id = intval($_POST['user_id']);
    $stmt = $mysqli->prepare("UPDATE users SET status = 2 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_users.php?success=suspended");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_user'])) {
    $user_id = intval($_POST['user_id']);
    $stmt = $mysqli->prepare("UPDATE users SET status = 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_users.php?success=activated");
    exit();
}

// Include layout
require_once __DIR__ . '/../helper/layout.php';

// Get pending users
$pendingQuery = "SELECT id, name, email, username, role, created_at FROM users WHERE status = 0 ORDER BY created_at DESC";
$pendingResult = $mysqli->query($pendingQuery);
$pendingUsers = $pendingResult->fetch_all(MYSQLI_ASSOC);

// Get active users
$activeQuery = "SELECT id, name, email, username, role, created_at FROM users WHERE status = 1 ORDER BY created_at DESC";
$activeResult = $mysqli->query($activeQuery);
$activeUsers = $activeResult->fetch_all(MYSQLI_ASSOC);

// Get suspended users
$suspendedQuery = "SELECT id, name, email, username, role, created_at FROM users WHERE status = 2 ORDER BY created_at DESC";
$suspendedResult = $mysqli->query($suspendedQuery);
$suspendedUsers = $suspendedResult->fetch_all(MYSQLI_ASSOC);
?>

<style>
.user-tabs {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
}

.user-tab-btn {
    padding: 10px 20px;
    font-weight: 600;
    border-radius: 4px;
    border: 2px solid #17a2b8;
    background-color: white;
    color: #17a2b8;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.user-tab-btn.active {
    background-color: #17a2b8;
    color: white;
}

.user-tab-btn:hover {
    background-color: #17a2b8;
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.badge-pending {
    background-color: #ffc107;
    color: #000;
    padding: 6px 12px;
    border-radius: 4px;
    font-weight: 600;
}

.badge-active {
    background-color: #28a745;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    font-weight: 600;
}

.badge-suspended {
    background-color: #dc3545;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    font-weight: 600;
}

.action-btn-group {
    display: flex;
    gap: 8px;
}

.btn-approve {
    background-color: #28a745;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
}

.btn-reject {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
}

.btn-suspend {
    background-color: #ffc107;
    color: #000;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
}

.btn-activate {
    background-color: #17a2b8;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
}
</style>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success mb-4">
        <i class="bi bi-check-circle"></i> 
        <?php
        switch ($_GET['success']) {
            case 'approved': echo 'User account approved successfully!'; break;
            case 'rejected': echo 'User account rejected successfully!'; break;
            case 'suspended': echo 'User account suspended successfully!'; break;
            case 'activated': echo 'User account activated successfully!'; break;
        }
        ?>
    </div>
<?php endif; ?>

<!-- User Tabs -->
<div class="user-tabs">
    <button class="user-tab-btn active" onclick="switchTab('pending')">
        <i class="bi bi-clock-history"></i> Pending Approval (<?= count($pendingUsers) ?>)
    </button>
    <button class="user-tab-btn" onclick="switchTab('active')">
        <i class="bi bi-check-circle"></i> Active Users (<?= count($activeUsers) ?>)
    </button>
    <button class="user-tab-btn" onclick="switchTab('suspended')">
        <i class="bi bi-x-circle"></i> Suspended Users (<?= count($suspendedUsers) ?>)
    </button>
</div>

<!-- Pending Users Tab -->
<div id="pending-tab" class="tab-content active">
    <div class="card">
        <div class="card-header" style="background-color: #17a2b8; color: white;">
            <h5 class="mb-0">Pending User Approvals</h5>
        </div>
        <div class="card-body">
            <?php if (empty($pendingUsers)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No pending user approvals.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingUsers as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                                    <td><span class="badge-pending">Pending</span></td>
                                    <td>
                                        <div class="action-btn-group">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="approve_user" class="btn-approve" onclick="return confirm('Approve this user?')">
                                                    <i class="bi bi-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="reject_user" class="btn-reject" onclick="return confirm('Reject this user?')">
                                                    <i class="bi bi-x"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Active Users Tab -->
<div id="active-tab" class="tab-content">
    <div class="card">
        <div class="card-header" style="background-color: #28a745; color: white;">
            <h5 class="mb-0">Active Users</h5>
        </div>
        <div class="card-body">
            <?php if (empty($activeUsers)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No active users.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeUsers as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                                    <td><span class="badge-active">Active</span></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" name="suspend_user" class="btn-suspend" onclick="return confirm('Suspend this user?')">
                                                <i class="bi bi-pause-circle"></i> Suspend
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Suspended Users Tab -->
<div id="suspended-tab" class="tab-content">
    <div class="card">
        <div class="card-header" style="background-color: #dc3545; color: white;">
            <h5 class="mb-0">Suspended Users</h5>
        </div>
        <div class="card-body">
            <?php if (empty($suspendedUsers)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No suspended users.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suspendedUsers as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                                    <td><span class="badge-suspended">Suspended</span></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" name="activate_user" class="btn-activate" onclick="return confirm('Activate this user?')">
                                                <i class="bi bi-play-circle"></i> Activate
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active from all buttons
    document.querySelectorAll('.user-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Mark button as active
    event.target.classList.add('active');
}
</script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>


<!-- Username: superadmin
Password: password
Role: Admin
Status: Active (1) -->