<?php
$title = "Results Entry";
require_once __DIR__ . '/../helper/layout.php';

// Check roles
if (!in_array($_SESSION['role'], ['dean', 'admin', 'principal'])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$error = '';

// Handle Results Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results'])) {
    $class_id = intval($_POST['class_id']);
    $subject_id = intval($_POST['subject_id']);
    $term = $_POST['term'];
    $exam_type = $_POST['exam_type'];
    $scores = $_POST['scores']; // Array of student_id => score

    if ($class_id && $subject_id && $term && $exam_type && !empty($scores)) {
        $mysqli->begin_transaction();
        try {
            foreach ($scores as $student_id => $score) {
                if ($score === '') continue; // Skip empty entries
                
                $student_id = intval($student_id);
                $score = floatval($score);
                $recorded_by = $_SESSION['user_id'];

                // Check if result already exists to update or insert
                $check = $mysqli->prepare("SELECT id FROM student_results WHERE student_id = ? AND subject_id = ? AND term = ? AND exam_type = ?");
                $check->bind_param("iiss", $student_id, $subject_id, $term, $exam_type);
                $check->execute();
                $result = $check->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $update = $mysqli->prepare("UPDATE student_results SET score = ?, recorded_by = ? WHERE id = ?");
                    $update->bind_param("dii", $score, $recorded_by, $row['id']);
                    $update->execute();
                    $update->close();
                } else {
                    $insert = $mysqli->prepare("INSERT INTO student_results (student_id, class_id, subject_id, term, exam_type, score, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $insert->bind_param("iiissdi", $student_id, $class_id, $subject_id, $term, $exam_type, $score, $recorded_by);
                    $insert->execute();
                    $insert->close();
                }
                $check->close();
            }
            $mysqli->commit();
            $message = "Results saved successfully!";
        } catch (Exception $e) {
            $mysqli->rollback();
            $error = "Error saving results: " . $e->getMessage();
        }
    }
}

// Data for filters
$classes = $mysqli->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetch_all(MYSQLI_ASSOC);
$subjects = $mysqli->query("SELECT id, subject_name FROM subjects ORDER BY subject_name ASC")->fetch_all(MYSQLI_ASSOC);

// If filters are active, get students
$sel_class = $_GET['class_id'] ?? '';
$sel_subject = $_GET['subject_id'] ?? '';
$sel_term = $_GET['term'] ?? '';
$sel_exam = $_GET['exam_type'] ?? '';

$students = [];
if ($sel_class && $sel_subject && $sel_term && $sel_exam) {
    // Get students in this class
    $studQuery = "SELECT id, admission_no, first_name as name FROM admit_students WHERE class_id = ? ORDER BY first_name ASC";
    $stmt = $mysqli->prepare($studQuery);
    $stmt->bind_param("i", $sel_class);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch existing scores for these students
    $existingScores = [];
    $scoreQuery = "SELECT student_id, score FROM student_results WHERE class_id = ? AND subject_id = ? AND term = ? AND exam_type = ?";
    $stmt = $mysqli->prepare($scoreQuery);
    $stmt->bind_param("iiss", $sel_class, $sel_subject, $sel_term, $sel_exam);
    $stmt->execute();
    $scoreRes = $stmt->get_result();
    while ($row = $scoreRes->fetch_assoc()) {
        $existingScores[$row['student_id']] = $row['score'];
    }
    $stmt->close();
}
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header text-white py-3" style="background-color: #17a2b8;">
        <h5 class="mb-0">Select Class & Subject for Results</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select" required>
                    <option value="">Select Class...</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $sel_class == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select" required>
                    <option value="">Select Subject...</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $sel_subject == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Term</label>
                <select name="term" class="form-select" required>
                    <option value="">Select Term...</option>
                    <option value="Term 1" <?= $sel_term == 'Term 1' ? 'selected' : '' ?>>Term 1</option>
                    <option value="Term 2" <?= $sel_term == 'Term 2' ? 'selected' : '' ?>>Term 2</option>
                    <option value="Term 3" <?= $sel_term == 'Term 3' ? 'selected' : '' ?>>Term 3</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Exam Type</label>
                <select name="exam_type" class="form-select" required>
                    <option value="">Select Exam...</option>
                    <option value="Beginning of Term" <?= $sel_exam == 'Beginning of Term' ? 'selected' : '' ?>>Beginning of Term</option>
                    <option value="Midterm" <?= $sel_exam == 'Midterm' ? 'selected' : '' ?>>Midterm</option>
                    <option value="End of Term" <?= $sel_exam == 'End of Term' ? 'selected' : '' ?>>End of Term</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn text-white" style="background-color: #17a2b8;">
                    <i class="bi bi-search"></i> Load Students
                </button>
                <?php if ($sel_class): ?>
                    <a href="results.php" class="btn btn-outline-secondary">Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($sel_class && $sel_subject && $sel_term && $sel_exam): ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #17a2b8;">
            <h5 class="mb-0" style="color: #17a2b8;">Enter Scores: <?= htmlspecialchars($sel_term) ?> - <?= htmlspecialchars($sel_exam) ?></h5>
            <span class="badge bg-soft-info text-info">Class: <?= $sel_class ?> | Subject: <?= $sel_subject ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($students)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                    <p class="mt-2 text-muted">No students found in this class.</p>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="class_id" value="<?= $sel_class ?>">
                    <input type="hidden" name="subject_id" value="<?= $sel_subject ?>">
                    <input type="hidden" name="term" value="<?= $sel_term ?>">
                    <input type="hidden" name="exam_type" value="<?= $sel_exam ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="background-color: #17a2b8; color: white;">
                                <tr>
                                    <th style="width: 150px;">Adm No</th>
                                    <th>Student Name</th>
                                    <th style="width: 200px;">Score (Max 100)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $stud): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($stud['admission_no']) ?></code></td>
                                        <td><?= htmlspecialchars($stud['name']) ?></td>
                                        <td>
                                            <input type="number" name="scores[<?= $stud['id'] ?>]" 
                                                   class="form-control form-control-sm score-input" 
                                                   step="0.1" min="0" max="100" 
                                                   value="<?= $existingScores[$stud['id']] ?? '' ?>" 
                                                   placeholder="0.0">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="p-3 bg-light border-top text-end">
                        <button type="submit" name="save_results" class="btn btn-success px-5">
                            <i class="bi bi-check2-circle"></i> Save All Results
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<style>
.bg-soft-info { background-color: rgba(23, 162, 184, 0.1); }
.score-input {
    border-radius: 4px;
    border: 1px solid #ced4da;
    text-align: center;
    font-weight: 600;
}
.score-input:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}
</style>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
