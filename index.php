<?php
// กำหนดค่าคงที่เพื่อบอกว่าการเข้าถึงไฟล์ถูกต้อง
define('SECURE_ACCESS', true);

// เริ่มต้น session
session_start();

// รวมไฟล์การเชื่อมต่อฐานข้อมูล
include_once 'config/db.php';

// สร้างอินสแตนซ์ของคลาส Database
$database = new Database();
$db = $database->getConnection();

// กำหนด base URL
$base_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

// รับค่า page จาก URL (ใช้ $_GET)
$page = isset($_GET['page']) ? $_GET['page'] : '';

// ตรวจสอบหน้าพิเศษสำหรับตรวจสอบการตั้งค่า Google
if ($page === 'check_google_config') {
    include_once 'auth/check_google_config.php';
    exit;
}

// ส่วนหัวของเว็บไซต์ (Header)
include_once 'includes/header.php';

// ตรวจสอบไฟล์ที่ต้องการโหลด
switch ($page) {
    // หน้าหลัก
    case '':
    case 'home':
        include_once 'pages/home.php';
        break;

    // ส่วนของการยืนยันตัวตน
    case 'login':
        include_once 'auth/login.php';
        break;
    case 'student_login':
        include_once 'auth/student_login.php';
        break;
    case 'admin_login':
        include_once 'auth/admin_login.php';
        break;
    case 'logout':
        include_once 'auth/logout.php';
        break;
    case 'google_login':
        include_once 'auth/google_login.php';
        break;
    case 'google_callback':
        include_once 'auth/google_callback.php';
        break;

    // ส่วนของนักศึกษา
    case 'student_dashboard':
        if (isset($_SESSION['student_id'])) {
            include_once 'student/dashboard.php';
        } else {
            header("Location: {$base_url}?page=student_login");
            exit;
        }
        break;
    case 'student_profile':
        if (isset($_SESSION['student_id'])) {
            include_once 'student/profile.php';
        } else {
            header("Location: {$base_url}?page=student_login");
            exit;
        }
        break;
    case 'google_drive_files':
        if (isset($_SESSION['student_id'])) {
            include_once 'student/google_drive_files.php';
        } else {
            header("Location: {$base_url}?page=student_login");
            exit;
        }
        break;

    // ส่วนของผู้ดูแลระบบ
    case 'admin_dashboard':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/dashboard.php';
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_users':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/users.php';
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_add_user':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/add_user.php';
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_bulk_add':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/bulk_add.php';
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_edit_user':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/edit_user.php';
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_view_user':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/view_user.php';
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_profile':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/profile.php';
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_unlink_google':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/unlink_google.php';
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;

    // หน้าตรวจสอบและแก้ไข
    case 'auth_check_admin_login':
        include_once 'auth/check_admin_login.php';
        break;
    case 'reset_admin_password':
        include_once 'auth/reset_admin_password.php';
        break;
    case 'debug':
        include_once 'auth/debug.php';
        break;

    // หน้า 404 Not Found
    default:
        include_once 'pages/404.php';
        break;
}

// ส่วนท้ายของเว็บไซต์ (Footer)
include_once 'includes/footer.php';
