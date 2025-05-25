<?php
// ในไฟล์ auth/logout.php

if (!defined('SECURE_ACCESS')) {
    // ถ้ามีการเข้าถึงโดยตรง พยายามล้าง buffer ก่อน die
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    die('Direct access not permitted');
}

// ตรวจสอบให้แน่ใจว่า session ได้เริ่มแล้ว
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ล้างข้อมูล session ทั้งหมด
$_SESSION = array();

// ลบ session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย session
session_destroy();

// การ redirect จะถูกจัดการโดย header() ที่นี่ และ index.php จะไม่ output HTML ใดๆ ก่อน
// ไม่จำเป็นต้องเรียก ob_end_clean() ที่นี่อีก เพราะ index.php จะจัดการตอนท้ายสุด
// หรือถ้า logout.php ถูก include และ exit ก่อนถึง ob_end_flush() ใน index.php ก็ไม่เป็นไร
if (!headers_sent()) {
    header("Location: index.php"); // Redirect ไปหน้าหลัก
    exit; // สำคัญมาก: จบการทำงานทันที
} else {
    // Fallback ในกรณีที่ header ถูกส่งไปแล้ว (ซึ่งไม่ควรจะเกิดกับโครงสร้างใหม่นี้)
    // สามารถ log error หรือแสดงข้อความธรรมดาได้
    error_log("Logout failed: Headers already sent.");
    // อาจจะแสดงข้อความให้ผู้ใช้ทราบว่าออกจากระบบแล้ว แต่ redirect ไม่สำเร็จ
    echo "คุณได้ออกจากระบบแล้ว แต่การเปลี่ยนหน้าอัตโนมัติไม่สำเร็จ กรุณาไปยัง <a href='index.php'>หน้าหลัก</a>";
    exit;
}
?>
