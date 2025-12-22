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

// Get the highest admission number to generate next one
function generateAdmissionNo($mysqli) {
    $query = "SELECT MAX(CAST(SUBSTRING(admission_no, -3) AS UNSIGNED)) as max_no FROM admit_students WHERE admission_no LIKE 'ADM-%'";
    $result = $mysqli->query($query);
    $row = $result->fetch_assoc();
    $nextNo = ($row['max_no'] ?? 0) + 1;
    return 'ADM-' . str_pad($nextNo, 3, '0', STR_PAD_LEFT);
}

// Handle form submission
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admit_student'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $gender = trim($_POST['gender']);
    $class_id = trim($_POST['class_id']);
    $day_boarding = trim($_POST['day_boarding']);
    $admission_fee = trim($_POST['admission_fee']);
    $uniform_fee = trim($_POST['uniform_fee']);
    $parent_contact = trim($_POST['parent_contact']);
    $email = trim($_POST['parent_email']);
    $admission_date = trim($_POST['admission_date']);

    if (!$first_name || !$last_name || !$gender || !$class_id || !$day_boarding || !$admission_fee || !$uniform_fee || !$parent_contact || !$email || !$admission_date) {
        $error = "All required fields must be filled";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (!is_numeric($admission_fee) || $admission_fee <= 0) {
        $error = "Please enter a valid admission fee";
    } elseif (!is_numeric($uniform_fee) || $uniform_fee <= 0) {
        $error = "Please enter a valid uniform fee";
    } else {
        // Generate unique admission number
        $admission_no = generateAdmissionNo($mysqli);
        
        // Handle image upload (optional)
        $image_path = NULL;
        if (isset($_FILES['student_image']) && $_FILES['student_image']['size'] > 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['student_image']['type'], $allowed_types)) {
                $upload_dir = __DIR__ . '/../../assets/images/students/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $filename = 'student_' . time() . '_' . basename($_FILES['student_image']['name']);
                if (move_uploaded_file($_FILES['student_image']['tmp_name'], $upload_dir . $filename)) {
                    // Store relative path from project root
                    $image_path = 'assets/images/students/' . $filename;
                }
            } else {
                $error = "Invalid image file type. Only JPG, PNG, and GIF are allowed.";
            }
        }

        if (!$error) {
            // Insert into admit_students table with unapproved status
            $user_id = $_SESSION['user_id'];
            $status = 'unapproved';
            $stmt = $mysqli->prepare("INSERT INTO admit_students (admission_no, first_name, last_name, gender, class_id, day_boarding, admission_fee, uniform_fee, parent_contact, parent_email, student_image, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if ($stmt) {
                // Fixed: Correct type string with correct number of variables
                // Types: s=string, i=integer, d=double
                // admission_no(s), first_name(s), last_name(s), gender(s), class_id(i), day_boarding(s), admission_fee(d), uniform_fee(d), parent_contact(s), parent_email(s), student_image(s), status(s), created_by(i)
                $stmt->bind_param("ssssissddsssi", $admission_no, $first_name, $last_name, $gender, $class_id, $day_boarding, $admission_fee, $uniform_fee, $parent_contact, $email, $image_path, $status, $user_id);
                if ($stmt->execute()) {
                    // Redirect to prevent form resubmission
                    header("Location: admitStudents.php?success=1");
                    exit();
                } else {
                    $error = "Error admitting student: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $student_id = intval($_POST['student_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $gender = trim($_POST['gender']);
    $class_id = trim($_POST['class_id']);
    $day_boarding = trim($_POST['day_boarding']);
    $admission_fee = trim($_POST['admission_fee']);
    $uniform_fee = trim($_POST['uniform_fee']);
    $parent_contact = trim($_POST['parent_contact']);
    $email = trim($_POST['parent_email']);

    if (!$first_name || !$last_name || !$gender || !$class_id || !$day_boarding || !$admission_fee || !$uniform_fee || !$parent_contact || !$email) {
        $error = "All required fields must be filled";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (!is_numeric($admission_fee) || $admission_fee <= 0) {
        $error = "Please enter a valid admission fee";
    } elseif (!is_numeric($uniform_fee) || $uniform_fee <= 0) {
        $error = "Please enter a valid uniform fee";
    } else {
        $stmt = $mysqli->prepare("UPDATE admit_students SET first_name = ?, last_name = ?, gender = ?, class_id = ?, day_boarding = ?, admission_fee = ?, uniform_fee = ?, parent_contact = ?, parent_email = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssssissdsi", $first_name, $last_name, $gender, $class_id, $day_boarding, $admission_fee, $uniform_fee, $parent_contact, $email, $student_id);
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

if ($search_filter) {
    $searchTerm = '%' . $mysqli->real_escape_string($search_filter) . '%';
    $filterWhere .= " AND (admit_students.first_name LIKE '$searchTerm' OR admit_students.last_name LIKE '$searchTerm' OR admit_students.admission_no LIKE '$searchTerm' OR admit_students.parent_email LIKE '$searchTerm')";
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

// Get admitted students - with filters
$studentsQuery = "SELECT 
    admit_students.id,
    admit_students.admission_no,
    admit_students.first_name,
    admit_students.last_name,
    admit_students.gender,
    admit_students.class_id,
    c.class_name,
    admit_students.day_boarding,
    admit_students.admission_fee,
    admit_students.uniform_fee,
    admit_students.parent_contact,
    admit_students.parent_email,
    admit_students.student_image,
    admit_students.status,
    admit_students.created_at
FROM admit_students
LEFT JOIN classes c ON admit_students.class_id = c.id
WHERE $filterWhere
ORDER BY admit_students.created_at DESC";

$studentsResult = $mysqli->query($studentsQuery);
$students = $studentsResult->fetch_all(MYSQLI_ASSOC);
?>

<!-- Add Student Form -->
<div class="card shadow-sm border-0 mb-4">
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
            <div class="col-md-3">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" placeholder="First name" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" placeholder="Last name" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Sex</label>
                <select name="gender" class="form-control" required>
                    <option value="">Select Sex</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>

            <div class="col-md-3">
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
                <input type="text" name="parent_contact" class="form-control" placeholder="Phone number" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Parent Email</label>
                <input type="email" name="parent_email" class="form-control" placeholder="parent@email.com" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Admission Date</label>
                <input type="date" name="admission_date" class="form-control" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Student Image (Optional)</label>
                <input type="file" name="student_image" class="form-control" accept="image/*">
                <small class="text-muted">JPG, PNG, GIF only</small>
            </div>

            <div class="col-12">
                <button type="submit" name="admit_student" class="btn btn-form-submit">
                    <i class="bi bi-person-plus"></i> Admit Student
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Filter Section -->
<div class="card filter-card">
    <div class="card-body">
        <form method="GET">
            <input type="hidden" name="day_boarding" value="<?= htmlspecialchars($day_boarding_filter) ?>">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Search Student</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, Admission No, Email" value="<?= htmlspecialchars($search_filter) ?>">
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
        <a href="?day_boarding=Day<?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($gender_filter ? '&gender=' . $gender_filter : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>" 
           class="btn <?= $day_boarding_filter === 'Day' ? 'btn-primary' : 'btn-outline-primary' ?>" role="tab">
            <i class="bi bi-person-check"></i> Day Students
        </a>
        <a href="?day_boarding=Boarding<?php echo ($search_filter ? '&search=' . urlencode($search_filter) : '') . ($class_filter ? '&class=' . $class_filter : '') . ($gender_filter ? '&gender=' . $gender_filter : '') . ($date_from ? '&date_from=' . $date_from : '') . ($date_to ? '&date_to=' . $date_to : ''); ?>" 
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
                            <th>Adm No</th>
                            <th>Name</th>
                            <th>Sex</th>
                            <th>Class</th>
                            <th>Day/Boarding</th>
                            <th>Admission Fee</th>
                            <th>Uniform Fee</th>
                            <th>Parent Contact</th>
                            <th>Parent Email</th>
                            <th>Image</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['admission_no']) ?></td>
                                <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                <td><?= htmlspecialchars($student['gender']) ?></td>
                                <td><?= htmlspecialchars($student['class_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($student['day_boarding']) ?></td>
                                <td><?= number_format($student['admission_fee'], 2) ?></td>
                                <td><?= number_format($student['uniform_fee'], 2) ?></td>
                                <td><?= htmlspecialchars($student['parent_contact']) ?></td>
                                <td><?= htmlspecialchars($student['parent_email']) ?></td>
                                <td>
                                    <?php if ($student['student_image']): ?>
                                        <button type="button" class="btn-icon-view" data-image="<?= htmlspecialchars($student['student_image']) ?>" data-bs-toggle="modal" data-bs-target="#imageModal" title="View Image">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($student['created_at'])) ?></td>
                                <td>
                                    <span class="badge <?= $student['status'] === 'approved' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= ucfirst($student['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-icon-edit" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal" onclick="loadEditForm(<?= $student['id'] ?>, '<?= htmlspecialchars($student['first_name']) ?>', '<?= htmlspecialchars($student['last_name']) ?>', '<?= htmlspecialchars($student['gender']) ?>', <?= $student['admission_fee'] ?>, <?= $student['uniform_fee'] ?>, '<?= htmlspecialchars($student['parent_contact']) ?>', '<?= htmlspecialchars($student['parent_email']) ?>', '<?= htmlspecialchars($student['day_boarding']) ?>', <?= $student['class_id'] ?>)">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a href="deleteStudent.php?id=<?= $student['id'] ?>" class="btn-icon-delete" title="Delete" onclick="return confirm('Are you sure?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
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

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Student Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="Student Image" class="img-fluid" style="max-height: 400px; max-width: 100%;">
            </div>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
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
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" id="editFirstName" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" id="editLastName" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Sex</label>
                        <select name="gender" id="editGender" class="form-control" required>
                            <option value="">Select Sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
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

                    <div class="col-md-6">
                        <label class="form-label">Parent Contact</label>
                        <input type="text" name="parent_contact" id="editParentContact" class="form-control" required>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Parent Email</label>
                        <input type="email" name="parent_email" id="editParentEmail" class="form-control" required>
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

<script>
    function loadEditForm(id, firstName, lastName, gender, admissionFee, uniformFee, parentContact, parentEmail, dayBoarding, classId) {
        document.getElementById('editStudentId').value = id;
        document.getElementById('editFirstName').value = firstName;
        document.getElementById('editLastName').value = lastName;
        document.getElementById('editGender').value = gender;
        document.getElementById('editAdmissionFee').value = admissionFee;
        document.getElementById('editUniformFee').value = uniformFee;
        document.getElementById('editParentContact').value = parentContact;
        document.getElementById('editParentEmail').value = parentEmail;
        document.getElementById('editDayBoarding').value = dayBoarding;
        document.getElementById('editClassId').value = classId;
    }

    document.querySelectorAll('.btn-icon-view').forEach(btn => {
        btn.addEventListener('click', function() {
            const imageSrc = this.getAttribute('data-image');
            const fullImagePath = '/SchoolSystem/' + imageSrc;
            console.log('Loading image from:', fullImagePath);
            document.getElementById('modalImage').src = fullImagePath;
            document.getElementById('modalImage').onerror = function() {
                console.log('Image failed to load from:', fullImagePath);
                this.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200"%3E%3Crect fill="%23ddd" width="200" height="200"/%3E%3Ctext x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="16" fill="%23999"%3EImage Not Found%3C/text%3E%3C/svg%3E';
            };
        });
    });
</script>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
