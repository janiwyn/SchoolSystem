<?php
$title = "Tuition Management";
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
                // Redirect to prevent form resubmission - BEFORE layout include
                header("Location: tuition.php?success=1");
                exit();
            } else {
                $error = "Error adding tuition: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Tuition record added successfully!";
}

// Include layout AFTER all header operations
require_once __DIR__ . '/../helper/layout.php';

// Build filter query
$filterWhere = "1=1";
$year_filter = $_GET['year'] ?? '';
$class_filter = $_GET['class'] ?? '';
$term_filter = $_GET['term'] ?? '';

if ($year_filter) {
    $filterWhere .= " AND YEAR(fee_structure.created_at) = '" . intval($year_filter) . "'";
}
if ($class_filter) {
    $filterWhere .= " AND fee_structure.class_id = " . intval($class_filter);
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

// Get unique years from fee_structure table
$yearsQuery = "SELECT DISTINCT YEAR(created_at) as year FROM fee_structure ORDER BY year DESC";
$yearsResult = $mysqli->query($yearsQuery);
$years = $yearsResult->fetch_all(MYSQLI_ASSOC);

// Get unique classes from fee_structure table
$filterClassesQuery = "SELECT DISTINCT fs.class_id, c.class_name 
                       FROM fee_structure fs 
                       LEFT JOIN classes c ON fs.class_id = c.id 
                       ORDER BY c.class_name ASC";
$filterClassesResult = $mysqli->query($filterClassesQuery);
$filterClasses = $filterClassesResult->fetch_all(MYSQLI_ASSOC);

// Get unique terms for filter
$termsQuery = "SELECT DISTINCT term FROM fee_structure ORDER BY term ASC";
$termsResult = $mysqli->query($termsQuery);
$terms = $termsResult->fetch_all(MYSQLI_ASSOC);

$styles = "<style>
    .form-header {
        background-color: #17a2b8 !important;
    }
    
    .btn-form-submit {
        background-color: #17a2b8;
        border-color: #17a2b8;
        color: white;
        font-weight: 600;
        padding: 10px 24px;
    }
    
    .btn-form-submit:hover {
        background-color: #138496;
        border-color: #138496;
        color: white;
    }
    
    .filter-card {
        background: #f8f9fa;
        border: none;
        margin-bottom: 30px;
    }
    
    .filter-card .card-body {
        padding: 25px;
    }
    
    .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        align-items: flex-end;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .filter-group label {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
    }
    
    .filter-group input,
    .filter-group select {
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        height: 40px;
    }
    
    .filter-group input:focus,
    .filter-group select:focus {
        border-color: #17a2b8;
        box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
    }
    
    .filter-buttons {
        display: flex;
        gap: 10px;
    }
    
    .btn-filter {
        background-color: #17a2b8;
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
        height: 40px;
        font-size: 14px;
    }
    
    .btn-filter:hover {
        background-color: #138496;
    }
    
    .btn-reset {
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        height: 40px;
        width: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-reset:hover {
        background-color: #5a6268;
    }
    
    .table-container {
        overflow-x: auto;
    }
    
    .table {
        margin: 0;
        font-size: 14px;
    }
    
    .table thead th {
        background-color: #17a2b8;
        color: white;
        font-weight: 600;
        border: none;
        padding: 16px 12px;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
    }
    
    .table tbody td {
        padding: 14px 12px;
        border-color: #eee;
        vertical-align: middle;
    }
    
    .table tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    .table tbody tr:last-child td {
        border-bottom: 1px solid #eee;
    }
</style>";
echo $styles;
?>

<!-- Add Tuition Form -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header form-header text-white">
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
                <button type="submit" name="add_tuition" class="btn btn-form-submit">
                    <i class="bi bi-plus-circle"></i> Add Tuition
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Filter Section -->
<div class="card filter-card">
    <div class="card-body">
        <form method="GET">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Year</label>
                    <select name="year" class="form-control">
                        <option value="">All Years</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y['year'] ?>" <?= $year_filter == $y['year'] ? 'selected' : '' ?>>
                                <?= $y['year'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Class</label>
                    <select name="class" class="form-control">
                        <option value="">All Classes</option>
                        <?php foreach ($filterClasses as $fc): ?>
                            <option value="<?= $fc['class_id'] ?>" <?= $class_filter == $fc['class_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fc['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Term</label>
                    <select name="term" class="form-control">
                        <option value="">All Terms</option>
                        <?php foreach ($terms as $t): ?>
                            <option value="<?= $t['term'] ?>" <?= $term_filter == $t['term'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['term']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <div class="filter-buttons">
                        <button type="submit" class="btn-filter">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="tuition.php" class="btn-reset">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tuition Records Table -->
<div class="card shadow-sm border-0">
    <div class="card-body">
        <?php if (empty($tuitions)): ?>
            <div class="alert alert-info">No tuition records found.</div>
        <?php else: ?>
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Year</th>
                            <th>Class</th>
                            <th>Term</th>
                            <th>Expected Tuition</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tuitions as $tuition): ?>
                            <tr>
                                <td><?= date('Y-m-d H:i', strtotime($tuition['created_at'])) ?></td>
                                <td><?= $tuition['year'] ?></td>
                                <td><?= htmlspecialchars($tuition['class_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($tuition['term']) ?></td>
                                <td><?= number_format($tuition['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($tuition['recorded_by'] ?? 'System') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
