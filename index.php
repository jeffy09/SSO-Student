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

// ตรวจสอบ API สำหรับตรวจสอบสถานะ Token (เพิ่มใหม่)
if ($page === 'check_token_status') {
    if (!isset($_SESSION['student_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    // ตรวจสอบว่าเป็น POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    // Set content type เป็น JSON
    header('Content-Type: application/json');
    
    try {
        $student_id = $_SESSION['student_id'];
        
        // ดึงข้อมูล token จากฐานข้อมูล
        $query = "SELECT google_id, google_access_token, google_refresh_token, token_expires_at FROM students WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $student_id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            echo json_encode([
                'connected' => false,
                'expired' => false,
                'needs_reconnect' => false,
                'message' => 'ไม่พบข้อมูลผู้ใช้'
            ]);
            exit;
        }
        
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ตรวจสอบว่ามีการเชื่อมต่อ Google หรือไม่
        if (empty($student['google_id'])) {
            echo json_encode([
                'connected' => false,
                'expired' => false,
                'needs_reconnect' => false,
                'message' => 'ยังไม่ได้เชื่อมต่อบัญชี Google'
            ]);
            exit;
        }
        
        // ตรวจสอบว่ามี access token หรือไม่
        if (empty($student['google_access_token'])) {
            echo json_encode([
                'connected' => true,
                'expired' => true,
                'needs_reconnect' => true,
                'message' => 'ไม่มี access token'
            ]);
            exit;
        }
        
        // ตรวจสอบวันหมดอายุ
        if (!empty($student['token_expires_at'])) {
            $expires_at = new DateTime($student['token_expires_at']);
            $now = new DateTime();
            
            $time_diff = $expires_at->getTimestamp() - $now->getTimestamp();
            
            if ($time_diff <= 0) {
                // Token หมดอายุแล้ว
                echo json_encode([
                    'connected' => true,
                    'expired' => true,
                    'needs_reconnect' => true,
                    'message' => 'Token หมดอายุแล้ว',
                    'expired_at' => $student['token_expires_at']
                ]);
                exit;
            } elseif ($time_diff <= 300) {
                // ใกล้หมดอายุ (เหลือน้อยกว่า 5 นาที)
                echo json_encode([
                    'connected' => true,
                    'expired' => false,
                    'needs_reconnect' => false,
                    'near_expiry' => true,
                    'message' => 'Token ใกล้หมดอายุ',
                    'expires_in' => $time_diff,
                    'expired_at' => $student['token_expires_at']
                ]);
                exit;
            }
        }
        
        // Token ยังใช้ได้
        echo json_encode([
            'connected' => true,
            'expired' => false,
            'needs_reconnect' => false,
            'message' => 'Token ยังใช้ได้',
            'expires_at' => $student['token_expires_at']
        ]);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Database error',
            'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล'
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Server error',
            'message' => 'เกิดข้อผิดพลาดในเซิร์ฟเวอร์'
        ]);
    }
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
?>