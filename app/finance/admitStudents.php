<?php
$title = "Admit Students";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Get all classes from fee_structure
$classesQuery = "SELECT DISTINCT fs.class_id, c.class_name 
                 FROM fee_structure fs 
                 LEFT JOIN classes c ON fs.class_id = c.id 
                 ORDER BY c.class_name ASC";
$classesResult = $mysqli->query($classesQuery);
$classes = $classesResult->fetch_all(MYSQLI_ASSOC);

// Generate next serial number
function generateSerialNumber($mysqli) {
    $query = "SELECT MAX(CAST(admission_no AS UNSIGNED)) as max_sn FROM admit_students";
    $result = $mysqli->query($query);
    $row = $result->fetch_assoc();
    $nextSN = ($row['max_sn'] ?? 0) + 1;
    return $nextSN;
}

// Handle form submission
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admit_student'])) {
    $first_name = trim($_POST['first_name']);
    $gender = trim($_POST['gender']);
    $class_id = trim($_POST['class_id']);
    $day_boarding = trim($_POST['day_boarding']);
    $admission_fee = trim($_POST['admission_fee']);
    $uniform_fee = trim($_POST['uniform_fee']);
    $parent_contact = trim($_POST['parent_contact']);

    // Validate required fields
    if (!$first_name || !$gender || !$class_id || !$day_boarding || !$admission_fee || !$uniform_fee || !$parent_contact) {
        $error = "All required fields must be filled";
    } elseif (!is_numeric($admission_fee) || $admission_fee <= 0) {
        $error = "Please enter a valid admission fee";
    } elseif (!is_numeric($uniform_fee) || $uniform_fee <= 0) {
        $error = "Please enter a valid uniform fee";
    } else {
        // Generate next serial number
        $admission_no = generateSerialNumber($mysqli);

        if (!$error) {
            $user_id = $_SESSION['user_id'] ?? null;
            
            if (!$user_id) {
                $error = "User session error. Please log in again.";
            } else {
                $checkUserStmt = $mysqli->prepare("SELECT id FROM users WHERE id = ?");
                $checkUserStmt->bind_param("i", $user_id);
                $checkUserStmt->execute();
                $userCheckResult = $checkUserStmt->get_result();
                
                if ($userCheckResult->num_rows === 0) {
                    $error = "Invalid user. Please log in again.";
                } else {
                    $status = 'approved';
                    
                    // Insert without last_name, parent_email, and student_image
                    // Fixed: Changed 'i' to 's' for admission_no (it's a string)
                    $stmt = $mysqli->prepare("INSERT INTO admit_students (admission_no, first_name, gender, class_id, day_boarding, admission_fee, uniform_fee, parent_contact, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    
                    if ($stmt) {
                        // Fixed type string: "sssissddsi" (admission_no is string, class_id is integer)
                        $stmt->bind_param("sssissddsi", $admission_no, $first_name, $gender, $class_id, $day_boarding, $admission_fee, $uniform_fee, $parent_contact, $status, $user_id);
                        
                        if ($stmt->execute()) {
                            header("Location: admitStudents.php?success=1");
                            exit();
                        } else {
                            $error = "Error admitting student: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error = "Database error: " . $mysqli->error;
                    }
                }
                $checkUserStmt->close();
            }
        }
    }
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $student_id = intval($_POST['student_id']);
    $first_name = trim($_POST['first_name']);
    $gender = trim($_POST['gender']);
    $class_id = trim($_POST['class_id']);
    $day_boarding = trim($_POST['day_boarding']);
    $admission_fee = trim($_POST['admission_fee']);
    $uniform_fee = trim($_POST['uniform_fee']);
    $parent_contact = trim($_POST['parent_contact']);

    if (!$first_name || !$gender || !$class_id || !$day_boarding || !$admission_fee || !$uniform_fee || !$parent_contact) {
        $error = "All required fields must be filled";
    } elseif (!is_numeric($admission_fee) || $admission_fee <= 0) {
        $error = "Please enter a valid admission fee";
    } elseif (!is_numeric($uniform_fee) || $uniform_fee <= 0) {
        $error = "Please enter a valid uniform fee";
    } else {
        // Fixed: Changed type string to match parameters correctly
        $stmt = $mysqli->prepare("UPDATE admit_students SET first_name = ?, gender = ?, class_id = ?, day_boarding = ?, admission_fee = ?, uniform_fee = ?, parent_contact = ?, status = ? WHERE id = ?");
        if ($stmt) {
            // Fixed type string: "ssissddsi"
            $stmt->bind_param("ssissddsi", $first_name, $gender, $class_id, $day_boarding, $admission_fee, $uniform_fee, $parent_contact, $student_id);
            if ($stmt->execute()) {
                header("Location: admitStudents.php?updated=1");
                exit();
            } else {
                $error = "Error updating student: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Student admitted successfully!";
}

// Check for update message
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = "Student information updated successfully!";
}

// Check for deletion message
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = "Student deleted successfully!";
}

// Check for deletion error
if (isset($_GET['error']) && $_GET['error'] == 1) {
    $error = "Error deleting student. Please try again.";
}

// Include layout AFTER all header operations
require_once __DIR__ . '/../helper/layout.php';

// Build filter query
$filterWhere = "1=1";
$search_filter = $_GET['search'] ?? '';
$class_filter = $_GET['class'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$day_boarding_filter = $_GET['day_boarding'] ?? 'Day'; // Default to Day students
$sort_order = $_GET['sort'] ?? 'DESC'; // Default sort order

// Toggle sort order
$next_sort_order = ($sort_order === 'ASC') ? 'DESC' : 'ASC';

if ($search_filter) {
    $searchTerm = '%' . $mysqli->real_escape_string($search_filter) . '%';
    $filterWhere .= " AND (admit_students.first_name LIKE '$searchTerm' OR admit_students.admission_no LIKE '$searchTerm')";
}
if ($class_filter) {
    $filterWhere .= " AND admit_students.class_id = " . intval($class_filter);
}
if ($gender_filter) {
    $filterWhere .= " AND admit_students.gender = '" . $mysqli->real_escape_string($gender_filter) . "'";
}
if ($date_from) {
    $filterWhere .= " AND DATE(admit_students.created_at) >= '" . $mysqli->real_escape_string($date_from) . "'";
}
if ($date_to) {
    $filterWhere .= " AND DATE(admit_students.created_at) <= '" . $mysqli->real_escape_string($date_to) . "'";
}
if ($day_boarding_filter) {
    $filterWhere .= " AND admit_students.day_boarding = '" . $mysqli->real_escape_string($day_boarding_filter) . "'";
}

// Pagination setup
$records_per_page = 60;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM admit_students 
              LEFT JOIN classes c ON admit_students.class_id = c.id
              WHERE $filterWhere";
$countResult = $mysqli->query($countQuery);
$countRow = $countResult->fetch_assoc();
$total_records = $countRow['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get admitted students - with filters, sorting and pagination
$studentsQuery = "SELECT 
    admit_students.id,
    admission_no,
    first_name,
    gender,
    class_id,
    c.class_name,
    day_boarding,
    admission_fee,
    uniform_fee,
    parent_contact,
    status,
    created_at
FROM admit_students
LEFT JOIN classes c ON admit_students.class_id = c.id
WHERE $filterWhere
ORDER BY CAST(admission_no AS UNSIGNED) $sort_order
LIMIT $offset, $records_per_page";

$studentsResult = $mysqli->query($studentsQuery);
$students = $studentsResult->fetch_all(MYSQLI_ASSOC);

// Get current user role
$userRole = $_SESSION['role'] ?? '';
$canAdmitStudent = in_array($userRole, ['admin', 'principal']);
?>

<!DOCTYPE html>

<!-- Toggle Button for Admit Student Form -->
<div class="mb-3">
    <?php if ($canAdmitStudent): ?>
        <button type="button" class="btn-toggle-form" onclick="toggleAdmitForm()">
            <i class="bi bi-chevron-right"></i> Admit New Student
        </button>
    <?php else: ?>
        <button type="button" class="btn-toggle-form" data-bs-toggle="modal" data-bs-target="#admitRestrictionModal">
            <i class="bi bi-chevron-right"></i> Admit New Student
        </button>
    <?php endif; ?>
</div>

<!-- Admit Student Form (Collapsible) - Only show if user can admit -->
<?php if ($canAdmitStudent): ?>
    <div class="card shadow-sm border-0 mb-4" id="admitFormCard" style="display: none;">
        <div class="card-header form-header text-white">
            <h5 class="mb-0">Admit New Student</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="first_name" class="form-control" placeholder="Student full name" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">F/M</label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select</option>
                        <option value="Male">M</option>
                        <option value="Female">F</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Class</label>
                    <select name="class_id" class="form-control" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Day/Boarding</label>
                    <select name="day_boarding" class="form-control" required>
                        <option value="">Select Option</option>
                        <option value="Day">Day</option>
                        <option value="Boarding">Boarding</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Admission Fee</label>
                    <input type="number" name="admission_fee" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Uniform Fee</label>
                    <input type="number" name="uniform_fee" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Parent Contact</label>
                    <input type="text" name="parent_contact" class="form-control" placeholder="e.g., 0774323232" required>
                    <small class="text-muted">Enter phone number with leading zeros</small>
                </div>

                <div class="col-12">
                    <button type="submit" name="admit_student" class="btn btn-form-submit">
                        <i class="bi bi-person-plus"></i> Admit Student
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Filter Section -->
<div class="card filter-card">
    <div class="card-body">
        <form method="GET">
            <input type="hidden" name="day_boarding" value="<?= htmlspecialchars($day_boarding_filter) ?>">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Search Student</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, Admission No" value="<?= htmlspecialchars($search_filter) ?>">
                </div>

                <div class="filter-group">
                    <label>Class</label>
                    <select name="class" class="form-control">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>" <?= $class_filter == $class['class_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Gender</label>
                    <select name="gender" class="form-control">
                        <option value="">All Genders</option>
                        <option value="Male" <?= $gender_filter === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $gender_filter === 'Female' ? 'selected' : '' ?>>Female</option>
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
                        <a href="admitStudents.php" class="btn-reset">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Day/Boarding Tabs -->
<div class="tabs-section mb-4">
    <div class="btn-group" role="tablist">
        <a href="?day_boarding=Day&page=1<?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($gender_filter ? '&gender=' . $gender_filter : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>" 
           class="btn <?= $day_boarding_filter === 'Day' ? 'btn-primary' : 'btn-outline-primary' ?>" role="tab">
            <i class="bi bi-person-check"></i> Day Students
        </a>
        <a href="?day_boarding=Boarding&page=1<?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($gender_filter ? '&gender=' . $gender_filter : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>" 
           class="btn <?= $day_boarding_filter === 'Boarding' ? 'btn-primary' : 'btn-outline-primary' ?>" role="tab">
            <i class="bi bi-house-fill"></i> Boarding Students
        </a>
    </div>
</div>

<!-- Admitted Students Table -->
<div class="card shadow-sm border-0">
    <div class="card-body">
        <?php if (empty($students)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No <?= $day_boarding_filter ?> students found.
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>
                                <a href="?day_boarding=<?= $day_boarding_filter ?>&sort=<?= $next_sort_order ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($gender_filter ? '&gender=' . $gender_filter : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>" style="text-decoration: none; color: white; display: flex; align-items: center; gap: 8px;">
                                    SN
                                    <i class="bi <?= $sort_order === 'ASC' ? 'bi-sort-up' : 'bi-sort-down' ?>"></i>
                                </a>
                            </th>
                            <th>Name</th>
                            <th>F/M</th>
                            <th>Class</th>
                            <th>Day/Boarding</th>
                            <th>Adm Fee</th>
                            <th>U Fee</th>
                            <th>Parent Contact</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['admission_no']) ?></td>
                                <td><?= htmlspecialchars($student['first_name']) ?></td>
                                <td><?= $student['gender'] === 'Male' ? 'M' : 'F' ?></td>
                                <td><?= htmlspecialchars($student['class_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($student['day_boarding']) ?></td>
                                <td><?= number_format($student['admission_fee'], 2) ?></td>
                                <td><?= number_format($student['uniform_fee'], 2) ?></td>
                                <td><?= htmlspecialchars($student['parent_contact']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($student['created_at'])) ?></td>
                                <td>
                                    <span class="badge <?= $student['status'] === 'approved' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= ucfirst($student['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($canAdmitStudent): ?>
                                            <!-- Admin/Principal can edit/delete -->
                                            <button type="button" class="btn-icon-edit" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal" onclick="loadEditForm(<?= $student['id'] ?>, '<?= htmlspecialchars($student['first_name']) ?>', '<?= htmlspecialchars($student['gender']) ?>', <?= $student['admission_fee'] ?>, <?= $student['uniform_fee'] ?>, '<?= htmlspecialchars($student['parent_contact']) ?>', '<?= htmlspecialchars($student['day_boarding']) ?>', <?= $student['class_id'] ?>)">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <a href="deleteStudent.php?id=<?= $student['id'] ?>" class="btn-icon-delete" title="Delete" onclick="return confirm('Are you sure?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <!-- Bursar cannot edit/delete -->
                                            <button type="button" class="btn-icon-edit-restricted" title="Edit" data-bs-toggle="modal" data-bs-target="#admitRestrictionModal">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button type="button" class="btn-icon-delete-restricted" title="Delete" data-bs-toggle="modal" data-bs-target="#admitRestrictionModal">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Button -->
                        <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= max(1, $current_page - 1) ?>&sort=<?= $sort_order ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($gender_filter ? '&gender=' . $gender_filter : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . '&day_boarding=' . $day_boarding_filter; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1&sort=<?= $sort_order ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($gender_filter ? '&gender=' . $gender_filter : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . '&day_boarding=' . $day_boarding_filter; ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($page = $start_page; $page <= $end_page; $page++): ?>
                            <li class="page-item <?= $page === $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $page ?>&sort=<?= $sort_order ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($gender_filter ? '&gender=' . $gender_filter : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . '&day_boarding=' . $day_boarding_filter; ?>">
                                    <?= $page ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?>&sort=<?= $sort_order ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($gender_filter ? '&gender=' . $gender_filter : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . '&day_boarding=' . $day_boarding_filter; ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>

                        <!-- Next Button -->
                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($total_pages, $current_page + 1) ?>&sort=<?= $sort_order ?><?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($gender_filter ? '&gender=' . $gender_filter : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : '') . '&day_boarding=' . $day_boarding_filter; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>

                <!-- Pagination Info -->
                <div class="text-center mt-3">
                    <p class="text-muted" style="font-size: 13px;">
                        Showing <?= ($offset + 1) ?> to <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> students
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Student Modal - Only shown for Admin/Principal -->
<?php if ($canAdmitStudent): ?>
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header form-header text-white">
                    <h5 class="modal-title">Edit Student Information</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="modal-body">
                    <input type="hidden" name="edit_student" value="1">
                    <input type="hidden" name="student_id" id="editStudentId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="first_name" id="editFirstName" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">F/M</label>
                            <select name="gender" id="editGender" class="form-control" required>
                                <option value="">Select</option>
                                <option value="Male">M</option>
                                <option value="Female">F</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Class</label>
                            <select name="class_id" id="editClassId" class="form-control" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['class_id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Day/Boarding</label>
                            <select name="day_boarding" id="editDayBoarding" class="form-control" required>
                                <option value="">Select Option</option>
                                <option value="Day">Day</option>
                                <option value="Boarding">Boarding</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Admission Fee</label>
                            <input type="number" name="admission_fee" id="editAdmissionFee" class="form-control" step="0.01" min="0" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Uniform Fee</label>
                            <input type="number" name="uniform_fee" id="editUniformFee" class="form-control" step="0.01" min="0" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Parent Contact</label>
                            <input type="text" name="parent_contact" id="editParentContact" class="form-control" required>
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

<!-- Restriction Modal for Bursar -->
<div class="modal fade" id="admitRestrictionModal" tabindex="-1" aria-labelledby="admitRestrictionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="admitRestrictionModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Access Restricted
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-shield-lock" style="font-size: 64px; color: #dc3545; margin-bottom: 20px;"></i>
                <h5 class="mb-3">Cannot Modify Student Admissions</h5>
                <p class="text-muted mb-0">
                    Only <strong>Admin</strong> and <strong>Principal</strong> have permission to admit, edit, or delete students.
                </p>
                <p class="text-muted mt-2">
                    As a <strong class="text-primary">Bursar</strong>, you can view student records but cannot modify them.
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

<script src="../../assets/js/admitStudents.js"></script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
