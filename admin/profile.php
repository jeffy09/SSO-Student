<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// รวมไฟล์ configuration ของ Google
include_once 'config/google_config.php';

// ตรวจสอบว่ามี session หรือไม่
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php?page=admin_login");
    exit;
}

// ดึงข้อมูลผู้ดูแลระบบ
try {
    $query = "SELECT * FROM admins WHERE id = :id LIMIT 0,1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_SESSION['admin_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // หากไม่พบข้อมูล ให้ logout
        header("Location: index.php?page=logout");
        exit;
    }
} catch(PDOException $e) {
    $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// หากมีการ submit form แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['change_password']) && !isset($_POST['unlink_google'])) {
    try {
        // รับค่าจาก form
        $name = isset($_POST['name']) ? $database->sanitize($_POST['name']) : '';
        $email = isset($_POST['email']) ? $database->sanitize($_POST['email']) : '';
        
        // ตรวจสอบว่าอีเมลซ้ำหรือไม่ (ยกเว้นอีเมลปัจจุบันของผู้ใช้)
        $check_query = "SELECT COUNT(*) as count FROM admins WHERE email = :email AND id != :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->bindParam(':id', $_SESSION['admin_id']);
        $check_stmt->execute();
        
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            $error_message = "อีเมลนี้มีผู้ใช้งานแล้ว กรุณาใช้อีเมลอื่น";
        } else {
            // เตรียมคำสั่ง SQL
            $update_query = "UPDATE admins SET name = :name, email = :email, updated_at = NOW() WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':name', $name);
            $update_stmt->bindParam(':email', $email);
            $update_stmt->bindParam(':id', $_SESSION['admin_id']);
            
            // Execute
            if ($update_stmt->execute()) {
                $success_message = "อัพเดตข้อมูลสำเร็จ";
                
                // อัพเดตข้อมูลใน session
                $_SESSION['admin_name'] = $name;
                $_SESSION['admin_email'] = $email;
                
                // อัพเดตข้อมูลในตัวแปร $admin
                $admin['name'] = $name;
                $admin['email'] = $email;
            } else {
                $error_message = "ไม่สามารถอัพเดตข้อมูลได้";
            }
        }
    } catch(PDOException $e) {
        $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// หากมีการ submit form เปลี่ยนรหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    try {
        // รับค่าจาก form
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // ตรวจสอบรหัสผ่านปัจจุบัน
        if (!password_verify($current_password, $admin['password'])) {
            $password_error = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
        } 
        // ตรวจสอบว่ารหัสผ่านใหม่และยืนยันรหัสผ่านตรงกัน
        elseif ($new_password !== $confirm_password) {
            $password_error = "รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน";
        } 
        // ตรวจสอบความยาวรหัสผ่าน
        elseif (strlen($new_password) < 6) {
            $password_error = "รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
        } else {
            // เข้ารหัสรหัสผ่านใหม่
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // อัพเดตรหัสผ่าน
            $update_query = "UPDATE admins SET password = :password, updated_at = NOW() WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':id', $_SESSION['admin_id']);
            
            if ($update_stmt->execute()) {
                $password_success = "เปลี่ยนรหัสผ่านสำเร็จ";
                
                // อัพเดตข้อมูลในตัวแปร $admin
                $admin['password'] = $hashed_password;
            } else {
                $password_error = "ไม่สามารถเปลี่ยนรหัสผ่านได้";
            }
        }
    } catch(PDOException $e) {
        $password_error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// หากมีการ submit form ยกเลิกการเชื่อมต่อ Google
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unlink_google'])) {
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
            
            // อัพเดตข้อมูลในตัวแปร $admin
            $admin['google_id'] = NULL;
            
            $unlink_success = "ยกเลิกการเชื่อมต่อ Google สำเร็จ";
        } else {
            $unlink_error = "ไม่สามารถยกเลิกการเชื่อมต่อ Google ได้";
        }
    } catch(PDOException $e) {
        $unlink_error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// ตรวจสอบข้อความจาก Google Authentication
$google_success = isset($_SESSION['google_connect_success']) ? $_SESSION['google_connect_success'] : '';
$google_error = isset($_SESSION['google_connect_error']) ? $_SESSION['google_connect_error'] : '';

// ล้างข้อความจาก session หลังจากใช้แล้ว
if (isset($_SESSION['google_connect_success'])) {
    unset($_SESSION['google_connect_success']);
}
if (isset($_SESSION['google_connect_error'])) {
    unset($_SESSION['google_connect_error']);
}
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <h2>โปรไฟล์ผู้ดูแลระบบ</h2>
        <p>แก้ไขข้อมูลส่วนตัวของคุณ</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- แก้ไขข้อมูลส่วนตัว -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">ข้อมูลส่วนตัว</h5>
            </div>
            <div class="card-body">
                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <form action="?page=admin_profile" method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">ชื่อผู้ใช้</label>
                        <input type="text" class="form-control" id="username" value="<?php echo $admin['username']; ?>" readonly>
                        <div class="form-text">ไม่สามารถแก้ไขชื่อผู้ใช้ได้</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $admin['name']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">อีเมล</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $admin['email']; ?>" required>
                        <div class="form-text">ใช้อีเมลนี้สำหรับการเชื่อมต่อกับ Google</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">วันที่สร้าง</label>
                        <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i:s', strtotime($admin['created_at'])); ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">เข้าสู่ระบบล่าสุด</label>
                        <input type="text" class="form-control" value="<?php echo !empty($admin['last_login']) ? date('d/m/Y H:i:s', strtotime($admin['last_login'])) : 'ไม่มีข้อมูล'; ?>" readonly>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                </form>
            </div>
        </div>
        
        <!-- เปลี่ยนรหัสผ่าน -->
        <div class="card shadow mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">เปลี่ยนรหัสผ่าน</h5>
            </div>
            <div class="card-body">
                <?php if(isset($password_success)): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo $password_success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($password_error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $password_error; ?>
                    </div>
                <?php endif; ?>
                
                <form action="?page=admin_profile" method="post">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">รหัสผ่านปัจจุบัน</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="form-text">รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">เปลี่ยนรหัสผ่าน</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- การเชื่อมต่อบัญชี -->
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">การเชื่อมต่อบัญชี</h5>
            </div>
            <div class="card-body">
                <?php if(isset($unlink_success)): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo $unlink_success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($unlink_error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $unlink_error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(empty($admin['google_id'])): ?>
                    <div class="alert alert-warning alert-permanent">
                        คุณยังไม่ได้เชื่อมต่อบัญชี Google กับระบบ
                    </div>
                    <div class="mb-3">
                        <p>เชื่อมต่อกับบัญชี Google ของคุณเพื่อให้สามารถลงชื่อเข้าใช้ด้วย Google ได้ในครั้งต่อไป</p>
                        <p><strong>หมายเหตุ:</strong> อีเมลในบัญชี Google ต้องตรงกับอีเมลในโปรไฟล์ของคุณ (<?php echo $admin['email']; ?>)</p>
                        <a href="?page=google_login&user_type=admin&from_profile=1" class="btn btn-danger w-100">
                            <i class="fab fa-google me-2"></i>เชื่อมต่อบัญชี Google
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success alert-permanent">
                        <i class="fas fa-check-circle me-2"></i> คุณได้เชื่อมต่อบัญชี Google กับระบบแล้ว
                    </div>
                    <p>คุณสามารถใช้ Google Sign-In เพื่อเข้าสู่ระบบในครั้งต่อไปได้</p>
                    
                    <form action="?page=admin_profile" method="post" id="unlinkGoogleForm">
                        <input type="hidden" name="unlink_google" value="1">
                        <button type="button" class="btn btn-outline-danger w-100" onclick="confirmUnlinkGoogle()">
                            <i class="fas fa-unlink me-2"></i>ยกเลิกการเชื่อมต่อ Google
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- คำแนะนำ -->
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">คำแนะนำ</h5>
            </div>
            <div class="card-body">
                <p><i class="fas fa-info-circle me-2"></i> <strong>การเชื่อมต่อ Google:</strong></p>
                <ul>
                    <li>อีเมลในบัญชี Google ต้องตรงกับอีเมลในระบบ</li>
                    <li>หากต้องการเปลี่ยนอีเมล ให้แก้ไขอีเมลในโปรไฟล์ก่อนทำการเชื่อมต่อ</li>
                    <li>การเชื่อมต่อกับ Google จะช่วยให้คุณสามารถล็อกอินได้สะดวกขึ้น</li>
                </ul>
                
                <p><i class="fas fa-shield-alt me-2"></i> <strong>ความปลอดภัย:</strong></p>
                <ul>
                    <li>ควรเปลี่ยนรหัสผ่านเป็นประจำ</li>
                    <li>รหัสผ่านควรมีความซับซ้อนเพียงพอ</li>
                    <li>ไม่ควรใช้รหัสผ่านเดียวกันกับเว็บไซต์อื่น</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// ฟังก์ชันสำหรับยืนยันการยกเลิกการเชื่อมต่อ Google
function confirmUnlinkGoogle() {
    Swal.fire({
        title: 'ยืนยันการยกเลิกการเชื่อมต่อ',
        text: 'คุณแน่ใจหรือไม่ที่จะยกเลิกการเชื่อมต่อกับ Google?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, ยกเลิกการเชื่อมต่อ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('unlinkGoogleForm').submit();
        }
    });
}

// แสดง Sweet Alert สำหรับข้อความต่างๆ
<?php if(isset($success_message)): ?>
Swal.fire({
    title: 'สำเร็จ!',
    text: '<?php echo $success_message; ?>',
    icon: 'success',
    confirmButtonText: 'ตกลง'
});
<?php endif; ?>

<?php if(isset($password_success)): ?>
Swal.fire({
    title: 'สำเร็จ!',
    text: '<?php echo $password_success; ?>',
    icon: 'success',
    confirmButtonText: 'ตกลง'
});
<?php endif; ?>

<?php if(isset($unlink_success)): ?>
Swal.fire({
    title: 'สำเร็จ!',
    text: '<?php echo $unlink_success; ?>',
    icon: 'success',
    confirmButtonText: 'ตกลง'
});
<?php endif; ?>

<?php if(!empty($google_success)): ?>
Swal.fire({
    title: 'สำเร็จ!',
    text: '<?php echo $google_success; ?>',
    icon: 'success',
    confirmButtonText: 'ตกลง'
}).then(() => {
    // รีเฟรชหน้าเพื่อแสดงสถานะการเชื่อมต่อที่อัพเดต
    window.location.reload();
});
<?php endif; ?>

<?php if(!empty($google_error)): ?>
Swal.fire({
    title: 'เกิดข้อผิดพลาด!',
    text: '<?php echo $google_error; ?>',
    icon: 'error',
    confirmButtonText: 'ตกลง'
});
<?php endif; ?>
</script>