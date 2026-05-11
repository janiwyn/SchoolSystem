<?php
$file = 'app/finance/studentPayments.php';
$content = file_get_contents($file);
$content = str_replace('"sssis sdddsssi"', '"sssis sdddsssi"', $content);
file_put_contents($file, $content);
echo "Fix applied successfully\n";
?>
