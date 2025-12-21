<?php
require_once __DIR__ . '/../config/db.php';

function logFinancialAudit($data){
    global $pdo;

    $sql = "INSERT INTO financial_audit_logs
    (user_id, role, action, payment_id, student_id, amount_before, amount_after, reason, is_offline, ip_address, created_at)
    VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['user_id'],
        $data['role'],
        $data['action'],
        $data['payment_id'],
        $data['student_id'],
        $data['amount_before'],
        $data['amount_after'],
        $data['reason'],
        $data['is_offline'],
        $_SERVER['REMOTE_ADDR'] ?? 'offline'
    ]);
}



?>