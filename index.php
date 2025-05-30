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
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_path = ($script_dir === '/' || $script_dir === '\\' || $script_dir === '.') ? '' : $script_dir;
$base_url = $protocol . "://" . $host . $base_path;


// รับค่า page จาก URL (ใช้ $_GET)
$page = isset($_GET['page']) ? $_GET['page'] : '';

// ---- Redirect ผู้ใช้ที่ล็อกอินแล้วไปยังหน้า dashboard ที่ถูกต้อง ----
if (isset($_SESSION['student_id'])) {
    if ($page === '' || $page === 'home' || $page === 'student_login' || $page === 'admin_login' || $page === 'teacher_login') {
        header("Location: {$base_url}?page=student_dashboard");
        exit;
    }
} elseif (isset($_SESSION['admin_id'])) {
     if ($page === '' || $page === 'home' || $page === 'student_login' || $page === 'admin_login' || $page === 'teacher_login') {
        header("Location: {$base_url}?page=admin_dashboard");
        exit;
    }
} elseif (isset($_SESSION['teacher_user_id'])) { // เพิ่มการตรวจสอบ session ของอาจารย์
     if ($page === '' || $page === 'home' || $page === 'student_login' || $page === 'admin_login' || $page === 'teacher_login') {
        header("Location: {$base_url}?page=teacher_dashboard");
        exit;
    }
}


// ---- จัดการหน้าพิเศษที่ต้องทำงานก่อน Output HTML ----

// 1. จัดการ Logout ก่อนที่จะ output HTML ใดๆ
if ($page === 'logout') {
    include_once 'auth/logout.php';
    // exit อยู่ใน logout.php แล้ว
}

// 2. ตรวจสอบหน้าพิเศษสำหรับตรวจสอบการตั้งค่า Google
if ($page === 'check_google_config') {
    include_once 'auth/check_google_config.php';
    ob_end_flush();
    exit;
}

// 3. ตรวจสอบ API สำหรับตรวจสอบสถานะ Token
if ($page === 'check_token_status') {
    $user_id_session = null;
    $user_table_for_token = '';

    if (isset($_SESSION['student_id'])) {
        $user_id_session = $_SESSION['student_id'];
        $user_table_for_token = 'students';
    } elseif (isset($_SESSION['teacher_user_id'])) {
        $user_id_session = $_SESSION['teacher_user_id'];
        $user_table_for_token = 'teachers';
    }
    // Admin token check can be added here if needed

    if (!$user_id_session) {
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
        $query = "SELECT google_id, access_token, refresh_token, token_expires_at FROM {$user_table_for_token} WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user_id_session);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            echo json_encode(['connected' => false, 'expired' => false, 'needs_reconnect' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้']);
            ob_end_flush();
            exit;
        }
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($token_data['google_id'])) {
            echo json_encode(['connected' => false, 'expired' => false, 'needs_reconnect' => false, 'message' => 'ยังไม่ได้เชื่อมต่อบัญชี Google']);
            ob_end_flush();
            exit;
        }
        if (empty($token_data['access_token'])) {
            echo json_encode(['connected' => true, 'expired' => true, 'needs_reconnect' => true, 'message' => 'ไม่มี access token']);
            ob_end_flush();
            exit;
        }
        if (!empty($token_data['token_expires_at'])) {
            $expires_at = new DateTime($token_data['token_expires_at']);
            $now = new DateTime();
            $time_diff = $expires_at->getTimestamp() - $now->getTimestamp();

            if ($time_diff <= 0) {
                echo json_encode(['connected' => true, 'expired' => true, 'needs_reconnect' => true, 'message' => 'Token หมดอายุแล้ว', 'expired_at' => $token_data['token_expires_at']]);
                ob_end_flush();
                exit;
            } elseif ($time_diff <= 300) { // 5 minutes
                echo json_encode(['connected' => true, 'expired' => false, 'near_expiry' => true, 'message' => 'Token ใกล้หมดอายุ', 'expires_in' => $time_diff, 'expired_at' => $token_data['token_expires_at']]);
                ob_end_flush();
                exit;
            }
        }
        echo json_encode(['connected' => true, 'expired' => false, 'needs_reconnect' => false, 'message' => 'Token ยังใช้ได้', 'expires_at' => $token_data['token_expires_at']]);
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


