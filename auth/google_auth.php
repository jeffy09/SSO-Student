<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// ตรวจสอบว่ามีการส่งข้อมูล credential และ user_type มาหรือไม่
if (isset($_POST['credential']) && isset($_POST['user_type'])) {
    $credential = $_POST['credential'];
    $user_type = $_POST['user_type'];
    
    // ถอดรหัส JWT token จาก Google
    $jwt_parts = explode('.', $credential);
    $jwt_payload = json_decode(base64_decode($jwt_parts[1]), true);
    
    // ตรวจสอบว่าสามารถถอดรหัสได้หรือไม่
    if ($jwt_payload && isset($jwt_payload['email'])) {
        $email = $jwt_payload['email'];
        $google_id = $jwt_payload['sub'];
        
        try {
            // ตรวจสอบตาม user_type
            if ($user_type === 'student') {
                // ตรวจสอบว่าอีเมลนี้มีในฐานข้อมูลนักศึกษาหรือไม่
                $query = "SELECT * FROM students WHERE email = :email LIMIT 0,1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // พบข้อมูลนักศึกษา
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // อัพเดต google_id ถ้ายังไม่มี
                    if (empty($row['google_id'])) {
                        $update_query = "UPDATE students SET google_id = :google_id WHERE id = :id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':google_id', $google_id);
                        $update_stmt->bindParam(':id', $row['id']);
                        $update_stmt->execute();
                    }
                    
                    // สร้าง session
                    $_SESSION['student_id'] = $row['id'];
                    $_SESSION['student_code'] = $row['student_id'];
                    $_SESSION['student_name'] = $row['firstname'] . ' ' . $row['lastname'];
                    $_SESSION['student_email'] = $row['email'];
                    
                    // Redirect ไปยังหน้า dashboard
                    header("Location: index.php?page=student_profile");
                    exit;
                } else {
                    // ไม่พบอีเมลในระบบ
                    $_SESSION['auth_error'] = "ไม่พบอีเมลนี้ในระบบนักศึกษา กรุณาลงชื่อเข้าใช้ด้วยรหัสนักศึกษาและรหัสบัตรประชาชนก่อน";
                    header("Location: index.php?page=student_login");
                    exit;
                }
            } elseif ($user_type === 'admin') {
                // ตรวจสอบว่าอีเมลนี้มีในฐานข้อมูลผู้ดูแลระบบหรือไม่
                $query = "SELECT * FROM admins WHERE email = :email LIMIT 0,1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // พบข้อมูลผู้ดูแลระบบ
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // อัพเดต google_id ถ้ายังไม่มี
                    if (empty($row['google_id'])) {
                        $update_query = "UPDATE admins SET google_id = :google_id WHERE id = :id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':google_id', $google_id);
                        $update_stmt->bindParam(':id', $row['id']);
                        $update_stmt->execute();
                    }
                    
                    // สร้าง session
                    $_SESSION['admin_id'] = $row['id'];
                    $_SESSION['admin_name'] = $row['name'];
                    $_SESSION['admin_email'] = $row['email'];
                    
                    // Redirect ไปยังหน้า dashboard
                    header("Location: index.php?page=admin_dashboard");
                    exit;
                } else {
                    // ไม่พบอีเมลในระบบ
                    $_SESSION['auth_error'] = "ไม่พบอีเมลนี้ในระบบผู้ดูแล กรุณาลงชื่อเข้าใช้ด้วยชื่อผู้ใช้และรหัสผ่านก่อน";
                    header("Location: index.php?page=admin_login");
                    exit;
                }
            } else {
                throw new Exception("ประเภทผู้ใช้ไม่ถูกต้อง");
            }
        } catch(Exception $e) {
            $_SESSION['auth_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
            header("Location: index.php");
            exit;
        }
    } else {
        $_SESSION['auth_error'] = "ข้อมูลการยืนยันตัวตนไม่ถูกต้อง";
        header("Location: index.php");
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>