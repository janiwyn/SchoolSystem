<?php
$title = "Tuition Management";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Get all classes for dropdown
$classesQuery = "SELECT id, class_name FROM classes ORDER BY class_name ASC";
$classesResult = $mysqli->query($classesQuery);
if (!$classesResult) {
    // Capture DB error so we can display it instead of crashing with 500
    $error = "Database error loading classes: " . $mysqli->error;
    $classes = [];
} else {
    $classes = $classesResult->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tuition'])) {
    $class_name = trim($_POST['class_name']);
    $term = trim($_POST['term']);
    $amount = trim($_POST['amount']);

    // Only class and term are strictly required
    if (!$class_name || !$term) {
        $error = "Class and term are required";
    } else {
        // If amount is empty, treat as 0
        if ($amount === '' || $amount === null) {
            $amount = 0;
        } elseif (!is_numeric($amount) || $amount < 0) {
            $error = "Please enter a valid amount (0 or more)";
        }

        if ($error === '') {
            $amount = (float)$amount;

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
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_tuition'])) {
    $tuition_id = intval($_POST['tuition_id']);
    $class_name = trim($_POST['class_name']);
    $term = trim($_POST['term']);
    $amount = trim($_POST['amount']);

    if (!$class_name || !$term) {
        $error = "Class and term are required";
    } else {
        if ($amount === '' || $amount === null) {
            $amount = 0;
        } elseif (!is_numeric($amount) || $amount < 0) {
            $error = "Please enter a valid amount (0 or more)";
        }

        if ($error === '') {
            $amount = (float)$amount;

            // Get or create class
            $checkClassStmt = $mysqli->prepare("SELECT id FROM classes WHERE class_name = ?");
            $checkClassStmt->bind_param("s", $class_name);
            $checkClassStmt->execute();
            $classResult = $checkClassStmt->get_result();
            
            if ($classResult->num_rows > 0) {
                $classRow = $classResult->fetch_assoc();
                $class_id = $classRow['id'];
            } else {
                $insertClassStmt = $mysqli->prepare("INSERT INTO classes (class_name) VALUES (?)");
                $insertClassStmt->bind_param("s", $class_name);
                $insertClassStmt->execute();
                $class_id = $mysqli->insert_id;
                $insertClassStmt->close();
            }
            $checkClassStmt->close();

            // Update tuition record
            $stmt = $mysqli->prepare("UPDATE fee_structure SET class_id = ?, term = ?, amount = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("isdi", $class_id, $term, $amount, $tuition_id);
                if ($stmt->execute()) {
                    // Redirect to prevent form resubmission - BEFORE layout include
                    header("Location: tuition.php?updated=1");
                    exit();
                } else {
                    $error = "Error updating tuition: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Check for success/update messages
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Tuition record added successfully!";
}

if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = "Tuition record updated successfully!";
}

if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = "Tuition record deleted successfully!";
}

// Include layout AFTER all header operations
require_once __DIR__ . '/../helper/layout.php';

// Build filter query (remove year filter)
$filterWhere = "1=1";
$class_filter = $_GET['class'] ?? '';
$term_filter = $_GET['term'] ?? '';

if ($class_filter) {
    $filterWhere .= " AND fee_structure.class_id = " . intval($class_filter);
}
if ($term_filter) {
    $filterWhere .= " AND fee_structure.term = '" . $mysqli->real_escape_string($term_filter) . "'";
}

// Fetch tuition records with user and class info (remove year)
$query = "SELECT 
    fee_structure.id,
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
if (!$result) {
    $error = "Database error loading tuition records: " . $mysqli->error;
    $tuitions = [];
} else {
    $tuitions = $result->fetch_all(MYSQLI_ASSOC);
}

// Get unique classes from fee_structure table
$filterClassesQuery = "SELECT DISTINCT fs.class_id, c.class_name 
                       FROM fee_structure fs 
                       LEFT JOIN classes c ON fs.class_id = c.id 
                       ORDER BY c.class_name ASC";
$filterClassesResult = $mysqli->query($filterClassesQuery);
if (!$filterClassesResult) {
    $error = "Database error loading filter classes: " . $mysqli->error;
    $filterClasses = [];
} else {
    $filterClasses = $filterClassesResult->fetch_all(MYSQLI_ASSOC);
}

// Get unique terms for filter
$termsQuery = "SELECT DISTINCT term FROM fee_structure ORDER BY term ASC";
$termsResult = $mysqli->query($termsQuery);
if (!$termsResult) {
    $error = "Database error loading terms: " . $mysqli->error;
    $terms = [];
} else {
    $terms = $termsResult->fetch_all(MYSQLI_ASSOC);
}

// Get current user role
$userRole = $_SESSION['role'] ?? '';
$canAddTuition = ($userRole === 'admin');
$canModifyTuition = ($userRole === 'admin');
?>

<!-- Global DB error message (will show on InfinityFree instead of HTTP 500) -->
<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Add Tuition Form - Only show for Admin -->
<?php if ($canAddTuition): ?>
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
                <div class="col-md-4">
                    <label class="form-label">Class</label>
                    <input type="text" name="class_name" class="form-control" placeholder="e.g., Form 1A" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Term</label>
                    <select name="term" class="form-control" required>
                        <option value="">Select Term</option>
                        <option value="Term 1">Term 1</option>
                        <option value="Term 2">Term 2</option>
                        <option value="Term 3">Term 3</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Expected Tuition</label>
                    <!-- removed required so it can be empty / 0 -->
                    <input type="number" name="amount" class="form-control" step="0.01" min="0" placeholder="0.00">
                </div>

                <div class="col-12">
                    <button type="submit" name="add_tuition" class="btn btn-form-submit">
                        <i class="bi bi-plus-circle"></i> Add Tuition
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Restricted form card for Bursar/Principal -->
    <div class="card shadow-sm border-0 mb-4" style="cursor: pointer; opacity: 0.7;" data-bs-toggle="modal" data-bs-target="#tuitionAddRestrictionModal">
        <div class="card-header form-header text-white">
            <h5 class="mb-0">Add Tuition Record <i class="bi bi-lock-fill ms-2"></i></h5>
        </div>
        <div class="card-body text-center py-5">
            <i class="bi bi-shield-lock" style="font-size: 48px; color: #6c757d; margin-bottom: 15px;"></i>
            <p class="text-muted mb-0">Only Admin can add tuition records. Click for details.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Filter Section -->
<div class="card filter-card">
    <div class="card-body">
        <form method="GET">
            <div class="filter-row">
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
                            <th>Class</th>
                            <th>Term</th>
                            <th>Expected Tuition</th>
                            <th>Recorded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tuitions as $tuition): ?>
                            <tr>
                                <td><?= date('Y-m-d H:i', strtotime($tuition['created_at'])) ?></td>
                                <td><?= htmlspecialchars($tuition['class_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($tuition['term']) ?></td>
                                <td><?= number_format($tuition['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($tuition['recorded_by'] ?? 'System') ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($canModifyTuition): ?>
                                            <!-- Admin can edit/delete -->
                                            <button type="button" class="btn-icon-edit" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal" onclick="loadEditForm(<?= $tuition['id'] ?>, '<?= htmlspecialchars($tuition['class_name']) ?>', '<?= htmlspecialchars($tuition['term']) ?>', <?= $tuition['amount'] ?>)" style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease; text-decoration: none;">
                                                Edit
                                            </button>
                                            <a href="deleteTuition.php?id=<?= $tuition['id'] ?>" class="btn-icon-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this record?')" style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #dc3545; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease; text-decoration: none;">
                                                Delete
                                            </a>
                                        <?php else: ?>
                                            <!-- Bursar/Principal cannot edit/delete -->
                                            <button type="button" class="btn-icon-edit-restricted" title="Edit" data-bs-toggle="modal" data-bs-target="#tuitionRestrictionModal" style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease; text-decoration: none;">
                                                Edit
                                            </button>
                                            <button type="button" class="btn-icon-delete-restricted" title="Delete" data-bs-toggle="modal" data-bs-target="#tuitionRestrictionModal" style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease; text-decoration: none;">
                                                Delete
                                            </button>
                                        <?php endif; ?>
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

<!-- Edit Tuition Modal - Only shown for Admin -->
<?php if ($canModifyTuition): ?>
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header form-header text-white">
                    <h5 class="modal-title">Edit Tuition Record</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="modal-body">
                    <input type="hidden" name="edit_tuition" value="1">
                    <input type="hidden" name="tuition_id" id="editTuitionId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Class</label>
                            <input type="text" name="class_name" id="editClassName" class="form-control" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Term</label>
                            <input type="text" name="term" id="editTerm" class="form-control" readonly>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Expected Tuition</label>
                            <!-- remove required to allow empty / 0 -->
                            <input type="number" name="amount" id="editAmount" class="form-control" step="0.01" min="0">
                        </div>
                    </div>

                    <div class="modal-footer mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-form-submit">
                            <i class="bi bi-check-circle"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Restriction Modal for Add Tuition -->
<div class="modal fade" id="tuitionAddRestrictionModal" tabindex="-1" aria-labelledby="tuitionAddRestrictionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="tuitionAddRestrictionModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Access Restricted
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-shield-lock" style="font-size: 64px; color: #dc3545; margin-bottom: 20px;"></i>
                <h5 class="mb-3">Cannot Add Tuition Records</h5>
                <p class="text-muted mb-0">
                    Only <strong>Admin</strong> has permission to add new tuition records.
                </p>
                <p class="text-muted mt-2">
                    As a <strong class="text-primary"><?= ucfirst($userRole) ?></strong>, you can view tuition records but cannot create new ones.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Restriction Modal for Bursar/Principal -->
<div class="modal fade" id="tuitionRestrictionModal" tabindex="-1" aria-labelledby="tuitionRestrictionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="tuitionRestrictionModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Access Restricted
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-shield-lock" style="font-size: 64px; color: #dc3545; margin-bottom: 20px;"></i>
                <h5 class="mb-3">Cannot Modify Tuition Records</h5>
                <p class="text-muted mb-0">
                    Only <strong>Admin</strong> has permission to edit or delete tuition records.
                </p>
                <p class="text-muted mt-2">
                    As a <strong class="text-primary"><?= ucfirst($userRole) ?></strong>, you can view tuition records but cannot modify them.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add cache‑buster so hosted app loads new JS -->
<script src="../../assets/js/tuition.js?v=2"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