// ส่วนหัวของเว็บไซต์ (Header)
include_once 'includes/header.php';

// ตรวจสอบไฟล์ที่ต้องการโหลด
switch ($page) {
    // หน้าหลัก
    case '':
    case 'home':
        include_once 'pages/home.php';
        break;

    // ส่วนของการยืนยันตัวตน (ยกเว้น logout ที่จัดการไปแล้ว)
    case 'student_login':
        include_once 'auth/student_login.php';
        break;
    case 'admin_login':
        include_once 'auth/admin_login.php';
        break;
    case 'teacher_login': // เพิ่มหน้าล็อกอินอาจารย์
        include_once 'auth/teacher_login.php';
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
            if (file_exists('student/dashboard.php')) { //
                include_once 'student/dashboard.php'; //
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
    case 'helpdesk':
        if (isset($_SESSION['student_id'])) {
            include_once 'student/helpdesk.php'; //
        } else {
            header("Location: {$base_url}?page=student_login");
            exit;
        }
        break;
    case 'helpdesk_create':
        if (isset($_SESSION['student_id'])) {
            include_once 'student/helpdesk_create.php'; //
        } else {
            header("Location: {$base_url}?page=student_login");
            exit;
        }
        break;
    case 'helpdesk_view':
        if (isset($_SESSION['student_id'])) {
            include_once 'student/helpdesk_view.php'; //
        } else {
            header("Location: {$base_url}?page=student_login");
            exit;
        }
        break;

    // ส่วนของอาจารย์ (ใหม่)
    case 'teacher_dashboard':
        if (isset($_SESSION['teacher_user_id'])) {
            if (file_exists('teacher/dashboard.php')) {
                include_once 'teacher/dashboard.php';
            } else {
                include_once 'pages/404.php'; //
            }
        } else {
            header("Location: {$base_url}?page=teacher_login");
            exit;
        }
        break;
    case 'teacher_profile':
        if (isset($_SESSION['teacher_user_id'])) {
             if (file_exists('teacher/profile.php')) {
                include_once 'teacher/profile.php';
            } else {
                include_once 'pages/404.php'; //
            }
        } else {
            header("Location: {$base_url}?page=teacher_login");
            exit;
        }
        break;
    // สามารถเพิ่มหน้าอื่นๆ สำหรับอาจารย์ได้ที่นี่


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
    case 'admin_unlink_google':
        if (isset($_SESSION['admin_id'])) {
            if (file_exists('admin/unlink_google.php')) {
                include_once 'admin/unlink_google.php';
            } else if (file_exists('admin/unlink.google.php')) {
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
    case 'admin_helpdesk':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/helpdesk.php'; //
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;
    case 'admin_helpdesk_view':
        if (isset($_SESSION['admin_id'])) {
            include_once 'admin/helpdesk_view.php'; //
        } else {
            header("Location: {$base_url}?page=admin_login");
            exit;
        }
        break;

    // หน้าตรวจสอบและแก้ไข
    case 'auth_check_admin_login':
        include_once 'auth/check_admin_login.php'; //
        break;
    case 'reset_admin_password':
        include_once 'auth/reset_admin_password.php';
        break;
    case 'debug':
        include_once 'auth/debug.php'; //
        break;
    case 'migrate_passwords_auth':
        if (file_exists('auth/migrate_passwords.php')) {
            include_once 'auth/migrate_passwords.php'; //
        } else if (file_exists('config/migrate_passwords.php')) {
            include_once 'config/migrate_passwords.php'; //
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
