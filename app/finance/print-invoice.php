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

if (!$payment || $payment['balance'] <= 0) {
    die("Invoice can only be printed for payments with outstanding balance");
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
    <title>Invoice - <?= htmlspecialchars($payment['full_name']) ?></title>
    <link rel="stylesheet" href="../../assets/css/print-invoice.css">
</head>
<body>

<div class="invoice-container">
    <!-- Header -->
    <div class="invoice-header">
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

    <!-- Invoice Title -->
    <div class="invoice-title">INVOICE</div>

    <!-- Invoice Meta -->
    <div class="invoice-meta">
        <div class="invoice-meta-item">
            <div class="invoice-meta-label">Date:</div>
            <div class="invoice-meta-value"><?= date('M d, Y', strtotime($payment['created_at'])) ?></div>
        </div>
        <div class="invoice-meta-item">
            <div class="invoice-meta-label">Invoice Number:</div>
            <div class="invoice-meta-value"><?= date('Y') ?>-A<?= str_pad($payment['id'], 4, '0', STR_PAD_LEFT) ?></div>
        </div>
    </div>

    <!-- Invoice Body -->
    <div class="invoice-body">
        <!-- Bill From -->
        <div class="section">
            <div class="section-title">Bill From</div>
            <div class="detail-row">
                <span class="detail-label">Name:</span>
                <span class="detail-value"><?= htmlspecialchars($schoolName) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Address:</span>
                <span class="detail-value"><?= htmlspecialchars($schoolAddress) ?></span>
            </div>
        </div>

        <!-- Bill To -->
        <div class="section">
            <div class="section-title">Bill To</div>
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
        </div>

        <!-- Invoice Details -->
        <div class="section">
            <div class="section-title">Details of Payment</div>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="amount-right">Amount</th>
                        <th class="amount-right">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Tuition Fee for <?= htmlspecialchars($payment['term']) ?></td>
                        <td class="amount-right">$ <?= number_format($payment['expected_tuition'], 2) ?></td>
                        <td class="amount-right"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                    </tr>
                    <tr>
                        <td>Amount Paid</td>
                        <td class="amount-right">$ <?= number_format($payment['amount_paid'], 2) ?></td>
                        <td class="amount-right"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                    </tr>
                    <tr style="background-color: #fff3cd;">
                        <td><strong>Balance Outstanding</strong></td>
                        <td class="amount-right"><strong>$ <?= number_format($payment['balance'], 2) ?></strong></td>
                        <td class="amount-right">-</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Totals Section -->
        <div class="total-section">
            <div class="total-row">
                <span>Total Amount Due:</span>
                <span>$ <?= number_format($payment['expected_tuition'], 2) ?></span>
            </div>
            <div class="total-row">
                <span>Amount Paid:</span>
                <span>$ <?= number_format($payment['amount_paid'], 2) ?></span>
            </div>
            <div class="total-row grand-total">
                <span>Balance Outstanding:</span>
                <span>$ <?= number_format($payment['balance'], 2) ?></span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="invoice-footer">
        <div class="note">
            Thank you for your payment. If you have any questions, please contact us at <?= htmlspecialchars($schoolPhone1) ?> for further inquiries or additional payments. Feel free to reach out at any time.
        </div>
    </div>

    <!-- Print Button -->
    <div class="print-button">
        <button class="btn-print">
            <i class="bi bi-printer"></i> Print Invoice
        </button>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<script src="../../assets/js/print-invoice.js"></script>

</body>
</html>
