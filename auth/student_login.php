<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// รวมไฟล์ configuration ของ Google
include_once 'config/google_config.php';
$google_config = new GoogleConfig();

// หากมีการ submit form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับค่าจาก form
    $student_id = isset($_POST['student_id']) ? $database->sanitize($_POST['student_id']) : '';
    $id_card = isset($_POST['id_card']) ? $_POST['id_card'] : '';

    // ตรวจสอบว่าข้อมูลถูกส่งมาหรือไม่
    if (!empty($student_id) && !empty($id_card)) {
        try {
            // เตรียมคำสั่ง SQL
            $query = "SELECT * FROM students WHERE student_id = :student_id LIMIT 0,1";
            
            // เตรียมคำสั่ง
            $stmt = $db->prepare($query);
            
            // Bind param
            $stmt->bindParam(':student_id', $student_id);
            
            // Execute
            $stmt->execute();
            
            // ตรวจสอบว่ามีข้อมูลหรือไม่
            if ($stmt->rowCount() > 0) {
                // ดึงข้อมูล
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // ตรวจสอบรหัสบัตรประชาชน
                if ($row['id_card'] == $id_card) {
                    // ล็อกอินสำเร็จ
                    $_SESSION['student_id'] = $row['id'];
                    $_SESSION['student_code'] = $row['student_id'];
                    $_SESSION['student_name'] = $row['firstname'] . ' ' . $row['lastname'];
                    $_SESSION['student_email'] = $row['email'];
                    
                    // ถ้าเป็นการล็อกอินครั้งแรก และมีอีเมล ให้แสดง modal เชื่อมต่อ Gmail
                    if ($row['first_login'] == 1 && !empty($row['email'])) {
                        $_SESSION['show_google_link'] = true;
                    }
                    
                    // อัพเดตสถานะการล็อกอินครั้งแรก
                    $update_query = "UPDATE students SET first_login = 0 WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':id', $row['id']);
                    $update_stmt->execute();
                    
                    // Redirect ไปยังหน้า dashboard
                    header("Location: index.php?page=student_profile");
                    exit;
                } else {
                    $login_error = "รหัสบัตรประชาชนไม่ถูกต้อง";
                }
            } else {
                $login_error = "ไม่พบรหัสนักศึกษานี้ในระบบ";
            }
        } catch(PDOException $e) {
            $login_error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    } else {
        $login_error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}

// ดึงข้อความแจ้งเตือนจาก session (ถ้ามี)
if (isset($_SESSION['auth_error'])) {
    $login_error = $_SESSION['auth_error'];
    unset($_SESSION['auth_error']);
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">เข้าสู่ระบบสำหรับนักศึกษา</h4>
            </div>
            <div class="card-body">
                <?php if(isset($login_error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $login_error; ?>
                    </div>
                <?php endif; ?>
                
                <form action="?page=student_login" method="post">
                    <div class="mb-3">
                        <label for="student_id" class="form-label">รหัสนักศึกษา</label>
                        <input type="text" class="form-control" id="student_id" name="student_id" required>
                    </div>
                    <div class="mb-3">
                        <label for="id_card" class="form-label">รหัสบัตรประชาชน</label>
                        <input type="password" class="form-control" id="id_card" name="id_card" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">เข้าสู่ระบบ</button>
                </form>
                
                <hr>
                
                <div class="text-center">
                    <p>หรือเข้าสู่ระบบด้วย</p>
                    <a href="?page=google_login&user_type=student" class="btn btn-outline-danger">
                        <i class="fab fa-google me-2"></i>เข้าสู่ระบบด้วย Google
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>