<?php
require_once 'app/config/db.php';

echo "=== student_payments columns ===\n";
$res = $mysqli->query("SHOW COLUMNS FROM student_payments");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

echo "\n=== admit_students columns ===\n";
$res2 = $mysqli->query("SHOW COLUMNS FROM admit_students");
if ($res2) {
    while($row = $res2->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}
?>
