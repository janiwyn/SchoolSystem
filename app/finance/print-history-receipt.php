<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Get payment ID from URL
$payment_id = intval($_GET['id'] ?? $_GET['payment_id'] ?? 0);

if (!$payment_id) {
    die("Invalid payment ID");
}

// Get primary payment details
$query = "SELECT * FROM student_payments WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment) {
    die("Payment record not found");
}

// Fetch topups
$topupStmt = $mysqli->prepare("SELECT id, topup_amount, original_balance, new_balance, status_approved, created_at FROM student_payment_topups WHERE payment_id = ? ORDER BY created_at ASC, id ASC");
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

$topup_count = count($topups);
$is_dense = ($topup_count >= 5);

$expected_tuition = (float)$payment['expected_tuition'];
$total_paid = (float)$payment['amount_paid'];
$initial_deposit = max(0, $total_paid - $total_topups_sum);
$initial_balance = max(0, $expected_tuition - $initial_deposit);
$initial_date_str = !empty($payment['payment_date']) ? date('d/m/Y', strtotime($payment['payment_date'])) : date('d/m/Y', strtotime($payment['created_at']));

// School details
$schoolName = "Bornwell Academy";
$schoolAddress = "South Sudan Shirkat along Nimule JUBA highway";
$schoolPhone1 = "+211921315000";
$schoolPhone2 = "+211911315000";
$schoolMoto = "For quality education and excellence";
$logoPath = __DIR__ . '/../../assets/images/logo.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History Receipt - <?= htmlspecialchars($payment['full_name']) ?></title>
    <link rel="stylesheet" href="../../assets/css/print-receipt.css">
</head>
<body>

<div class="receipt-container">
    <!-- Header -->
    <div class="receipt-header">
        <?php if (file_exists($logoPath)): ?>
            <img src="data:image/png;base64,<?= base64_encode(file_get_contents($logoPath)) ?>" alt="School Logo" class="school-logo">
        <?php endif; ?>
        <div class="school-name"><?= htmlspecialchars($schoolName) ?></div>
        <div class="school-moto"><?= htmlspecialchars($schoolMoto) ?></div>
        <div class="school-details">
            <div><?= htmlspecialchars($schoolAddress) ?></div>
            <div class="contact-info">
                <span class="contact-item">Tel: <?= htmlspecialchars($schoolPhone1) ?></span>
                <span class="contact-item"><?= htmlspecialchars($schoolPhone2) ?></span>
            </div>
        </div>
    </div>

    <!-- Receipt Title -->
    <div class="receipt-title">OFFICIAL PAYMENT RECEIPT</div>
    <div class="receipt-number">
        Receipt #: REC-<?= str_pad($payment['id'], 6, '0', STR_PAD_LEFT) ?> | Date: <?= date('d/m/Y') ?>
    </div>

    <!-- Receipt Body -->
    <div class="receipt-body">
        <!-- Student Information -->
        <div class="section">
            <div class="section-title">Student Information</div>
            <div class="detail-row">
                <span class="detail-label">Name:</span>
                <span class="detail-value"><?= htmlspecialchars($payment['full_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Admission No:</span>
                <span class="detail-value"><?= htmlspecialchars($payment['admission_no']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Class & Term:</span>
                <span class="detail-value"><?= htmlspecialchars($payment['class_name']) ?> (<?= htmlspecialchars($payment['term']) ?>)</span>
            </div>
        </div>

        <!-- Payments Breakdown Table -->
        <div class="section">
            <div class="section-title">Payment History Breakdown</div>
            <table class="payment-table <?= $is_dense ? 'dense-table' : '' ?>">
                <thead>
                    <tr>
                        <th style="width: 25px;">#</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="amount-right">Expected Tuition</th>
                        <th class="amount-right">Paid / Top-up</th>
                        <th class="amount-right">Remaining Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td><?= htmlspecialchars($initial_date_str) ?></td>
                        <td>Initial Deposit</td>
                        <td class="amount-right">$ <?= number_format($expected_tuition, 2) ?></td>
                        <td class="amount-right">$ <?= number_format($initial_deposit, 2) ?></td>
                        <td class="amount-right">$ <?= number_format($initial_balance, 2) ?></td>
                    </tr>
                    <?php 
                    $step = 2;
                    $current_running_balance = $initial_balance;
                    foreach ($topups as $topup):
                        $t_amount = (float)$topup['topup_amount'];
                        $t_new_bal = max(0, $current_running_balance - $t_amount);
                        $current_running_balance = $t_new_bal;
                        $t_date = date('d/m/Y', strtotime($topup['created_at']));
                    ?>
                    <tr>
                        <td><?= $step++ ?></td>
                        <td><?= htmlspecialchars($t_date) ?></td>
                        <td>Balance Top-up</td>
                        <td class="amount-right">-</td>
                        <td class="amount-right">+$ <?= number_format($t_amount, 2) ?></td>
                        <td class="amount-right">$ <?= number_format($t_new_bal, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals Section -->
        <div class="total-section">
            <div class="total-row">
                <span>Expected Tuition:</span>
                <span>$ <?= number_format($expected_tuition, 2) ?></span>
            </div>
            <?php if ($payment['admission_fee'] > 0 || $payment['uniform_fee'] > 0): ?>
            <div class="total-row">
                <span>Additional Fees (Admission/Uniform):</span>
                <span>$ <?= number_format($payment['admission_fee'] + $payment['uniform_fee'], 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row">
                <span>Total Amount Paid:</span>
                <span>$ <?= number_format($total_paid, 2) ?></span>
            </div>
            <div class="total-row grand-total">
                <span>Remaining Balance:</span>
                <span>$ <?= number_format($payment['balance'], 2) ?></span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="receipt-footer">
        <div class="thank-you">Thank You!</div>
        <div>This is to certify that all payments shown above have been officially recorded.</div>
        <div style="margin-top: 5px; font-size: 10.5px;">
            This receipt is proof of payment. Please keep it for your records.
        </div>
    </div>

    <!-- Print Button -->
    <div class="print-button">
        <button class="btn-print" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Receipt
        </button>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<script src="../../assets/js/print-receipt.js"></script>

</body>
</html>
