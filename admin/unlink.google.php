<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// ตรวจสอบว่ามี session หรือไม่
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php?page=admin_login");
    exit;
}

// ตรวจสอบว่าเป็นการส่งแบบ POST หรือไม่
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // อัพเดตข้อมูล Google ID
        $query = "UPDATE admins SET google_id = NULL WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_SESSION['admin_id']);
        
        if ($stmt->execute()) {
            // บันทึก log
            $log_action = "ยกเลิกการเชื่อมต่อ Google: " . $_SESSION['admin_name'];
            $log_query = "INSERT INTO admin_logs (admin_id, action, created_at) VALUES (:admin_id, :action, NOW())";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':admin_id', $_SESSION['admin_id']);
            $log_stmt->bindParam(':action', $log_action);
            $log_stmt->execute();
            
            $_SESSION['success_message'] = "ยกเลิกการเชื่อมต่อ Google สำเร็จ";
        } else {
            $_SESSION['error_message'] = "ไม่สามารถยกเลิกการเชื่อมต่อ Google ได้";
        }
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// Redirect กลับไปยังหน้าโปรไฟล์
header("Location: index.php?page=admin_profile");
exit;
?>