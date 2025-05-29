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
    $username = isset($_POST['username']) ? $database->sanitize($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // ตรวจสอบว่าข้อมูลถูกส่งมาหรือไม่
    if (!empty($username) && !empty($password)) {
        try {
            // เตรียมคำสั่ง SQL
            $query = "SELECT * FROM admins WHERE username = :username LIMIT 0,1";
            
            // เตรียมคำสั่ง
            $stmt = $db->prepare($query);
            
            // Bind param
            $stmt->bindParam(':username', $username);
            
            // Execute
            $stmt->execute();
            
            // ตรวจสอบว่ามีข้อมูลหรือไม่
            if ($stmt->rowCount() > 0) {
                // ดึงข้อมูล
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // ตรวจสอบรหัสผ่าน
                if (password_verify($password, $row['password'])) {
                    // ล็อกอินสำเร็จ
                    $_SESSION['admin_id'] = $row['id'];
                    $_SESSION['admin_name'] = $row['name'];
                    $_SESSION['admin_email'] = $row['email'];
                    
                    // อัพเดตเวลาเข้าสู่ระบบล่าสุด
                    $update_login = "UPDATE admins SET last_login = NOW() WHERE id = :id";
                    $update_login_stmt = $db->prepare($update_login);
                    $update_login_stmt->bindParam(':id', $row['id']);
                    $update_login_stmt->execute();
                    
                    // Redirect ไปยังหน้า dashboard
                    header("Location: index.php?page=admin_dashboard");
                    exit;
                } else {
                    $login_error = "รหัสผ่านไม่ถูกต้อง";
                }
            } else {
                $login_error = "ไม่พบชื่อผู้ใช้นี้ในระบบ";
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

<div class="position-relative overflow-hidden radial-gradient min-vh-100 d-flex align-items-center justify-content-center">
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
                        <p class="text-center fw-semibold">เข้าสู่ระบบสำหรับผู้ดูแลระบบ</p>
                        <?php if(isset($login_error)): ?>
                            <div class="alert alert-danger text-center py-2" role="alert">
                                <?php echo $login_error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="?page=admin_login" method="post">
                            <div class="mb-3">
                                <label for="username" class="form-label">ชื่อผู้ใช้</label>
                                <input type="text" class="form-control fs-6" id="username" name="username" required value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" placeholder="กรอกชื่อผู้ใช้">
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">รหัสผ่าน</label>
                                <input type="password" class="form-control fs-6" id="password" name="password" required placeholder="กรอกรหัสผ่าน">
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2 fs-5 mb-4 rounded-2">เข้าสู่ระบบ</button>
                        </form>
                        
                        <div class="d-flex align-items-center justify-content-center">
                             <p class="fs-4 mb-0 fw-semibold me-2">หรือ</p>
                             <a href="?page=google_login&user_type=admin" class="btn btn-outline-danger d-flex align-items-center justify-content-center">
                                <i class="fab fa-google fs-5 me-2"></i>
                                <span class="fw-semibold">เข้าสู่ระบบด้วย Google</span>
                            </a>
                        </div>
                         <div class="text-center mt-4">
                            <small class="text-muted">นักศึกษา กรุณาเข้าสู่ระบบผ่าน <a href="<?php echo $base_url; ?>?page=home" class="text-primary fw-semibold">หน้าหลักสำหรับนักศึกษา</a></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
