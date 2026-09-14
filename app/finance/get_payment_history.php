<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

// Authentication check
requireRole(['bursar', 'admin', 'principal']);

header('Content-Type: text/html; charset=utf-8');

$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;

if ($payment_id <= 0) {
    echo '<div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Invalid payment ID.</div>';
    exit;
}

// Fetch primary payment record
$stmt = $mysqli->prepare("SELECT id, student_id, admission_no, full_name, expected_tuition, amount_paid, balance, admission_fee, uniform_fee, payment_date, created_at, recorded_by FROM student_payments WHERE id = ?");
if (!$stmt) {
    echo '<div class="alert alert-danger py-2 mb-0">Database query error.</div>';
    exit;
}
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$res = $stmt->get_result();
$payment = $res->fetch_assoc();
$stmt->close();

if (!$payment) {
    echo '<div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Payment record not found.</div>';
    exit;
}

// Fetch topups
$topupStmt = $mysqli->prepare("SELECT id, topup_amount, original_balance, new_balance, previous_status, status_approved, created_at FROM student_payment_topups WHERE payment_id = ? ORDER BY created_at ASC, id ASC");
$topups = [];
if ($topupStmt) {
    $topupStmt->bind_param("i", $payment_id);
    $topupStmt->execute();
    $topupRes = $topupStmt->get_result();
    while ($row = $topupRes->fetch_assoc()) {
        $topups[] = $row;
    }
    $topupStmt->close();
}

// Calculate initial deposit details
$total_topups_sum = 0;
foreach ($topups as $t) {
    $total_topups_sum += (float)$t['topup_amount'];
}

$initial_deposit = (float)$payment['amount_paid'] - $total_topups_sum;
if ($initial_deposit < 0) $initial_deposit = 0;

$expected_tuition = (float)$payment['expected_tuition'];
$initial_balance = $expected_tuition - $initial_deposit;
if ($initial_balance < 0) $initial_balance = 0;

// Format initial payment date
$initial_date_str = !empty($payment['payment_date']) ? date('d M Y', strtotime($payment['payment_date'])) : date('d M Y', strtotime($payment['created_at']));
?>

<div class="payment-timeline-container p-3 rounded bg-light border border-info border-opacity-25 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom flex-wrap gap-2">
        <h6 class="mb-0 text-primary fw-bold">
            <i class="bi bi-clock-history me-1"></i> Payment Timeline & History: <?= htmlspecialchars($payment['full_name']) ?> (SN: <?= htmlspecialchars($payment['admission_no']) ?>)
        </h6>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary">Total Paid So Far: $<?= number_format((float)$payment['amount_paid'], 2) ?> / Expected: $<?= number_format($expected_tuition, 2) ?></span>
            <a href="print-history-receipt.php?id=<?= $payment['id'] ?>" target="_blank" class="btn btn-sm btn-success fw-semibold px-2.5 py-1 shadow-sm" title="Print Complete Receipt">
                <i class="bi bi-printer-fill me-1"></i> Print Receipt
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 bg-white rounded border">
            <thead class="table-light">
                <tr class="small text-muted">
                    <th style="width: 50px;">#</th>
                    <th>Date</th>
                    <th>Payment Type</th>
                    <th class="text-end">Expected Tuition</th>
                    <th class="text-end">Amount Paid / Top-up</th>
                    <th class="text-end">Remaining Balance</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <!-- Step 1: Initial Deposit -->
                <tr>
                    <td><span class="badge rounded-pill bg-primary">1</span></td>
                    <td>
                        <i class="bi bi-calendar-event me-1 text-secondary"></i>
                        <?= htmlspecialchars($initial_date_str) ?>
                    </td>
                    <td>
                        <span class="fw-semibold text-dark"><i class="bi bi-box-arrow-in-right text-success me-1"></i> Initial Deposit</span>
                    </td>
                    <td class="text-end fw-semibold text-primary">
                        $<?= number_format($expected_tuition, 2) ?>
                    </td>
                    <td class="text-end fw-bold text-success">
                        $<?= number_format($initial_deposit, 2) ?>
                    </td>
                    <td class="text-end fw-bold text-<?= $initial_balance > 0 ? 'danger' : 'success' ?>">
                        $<?= number_format($initial_balance, 2) ?>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2">Approved</span>
                    </td>
                </tr>

                <!-- Subsequent Top-ups -->
                <?php 
                $step = 2;
                $current_running_balance = $initial_balance;
                foreach ($topups as $topup): 
                    $t_amount = (float)$topup['topup_amount'];
                    $t_orig_bal = $current_running_balance;
                    $t_new_bal = max(0, $t_orig_bal - $t_amount);
                    $current_running_balance = $t_new_bal;
                    $t_date = date('d M Y', strtotime($topup['created_at']));
                ?>
                <tr>
                    <td><span class="badge rounded-pill bg-info text-dark"><?= $step++ ?></span></td>
                    <td>
                        <i class="bi bi-calendar-event me-1 text-secondary"></i>
                        <?= htmlspecialchars($t_date) ?>
                    </td>
                    <td>
                        <span class="fw-semibold text-primary"><i class="bi bi-plus-circle text-primary me-1"></i> Balance Top-up</span>
                    </td>
                    <td class="text-end text-muted">
                        -
                    </td>
                    <td class="text-end fw-bold text-success">
                        +$<?= number_format($t_amount, 2) ?>
                    </td>
                    <td class="text-end fw-bold text-<?= $t_new_bal > 0 ? 'danger' : 'success' ?>">
                        $<?= number_format($t_new_bal, 2) ?>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2">
                            <?= ucfirst(htmlspecialchars($topup['status_approved'] ?? 'approved')) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (count($topups) === 0): ?>
                <tr class="table-light">
                    <td colspan="7" class="text-center text-muted py-2 small">
                        <i class="bi bi-info-circle me-1"></i> No additional top-up payments recorded for this record yet.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
