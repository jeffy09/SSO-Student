<?php
// เปิดการแสดงข้อผิดพลาด
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>PHP Configuration & Debug Info</h1>";

// แสดงข้อมูลเวอร์ชัน PHP
echo "<h2>PHP Version</h2>";
echo "<p>" . phpversion() . "</p>";

// แสดงข้อมูล PATH
echo "<h2>System Paths</h2>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Current Script:</strong> " . $_SERVER['SCRIPT_FILENAME'] . "</p>";
echo "<p><strong>Include Path:</strong> " . get_include_path() . "</p>";

// แสดงข้อมูลการเชื่อมต่อฐานข้อมูล
echo "<h2>Database Connection</h2>";
try {
    // ลองเชื่อมต่อฐานข้อมูล
    $dsn = "mysql:host=localhost;dbname=admin_ssostu";
    $username = "admin_ssostu";
    $password = "ZqMNhS2EY9U2mxjSYGry";
    
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color:green;'>Database connection successful!</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Database connection failed: " . $e->getMessage() . "</p>";
}

// แสดงรายการโมดูลที่ติดตั้ง
echo "<h2>Installed Extensions</h2>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";

// ตรวจสอบว่ามีโมดูลที่จำเป็นหรือไม่
echo "<h2>Required Extensions</h2>";
$required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'openssl'];
echo "<ul>";
foreach ($required_extensions as $ext) {
    echo "<li>" . $ext . ": " . (extension_loaded($ext) ? '<span style="color:green;">Available</span>' : '<span style="color:red;">Missing</span>') . "</li>";
}
echo "</ul>";

// ตรวจสอบการมีอยู่ของไฟล์
echo "<h2>File Existence Check</h2>";
$files_to_check = [
    '../config/db.php',
    '../config/google_config.php',
    '../auth/google_login.php',
    '../auth/google_callback.php',
    '../index.php'
];

echo "<ul>";
foreach ($files_to_check as $file) {
    echo "<li>" . $file . ": " . (file_exists($file) ? '<span style="color:green;">Exists</span>' : '<span style="color:red;">Missing</span>') . "</li>";
}
echo "</ul>";

// ตรวจสอบความสามารถในการเขียนไฟล์ log
echo "<h2>Log Writing Test</h2>";
$log_file = '../logs/debug.log';
$log_dir = dirname($log_file);

if (!is_dir($log_dir)) {
    if (mkdir($log_dir, 0755, true)) {
        echo "<p>Created log directory: " . $log_dir . "</p>";
    } else {
        echo "<p style='color:red;'>Failed to create log directory: " . $log_dir . "</p>";
    }
}

if (is_dir($log_dir)) {
    if (is_writable($log_dir)) {
        $log_content = date('Y-m-d H:i:s') . " - Debug test message\n";
        if (file_put_contents($log_file, $log_content, FILE_APPEND)) {
            echo "<p style='color:green;'>Successfully wrote to log file: " . $log_file . "</p>";
        } else {
            echo "<p style='color:red;'>Failed to write to log file: " . $log_file . "</p>";
        }
    } else {
        echo "<p style='color:red;'>Log directory is not writable: " . $log_dir . "</p>";
    }
} else {
    echo "<p style='color:red;'>Log directory does not exist: " . $log_dir . "</p>";
}

// แสดง PHP Info ในบางส่วน
echo "<h2>PHP Info</h2>";
ob_start();
phpinfo(INFO_MODULES);
$phpinfo = ob_get_clean();

// กรองเฉพาะข้อมูลที่สำคัญ
$phpinfo = preg_replace('/<style>(.*)<\/style>/s', '', $phpinfo);
$phpinfo = preg_replace('/<img(.*?)>/s', '', $phpinfo);
$phpinfo = preg_replace('/<a(.*?)>(.*?)<\/a>/s', '\2', $phpinfo);

echo $phpinfo;
?>