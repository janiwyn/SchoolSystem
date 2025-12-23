<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../auth/auth.php'; // login check
require_once __DIR__ . '/../helper/layout.php';
require_role(['admin']);

// Get payment ID
$payment_id = $_GET['id'] ?? null;
if (!$payment_id) {
    header("Location: payments.php");
    exit;
}

// Fetch payment details with teacher info
$stmt = $mysqli->prepare("
    SELECT tp.*, t.first_name, t.last_name, t.photo, t.id_no
    FROM teacher_payments tp
    JOIN teachers t ON t.id = tp.teacher_id
    WHERE tp.id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();


if (!$payment) {
    header("Location: payments.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Payment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f4fbff;
            font-family: "Segoe UI", sans-serif;
        }

        .page-wrapper {
            max-width: 900px;
            margin: auto;
        }

        .card-view {
            border: none;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 15px 40px rgba(13,110,253,0.12);
        }

        .card-header {
            background: linear-gradient(135deg, #0d6efd, #5bc0de);
            border-radius: 18px 18px 0 0;
            color: #fff;
            text-align: center;
            padding: 25px;
        }

        .employee-photo {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid #fff;
            margin-top: -75px;
            background: #fff;
        }

        .table th {
            width: 35%;
            background: #f1f8ff;
            color: #0d6efd;
            font-weight: 500;
        }

        .table td {
            background: #ffffff;
        }

        .btn {
            border-radius: 10px;
            padding: 10px 20px;
        }
 .teacher-photo {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid #0d6efd;
    margin-top: -75px; /* adjust for header overlap */
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    background: #fff;
}

    </style>
</head>
<body>

<div class="container-fluid py-5">
    <div class="page-wrapper">

        <!-- Card -->
        <div class="card card-view">

            <!-- Header -->
            <div class="card-header">
                <h4><i class="bi bi-cash-stack"></i> Payment Details</h4>
            </div>

            <!-- Card Body -->
            <div class="card-body px-5 pb-5">

                <!-- Employee Photo -->
    <div class="text-center mb-4">
    <?php 
    $photoPath = !empty($payment['photo']) && file_exists("uploads/" . $payment['photo'])
                 ? "uploads/" . $payment['photo']
                 : "uploads/default.png";
    ?>
    <img src="<?= $photoPath ?>" class="rounded-circle teacher-photo" alt="Teacher Photo">
</div>


                <!-- Employee Name -->
                <h5 class="text-center mb-4 text-primary">
                    <?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?>
                </h5>

                <!-- Payment Details Table -->
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <tr>
                            <th>Teacher ID</th>
                            <td><?= htmlspecialchars($payment['id_no']) ?></td>
                        </tr>
                        <tr>
                            <th>Amount</th>
                            <td><?= number_format($payment['amount'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>Payment Date</th>
                            <td><?= date("d M Y", strtotime($payment['payment_date'])) ?></td>
                        </tr>
                        <tr>
                            <th>Description</th>
                            <td><?= htmlspecialchars($payment['description']) ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Back Button -->
                <div class="text-end mt-4">
                    <a href="payments.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Payments
                    </a>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
