<?php
$file = 'app/finance/studentPayments.php';
$content = file_get_contents($file);
// Correct 13 characters: sssis sdddsssi
$correct_string = 'sssis' . 's' . 'ddd' . 'sss' . 'i'; 
$content = preg_replace('/\$admitStmt->bind_param\("[^"]+",/', '$admitStmt->bind_param("' . $correct_string . '",', $content);
file_put_contents($file, $content);
echo "Fix applied: " . $correct_string . " (length: " . strlen($correct_string) . ")\n";
?>
