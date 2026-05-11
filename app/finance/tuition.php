<?php
$title = "Tuition Management";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Get all classes for dropdown
$classesQuery = "SELECT id, class_name FROM classes ORDER BY class_name ASC";
$classesResult = $mysqli->query($classesQuery);
if (!$classesResult) {
    $error = "Database error loading classes: " . $mysqli->error;
    $classes = [];
} else {
    $classes = $classesResult->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission for CLASS FEES
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tuition'])) {
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

            // Check if class exists, if not create it
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

            $user_id = $_SESSION['user_id'];
            $stmt = $mysqli->prepare("INSERT INTO fee_structure (class_id, term, amount, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("isdi", $class_id, $term, $amount, $user_id);
                if ($stmt->execute()) {
                    header("Location: tuition.php?success=1&tab=class");
                    exit();
                } else {
                    $error = "Error adding tuition: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Handle form submission for CATEGORY FEES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category_fee'])) {
    $cat_name = trim($_POST['category_name']);
    $term = trim($_POST['term']);
    $amount = trim($_POST['amount']);

    if (!$cat_name || !$term) {
        $error = "Category and term are required";
    } else {
        if ($amount === '' || $amount === null) $amount = 0;
        if ($error === '') {
            $amount = (float)$amount;
            $user_id = $_SESSION['user_id'];
            $stmt = $mysqli->prepare("INSERT INTO category_fees (category_name, term, amount, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("ssdi", $cat_name, $term, $amount, $user_id);
                if ($stmt->execute()) {
                    header("Location: tuition.php?success=1&tab=category");
                    exit();
                } else {
                    $error = "Error adding category fee: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Handle edit form submission for CLASS FEES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_tuition'])) {
    $tuition_id = intval($_POST['tuition_id']);
    $class_name = trim($_POST['class_name']);
    $term = trim($_POST['term']);
    $amount = trim($_POST['amount']);

    if (!$class_name || !$term) {
        $error = "Class and term are required";
    } else {
        if ($amount === '' || $amount === null) $amount = 0;
        if ($error === '') {
            $amount = (float)$amount;
            $checkClassStmt = $mysqli->prepare("SELECT id FROM classes WHERE class_name = ?");
            $checkClassStmt->bind_param("s", $class_name);
            $checkClassStmt->execute();
            $classResult = $checkClassStmt->get_result();
            if ($classResult->num_rows > 0) {
                $class_id = $classResult->fetch_assoc()['id'];
            } else {
                $insertClassStmt = $mysqli->prepare("INSERT INTO classes (class_name) VALUES (?)");
                $insertClassStmt->bind_param("s", $class_name);
                $insertClassStmt->execute();
                $class_id = $mysqli->insert_id;
                $insertClassStmt->close();
            }
            $checkClassStmt->close();

            $stmt = $mysqli->prepare("UPDATE fee_structure SET class_id = ?, term = ?, amount = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("isdi", $class_id, $term, $amount, $tuition_id);
                if ($stmt->execute()) {
                    header("Location: tuition.php?updated=1&tab=class");
                    exit();
                } else {
                    $error = "Error updating tuition: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Handle edit form submission for CATEGORY FEES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category_fee_submit'])) {
    $cf_id = intval($_POST['cf_id']);
    $cat_name = trim($_POST['category_name']);
    $term = trim($_POST['term']);
    $amount = trim($_POST['amount']);

    if (!$cat_name || !$term) {
        $error = "Category and term are required";
    } else {
        if ($amount === '' || $amount === null) $amount = 0;
        if ($error === '') {
            $amount = (float)$amount;
            $stmt = $mysqli->prepare("UPDATE category_fees SET category_name = ?, term = ?, amount = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ssdi", $cat_name, $term, $amount, $cf_id);
                if ($stmt->execute()) {
                    header("Location: tuition.php?updated=1&tab=category");
                    exit();
                } else {
                    $error = "Error updating category fee: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Check for success/update messages
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Record added successfully!";
}
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = "Record updated successfully!";
}
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = "Record deleted successfully!";
}

// Include layout AFTER all header operations
require_once __DIR__ . '/../helper/layout.php';

// Fetch Class Tuition records
$query = "SELECT fs.id, c.class_name, fs.term, fs.amount, u.name as recorded_by, fs.created_at
          FROM fee_structure fs
          LEFT JOIN classes c ON fs.class_id = c.id
          LEFT JOIN users u ON fs.created_by = u.id
          ORDER BY fs.created_at DESC";
$classTuitions = $mysqli->query($query)->fetch_all(MYSQLI_ASSOC);

// Fetch Category Fee records
$queryCat = "SELECT cf.id, cf.category_name, cf.term, cf.amount, u.name as recorded_by, cf.created_at
             FROM category_fees cf
             LEFT JOIN users u ON cf.created_by = u.id
             ORDER BY cf.created_at DESC";
$categoryFees = [];
$catRes = $mysqli->query($queryCat);
if ($catRes) $categoryFees = $catRes->fetch_all(MYSQLI_ASSOC);

$userRole = $_SESSION['role'] ?? '';
$canModifyTuition = ($userRole === 'admin');

// Determine active tab
$activeTab = $_GET['tab'] ?? 'class';
?>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i> <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" id="tuitionTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'class' ? 'active' : '' ?>" id="class-tab" data-bs-toggle="tab" data-bs-target="#classFees" type="button" role="tab">
            <i class="bi bi-book me-2"></i> Class Fees
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'category' ? 'active' : '' ?>" id="category-tab" data-bs-toggle="tab" data-bs-target="#categoryFeesTab" type="button" role="tab">
            <i class="bi bi-tags me-2"></i> Category Fees
        </button>
    </li>
</ul>

<div class="tab-content" id="tuitionTabsContent">
    <!-- Class Fees Tab -->
    <div class="tab-pane fade <?= $activeTab === 'class' ? 'show active' : '' ?>" id="classFees" role="tabpanel">
        <?php if ($canModifyTuition): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header form-header text-white">
                    <h5 class="mb-0">Add Tuition Record</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Class</label>
                            <input type="text" name="class_name" class="form-control" placeholder="e.g. Form 1A" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Term</label>
                            <input type="text" name="term" class="form-control" placeholder="e.g. Term 1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expected Tuition</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="add_tuition" class="btn btn-form-submit">
                                <i class="bi bi-plus-circle me-2"></i> Add Tuition
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Term</th>
                                <th>Expected Tuition</th>
                                <th>Recorded By</th>
                                <th>Date & Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classTuitions as $t): ?>
                                <tr>
                                    <td><?= htmlspecialchars($t['class_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($t['term']) ?></td>
                                    <td><?= number_format($t['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($t['recorded_by'] ?? 'System') ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($t['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($canModifyTuition): ?>
                                                <button type="button" class="btn-icon-edit" title="Edit" onclick="loadEditForm(<?= $t['id'] ?>, '<?= htmlspecialchars($t['class_name'] ?? 'N/A', ENT_QUOTES) ?>', '<?= htmlspecialchars($t['term'], ENT_QUOTES) ?>', <?= $t['amount'] ?>)" data-bs-toggle="modal" data-bs-target="#editModal" style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease; text-decoration: none;">
                                                    Edit
                                                </button>
                                                <a href="deleteTuition.php?id=<?= $t['id'] ?>&type=class" class="btn-icon-delete" title="Delete" onclick="return confirm('Delete this record?')" style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #dc3545; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease; text-decoration: none;">
                                                    Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Fees Tab -->
    <div class="tab-pane fade <?= $activeTab === 'category' ? 'show active' : '' ?>" id="categoryFeesTab" role="tabpanel">
        <?php if ($canModifyTuition): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header form-header text-white">
                    <h5 class="mb-0">Add Category Fee</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Category Name</label>
                            <input type="text" name="category_name" class="form-control" placeholder="e.g. DG Foundation" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Term</label>
                            <input type="text" name="term" class="form-control" placeholder="e.g. Term 1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expected Tuition</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="add_category_fee" class="btn btn-form-submit">
                                <i class="bi bi-plus-circle me-2"></i> Add Category Fee
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Term</th>
                                <th>Expected Tuition</th>
                                <th>Recorded By</th>
                                <th>Date & Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categoryFees)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No category fees defined.</td></tr>
                            <?php else: ?>
                                <?php foreach ($categoryFees as $cf): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($cf['category_name']) ?></td>
                                        <td><?= htmlspecialchars($cf['term']) ?></td>
                                        <td><?= number_format($cf['amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($cf['recorded_by'] ?? 'System') ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($cf['created_at'])) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($canModifyTuition): ?>
                                                    <button type="button" class="btn-icon-edit" title="Edit" onclick="loadEditCategoryForm(<?= $cf['id'] ?>, '<?= htmlspecialchars($cf['category_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cf['term'], ENT_QUOTES) ?>', <?= $cf['amount'] ?>)" data-bs-toggle="modal" data-bs-target="#editCategoryModal" style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease; text-decoration: none;">
                                                        Edit
                                                    </button>
                                                    <a href="deleteTuition.php?id=<?= $cf['id'] ?>&type=category" class="btn-icon-delete" title="Delete" onclick="return confirm('Delete this record?')" style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; background-color: #dc3545; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease; text-decoration: none;">
                                                        Delete
                                                    </a>
                                                <?php endif; ?>
                                            </div>
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

<!-- Edit Class Fee Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <div class="modal-header form-header text-white">
                <h5 class="modal-title">Edit Tuition Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="edit_tuition" value="1">
                <input type="hidden" name="tuition_id" id="editTuitionId">
                <div class="mb-3">
                    <label class="form-label">Class</label>
                    <input type="text" name="class_name" id="editClassName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Term</label>
                    <input type="text" name="term" id="editTerm" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Expected Tuition</label>
                    <input type="number" name="amount" id="editAmount" class="form-control" step="0.01" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-form-submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Fee Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <div class="modal-header form-header text-white">
                <h5 class="modal-title">Edit Category Fee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="edit_category_fee_submit" value="1">
                <input type="hidden" name="cf_id" id="editCategoryId">
                <div class="mb-3">
                    <label class="form-label">Category Name</label>
                    <input type="text" name="category_name" id="editCategoryName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Term</label>
                    <input type="text" name="term" id="editCategoryTerm" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Expected Tuition</label>
                    <input type="number" name="amount" id="editCategoryAmount" class="form-control" step="0.01" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-form-submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function loadEditForm(id, name, term, amount) {
    document.getElementById('editTuitionId').value = id;
    document.getElementById('editClassName').value = name;
    document.getElementById('editTerm').value = term;
    document.getElementById('editAmount').value = amount;
}

function loadEditCategoryForm(id, name, term, amount) {
    document.getElementById('editCategoryId').value = id;
    document.getElementById('editCategoryName').value = name;
    document.getElementById('editCategoryTerm').value = term;
    document.getElementById('editCategoryAmount').value = amount;
}
</script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
