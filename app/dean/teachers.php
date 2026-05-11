<?php
$title = "Teacher Allocations";
require_once __DIR__ . '/../helper/layout.php';

// Check roles
if (!in_array($_SESSION['role'], ['dean', 'admin', 'principal'])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$error = '';

// Handle allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate_teacher'])) {
    $user_id = intval($_POST['user_id']);
    $class_id = intval($_POST['class_id']);
    $subject_id = intval($_POST['subject_id']);
    
    if ($user_id && $class_id && $subject_id) {
        // Check if already allocated
        $check = $mysqli->prepare("SELECT id FROM teacher_allocations WHERE user_id = ? AND class_id = ? AND subject_id = ?");
        $check->bind_param("iii", $user_id, $class_id, $subject_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "This teacher is already allocated to this class and subject.";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO teacher_allocations (user_id, class_id, subject_id) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $user_id, $class_id, $subject_id);
            if ($stmt->execute()) {
                $message = "Teacher allocated successfully!";
            } else {
                $error = "Database error: " . $mysqli->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Handle removal
if (isset($_GET['remove_id'])) {
    $remove_id = intval($_GET['remove_id']);
    $mysqli->query("DELETE FROM teacher_allocations WHERE id = $remove_id");
    $message = "Teacher allocation removed.";
}

// Get all staff (Teachers/Deans/etc)
$staff = $mysqli->query("SELECT id, name, role FROM users WHERE status = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
// Get all classes
$classes = $mysqli->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetch_all(MYSQLI_ASSOC);
// Get all subjects
$subjects = $mysqli->query("SELECT id, subject_name FROM subjects ORDER BY subject_name ASC")->fetch_all(MYSQLI_ASSOC);

// Get current allocations
$allocQuery = "SELECT ta.id, u.name as teacher_name, c.class_name, s.subject_name 
               FROM teacher_allocations ta
               JOIN users u ON ta.user_id = u.id
               JOIN classes c ON ta.class_id = c.id
               JOIN subjects s ON ta.subject_id = s.id
               ORDER BY u.name ASC, c.class_name ASC";
$allocations = $mysqli->query($allocQuery)->fetch_all(MYSQLI_ASSOC);
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header text-white py-3" style="background-color: #17a2b8;">
        <h5 class="mb-0">New Teacher Allocation</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Teacher / Staff</label>
                <select name="user_id" class="form-select" required>
                    <option value="">Select Staff...</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= ucfirst($s['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select" required>
                    <option value="">Select Class...</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select" required>
                    <option value="">Select Subject...</option>
                    <?php foreach ($subjects as $sbj): ?>
                        <option value="<?= $sbj['id'] ?>"><?= htmlspecialchars($sbj['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" name="allocate_teacher" class="btn text-white" style="background-color: #17a2b8;">
                    <i class="bi bi-person-plus-fill"></i> Assign Teacher
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3" style="border-bottom: 2px solid #17a2b8;">
        <h5 class="mb-0" style="color: #17a2b8;">Current Teacher Allocations</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background-color: #17a2b8; color: white;">
                    <tr>
                        <th>Teacher Name</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allocations)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No teacher allocations found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allocations as $a): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-soft-primary p-2 rounded-circle me-2">
                                            <i class="bi bi-person-fill text-primary"></i>
                                        </div>
                                        <strong><?= htmlspecialchars($a['teacher_name']) ?></strong>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($a['class_name']) ?></span></td>
                                <td><?= htmlspecialchars($a['subject_name']) ?></td>
                                <td class="text-end">
                                    <a href="?remove_id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this allocation?')">
                                        <i class="bi bi-person-x"></i> Remove
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.bg-soft-primary {
    background-color: rgba(52, 152, 219, 0.1);
}
</style>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
