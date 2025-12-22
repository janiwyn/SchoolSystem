<?php
$title = "Tuition Management";
require_once __DIR__ . '/../helper/layout.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Get all classes for dropdown
$classesQuery = "SELECT id, class_name FROM classes ORDER BY class_name ASC";
$classesResult = $mysqli->query($classesQuery);
$classes = $classesResult->fetch_all(MYSQLI_ASSOC);

// Handle form submission
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tuition'])) {
    $year = trim($_POST['year']);
    $class_name = trim($_POST['class_name']);
    $term = trim($_POST['term']);
    $amount = trim($_POST['amount']);

    if (!$year || !$class_name || !$term || !$amount) {
        $error = "All fields are required";
    } elseif (!is_numeric($year) || $year < 2000 || $year > 2100) {
        $error = "Please enter a valid year";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount";
    } else {
        // Check if class exists, if not create it
        $checkClassStmt = $mysqli->prepare("SELECT id FROM classes WHERE class_name = ?");
        $checkClassStmt->bind_param("s", $class_name);
        $checkClassStmt->execute();
        $classResult = $checkClassStmt->get_result();
        
        if ($classResult->num_rows > 0) {
            $classRow = $classResult->fetch_assoc();
            $class_id = $classRow['id'];
        } else {
            // Insert new class if it doesn't exist
            $insertClassStmt = $mysqli->prepare("INSERT INTO classes (class_name) VALUES (?)");
            $insertClassStmt->bind_param("s", $class_name);
            $insertClassStmt->execute();
            $class_id = $mysqli->insert_id;
            $insertClassStmt->close();
        }
        $checkClassStmt->close();

        // Insert tuition record
        $user_id = $_SESSION['user_id'];
        $stmt = $mysqli->prepare("INSERT INTO fee_structure (class_id, term, amount, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("isdi", $class_id, $term, $amount, $user_id);
            if ($stmt->execute()) {
                $message = "Tuition record added successfully!";
            } else {
                $error = "Error adding tuition: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Build filter query
$filterWhere = "1=1";
$year_filter = $_GET['year'] ?? '';
$class_filter = $_GET['class'] ?? '';
$term_filter = $_GET['term'] ?? '';

if ($year_filter) {
    $filterWhere .= " AND YEAR(fee_structure.created_at) = '" . intval($year_filter) . "'";
}
if ($class_filter) {
    $filterWhere .= " AND classes.class_name LIKE '%" . $mysqli->real_escape_string($class_filter) . "%'";
}
if ($term_filter) {
    $filterWhere .= " AND fee_structure.term = '" . $mysqli->real_escape_string($term_filter) . "'";
}

// Fetch tuition records with user and class info
$query = "SELECT 
    fee_structure.id,
    YEAR(fee_structure.created_at) as year,
    classes.class_name,
    fee_structure.term,
    fee_structure.amount,
    users.name as recorded_by,
    fee_structure.created_at
FROM fee_structure
LEFT JOIN classes ON fee_structure.class_id = classes.id
LEFT JOIN users ON fee_structure.created_by = users.id
WHERE $filterWhere
ORDER BY fee_structure.created_at DESC";

$result = $mysqli->query($query);
$tuitions = $result->fetch_all(MYSQLI_ASSOC);

// Get unique years for filter
$yearsQuery = "SELECT DISTINCT YEAR(created_at) as year FROM fee_structure ORDER BY year DESC";
$yearsResult = $mysqli->query($yearsQuery);
$years = $yearsResult->fetch_all(MYSQLI_ASSOC);

// Get unique terms for filter
$termsQuery = "SELECT DISTINCT term FROM fee_structure ORDER BY term ASC";
$termsResult = $mysqli->query($termsQuery);
$terms = $termsResult->fetch_all(MYSQLI_ASSOC);
?>

<!-- Add Tuition Form -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Add Tuition Record</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Year</label>
                <input type="text" name="year" class="form-control" value="<?= date('Y') ?>" placeholder="e.g., 2024" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Class</label>
                <input type="text" name="class_name" class="form-control" placeholder="e.g., Form 1A" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Term</label>
                <select name="term" class="form-control" required>
                    <option value="">Select Term</option>
                    <option value="Term 1">Term 1</option>
                    <option value="Term 2">Term 2</option>
                    <option value="Term 3">Term 3</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Expected Tuition</label>
                <input type="number" name="amount" class="form-control" step="0.01" min="0" placeholder="0.00" required>
            </div>

            <div class="col-12">
                <button type="submit" name="add_tuition" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add Tuition
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Filter Section -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0">Filter Records</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Year</label>
                <input type="text" name="year" class="form-control" placeholder="All Years" value="<?= htmlspecialchars($year_filter) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Class</label>
                <input type="text" name="class" class="form-control" placeholder="All Classes" value="<?= htmlspecialchars($class_filter) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Term</label>
                <select name="term" class="form-control">
                    <option value="">All Terms</option>
                    <?php foreach ($terms as $t): ?>
                        <option value="<?= $t['term'] ?>" <?= $term_filter == $t['term'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['term']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-info w-100">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="tuition.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tuition Records Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">Tuition Records</h5>
    </div>
    <div class="card-body">
        <?php if (empty($tuitions)): ?>
            <div class="alert alert-info">No tuition records found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Year</th>
                            <th>Class</th>
                            <th>Term</th>
                            <th>Expected Tuition</th>
                            <th>Recorded By</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tuitions as $tuition): ?>
                            <tr>
                                <td><?= $tuition['year'] ?></td>
                                <td><?= htmlspecialchars($tuition['class_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($tuition['term']) ?></td>
                                <td><?= number_format($tuition['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($tuition['recorded_by'] ?? 'System') ?></td>
                                <td><?= date('M d, Y H:i', strtotime($tuition['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
