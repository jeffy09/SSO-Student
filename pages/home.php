<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// ตรวจสอบสถานะการล็อกอิน และ redirect ไปยัง dashboard ของแต่ละประเภทผู้ใช้
if (isset($_SESSION['student_id'])) {
    // หากเป็นนักศึกษาที่ล็อกอินแล้ว ให้ไปยังหน้า dashboard ของนักศึกษา
    header("Location: index.php?page=student_dashboard");
    exit;
} elseif (isset($_SESSION['admin_id'])) {
    // หากเป็นผู้ดูแลระบบที่ล็อกอินแล้ว ให้ไปยังหน้า dashboard ของผู้ดูแลระบบ
    // (แม้ว่าหน้านี้จะเน้นนักศึกษา แต่ถ้าแอดมินเข้ามาโดยตรงก็ redirect ไป)
    header("Location: index.php?page=admin_dashboard");
    exit;
}

// รวมไฟล์ configuration ของ Google
include_once 'config/google_config.php';
$google_config = new GoogleConfig();

// หากมีการ submit form (คัดลอกจาก auth/student_login.php)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับค่าจาก form
    $student_id_form = isset($_POST['student_id']) ? $database->sanitize($_POST['student_id']) : '';
    $id_card_form = isset($_POST['id_card']) ? $_POST['id_card'] : '';

    // ตรวจสอบว่าข้อมูลถูกส่งมาหรือไม่
    if (!empty($student_id_form) && !empty($id_card_form)) {
        try {
            // เตรียมคำสั่ง SQL
            $query = "SELECT * FROM students WHERE student_id = :student_id LIMIT 0,1";
            
            // เตรียมคำสั่ง
            $stmt = $db->prepare($query);
            
            // Bind param
            $stmt->bindParam(':student_id', $student_id_form);
            
            // Execute
            $stmt->execute();
            
            // ตรวจสอบว่ามีข้อมูลหรือไม่
            if ($stmt->rowCount() > 0) {
                // ดึงข้อมูล
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $password_valid = false;
                
                // ตรวจสอบรหัสผ่าน
                // หากมี password_hash ให้ใช้ password_verify
                if (!empty($row['password_hash'])) {
                    $password_valid = password_verify($id_card_form, $row['password_hash']);
                } else {
                    // หากไม่มี password_hash (ข้อมูลเก่า) ให้เปรียบเทียบ id_card โดยตรง และ hash ใหม่ถ้าตรง
                    if ($row['id_card'] == $id_card_form) {
                        $password_valid = true;
                        // สร้าง hash สำหรับข้อมูลเก่าที่ยังไม่ได้ hash
                        $password_hash = password_hash($id_card_form, PASSWORD_DEFAULT);
                        $update_hash_query = "UPDATE students SET password_hash = :password_hash WHERE id = :id";
                        $update_hash_stmt = $db->prepare($update_hash_query);
                        $update_hash_stmt->bindParam(':password_hash', $password_hash);
                        $update_hash_stmt->bindParam(':id', $row['id']);
                        $update_hash_stmt->execute();
                    }
                }
                
                if ($password_valid) {
                    // ล็อกอินสำเร็จ
                    $_SESSION['student_id'] = $row['id'];
                    $_SESSION['student_code'] = $row['student_id'];
                    $_SESSION['student_name'] = $row['firstname'] . ' ' . $row['lastname'];
                    $_SESSION['student_email'] = $row['email'];
                    
                    // ตรวจสอบ first_login (ถ้ายังไม่ได้เชื่อม Google อาจจะแสดง popup)
                    if ($row['first_login'] == 1 && !empty($row['email'])) {
                        $_SESSION['show_google_link'] = true;
                    }
                    
                    // อัพเดต first_login status
                    $update_first_login_query = "UPDATE students SET first_login = 0 WHERE id = :id";
                    $update_first_login_stmt = $db->prepare($update_first_login_query);
                    $update_first_login_stmt->bindParam(':id', $row['id']);
                    $update_first_login_stmt->execute();
                    
                    // Redirect ไปยังหน้า dashboard ของนักศึกษา
                    header("Location: index.php?page=student_dashboard");
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

<div class="login-background-wrapper">
    <div class="d-flex align-items-center justify-content-center w-100">
        <div class="row justify-content-center w-100">
            <div class="col-md-8 col-lg-6 col-xxl-3">
                <div class="login-logo-container text-center mb-4">
                    <h2 class="text-primary fw-bold">ระบบจัดการนักศึกษา</h2>
                </div>
                <div class="card mb-0 login-card">
                    <div class="card-body">
                        <a href="<?php echo $base_url; ?>?page=home" class="text-nowrap logo-img text-center d-block py-3 w-100 mb-2">
                        </a>
                        <p class="text-center fw-semibold">เข้าสู่ระบบสำหรับนักศึกษา</p>
                        <?php if(isset($login_error)): ?>
                            <div class="alert alert-danger text-center py-2" role="alert">
                                <?php echo $login_error; ?>
                            </div>
                        <?php endif; ?>

                        <form action="?page=home" method="post">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">รหัสนักศึกษา</label>
                                <input type="text" class="form-control fs-6" id="student_id" name="student_id" required value="<?php echo isset($student_id_form) ? htmlspecialchars($student_id_form) : ''; ?>" placeholder="กรอกรหัสนักศึกษา">
                            </div>
                            <div class="mb-4"> <label for="id_card" class="form-label">รหัสบัตรประชาชน</label>
                                <input type="password" class="form-control fs-6" id="id_card" name="id_card" required placeholder="กรอกรหัสบัตรประชาชน">
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2 fs-5 mb-4 rounded-2">เข้าสู่ระบบ</button>
                        </form>

                        <div class="d-flex align-items-center justify-content-center">
                            <p class="fs-4 mb-0 fw-semibold me-2">หรือ</p>
                            <a href="?page=google_login&user_type=student" class="btn btn-outline-danger d-flex align-items-center justify-content-center">
                                <i class="fab fa-google fs-5 me-2"></i>
                                <span class="fw-semibold">เข้าสู่ระบบด้วย Google</span>
                            </a>
                        </div>
                        <div class="text-center mt-4">
                            <small class="text-muted">ผู้ดูแลระบบกรุณาเข้าสู่ระบบผ่าน <a href="<?php echo $base_url; ?>?page=admin_login" class="text-primary fw-semibold">URL สำหรับผู้ดูแลระบบ</a></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
