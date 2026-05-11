<?php
$title = "Performance Analysis";
require_once __DIR__ . '/../helper/layout.php';

// Check roles
if (!in_array($_SESSION['role'], ['dean', 'admin', 'principal'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Data for filters
$classes = $mysqli->query("SELECT id, class_name FROM classes ORDER BY class_name ASC")->fetch_all(MYSQLI_ASSOC);

$sel_class = $_GET['class_id'] ?? '';
$sel_term = $_GET['term'] ?? '';
$sel_exam = $_GET['exam_type'] ?? '';

$performanceData = [];
if ($sel_class && $sel_term && $sel_exam) {
    // Calculate average score per student for selected filters
    $perfQuery = "SELECT 
                    s.id as student_id,
                    s.admission_no,
                    s.first_name as name,
                    AVG(r.score) as avg_score,
                    COUNT(r.subject_id) as subjects_count
                  FROM admit_students s
                  JOIN student_results r ON s.id = r.student_id
                  WHERE r.class_id = ? AND r.term = ? AND r.exam_type = ?
                  GROUP BY s.id
                  ORDER BY avg_score DESC";
    
    $stmt = $mysqli->prepare($perfQuery);
    $stmt->bind_param("iss", $sel_class, $sel_term, $sel_exam);
    $stmt->execute();
    $performanceData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Extract Top 3 and Bottom 3 if data exists
$topPerformers = array_slice($performanceData, 0, 3);
$bottomPerformers = count($performanceData) > 3 ? array_slice($performanceData, -3) : [];
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header text-white py-3" style="background-color: #17a2b8;">
        <h5 class="mb-0">Filter Performance</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select" required>
                    <option value="">Select Class...</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $sel_class == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Term</label>
                <select name="term" class="form-select" required>
                    <option value="">Select Term...</option>
                    <option value="Term 1" <?= $sel_term == 'Term 1' ? 'selected' : '' ?>>Term 1</option>
                    <option value="Term 2" <?= $sel_term == 'Term 2' ? 'selected' : '' ?>>Term 2</option>
                    <option value="Term 3" <?= $sel_term == 'Term 3' ? 'selected' : '' ?>>Term 3</option>
                </select>
            </div>
            <div class="col-md-4">
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
                    <i class="bi bi-graph-up-arrow"></i> Analyze Performance
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($performanceData)): ?>
    <div class="row g-4 mb-4">
        <!-- Top Performer -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-gradient-success text-white">
                <div class="card-body text-center py-4">
                    <div class="display-6 mb-2">🏆</div>
                    <h6 class="text-uppercase small fw-bold opacity-75">Top Performer</h6>
                    <h4 class="mb-0"><?= htmlspecialchars($topPerformers[0]['name']) ?></h4>
                    <div class="mt-2 fs-3 fw-bold"><?= number_format($topPerformers[0]['avg_score'], 1) ?>%</div>
                </div>
            </div>
        </div>
        
        <!-- Summary Stats -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 text-primary fw-bold">Performance Summary</h6>
                </div>
                <div class="card-body d-flex align-items-center">
                    <div class="row w-100 text-center">
                        <div class="col-4 border-end">
                            <div class="text-muted small">Students Analyzed</div>
                            <div class="h3 mb-0"><?= count($performanceData) ?></div>
                        </div>
                        <div class="col-4 border-end">
                            <div class="text-muted small">Class Average</div>
                            <?php 
                                $classAvg = array_sum(array_column($performanceData, 'avg_score')) / count($performanceData);
                            ?>
                            <div class="h3 mb-0 text-success"><?= number_format($classAvg, 1) ?>%</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Highest Score</div>
                            <div class="h3 mb-0 text-primary"><?= number_format($topPerformers[0]['avg_score'], 1) ?>%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Overall Rankings -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3" style="border-bottom: 2px solid #17a2b8;">
                    <h5 class="mb-0" style="color: #17a2b8;">Class Rankings</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead style="background-color: #17a2b8; color: white;">
                                <tr>
                                    <th class="text-center" style="width: 60px;">Rank</th>
                                    <th>Student</th>
                                    <th class="text-center">Subjects</th>
                                    <th class="text-center">Average Score</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performanceData as $index => $p): ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php if ($index < 3): ?>
                                                <span class="badge bg-warning text-dark rounded-circle p-2" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;"><?= $index + 1 ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><?= $index + 1 ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($p['name']) ?></strong>
                                            <div class="text-muted small"><?= htmlspecialchars($p['admission_no']) ?></div>
                                        </td>
                                        <td class="text-center"><?= $p['subjects_count'] ?></td>
                                        <td class="text-center fw-bold text-primary"><?= number_format($p['avg_score'], 1) ?>%</td>
                                        <td style="min-width: 150px;">
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar <?= $p['avg_score'] >= 50 ? 'bg-success' : 'bg-danger' ?>" 
                                                     style="width: <?= $p['avg_score'] ?>%"></div>
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

        <!-- Best/Worst Performers -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-success text-white py-3">
                    <h6 class="mb-0">Top 3 Students</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topPerformers as $tp): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($tp['name']) ?>
                                <span class="badge bg-success rounded-pill"><?= number_format($tp['avg_score'], 1) ?>%</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <?php if (!empty($bottomPerformers)): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-danger text-white py-3">
                        <h6 class="mb-0">Needs Improvement (Bottom 3)</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($bottomPerformers as $bp): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center text-danger">
                                    <?= htmlspecialchars($bp['name']) ?>
                                    <span class="badge bg-danger rounded-pill"><?= number_format($bp['avg_score'], 1) ?>%</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($sel_class): ?>
    <div class="alert alert-info py-5 text-center">
        <i class="bi bi-info-circle display-4 d-block mb-3"></i>
        No performance data found for the selected filters. Please ensure results have been entered.
    </div>
<?php endif; ?>

<style>
.bg-gradient-success {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
}
.progress-bar { transition: width 1s ease; }
</style>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
