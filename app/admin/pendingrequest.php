<?php
$title = "Pending Requests";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

// Only admin can access this page
requireRole(['admin']);

// Handle approval BEFORE layout include
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_student'])) {
    $student_id = intval($_POST['student_id']);
    $stmt = $mysqli->prepare("UPDATE admit_students SET status = 'approved' WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute()) {
            header("Location: pendingrequest.php?tab=admitted_students&approved=1");
            exit();
        }
        $stmt->close();
    }
}

// Handle rejection BEFORE layout include
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_student'])) {
    $student_id = intval($_POST['student_id']);
    $stmt = $mysqli->prepare("DELETE FROM admit_students WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        if ($stmt->execute()) {
            header("Location: pendingrequest.php?tab=admitted_students&rejected=1");
            exit();
        }
        $stmt->close();
    }
}

// Include layout AFTER all header operations
require_once __DIR__ . '/../helper/layout.php';

// Get active tab
$active_tab = $_GET['tab'] ?? 'admitted_students';

// Get unapproved admitted students
$admittedQuery = "SELECT 
    admit_students.id,
    admit_students.admission_no,
    admit_students.first_name,
    admit_students.last_name,
    admit_students.gender,
    c.class_name,
    admit_students.day_boarding,
    admit_students.admission_fee,
    admit_students.uniform_fee,
    admit_students.parent_contact,
    admit_students.parent_email,
    admit_students.status,
    admit_students.created_at
FROM admit_students
LEFT JOIN classes c ON admit_students.class_id = c.id
WHERE admit_students.status = 'unapproved'
ORDER BY admit_students.created_at DESC";

$admittedResult = $mysqli->query($admittedQuery);
$admitted_students = $admittedResult->fetch_all(MYSQLI_ASSOC);

$message = '';
$error = '';
if (isset($_GET['approved']) && $_GET['approved'] == 1) {
    $message = "Student approved successfully!";
}
if (isset($_GET['rejected']) && $_GET['rejected'] == 1) {
    $message = "Student rejected successfully!";
}
?>

<style>
    .pending-card-header {
        background-color: #17a2b8 !important;
    }
    
    .pending-tabs {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }
    
    .pending-tab-btn {
        padding: 8px 16px;
        font-weight: 600;
        border-radius: 4px;
        border: 2px solid #17a2b8;
        background-color: white;
        color: #17a2b8;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
    }
    
    .pending-tab-btn.active {
        background-color: #17a2b8;
        color: white;
    }
    
    .pending-tab-btn:hover {
        background-color: #17a2b8;
        color: white;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .action-button-group {
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
        transition: all 0.3s ease;
    }
    
    .btn-approve:hover {
        background-color: #218838;
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
        transition: all 0.3s ease;
    }
    
    .btn-reject:hover {
        background-color: #c82333;
    }
</style>

<!-- Message Display -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success mb-4"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Pending Request Tabs -->
<div class="pending-tabs">
    <a href="?tab=admitted_students" class="pending-tab-btn <?= $active_tab === 'admitted_students' ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i> Admitted Students
    </a>
    <a href="?tab=student_payments" class="pending-tab-btn <?= $active_tab === 'student_payments' ? 'active' : '' ?>">
        <i class="bi bi-credit-card"></i> Student Payments
    </a>
    <a href="?tab=payrolls" class="pending-tab-btn <?= $active_tab === 'payrolls' ? 'active' : '' ?>">
        <i class="bi bi-file-earmark-text"></i> Payrolls
    </a>
</div>

<!-- Tab 1: Admitted Students -->
<div class="tab-content <?= $active_tab === 'admitted_students' ? 'active' : '' ?>">
    <div class="card shadow-sm border-0">
        <div class="card-header pending-card-header text-white">
            <h5 class="mb-0">Pending Admitted Students</h5>
        </div>
        <div class="card-body">
            <?php if (empty($admitted_students)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No pending student admissions.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Adm No</th>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Class</th>
                                <th>Type</th>
                                <th>Fees</th>
                                <th>Parent Contact</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admitted_students as $student): ?>
                                <tr>
                                    <td><?= htmlspecialchars($student['admission_no']) ?></td>
                                    <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                    <td><?= htmlspecialchars($student['gender']) ?></td>
                                    <td><?= htmlspecialchars($student['class_name'] ?? 'N/A') ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($student['day_boarding']) ?></span></td>
                                    <td><?= number_format($student['admission_fee'] + $student['uniform_fee'], 2) ?></td>
                                    <td><?= htmlspecialchars($student['parent_contact']) ?></td>
                                    <td><?= date('Y-m-d', strtotime($student['created_at'])) ?></td>
                                    <td>
                                        <div class="action-button-group">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                <button type="submit" name="approve_student" class="btn-approve" onclick="return confirm('Approve this student?')">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                <button type="submit" name="reject_student" class="btn-reject" onclick="return confirm('Reject this student?')">
                                                    <i class="bi bi-x-circle"></i> Reject
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

<!-- Tab 2: Student Payments -->
<div class="tab-content <?= $active_tab === 'student_payments' ? 'active' : '' ?>">
    <div class="card shadow-sm border-0">
        <div class="card-header pending-card-header text-white">
            <h5 class="mb-0">Pending Student Payments</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Student payment approvals will be displayed here.
            </div>
        </div>
    </div>
</div>

<!-- Tab 3: Payrolls -->
<div class="tab-content <?= $active_tab === 'payrolls' ? 'active' : '' ?>">
    <div class="card shadow-sm border-0">
        <div class="card-header pending-card-header text-white">
            <h5 class="mb-0">Pending Payrolls</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Payroll approvals will be displayed here.
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
