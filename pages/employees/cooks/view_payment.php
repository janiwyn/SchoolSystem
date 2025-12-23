<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../auth/auth.php';
require_once __DIR__ . '/../helper/layout.php';

require_role(['admin']);

$payment_id = $_GET['id'] ?? 0;

$stmt = $mysqli->prepare("
    SELECT p.*, c.first_name, c.last_name, c.photo, c.id_no
    FROM cook_payments p
    JOIN cooks c ON c.id = p.cook_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();

if (!$payment) {
    die("Payment not found");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Payment</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { background: #f4fbff; }
    .page-wrapper { max-width: 700px; margin: auto; }
    .card { border-radius: 18px; box-shadow: 0 15px 40px rgba(13,110,253,0.12); }
    .cook-photo { width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 5px solid #fff; margin-top: -60px; }
    .profile-header { background: linear-gradient(135deg, #0d6efd, #5bc0de); color: #fff; padding: 40px; border-radius: 18px 18px 0 0; text-align: center; }
    .details-table th { width: 40%; background: #e7f3ff; color: #0d6efd; font-weight: 500; }
    .details-table td { background: #fff; }
    .btn { border-radius: 10px; padding: 10px 20px; }

    .cook-photo {
    width: 140px;
    height: 140px;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid #0d6efd;
    margin-top: -70px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

</style>
</head>
<body>
<div class="container py-5">
<div class="page-wrapper">

    <div class="card">
        <div class="profile-header">
            <h4><i class="bi bi-cash-stack"></i> Cook Payment Details</h4>
        </div>

        <div class="card-body px-5 pb-5 text-center">
 <div class="text-center mb-4">
    <?php 
    $photoPath = !empty($payment['photo']) && file_exists("uploads/" . $payment['photo'])
                 ? "uploads/" . $payment['photo']
                 : "uploads/default.png";
    ?>
    <img src="<?= $photoPath ?>" class="rounded-circle cook-photo" alt="Cook Photo">
</div>
<h5 class="mt-3 mb-4 text-primary"><?= htmlspecialchars($payment['first_name'].' '.$payment['last_name']) ?></h5>

            <table class="table table-bordered details-table">
                <tr><th>Cook ID</th><td><?= htmlspecialchars($payment['id_no']) ?></td></tr>
                <tr><th>Amount</th><td><?= number_format($payment['amount'],2) ?></td></tr>
                <tr><th>Payment Date</th><td><?= date('d M Y', strtotime($payment['payment_date'])) ?></td></tr>
                <tr><th>Description</th><td><?= htmlspecialchars($payment['description']) ?></td></tr>
            </table>

            <a href="payments.php" class="btn btn-outline-secondary mt-4"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
