<?php
// // เปิดการแสดงข้อผิดพลาด
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// กำหนดค่าคงที่เพื่อบอกว่าการเข้าถึงไฟล์ถูกต้อง
define('SECURE_ACCESS', true);

// รวมไฟล์การเชื่อมต่อฐานข้อมูล
require_once '../config/db.php';

// สร้างการเชื่อมต่อฐานข้อมูล
$database = new Database();
$db = $database->getConnection();

// ข้อมูลที่ต้องการทดสอบ
$username = 'admin';
$password = 'admin123';
$result = '';

// ทดสอบการล็อกอิน
try {
    // ดึงข้อมูลผู้ใช้
    $query = "SELECT * FROM admins WHERE username = :username LIMIT 0,1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ทดสอบตรวจสอบรหัสผ่าน
        $password_check = password_verify($password, $admin['password']);
        
        if ($password_check) {
            $result = "การล็อกอินสำเร็จ! รหัสผ่านถูกต้อง";
        } else {
            $result = "รหัสผ่านไม่ถูกต้อง";
            
            // แสดงรายละเอียดเพิ่มเติมเพื่อการตรวจสอบ
            $result .= "<br>รหัสผ่านที่ป้อน: $password";
            $result .= "<br>รหัสผ่านที่เข้ารหัสในฐานข้อมูล: " . $admin['password'];
            
            // ทดสอบสร้างรหัสผ่านใหม่
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $result .= "<br>รหัสผ่านที่เข้ารหัสใหม่: $new_hash";
            
            // ข้อมูลอื่นๆ ที่อาจเป็นประโยชน์
            $result .= "<br>PHP Version: " . phpversion();
            $result .= "<br>Password Hash Algorithm: " . (defined('PASSWORD_ARGON2ID') ? 'Argon2id available' : 'Using Bcrypt');
        }
    } else {
        $result = "ไม่พบผู้ใช้ $username ในระบบ";
    }
} catch(PDOException $e) {
    $result = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// ตรวจสอบการเชื่อมต่อ Google
try {
    // ดึงข้อมูลผู้ดูแลระบบที่เชื่อมต่อ Google แล้ว
    $query = "SELECT COUNT(*) as count FROM admins WHERE google_id IS NOT NULL AND google_id != ''";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $google_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $google_result = "จำนวนผู้ดูแลระบบที่เชื่อมต่อ Google แล้ว: $google_count";
} catch(PDOException $e) {
    $google_result = "เกิดข้อผิดพลาดในการตรวจสอบการเชื่อมต่อ Google: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบการล็อกอินผู้ดูแลระบบ</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>ตรวจสอบการล็อกอินผู้ดูแลระบบ</h1>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">ผลการทดสอบล็อกอิน</h5>
            </div>
            <div class="card-body">
                <p><strong>ชื่อผู้ใช้:</strong> <?php echo $username; ?></p>
                <p><strong>รหัสผ่าน:</strong> <?php echo $password; ?></p>
                
                <div class="alert <?php echo strpos($result, 'สำเร็จ') !== false ? 'alert-success' : 'alert-danger'; ?>">
                    <?php echo $result; ?>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">การเชื่อมต่อ Google</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <?php echo $google_result; ?>
                </div>
                
                <p>หากต้องการทดสอบการล็อกอินด้วย Google สำหรับผู้ดูแลระบบ:</p>
                <ol>
                    <li>ตรวจสอบว่าอีเมลในบัญชี Google ของคุณตรงกับอีเมลในฐานข้อมูลผู้ดูแลระบบ</li>
                    <li>ไปที่หน้าล็อกอินผู้ดูแลระบบและคลิกปุ่ม "เข้าสู่ระบบด้วย Google"</li>
                    <li>หากมีข้อผิดพลาด "ไม่พบอีเมลนี้ในระบบผู้ดูแล" ให้ตรวจสอบว่าอีเมลที่ใช้ในบัญชี Google ตรงกับในฐานข้อมูล</li>
                </ol>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">แก้ไขปัญหา</h5>
            </div>
            <div class="card-body">
                <p>หากยังพบปัญหาการล็อกอิน ลองทำตามขั้นตอนต่อไปนี้:</p>
                <ol>
                    <li>ใช้หน้า <a href="reset_admin_password.php" target="_blank">reset_admin_password.php</a> เพื่อรีเซ็ตรหัสผ่านผู้ดูแลระบบ</li>
                    <li>ตรวจสอบโครงสร้างตาราง <code>admins</code> ในฐานข้อมูลว่าถูกต้องหรือไม่</li>
                    <li>ลองเพิ่มผู้ดูแลระบบใหม่ผ่านหน้า reset_admin_password.php</li>
                </ol>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="../index.php" class="btn btn-secondary">กลับไปยังหน้าหลัก</a>
            <a href="reset_admin_password.php" class="btn btn-primary">ไปที่หน้ารีเซ็ตรหัสผ่าน</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>