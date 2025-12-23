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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .invoice-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }

        .invoice-header {
            text-align: center;
            border-bottom: 2px solid #17a2b8;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .school-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
            display: inline-block;
        }

        .school-name {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .school-moto {
            font-size: 12px;
            color: #17a2b8;
            font-style: italic;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .school-details {
            font-size: 11px;
            color: #7f8c8d;
            line-height: 1.6;
        }

        .contact-info {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .contact-item {
            font-size: 11px;
            color: #555;
        }

        .invoice-title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            color: #17a2b8;
            margin: 20px 0;
            text-transform: uppercase;
        }

        .invoice-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 12px;
        }

        .invoice-meta-item {
            text-align: left;
        }

        .invoice-meta-label {
            font-weight: 700;
            color: #2c3e50;
        }

        .invoice-meta-value {
            color: #555;
        }

        .invoice-body {
            margin-bottom: 30px;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-weight: 700;
            color: #2c3e50;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 10px;
            border-bottom: 1px solid #ecf0f1;
            padding-bottom: 8px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 8px;
            color: #555;
        }

        .detail-label {
            font-weight: 600;
            color: #2c3e50;
        }

        .detail-value {
            text-align: right;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 13px;
        }

        .invoice-table th {
            background-color: #ecf0f1;
            padding: 10px;
            text-align: left;
            font-weight: 700;
            color: #2c3e50;
            border-bottom: 2px solid #17a2b8;
        }

        .invoice-table td {
            padding: 10px;
            border-bottom: 1px solid #ecf0f1;
            color: #555;
        }

        .amount-right {
            text-align: right;
        }

        .total-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-left: 4px solid #17a2b8;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .total-row.grand-total {
            font-size: 16px;
            font-weight: 700;
            color: #17a2b8;
            border-top: 2px solid #17a2b8;
            padding-top: 10px;
            margin-top: 10px;
        }

        .invoice-footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
            font-size: 12px;
            color: #7f8c8d;
            line-height: 1.8;
        }

        .note {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        .print-button {
            text-align: center;
            margin-top: 30px;
        }

        .btn-print {
            background-color: #17a2b8;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-print:hover {
            background-color: #138496;
        }

        @media print {
            body {
                background-color: white;
                padding: 0;
            }

            .invoice-container {
                box-shadow: none;
                padding: 20px;
            }

            .print-button {
                display: none;
            }
        }
    </style>
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
        <button class="btn-print" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Invoice
        </button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"></script>

</body>
</html>
