<?php
/**
 * Database Optimization Script
 * Run this to add indexes and optimize your database
 * 
 * SECURITY: Only Admin can access this script
 * 
 * How to run:
 * 1. Login as Admin
 * 2. Navigate to: https://bornwell-academy.great-site.net/app/config/optimize_database.php
 * 3. Script will check your admin credentials before running
 */

require_once __DIR__ . '/db.php';

// STRICT AUTHENTICATION: Must be logged in as Admin
if (php_sapi_name() !== 'cli') {
    session_start();
    
    // Check if logged in as admin
    if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
        die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Access Denied</title>
            <style>
                body { font-family: Arial; padding: 50px; text-align: center; background: #f5f5f5; }
                .error { color: #dc3545; font-size: 24px; margin-bottom: 20px; font-weight: bold; }
                .info { color: #2c3e50; margin-top: 20px; font-size: 16px; }
                .steps { background: white; padding: 30px; margin: 30px auto; max-width: 500px; text-align: left; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .steps ol { line-height: 2; }
            </style>
        </head>
        <body>
            <div class='error'>🔒 Admin Access Only</div>
            <div class='info'>This database optimization tool requires Admin privileges.</div>
            
            <div class='steps'>
                <strong>To run this script:</strong>
                <ol>
                    <li>Go to: <strong>https://bornwell-academy.great-site.net</strong></li>
                    <li>Login as <strong>Admin</strong></li>
                    <li>Return to this URL</li>
                </ol>
            </div>
        </body>
        </html>
        ");
    }
    
    // Set PHP execution time limit for InfinityFree
    @set_time_limit(300); // 5 minutes max
}

// Output HTML header for browser viewing
if (php_sapi_name() !== 'cli') {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Database Optimization</title>
        <style>
            body { font-family: monospace; padding: 20px; background: #f5f5f5; }
            pre { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .success { color: #28a745; }
            .skip { color: #6c757d; }
            .error { color: #dc3545; }
            .summary { background: #e8f4e8; padding: 15px; margin-top: 20px; border-left: 4px solid #28a745; }
            .warning { background: #fff3cd; padding: 15px; margin-top: 20px; border-left: 4px solid #ffc107; }
        </style>
    </head>
    <body>";
}

echo "<pre>";
echo "==============================================\n";
echo "DATABASE OPTIMIZATION SCRIPT\n";
echo "==============================================\n\n";

$optimizations = [
    // ============ ADMIT_STUDENTS TABLE ============
    [
        'name' => 'admit_students: admission_no index',
        'sql' => "CREATE INDEX idx_admission_no ON admit_students(admission_no)",
        'check' => "SHOW INDEX FROM admit_students WHERE Key_name = 'idx_admission_no'"
    ],
    [
        'name' => 'admit_students: class_id index',
        'sql' => "CREATE INDEX idx_class_id ON admit_students(class_id)",
        'check' => "SHOW INDEX FROM admit_students WHERE Key_name = 'idx_class_id'"
    ],
    [
        'name' => 'admit_students: status index',
        'sql' => "CREATE INDEX idx_status ON admit_students(status)",
        'check' => "SHOW INDEX FROM admit_students WHERE Key_name = 'idx_status'"
    ],
    [
        'name' => 'admit_students: created_at index',
        'sql' => "CREATE INDEX idx_created_at ON admit_students(created_at)",
        'check' => "SHOW INDEX FROM admit_students WHERE Key_name = 'idx_created_at'"
    ],
    [
        'name' => 'admit_students: gender index',
        'sql' => "CREATE INDEX idx_gender ON admit_students(gender)",
        'check' => "SHOW INDEX FROM admit_students WHERE Key_name = 'idx_gender'"
    ],
    [
        'name' => 'admit_students: day_boarding index',
        'sql' => "CREATE INDEX idx_day_boarding ON admit_students(day_boarding)",
        'check' => "SHOW INDEX FROM admit_students WHERE Key_name = 'idx_day_boarding'"
    ],
    [
        'name' => 'admit_students: composite index (status, class_id)',
        'sql' => "CREATE INDEX idx_status_class ON admit_students(status, class_id)",
        'check' => "SHOW INDEX FROM admit_students WHERE Key_name = 'idx_status_class'"
    ],

    // ============ STUDENT_PAYMENTS TABLE ============
    [
        'name' => 'student_payments: student_id index',
        'sql' => "CREATE INDEX idx_student_id ON student_payments(student_id)",
        'check' => "SHOW INDEX FROM student_payments WHERE Key_name = 'idx_student_id'"
    ],
    [
        'name' => 'student_payments: admission_no index',
        'sql' => "CREATE INDEX idx_admission_no ON student_payments(admission_no)",
        'check' => "SHOW INDEX FROM student_payments WHERE Key_name = 'idx_admission_no'"
    ],
    [
        'name' => 'student_payments: class_id index',
        'sql' => "CREATE INDEX idx_class_id ON student_payments(class_id)",
        'check' => "SHOW INDEX FROM student_payments WHERE Key_name = 'idx_class_id'"
    ],
    [
        'name' => 'student_payments: payment_date index',
        'sql' => "CREATE INDEX idx_payment_date ON student_payments(payment_date)",
        'check' => "SHOW INDEX FROM student_payments WHERE Key_name = 'idx_payment_date'"
    ],
    [
        'name' => 'student_payments: status_approved index',
        'sql' => "CREATE INDEX idx_status_approved ON student_payments(status_approved)",
        'check' => "SHOW INDEX FROM student_payments WHERE Key_name = 'idx_status_approved'"
    ],
    [
        'name' => 'student_payments: term index',
        'sql' => "CREATE INDEX idx_term ON student_payments(term)",
        'check' => "SHOW INDEX FROM student_payments WHERE Key_name = 'idx_term'"
    ],
    [
        'name' => 'student_payments: composite index (payment_date, status_approved)',
        'sql' => "CREATE INDEX idx_payment_status ON student_payments(payment_date, status_approved)",
        'check' => "SHOW INDEX FROM student_payments WHERE Key_name = 'idx_payment_status'"
    ],

    // ============ STUDENT_PAYMENT_TOPUPS TABLE ============
    [
        'name' => 'student_payment_topups: payment_id index',
        'sql' => "CREATE INDEX idx_payment_id ON student_payment_topups(payment_id)",
        'check' => "SHOW INDEX FROM student_payment_topups WHERE Key_name = 'idx_payment_id'"
    ],
    [
        'name' => 'student_payment_topups: student_id index',
        'sql' => "CREATE INDEX idx_student_id ON student_payment_topups(student_id)",
        'check' => "SHOW INDEX FROM student_payment_topups WHERE Key_name = 'idx_student_id'"
    ],
    [
        'name' => 'student_payment_topups: status_approved index',
        'sql' => "CREATE INDEX idx_status_approved ON student_payment_topups(status_approved)",
        'check' => "SHOW INDEX FROM student_payment_topups WHERE Key_name = 'idx_status_approved'"
    ],

    // ============ PAYROLL TABLE ============
    [
        'name' => 'payroll: date index',
        'sql' => "CREATE INDEX idx_date ON payroll(date)",
        'check' => "SHOW INDEX FROM payroll WHERE Key_name = 'idx_date'"
    ],
    [
        'name' => 'payroll: department index',
        'sql' => "CREATE INDEX idx_department ON payroll(department)",
        'check' => "SHOW INDEX FROM payroll WHERE Key_name = 'idx_department'"
    ],
    [
        'name' => 'payroll: status index',
        'sql' => "CREATE INDEX idx_status ON payroll(status)",
        'check' => "SHOW INDEX FROM payroll WHERE Key_name = 'idx_status'"
    ],
    [
        'name' => 'payroll: name index',
        'sql' => "CREATE INDEX idx_name ON payroll(name)",
        'check' => "SHOW INDEX FROM payroll WHERE Key_name = 'idx_name'"
    ],
    [
        'name' => 'payroll: composite index (name, date, salary)',
        'sql' => "CREATE INDEX idx_name_date_salary ON payroll(name(100), date, salary)",
        'check' => "SHOW INDEX FROM payroll WHERE Key_name = 'idx_name_date_salary'"
    ],

    // ============ EXPENSES TABLE ============
    [
        'name' => 'expenses: date index',
        'sql' => "CREATE INDEX idx_date ON expenses(date)",
        'check' => "SHOW INDEX FROM expenses WHERE Key_name = 'idx_date'"
    ],
    [
        'name' => 'expenses: category index',
        'sql' => "CREATE INDEX idx_category ON expenses(category)",
        'check' => "SHOW INDEX FROM expenses WHERE Key_name = 'idx_category'"
    ],
    [
        'name' => 'expenses: status index',
        'sql' => "CREATE INDEX idx_status ON expenses(status)",
        'check' => "SHOW INDEX FROM expenses WHERE Key_name = 'idx_status'"
    ],
    [
        'name' => 'expenses: recorded_by index',
        'sql' => "CREATE INDEX idx_recorded_by ON expenses(recorded_by)",
        'check' => "SHOW INDEX FROM expenses WHERE Key_name = 'idx_recorded_by'"
    ],
    [
        'name' => 'expenses: composite index (category, date, status)',
        'sql' => "CREATE INDEX idx_category_date_status ON expenses(category, date, status)",
        'check' => "SHOW INDEX FROM expenses WHERE Key_name = 'idx_category_date_status'"
    ],
    [
        'name' => 'expenses: composite index (category, item, date, amount) for salary matching',
        'sql' => "CREATE INDEX idx_salary_match ON expenses(category, item(100), date, amount)",
        'check' => "SHOW INDEX FROM expenses WHERE Key_name = 'idx_salary_match'"
    ],

    // ============ FEE_STRUCTURE TABLE ============
    [
        'name' => 'fee_structure: class_id index',
        'sql' => "CREATE INDEX idx_class_id ON fee_structure(class_id)",
        'check' => "SHOW INDEX FROM fee_structure WHERE Key_name = 'idx_class_id'"
    ],
    [
        'name' => 'fee_structure: term index',
        'sql' => "CREATE INDEX idx_term ON fee_structure(term)",
        'check' => "SHOW INDEX FROM fee_structure WHERE Key_name = 'idx_term'"
    ],

    // ============ USERS TABLE ============
    [
        'name' => 'users: status index',
        'sql' => "CREATE INDEX idx_status ON users(status)",
        'check' => "SHOW INDEX FROM users WHERE Key_name = 'idx_status'"
    ],
    [
        'name' => 'users: role index',
        'sql' => "CREATE INDEX idx_role ON users(role)",
        'check' => "SHOW INDEX FROM users WHERE Key_name = 'idx_role'"
    ],

    // ============ CLASSES TABLE ============
    [
        'name' => 'classes: class_name index',
        'sql' => "CREATE INDEX idx_class_name ON classes(class_name)",
        'check' => "SHOW INDEX FROM classes WHERE Key_name = 'idx_class_name'"
    ],
];

$success_count = 0;
$skip_count = 0;
$error_count = 0;

foreach ($optimizations as $opt) {
    echo "Checking: {$opt['name']}... ";
    
    // Check if index already exists
    $checkResult = $mysqli->query($opt['check']);
    if ($checkResult && $checkResult->num_rows > 0) {
        echo "✓ Already exists (skipped)\n";
        $skip_count++;
        continue;
    }
    
    // Create index
    if ($mysqli->query($opt['sql'])) {
        echo "✓ Created successfully\n";
        $success_count++;
    } else {
        echo "✗ Failed: " . $mysqli->error . "\n";
        $error_count++;
    }
}

echo "\n==============================================\n";
echo "OPTIMIZATION SUMMARY\n";
echo "==============================================\n";
echo "✓ Successfully created: $success_count indexes\n";
echo "→ Already existed: $skip_count indexes\n";
echo "✗ Failed: $error_count indexes\n";
echo "\n";

// Run ANALYZE TABLE to update statistics
echo "Analyzing tables to update statistics...\n\n";
$tables = [
    'admit_students',
    'student_payments',
    'student_payment_topups',
    'payroll',
    'expenses',
    'fee_structure',
    'users',
    'classes'
];

foreach ($tables as $table) {
    echo "Analyzing table: $table... ";
    if ($mysqli->query("ANALYZE TABLE $table")) {
        echo "✓ Done\n";
    } else {
        echo "✗ Failed\n";
    }
}

echo "\n==============================================\n";
echo "OPTIMIZATION COMPLETE!\n";
echo "==============================================\n";
echo "Your database should now perform much faster.\n";
echo "You can close this page.\n";
echo "</pre>";

// Add delete instructions at the end for web access
if (php_sapi_name() !== 'cli') {
    echo "
    <div class='warning'>
        <strong>⚠️ SECURITY WARNING</strong><br>
        For security reasons, you should <strong>DELETE this file immediately</strong> after running.<br><br>
        <strong>How to delete:</strong><br>
        1. Login to InfinityFree File Manager<br>
        2. Navigate to: <strong>htdocs/app/config/</strong><br>
        3. Delete: <strong>optimize_database.php</strong><br><br>
        Or use FTP to delete the file.
    </div>
    </body>
    </html>";
}
?>
