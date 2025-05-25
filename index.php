<?php
ob_start(); // เริ่ม Output Buffering ที่บรรทัดแรกสุด

// กำหนดค่าคงที่เพื่อบอกว่าการเข้าถึงไฟล์ถูกต้อง
define('SECURE_ACCESS', true);

// เริ่มต้น session
if (session_status() == PHP_SESSION_NONE) { // ตรวจสอบก่อนเริ่ม session
    session_start();
}

// รวมไฟล์การเชื่อมต่อฐานข้อมูล
include_once 'config/db.php';

// สร้างอินสแตนซ์ของคลาส Database
$database = new Database();
$db = $database->getConnection();

// กำหนด base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// dirname($_SERVER['PHP_SELF']) อาจให้ผลลัพธ์เป็น '\' หรือ '.' ในบางกรณี
// ดังนั้นควรจัดการให้เป็น string ว่างถ้าเป็น root directory
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_path = ($script_dir === '/' || $script_dir === '\\' || $script_dir === '.') ? '' : $script_dir;
$base_url = $protocol . "://" . $host . $base_path;


// รับค่า page จาก URL (ใช้ $_GET)
$page = isset($_GET['page']) ? $_GET['page'] : '';

// ---- จัดการหน้าพิเศษที่ต้องทำงานก่อน Output HTML ----

// 1. จัดการ Logout ก่อนที่จะ output HTML ใดๆ
if ($page === 'logout') {
    include_once 'auth/logout.php'; // auth/logout.php จะมี header() และ exit;
    // ไม่ควรมีโค้ดใดๆ ทำงานต่อจากนี้ถ้าเป็นหน้า logout
    // ob_end_flush(); // หรือ ob_end_clean(); ถ้าไม่ต้องการ output ใดๆ จาก logout.php (ซึ่งไม่ควรมี)
    // exit; // exit อยู่ใน logout.php แล้ว
}

// 2. ตรวจสอบหน้าพิเศษสำหรับตรวจสอบการตั้งค่า Google
if ($page === 'check_google_config') {
    include_once 'auth/check_google_config.php';
    ob_end_flush(); // ส่ง output ของ check_google_config.php
    exit;
}

