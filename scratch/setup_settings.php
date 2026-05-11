<?php
require_once __DIR__ . '/../app/config/db.php';

$sql = "
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TINYINT(1) DEFAULT 0,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES 
('principal_edit_payments', 0, 'Allows Principal to edit/delete student payment records'),
('bursar_edit_payments', 0, 'Allows Bursar to edit student names and critical fields');
";

if ($mysqli->multi_query($sql)) {
    do {
        if ($res = $mysqli->store_result()) {
            $res->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
    echo "System settings table created and initialized.";
} else {
    echo "Error: " . $mysqli->error;
}
?>
