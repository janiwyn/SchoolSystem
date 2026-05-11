<?php
$title = "Dean Dashboard";
require_once __DIR__ . '/../helper/layout.php';

// Check if user is Dean, Admin or Principal
if (!in_array($_SESSION['role'], ['dean', 'admin', 'principal'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get statistics for Dean
// 1. Total Subjects
$subjQuery = "SELECT COUNT(*) as total FROM subjects";
$subjResult = $mysqli->query($subjQuery);
$totalSubjects = $subjResult->fetch_assoc()['total'] ?? 0;

// 2. Total Classes
$classQuery = "SELECT COUNT(*) as total FROM classes";
$classResult = $mysqli->query($classQuery);
$totalClasses = $classResult->fetch_assoc()['total'] ?? 0;

// 3. Total Teachers (Users with role dean or maybe a teacher role if added later)
// For now, let's count all users who are in teacher_allocations
$teacherQuery = "SELECT COUNT(DISTINCT user_id) as total FROM teacher_allocations";
$teacherResult = $mysqli->query($teacherQuery);
$totalTeachers = $teacherResult->fetch_assoc()['total'] ?? 0;

// 4. Results entered this term (Count of distinct student-subject entries)
$resultsQuery = "SELECT COUNT(*) as total FROM student_results";
$resultsResult = $mysqli->query($resultsQuery);
$totalResults = $resultsResult->fetch_assoc()['total'] ?? 0;

?>

<div class="row g-4 mb-4">
    <!-- Total Subjects -->
    <div class="col-md-3">
        <div class="card stat-card blue">
            <div class="card-body stat-card-body">
                <div class="stat-content">
                    <div class="stat-label">Total Subjects</div>
                    <div class="stat-value"><?= $totalSubjects ?></div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-book"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Classes -->
    <div class="col-md-3">
        <div class="card stat-card green">
            <div class="card-body stat-card-body">
                <div class="stat-content">
                    <div class="stat-label">Total Classes</div>
                    <div class="stat-value"><?= $totalClasses ?></div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-building"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Allocated Teachers -->
    <div class="col-md-3">
        <div class="card stat-card orange">
            <div class="card-body stat-card-body">
                <div class="stat-content">
                    <div class="stat-label">Allocated Teachers</div>
                    <div class="stat-value"><?= $totalTeachers ?></div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-person-workspace"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Records -->
    <div class="col-md-3">
        <div class="card stat-card purple">
            <div class="card-body stat-card-body">
                <div class="stat-content">
                    <div class="stat-label">Results Recorded</div>
                    <div class="stat-value"><?= $totalResults ?></div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-journal-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-primary">Academic Overview</h5>
            </div>
            <div class="card-body">
                <p>Welcome to the Academic Management Portal. From here you can manage the school's curriculum, teacher allocations, and student performance records.</p>
                
                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <a href="subjects.php" class="btn btn-outline-primary w-100 py-3 text-start">
                            <i class="bi bi-book me-2"></i> Manage Subjects & Classes
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="teachers.php" class="btn btn-outline-primary w-100 py-3 text-start">
                            <i class="bi bi-person-workspace me-2"></i> Teacher Allocations
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="results.php" class="btn btn-outline-primary w-100 py-3 text-start">
                            <i class="bi bi-journal-check me-2"></i> Enter Student Results
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="performance.php" class="btn btn-outline-primary w-100 py-3 text-start">
                            <i class="bi bi-bar-chart-fill me-2"></i> Performance Analysis
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-primary">Quick Status</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Active Terms
                        <span class="badge bg-primary rounded-pill">3</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Exam Types
                        <span class="badge bg-info rounded-pill">3</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    border: none;
    border-radius: 12px;
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}
.stat-card:hover { transform: translateY(-5px); }
.stat-card.blue { background: linear-gradient(135deg, #3498db, #2980b9); }
.stat-card.green { background: linear-gradient(135deg, #2ecc71, #27ae60); }
.stat-card.orange { background: linear-gradient(135deg, #e67e22, #d35400); }
.stat-card.purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); }

.stat-card-body {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
}
.stat-label { font-size: 0.9rem; opacity: 0.9; }
.stat-value { font-size: 1.8rem; font-weight: 700; }
.stat-icon { font-size: 2.5rem; opacity: 0.3; }
</style>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
