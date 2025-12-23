<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Get payment ID from URL
$payment_id = intval($_GET['id'] ?? 0);

if (!$payment_id) {
    die("Invalid payment ID");
}

// Get payment details
$query = "SELECT * FROM student_payments WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment || $payment['balance'] != 0) {
    die("Receipt can only be printed for fully paid payments");
}

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
    <title>Receipt - <?= htmlspecialchars($payment['full_name']) ?></title>
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
    <div class="receipt-title">RECEIPT</div>
    <div class="receipt-number">
        Receipt #: <?= str_pad($payment['id'], 6, '0', STR_PAD_LEFT) ?> | 
        Date: <?= date('d/m/Y', strtotime($payment['payment_date'])) ?>
    </div>

    <!-- Receipt Body -->
    <div class="receipt-body">
        <!-- Received From Section -->
        <div class="section">
            <div class="section-title">Received From</div>
            <div class="detail-row">
                <span class="detail-label">Name:</span>
                <span class="detail-value"><?= htmlspecialchars($payment['full_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Admission No:</span>
                <span class="detail-value"><?= htmlspecialchars($payment['admission_no']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Class:</span>
                <span class="detail-value"><?= htmlspecialchars($payment['class_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Term:</span>
                <span class="detail-value"><?= htmlspecialchars($payment['term']) ?></span>
            </div>
        </div>

        <!-- Payment Details Section -->
        <div class="section">
            <div class="section-title">Payment Details</div>
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="amount-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>School Tuition</td>
                        <td class="amount-right">$ <?= number_format($payment['expected_tuition'], 2) ?></td>
                    </tr>
                    <?php if ($payment['admission_fee'] > 0): ?>
                        <tr>
                            <td>Admission Fee</td>
                            <td class="amount-right">$ <?= number_format($payment['admission_fee'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($payment['uniform_fee'] > 0): ?>
                        <tr>
                            <td>Uniform Fee</td>
                            <td class="amount-right">$ <?= number_format($payment['uniform_fee'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals Section -->
        <div class="total-section">
            <div class="total-row">
                <span>Total Expected:</span>
                <span>$ <?= number_format($payment['expected_tuition'], 2) ?></span>
            </div>
            <div class="total-row">
                <span>Additional Fees:</span>
                <span>$ <?= number_format($payment['admission_fee'] + $payment['uniform_fee'], 2) ?></span>
            </div>
            <div class="total-row grand-total">
                <span>Amount Paid:</span>
                <span>$ <?= number_format($payment['amount_paid'], 2) ?></span>
            </div>
        </div>

        <!-- Payment Method -->
        <div class="section">
            <div class="detail-row">
                <span class="detail-label">Payment Date:</span>
                <span class="detail-value"><?= date('d/m/Y H:i', strtotime($payment['payment_date'])) ?></span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="receipt-footer">
        <div class="thank-you">Thank You!</div>
        <div>This is to certify that the above named student has paid the amount shown above.</div>
        <div style="margin-top: 15px; font-size: 11px;">
            This receipt is a proof of payment. Please keep it for your records.
        </div>
    </div>

    <!-- Print Button -->
    <div class="print-button">
        <button class="btn-print">
            <i class="bi bi-printer"></i> Print Receipt
        </button>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<script src="../../assets/js/print-receipt.js"></script>

</body>
</html>
