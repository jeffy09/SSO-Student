<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// ล้าง session
session_unset();
session_destroy();

// Redirect ไปยังหน้าหลัก
header("Location: index.php");
exit;
?>