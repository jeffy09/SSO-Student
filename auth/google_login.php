<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// รวมไฟล์ configuration ของ Google
include_once 'config/google_config.php';

// ฟังก์ชันสำหรับบันทึก log
function write_auth_log($message) {
    $log_dir = 'logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/google_login_' . date('Y-m-d') . '.log';
    $log_message = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// ตรวจสอบว่ามีการส่ง user_type มาหรือไม่
if (isset($_GET['user_type']) && ($_GET['user_type'] === 'student' || $_GET['user_type'] === 'admin')) {
    $user_type = $_GET['user_type'];
    
    // บันทึกประเภทผู้ใช้ใน session
    $_SESSION['google_auth_type'] = $user_type;
    
    write_auth_log("Starting Google OAuth for user_type: $user_type");
    
    // ตรวจสอบว่าเป็นการเชื่อมต่อจากหน้าโปรไฟล์หรือไม่
    $from_profile = $_GET['from_profile'] ?? '';
    if ($from_profile === '1' || $from_profile === 'true' || $from_profile === 'yes') {
        $_SESSION['connecting_from_profile'] = true;
        write_auth_log("Connection initiated from profile page");
    } else {
        // ลบ session เก่าถ้ามี
        unset($_SESSION['connecting_from_profile']);
        write_auth_log("Regular login flow");
    }
    
    try {
        // สร้าง instance ของ GoogleConfig
        $google_config = new GoogleConfig();
        
        // สร้าง URL สำหรับการเริ่มกระบวนการ OAuth
        $auth_url = $google_config->getAuthUrl();
        
        write_auth_log("Generated OAuth URL: " . $auth_url);
        write_auth_log("Redirecting to Google OAuth...");
        
        // Redirect ไปยัง Google OAuth
        header("Location: " . $auth_url);
        exit;
        
    } catch(Exception $e) {
        $error_message = "เกิดข้อผิดพลาดในการสร้าง OAuth URL: " . $e->getMessage();
        write_auth_log("Error: " . $error_message);
        
        // จัดการ error ตาม user type และการเชื่อมต่อ
        if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
            $_SESSION['google_connect_error'] = $error_message;
            unset($_SESSION['connecting_from_profile']);
            
            if ($user_type === 'student') {
                header("Location: index.php?page=student_profile&google_connect=error");
            } else {
                header("Location: index.php?page=admin_profile&google_connect=error");
            }
        } else {
            $_SESSION['auth_error'] = $error_message;
            
            if ($user_type === 'student') {
                header("Location: index.php?page=student_login");
            } else {
                header("Location: index.php?page=admin_login");
            }
        }
        exit;
    }
} else {
    // ไม่ระบุประเภทผู้ใช้หรือประเภทไม่ถูกต้อง
    $error_message = "กรุณาระบุประเภทผู้ใช้ที่ถูกต้อง";
    write_auth_log("Error: Invalid or missing user_type parameter");
    
    $_SESSION['auth_error'] = $error_message;
    header("Location: index.php");
    exit;
}
?>