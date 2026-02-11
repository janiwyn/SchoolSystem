<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['admin']);
require_once __DIR__ . '/../helper/layout.php';

$message = '';
$error = '';
$duplicates = [];

// Find duplicates
$findDuplicatesQuery = "
    SELECT 
        student_id,
        term,
        payment_date,
        amount_paid,
        COUNT(*) as duplicate_count,
        GROUP_CONCAT(id ORDER BY id) as ids,
        MIN(id) as keep_id
    FROM student_payments
    GROUP BY student_id, term, payment_date, amount_paid
    HAVING COUNT(*) > 1
    ORDER BY duplicate_count DESC
";

$result = $mysqli->query($findDuplicatesQuery);
if ($result) {
    $duplicates = $result->fetch_all(MYSQLI_ASSOC);
}

// Handle cleanup (TEST MODE - DELETE ONLY 1 DUPLICATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_duplicates'])) {
    $deletedCount = 0;
    $testMode = true; // SET TO FALSE AFTER TESTING
    
    foreach ($duplicates as $dup) {
        $ids = explode(',', $dup['ids']);
        $keepId = $dup['keep_id'];
        
        // Delete all except the first one
        foreach ($ids as $id) {
            if ($id != $keepId) {
                $deleteStmt = $mysqli->prepare("DELETE FROM student_payments WHERE id = ?");
                $deleteStmt->bind_param("i", $id);
                if ($deleteStmt->execute()) {
                    $deletedCount++;
                }
                $deleteStmt->close();
                
                // TEST MODE: Stop after first deletion
                if ($testMode) {
                    $message = "TEST MODE: Deleted 1 duplicate record (ID: $id). Check results before proceeding.";
                    break 2; // Exit both loops
                }
            }
        }
    }
    
    if (!$testMode) {
        $message = "Successfully deleted $deletedCount duplicate payment records!";
    }
    
    // Refresh duplicates list
    $result = $mysqli->query($findDuplicatesQuery);
    if ($result) {
        $duplicates = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Duplicate Payment Records</h5>
    </div>
    <div class="card-body">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (empty($duplicates)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> No duplicate payment records found!
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Found <strong><?= count($duplicates) ?></strong> sets of duplicate payments.
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Term</th>
                            <th>Payment Date</th>
                            <th>Amount</th>
                            <th>Duplicate Count</th>
                            <th>Record IDs (Keep First)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($duplicates as $dup): ?>
                            <tr>
                                <td><?= $dup['student_id'] ?></td>
                                <td><?= htmlspecialchars($dup['term']) ?></td>
                                <td><?= $dup['payment_date'] ?></td>
                                <td>$<?= number_format($dup['amount_paid'], 2) ?></td>
                                <td><span class="badge bg-danger"><?= $dup['duplicate_count'] ?></span></td>
                                <td>
                                    <small class="text-muted">
                                        Keep: <strong class="text-success"><?= $dup['keep_id'] ?></strong><br>
                                        Delete: <span class="text-danger"><?= str_replace($dup['keep_id'].',', '', $dup['ids']) ?></span>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <form method="POST" onsubmit="return confirm('⚠️ This will delete all duplicate records, keeping only the first one.\n\nAre you absolutely sure?\n\nThis action CANNOT be undone!');">
                <button type="submit" name="cleanup_duplicates" class="btn btn-danger btn-lg">
                    <i class="bi bi-trash"></i> Clean Up Duplicates (Keep First Record Only)
                </button>
            </form>
        <?php endif; ?>
        
        <hr class="my-4">
        
        <h6>ℹ️ How This Works:</h6>
        <ul class="mb-0">
            <li>Finds payment records with identical <code>student_id</code>, <code>term</code>, <code>payment_date</code>, and <code>amount_paid</code></li>
            <li>Keeps the <strong>first record</strong> (lowest ID)</li>
            <li>Deletes all subsequent duplicate records</li>
            <li>Safe to run multiple times</li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/../helper/layout-footer.php'; ?>
