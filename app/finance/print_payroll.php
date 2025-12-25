<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/role.php';

requireRole(['bursar', 'admin', 'principal']);

// Get payroll ID from URL
$payroll_id = $_GET['id'] ?? 0;

// Fetch payroll details
$query = "SELECT 
    payroll.id,
    payroll.name,
    payroll.department,
    payroll.salary,
    payroll.date,
    payroll.created_at,
    users.name as recorded_by
FROM payroll
LEFT JOIN users ON payroll.recorded_by = users.id
WHERE payroll.id = ?";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $payroll_id);
$stmt->execute();
$result = $stmt->get_result();
$payroll = $result->fetch_assoc();
$stmt->close();

if (!$payroll) {
    die("Payroll record not found.");
}

// Generate receipt number
$receipt_no = str_pad($payroll['id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Slip - <?= htmlspecialchars($payroll['name']) ?></title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 40px;
            background: #f5f5f5;
        }

        .payroll-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #17a2b8;
            padding-bottom: 20px;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .company-name {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .company-tagline {
            font-size: 14px;
            color: #17a2b8;
            font-style: italic;
            margin-bottom: 10px;
        }

        .company-details {
            font-size: 12px;
            color: #7f8c8d;
            line-height: 1.6;
        }

        .title-section {
            text-align: center;
            margin: 30px 0;
        }

        .document-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .receipt-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .meta-item {
            font-size: 13px;
        }

        .meta-label {
            font-weight: 600;
            color: #2c3e50;
        }

        .meta-value {
            color: #7f8c8d;
        }

        .divider {
            height: 2px;
            background: linear-gradient(to right, #17a2b8, transparent);
            margin: 25px 0;
        }

        .info-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #17a2b8;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table tr {
            border-bottom: 1px solid #ecf0f1;
        }

        .info-table td {
            padding: 12px 8px;
            font-size: 14px;
        }

        .info-table td:first-child {
            font-weight: 600;
            color: #2c3e50;
            width: 40%;
        }

        .info-table td:last-child {
            color: #7f8c8d;
        }

        .payment-details {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin: 30px 0;
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .amount-label {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }

        .amount-value {
            font-size: 28px;
            font-weight: 700;
            color: #27ae60;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ecf0f1;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }

        .signature-box {
            text-align: center;
            width: 45%;
        }

        .signature-line {
            border-top: 2px solid #2c3e50;
            margin-bottom: 10px;
            padding-top: 5px;
        }

        .signature-label {
            font-size: 13px;
            font-weight: 600;
            color: #7f8c8d;
        }

        .print-info {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #95a5a6;
        }

        .btn-print {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: block;
            margin: 20px auto;
            transition: all 0.3s ease;
        }

        .btn-print:hover {
            background: #138496;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .payroll-container {
                box-shadow: none;
                padding: 20px;
            }

            .btn-print {
                display: none;
            }

            .print-info {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="payroll-container">
    <!-- Header with Logo and Company Details -->
    <div class="header">
        <div class="logo">
            <img src="../../assets/images/logo.png" alt="Company Logo">
        </div>
        <div class="company-name">Bornwell Academy</div>
        <div class="company-tagline">For quality education and excellence</div>
        <div class="company-details">
            South Sudan Shrikat along Nimule JUBA highway<br>
            Tel: +211921315000 • +211911315000
        </div>
    </div>

    <!-- Title Section -->
    <div class="title-section">
        <div class="document-title">Payroll Slip</div>
    </div>

    <!-- Receipt Meta Information -->
    <div class="receipt-meta">
        <div class="meta-item">
            <span class="meta-label">Slip #:</span>
            <span class="meta-value"><?= $receipt_no ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Date:</span>
            <span class="meta-value"><?= date('d/m/Y', strtotime($payroll['created_at'])) ?></span>
        </div>
    </div>

    <div class="divider"></div>

    <!-- Employee Information -->
    <div class="info-section">
        <div class="section-title">Employee Information</div>
        <table class="info-table">
            <tr>
                <td>Name:</td>
                <td><?= htmlspecialchars($payroll['name']) ?></td>
            </tr>
            <tr>
                <td>Department:</td>
                <td><?= htmlspecialchars($payroll['department']) ?></td>
            </tr>
            <tr>
                <td>Payment Date:</td>
                <td><?= date('d F Y', strtotime($payroll['date'])) ?></td>
            </tr>
            <tr>
                <td>Processed By:</td>
                <td><?= htmlspecialchars($payroll['recorded_by']) ?></td>
            </tr>
        </table>
    </div>

    <!-- Payment Details -->
    <div class="payment-details">
        <div class="amount-row">
            <div class="amount-label">Salary Paid:</div>
            <div class="amount-value">$ <?= number_format($payroll['salary'], 2) ?></div>
        </div>
    </div>

    <!-- Footer with Signatures -->
    <div class="footer">
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">Employee Signature</div>
                <div class="signature-label">Received By</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Authorized Signature</div>
                <div class="signature-label">Approved By</div>
            </div>
        </div>
    </div>

    <div class="print-info">
        This is a computer-generated payroll slip and does not require a signature.<br>
        Printed on: <?= date('d F Y H:i:s') ?>
    </div>

    <!-- Print Button -->
    <button class="btn-print" onclick="window.print()">
        <i class="bi bi-printer"></i> Print Payroll Slip
    </button>
</div>

</body>
</html>