// 3. ตรวจสอบ API สำหรับตรวจสอบสถานะ Token (เพิ่มใหม่)
if ($page === 'check_token_status') {
    if (!isset($_SESSION['student_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        ob_end_flush();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        ob_end_flush();
        exit;
    }

    header('Content-Type: application/json');

    try {
        $student_id = $_SESSION['student_id'];
        $query = "SELECT google_id, access_token as google_access_token, refresh_token as google_refresh_token, token_expires_at FROM students WHERE id = :id"; // แก้ไขชื่อคอลัมน์
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $student_id);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            echo json_encode(['connected' => false, 'expired' => false, 'needs_reconnect' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้']);
            ob_end_flush();
            exit;
        }
        $student_token_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($student_token_data['google_id'])) {
            echo json_encode(['connected' => false, 'expired' => false, 'needs_reconnect' => false, 'message' => 'ยังไม่ได้เชื่อมต่อบัญชี Google']);
            ob_end_flush();
            exit;
        }
        if (empty($student_token_data['google_access_token'])) {
            echo json_encode(['connected' => true, 'expired' => true, 'needs_reconnect' => true, 'message' => 'ไม่มี access token']);
            ob_end_flush();
            exit;
        }
        if (!empty($student_token_data['token_expires_at'])) {
            $expires_at = new DateTime($student_token_data['token_expires_at']);
            $now = new DateTime();
            $time_diff = $expires_at->getTimestamp() - $now->getTimestamp();

            if ($time_diff <= 0) {
                echo json_encode(['connected' => true, 'expired' => true, 'needs_reconnect' => true, 'message' => 'Token หมดอายุแล้ว', 'expired_at' => $student_token_data['token_expires_at']]);
                ob_end_flush();
                exit;
            } elseif ($time_diff <= 300) {
                echo json_encode(['connected' => true, 'expired' => false, 'needs_reconnect' => false, 'near_expiry' => true, 'message' => 'Token ใกล้หมดอายุ', 'expires_in' => $time_diff, 'expired_at' => $student_token_data['token_expires_at']]);
                ob_end_flush();
                exit;
            }
        }
        echo json_encode(['connected' => true, 'expired' => false, 'needs_reconnect' => false, 'message' => 'Token ยังใช้ได้', 'expires_at' => $student_token_data['token_expires_at']]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error', 'message' => 'เกิดข้อผิดพลาดในเซิร์ฟเวอร์']);
    }
    ob_end_flush();
    exit;
}

// ---- สิ้นสุดการจัดการหน้าพิเศษ ----


// ส่วนหัวของเว็บไซต์ (Header) - จะถูก include ถ้าไม่ใช่หน้าพิเศษที่ exit ไปแล้ว
include_once 'includes/header.php'; //

// ตรวจสอบไฟล์ที่ต้องการโหลด
// (Switch case จะไม่ถูกเรียกถ้า $page เป็น 'logout', 'check_google_config', หรือ 'check_token_status' เพราะ script จะ exit ไปก่อน)
switch ($page) {
    // หน้าหลัก
    case '':
    case 'home':
        include_once 'pages/home.php'; //
        break;

    // ส่วนของการยืนยันตัวตน (ยกเว้น logout ที่จัดการไปแล้ว)
    case 'login': // หน้านี้อาจไม่ถูกใช้โดยตรงแล้ว ถ้ามี student_login และ admin_login
        include_once 'auth/login.php';
        break;
    case 'student_login':
        include_once 'auth/student_login.php'; //
        break;
    case 'admin_login':
        include_once 'auth/admin_login.php'; //
        break;
    case 'google_login':
        include_once 'auth/google_login.php'; //
        break;
    case 'google_callback':
        include_once 'auth/google_callback.php'; //
        break;

    // ส่วนของนักศึกษา
    case 'student_dashboard':
        if (isset($_SESSION['student_id'])) {
            // แก้ไขชื่อไฟล์จาก dashborad.php เป็น dashboard.php ตามที่ควรจะเป็น
            if (file_exists('student/dashboard.php')) {
                include_once 'student/dashboard.php';
            } else if (file_exists('student/dashborad.php')) { // Fallback เผื่อยังไม่ได้แก้ชื่อไฟล์
                include_once 'student/dashborad.php'; //
            } else {
                include_once 'pages/404.php'; //
            }
        } else {
            header("Location: {$base_url}?page=student_login");
            exit;
        }
        break;
    case 'student_profile':
        if (isset($_SESSION['student_id'])) {
            include_once 'student/profile.php'; //
        } else {
            header("Location: {$base_url}?page=student_login");
            exit;
        }
        break;
    case 'google_drive_files':
        if (isset($_SESSION['student_id'])) {
            include_once 'student/google_drive_files.php'; //
        } else {
            header("Location: {$base_url}?page=student_login");
            exit;
        }
        break;

    // ส่วนของผู้ดูแลระบบ
    case 'admin_dashboard':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/dashboard.php'; //
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_users':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/users.php'; //
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_add_user':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/add_user.php'; //
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_bulk_add':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/bulk_add.php'; //
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_edit_user':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/edit_user.php'; //
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_view_user':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/view_user.php'; //
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_profile':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/profile.php'; //
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_unlink_google': // หน้านี้ควรเป็น action ไม่ใช่ page ที่แสดงผล HTML โดยตรง
                                // แนะนำให้เปลี่ยนเป็น POST request ไปยัง admin_profile แล้วจัดการ logic ที่นั่น
        if (isset($_SESSION['admin_id'])) {
             if (file_exists('admin/unlink_google.php')) { // ตรวจสอบชื่อไฟล์ให้ถูกต้อง
                include_once 'admin/unlink_google.php';
            } else if (file_exists('admin/unlink.google.php')) { // ชื่อไฟล์เดิมที่คุณให้มา
                include_once 'admin/unlink.google.php'; //
            } else {
                 include_once 'pages/404.php'; //
            }
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_logs':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/logs.php'; //
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;

    // หน้าตรวจสอบและแก้ไข
    case 'auth_check_admin_login': // ควรเข้าถึงผ่าน path ที่ถูกต้อง เช่น auth/check_admin_login.php โดยตรง
        include_once 'auth/check_admin_login.php'; //
        break;
    case 'reset_admin_password': // ควรเข้าถึงผ่าน path ที่ถูกต้อง
        include_once 'auth/reset_admin_password.php';
        break;
    case 'debug': // ควรเข้าถึงผ่าน path ที่ถูกต้อง
        include_once 'auth/debug.php'; //
        break;
    
    // หน้า migrate passwords (ควรเข้าถึงผ่าน path ที่ถูกต้อง และรันครั้งเดียว)
    case 'migrate_passwords_auth': // เปลี่ยนชื่อ page route ไม่ให้ชนกับไฟล์ config
        if (file_exists('auth/migrate_passwords.php')) { //
            include_once 'auth/migrate_passwords.php';
        } else if (file_exists('config/migrate_passwords.php')) { //
            include_once 'config/migrate_passwords.php';
        } else {
            include_once 'pages/404.php'; //
        }
        break;


    // หน้า 404 Not Found
    default:
        include_once 'pages/404.php'; //
        break;
}

// ส่วนท้ายของเว็บไซต์ (Footer)
include_once 'includes/footer.php'; //

ob_end_flush(); // ส่ง Output Buffer ทั้งหมดไปยัง Browser
?>
