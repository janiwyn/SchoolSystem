<?php
$title = "Database Health";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['admin']);
require_once __DIR__ . '/../helper/layout.php';

// Check table sizes
$sizeQuery = "SELECT 
    table_name AS 'Table',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
    table_rows AS 'Rows'
FROM information_schema.TABLES 
WHERE table_schema = DATABASE()
ORDER BY (data_length + index_length) DESC";

$sizeResult = $mysqli->query($sizeQuery);
$tables = $sizeResult ? $sizeResult->fetch_all(MYSQLI_ASSOC) : [];

// Check index status for key tables
$indexQuery = "SHOW INDEX FROM student_payments";
$indexResult = $mysqli->query($indexQuery);
$indexes = $indexResult ? $indexResult->fetch_all(MYSQLI_ASSOC) : [];

// Calculate total database size
$totalSize = array_sum(array_column($tables, 'Size (MB)'));
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
        <h5 class="mb-0"><i class="bi bi-hdd"></i> Database Health Monitor</h5>
    </div>
    <div class="card-body">
        <!-- Database Summary -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white;">
                    <div class="card-body text-center">
                        <h6>Total Database Size</h6>
                        <h2><?= round($totalSize, 2) ?> MB</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card" style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%); color: white;">
                    <div class="card-body text-center">
                        <h6>Total Tables</h6>
                        <h2><?= count($tables) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white;">
                    <div class="card-body text-center">
                        <h6>Active Indexes</h6>
                        <h2><?= count($indexes) ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="mb-3"><i class="bi bi-table"></i> Database Tables</h6>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead style="background-color: #17a2b8; color: white;">
                    <tr>
                        <th>Table Name</th>
                        <th>Size (MB)</th>
                        <th>Rows</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $table): ?>
                        <tr>
                            <td><i class="bi bi-table"></i> <?= htmlspecialchars($table['Table']) ?></td>
                            <td><?= $table['Size (MB)'] ?></td>
                            <td><?= number_format($table['Rows']) ?></td>
                            <td>
                                <?php if ($table['Size (MB)'] > 100): ?>
                                    <span class="badge bg-warning">Large</span>
                                <?php elseif ($table['Size (MB)'] > 50): ?>
                                    <span class="badge bg-info">Medium</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Healthy</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h6 class="mt-4 mb-3"><i class="bi bi-lightning"></i> Index Status (student_payments table)</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead style="background-color: #f8f9fa;">
                    <tr>
                        <th>Index Name</th>
                        <th>Column</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($indexes as $index): ?>
                        <tr>
                            <td><?= htmlspecialchars($index['Key_name']) ?></td>
                            <td><?= htmlspecialchars($index['Column_name']) ?></td>
                            <td>
                                <?php if ($index['Key_name'] === 'PRIMARY'): ?>
                                    <span class="badge bg-primary">PRIMARY</span>
                                <?php elseif ($index['Non_unique'] == 0): ?>
                                    <span class="badge bg-info">UNIQUE</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">INDEX</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-success">Active</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recommendation Section -->
        <div class="alert <?= $totalSize > 500 ? 'alert-warning' : 'alert-success' ?> mt-4">
            <h6><i class="bi bi-info-circle"></i> Recommendation:</h6>
            <?php if ($totalSize > 500): ?>
                <p class="mb-2">
                    Database size is <strong><?= round($totalSize, 2) ?>MB</strong>. Consider running optimization to improve performance.
                </p>
                <a href="../config/optimize_database.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-gear"></i> Run Optimization
                </a>
            <?php else: ?>
                <p class="mb-0">
                    Database is healthy (<strong><?= round($totalSize, 2) ?>MB</strong>). No optimization needed at this time.
                </p>
            <?php endif; ?>
        </div>

        <!-- Performance Tips -->
        <div class="card mt-4" style="background-color: #f8f9fa; border-left: 4px solid #17a2b8;">
            <div class="card-body">
                <h6><i class="bi bi-lightbulb"></i> Performance Tips</h6>
                <ul class="mb-0">
                    <li>Run optimization every 3-6 months for best performance</li>
                    <li>Archive old data if tables exceed 100MB</li>
                    <li>Monitor query execution time using SHOW PROCESSLIST</li>
                    <li>Keep indexes up-to-date after major data changes</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Query Performance Monitor -->
<div class="card shadow-sm border-0">
    <div class="card-header" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white;">
        <h5 class="mb-0"><i class="bi bi-speedometer"></i> Query Performance</h5>
    </div>
    <div class="card-body">
        <?php
        // Get current process list
        $processQuery = "SHOW FULL PROCESSLIST";
        $processResult = $mysqli->query($processQuery);
        $processes = $processResult ? $processResult->fetch_all(MYSQLI_ASSOC) : [];
        ?>
        
        <div class="table-responsive">
            <table class="table table-striped">
                <thead style="background-color: #9b59b6; color: white;">
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Command</th>
                        <th>Time</th>
                        <th>State</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processes as $process): ?>
                        <tr>
                            <td><?= htmlspecialchars($process['Id']) ?></td>
                            <td><?= htmlspecialchars($process['User']) ?></td>
                            <td><?= htmlspecialchars($process['Command']) ?></td>
                            <td><?= htmlspecialchars($process['Time']) ?>s</td>
                            <td><?= htmlspecialchars($process['State'] ?? '-') ?></td>
                            <td>
                                <?php if ($process['Time'] > 5): ?>
                                    <span class="badge bg-danger">Slow</span>
                                <?php elseif ($process['Time'] > 1): ?>
                                    <span class="badge bg-warning">Medium</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Fast</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $slowQueries = array_filter($processes, function($p) {
            return intval($p['Time']) > 1;
        });
        ?>

        <?php if (count($slowQueries) > 0): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                Found <strong><?= count($slowQueries) ?></strong> slow queries (> 1 second). 
                Consider optimizing these queries or checking indexes.
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i>
                All queries are executing efficiently (< 1 second).
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
