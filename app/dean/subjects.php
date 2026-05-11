<?php
$title = "Subjects Management";
require_once __DIR__ . '/../helper/layout.php';

// Check roles
if (!in_array($_SESSION['role'], ['dean', 'admin', 'principal'])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$error = '';

// 1. Handle adding a new subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $subject_name = trim($_POST['subject_name']);
    if ($subject_name) {
        $stmt = $mysqli->prepare("INSERT INTO subjects (subject_name) VALUES (?)");
        $stmt->bind_param("s", $subject_name);
        if ($stmt->execute()) {
            $message = "Subject '$subject_name' added successfully!";
        } else {
            $error = "Error: Subject might already exist.";
        }
        $stmt->close();
    }
}

// 2. Handle linking subject to class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_subject'])) {
    $class_id = intval($_POST['class_id']);
    $subject_id = intval($_POST['subject_id']);
    
    if ($class_id && $subject_id) {
        // Check if already linked
        $check = $mysqli->prepare("SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ?");
        $check->bind_param("ii", $class_id, $subject_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "This subject is already linked to this class.";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO class_subjects (class_id, subject_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $class_id, $subject_id);
            if ($stmt->execute()) {
                $message = "Subject linked to class successfully!";
            }
        }
        $check->close();
    }
}

// 3. Handle unlinking
if (isset($_GET['unlink_id'])) {
    $unlink_id = intval($_GET['unlink_id']);
    $mysqli->query("DELETE FROM class_subjects WHERE id = $unlink_id");
    $message = "Subject unlinked successfully!";
}

// Get all subjects
$subjects = $mysqli->query("SELECT * FROM subjects ORDER BY subject_name ASC")->fetch_all(MYSQLI_ASSOC);
// Get all classes
$classes = $mysqli->query("SELECT * FROM classes ORDER BY class_name ASC")->fetch_all(MYSQLI_ASSOC);

// Get linked subjects for table
$linkedQuery = "SELECT cs.id, c.class_name, s.subject_name 
                FROM class_subjects cs
                JOIN classes c ON cs.class_id = c.id
                JOIN subjects s ON cs.subject_id = s.id
                ORDER BY c.class_name ASC, s.subject_name ASC";
$linkedSubjects = $mysqli->query($linkedQuery)->fetch_all(MYSQLI_ASSOC);
?>

<div class="row g-3">
    <!-- Top Row: Forms -->
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header text-white py-2" style="background-color: #17a2b8;">
                <h6 class="mb-0">Add New Subject</h6>
            </div>
            <div class="card-body py-3">
                <form method="POST" class="row g-2 align-items-end">
                    <div class="col-8">
                        <label class="form-label small fw-bold">Subject Name</label>
                        <input type="text" name="subject_name" class="form-control form-control-sm" placeholder="e.g., Mathematics" required>
                    </div>
                    <div class="col-4">
                        <button type="submit" name="add_subject" class="btn btn-sm text-white w-100" style="background-color: #17a2b8;">
                            <i class="bi bi-plus-circle"></i> Create
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header text-white py-2" style="background-color: #17a2b8;">
                <h6 class="mb-0">Link Subject to Class</h6>
            </div>
            <div class="card-body py-3">
                <form method="POST" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Class</label>
                        <select name="class_id" class="form-select form-select-sm" required>
                            <option value="">Select...</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Subject</label>
                        <select name="subject_id" class="form-select form-select-sm" required>
                            <option value="">Select...</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="link_subject" class="btn btn-sm text-white w-100" style="background-color: #17a2b8;">
                            <i class="bi bi-link-45deg"></i> Link
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bottom Row: Full Width Table -->
    <div class="col-12 mt-3">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-2" style="border-bottom: 2px solid #17a2b8;">
                <h6 class="mb-0" style="color: #17a2b8; font-weight: 700;">Class-Subject Allocations</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead style="background-color: #17a2b8; color: white;">
                            <tr>
                                <th class="px-3 py-2">Class Name</th>
                                <th class="py-2">Assigned Subject</th>
                                <th class="text-end px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($linkedSubjects)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">No subjects linked to classes yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($linkedSubjects as $ls): ?>
                                    <tr>
                                        <td class="px-3"><strong><?= htmlspecialchars($ls['class_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($ls['subject_name']) ?></td>
                                        <td class="text-end px-3">
                                            <a href="?unlink_id=<?= $ls['id'] ?>" class="btn btn-sm btn-outline-danger py-0" onclick="return confirm('Unlink this subject?')">
                                                <i class="bi bi-trash"></i>
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
    </div>
</div>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
