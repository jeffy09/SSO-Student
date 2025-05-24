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

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id'])) {
    header("Location: index.php?page=admin_users");
    exit;
}

// ตัวแปรสำหรับเก็บสถานะการแจ้งเตือน
$alert_type = '';
$alert_message = '';
$show_alert = false;
$redirect_url = '';

// ถอดรหัส ID
try {
    $id = base64_decode($_GET['id']);
    
    // ตรวจสอบว่า ID เป็นตัวเลขหรือไม่
    if (!is_numeric($id)) {
        throw new Exception("รหัสไม่ถูกต้อง");
    }
    
    // ดึงข้อมูลนักศึกษา
    $query = "SELECT * FROM students WHERE id = :id LIMIT 0,1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        throw new Exception("ไม่พบข้อมูลนักศึกษา");
    }
    
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: index.php?page=admin_users");
    exit;
}

// หากมีการ submit form แก้ไขข้อมูลทั่วไป
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['reset_id_card']) && !isset($_POST['unlink_google'])) {
    try {
        // เก็บข้อมูลเดิมก่อนการแก้ไข
        $old_data = json_encode($student);
        
        // รับค่าจาก form
        $firstname = isset($_POST['firstname']) ? $database->sanitize($_POST['firstname']) : '';
        $lastname = isset($_POST['lastname']) ? $database->sanitize($_POST['lastname']) : '';
        $email = isset($_POST['email']) ? $database->sanitize($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? $database->sanitize($_POST['phone']) : '';
        $faculty = isset($_POST['faculty']) ? $database->sanitize($_POST['faculty']) : '';
        $department = isset($_POST['department']) ? $database->sanitize($_POST['department']) : '';
        $address = isset($_POST['address']) ? $database->sanitize($_POST['address']) : '';
        
        // ตรวจสอบว่ามีข้อมูลที่จำเป็นครบหรือไม่
        if (empty($firstname) || empty($lastname) || empty($email) || empty($faculty)) {
            throw new Exception("กรุณากรอกข้อมูลสำคัญให้ครบถ้วน");
        }
        
        // เตรียมคำสั่ง SQL สำหรับอัพเดทข้อมูล
        $update_query = "UPDATE students SET 
                            firstname = :firstname, 
                            lastname = :lastname, 
                            email = :email, 
                            phone = :phone, 
                            faculty = :faculty, 
                            department = :department, 
                            address = :address, 
                            updated_at = NOW() 
                        WHERE id = :id";
        
        $update_stmt = $db->prepare($update_query);
        
        // Bind parameters
        $update_stmt->bindParam(':firstname', $firstname);
        $update_stmt->bindParam(':lastname', $lastname);
        $update_stmt->bindParam(':email', $email);
        $update_stmt->bindParam(':phone', $phone);
        $update_stmt->bindParam(':faculty', $faculty);
        $update_stmt->bindParam(':department', $department);
        $update_stmt->bindParam(':address', $address);
        $update_stmt->bindParam(':id', $id);
        
        // Execute
        if ($update_stmt->execute()) {
            // เก็บข้อมูลใหม่หลังการแก้ไข
            $new_data = [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email,
                'phone' => $phone,
                'faculty' => $faculty,
                'department' => $department,
                'address' => $address
            ];
            $new_data_json = json_encode($new_data);
            
            // บันทึก log
            $log_action = "แก้ไขข้อมูลนักศึกษา: " . $student['student_id'] . " - " . $firstname . " " . $lastname;
            $log_query = "INSERT INTO admin_logs (admin_id, action, old_data, new_data, created_at) VALUES (:admin_id, :action, :old_data, :new_data, NOW())";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':admin_id', $_SESSION['admin_id']);
            $log_stmt->bindParam(':action', $log_action);
            $log_stmt->bindParam(':old_data', $old_data);
            $log_stmt->bindParam(':new_data', $new_data_json);
            $log_stmt->execute();
            
            // อัพเดตข้อมูลในตัวแปร $student
            $student['firstname'] = $firstname;
            $student['lastname'] = $lastname;
            $student['email'] = $email;
            $student['phone'] = $phone;
            $student['faculty'] = $faculty;
            $student['department'] = $department;
            $student['address'] = $address;
            
            $alert_type = 'success';
            $alert_message = 'อัพเดตข้อมูลสำเร็จ!';
            $show_alert = true;
            $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'];
        } else {
            throw new Exception("ไม่สามารถอัพเดตข้อมูลได้");
        }
        
    } catch(Exception $e) {
        $alert_type = 'error';
        $alert_message = $e->getMessage();
        $show_alert = true;
        $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'];
    }
}

// หากมีการ POST รีเซ็ตรหัสบัตรประชาชน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_id_card'])) {
    try {
        $new_id_card = isset($_POST['new_id_card']) ? $database->sanitize($_POST['new_id_card']) : '';
        
        if (empty($new_id_card)) {
            throw new Exception("กรุณากรอกรหัสบัตรประชาชนใหม่");
        }
        
        // ตรวจสอบรูปแบบรหัสบัตรประชาชน
        if (!preg_match('/^[0-9]{13}$/', $new_id_card)) {
            throw new Exception("รหัสบัตรประชาชนต้องเป็นตัวเลข 13 หลัก");
        }
        
        // ตรวจสอบว่ารหัสบัตรประชาชนซ้ำกับคนอื่นหรือไม่
        $check_id_query = "SELECT COUNT(*) as count FROM students WHERE id_card = :id_card AND id != :id";
        $check_id_stmt = $db->prepare($check_id_query);
        $check_id_stmt->bindParam(':id_card', $new_id_card);
        $check_id_stmt->bindParam(':id', $id);
        $check_id_stmt->execute();
        
        if ($check_id_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            throw new Exception("รหัสบัตรประชาชนนี้มีผู้ใช้งานแล้ว");
        }
        
        // เข้ารหัสรหัสบัตรประชาชนใหม่
        $password_hash = password_hash($new_id_card, PASSWORD_DEFAULT);
        
        // อัพเดตรหัสบัตรประชาชนและ password hash
        $update_query = "UPDATE students SET id_card = :id_card, password_hash = :password_hash, first_login = 1 WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id_card', $new_id_card);
        $update_stmt->bindParam(':password_hash', $password_hash);
        $update_stmt->bindParam(':id', $id);
        
        if ($update_stmt->execute()) {
            // บันทึก log
            $log_action = "รีเซ็ตรหัสบัตรประชาชน: " . $student['student_id'] . " (เข้ารหัสใหม่)";
            $log_query = "INSERT INTO admin_logs (admin_id, action, created_at) VALUES (:admin_id, :action, NOW())";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':admin_id', $_SESSION['admin_id']);
            $log_stmt->bindParam(':action', $log_action);
            $log_stmt->execute();
            
            // อัพเดตข้อมูลในตัวแปร $student
            $student['id_card'] = $new_id_card;
            $student['password_hash'] = $password_hash;
            $student['first_login'] = 1;
            
            $alert_type = 'success';
            $alert_message = 'รีเซ็ตรหัสบัตรประชาชนสำเร็จ! รหัสผ่านได้ถูกเข้ารหัสแล้ว';
            $show_alert = true;
            $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'] . '#security';
        } else {
            throw new Exception("ไม่สามารถรีเซ็ตรหัสบัตรประชาชนได้");
        }
    } catch(Exception $e) {
        $alert_type = 'error';
        $alert_message = $e->getMessage();
        $show_alert = true;
        $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'] . '#security';
    }
}

// หากมีการ POST ลบการเชื่อมต่อ Google
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unlink_google'])) {
    try {
        // อัพเดตข้อมูล Google ID
        $update_query = "UPDATE students SET google_id = NULL WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $id);
        
        if ($update_stmt->execute()) {
            // บันทึก log
            $log_action = "ยกเลิกการเชื่อมต่อ Google: " . $student['student_id'];
            $log_query = "INSERT INTO admin_logs (admin_id, action, created_at) VALUES (:admin_id, :action, NOW())";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':admin_id', $_SESSION['admin_id']);
            $log_stmt->bindParam(':action', $log_action);
            $log_stmt->execute();
            
            // อัพเดตข้อมูลในตัวแปร $student
            $student['google_id'] = NULL;
            
            $alert_type = 'success';
            $alert_message = 'ยกเลิกการเชื่อมต่อ Google สำเร็จ!';
            $show_alert = true;
            $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'] . '#security';
        } else {
            throw new Exception("ไม่สามารถยกเลิกการเชื่อมต่อ Google ได้");
        }
    } catch(Exception $e) {
        $alert_type = 'error';
        $alert_message = $e->getMessage();
        $show_alert = true;
        $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'] . '#security';
    }
}
?>

<!-- เพิ่ม SweetAlert2 CSS และ JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>แก้ไขข้อมูลนักศึกษา</h2>
        <p>รหัสนักศึกษา: <strong><?php echo $student['student_id']; ?></strong></p>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=admin_users" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>กลับไปยังรายการผู้ใช้งาน
        </a>
    </div>
</div>

<!-- แท็บสำหรับแบ่งส่วนการแก้ไข -->
<ul class="nav nav-tabs mb-4" id="editTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">
            <i class="fas fa-user me-2"></i>ข้อมูลทั่วไป
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
            <i class="fas fa-shield-alt me-2"></i>ความปลอดภัย
        </button>
    </li>
</ul>

<!-- เนื้อหาของแต่ละแท็บ -->
<div class="tab-content" id="editTabsContent">
    <!-- แท็บข้อมูลทั่วไป -->
    <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-edit me-2"></i>แก้ไขข้อมูลส่วนตัว
                </h5>
            </div>
            <div class="card-body">
                <form action="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>" method="post" id="profileForm">
                    <div class="row">
                        <!-- ข้อมูลหลัก -->
                        <div class="col-md-6">
                            <h5 class="text-primary"><i class="fas fa-user me-2"></i>ข้อมูลหลัก</h5>
                            <hr>
                            
                            <div class="mb-3">
                                <label for="student_id" class="form-label">รหัสนักศึกษา</label>
                                <input type="text" class="form-control" id="student_id" value="<?php echo $student['student_id']; ?>" readonly>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="firstname" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo $student['firstname']; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastname" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo $student['lastname']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $student['email']; ?>" required>
                                <div class="form-text">ควรเป็นอีเมลมหาวิทยาลัยเพื่อใช้ในการเชื่อมต่อกับ Google</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $student['phone']; ?>" pattern="[0-9\-\+\s\(\)]{10,15}">
                                <div class="form-text">รูปแบบ: 0812345678 หรือ 081-234-5678</div>
                            </div>
                        </div>
                        
                        <!-- ข้อมูลเพิ่มเติม -->
                        <div class="col-md-6">
                            <h5 class="text-primary"><i class="fas fa-graduation-cap me-2"></i>ข้อมูลการศึกษา</h5>
                            <hr>
                            
                            <div class="mb-3">
                                <label for="faculty" class="form-label">คณะ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="faculty" name="faculty" value="<?php echo $student['faculty']; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="department" class="form-label">สาขา</label>
                                <input type="text" class="form-control" id="department" name="department" value="<?php echo $student['department']; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">ที่อยู่</label>
                                <textarea class="form-control" id="address" name="address" rows="4"><?php echo $student['address']; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">สถานะการเชื่อมต่อ Google</label>
                                <div>
                                    <?php if(!empty($student['google_id'])): ?>
                                        <span class="badge bg-success fs-6">
                                            <i class="fab fa-google me-1"></i>เชื่อมต่อแล้ว
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary fs-6">
                                            <i class="fas fa-times me-1"></i>ยังไม่เชื่อมต่อ
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-save me-2"></i>บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- แท็บความปลอดภัย -->
    <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
        <!-- รีเซ็ตรหัสบัตรประชาชน -->
        <div class="card shadow mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">
                    <i class="fas fa-key me-2"></i>รีเซ็ตรหัสบัตรประชาชน
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>หมายเหตุ:</strong> การรีเซ็ตรหัสบัตรประชาชนจะทำให้นักศึกษาต้องใช้รหัสใหม่ในการเข้าสู่ระบบครั้งถัดไป รหัสผ่านจะถูกเข้ารหัสเพื่อความปลอดภัย
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><strong>สถานะรหัสผ่าน:</strong></label>
                    <div>
                        <?php if(!empty($student['password_hash'])): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-shield-alt me-1"></i>เข้ารหัสแล้ว
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-exclamation-triangle me-1"></i>ยังไม่เข้ารหัส (ข้อมูลเก่า)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <form action="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>" method="post" id="resetPasswordForm">
                    <div class="mb-3">
                        <label for="new_id_card" class="form-label">รหัสบัตรประชาชนใหม่ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new_id_card" name="new_id_card" required maxlength="13" pattern="[0-9]{13}">
                        <div class="form-text">
                            <i class="fas fa-shield-alt me-1"></i>กรุณากรอกรหัสบัตรประชาชน 13 หลัก (ตัวเลขเท่านั้น) รหัสจะถูกเข้ารหัสอัตโนมัติ
                        </div>
                    </div>
                    
                    <input type="hidden" name="reset_id_card" value="1">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key me-2"></i>รีเซ็ตรหัสบัตรประชาชน
                    </button>
                </form>
            </div>
        </div>
        
        <!-- ยกเลิกการเชื่อมต่อ Google -->
        <div class="card shadow">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-unlink me-2"></i>ยกเลิกการเชื่อมต่อ Google
                </h5>
            </div>
            <div class="card-body">
                <?php if(!empty($student['google_id'])): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>คำเตือน:</strong> การยกเลิกการเชื่อมต่อ Google จะทำให้นักศึกษาไม่สามารถใช้ Google Sign-In ในการเข้าสู่ระบบได้อีกต่อไป
                    </div>
                    
                    <p class="mb-3">
                        <strong>สถานะปัจจุบัน:</strong> 
                        <span class="badge bg-success fs-6">
                            <i class="fab fa-google me-1"></i>เชื่อมต่อกับ Google แล้ว
                        </span>
                    </p>
                    
                    <form action="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>" method="post" id="unlinkGoogleForm">
                        <input type="hidden" name="unlink_google" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-unlink me-2"></i>ยกเลิกการเชื่อมต่อ Google
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        นักศึกษายังไม่ได้เชื่อมต่อบัญชี Google กับระบบ
                    </div>
                    <p class="text-muted">ไม่มีการเชื่อมต่อ Google ที่ต้องยกเลิก</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// แสดง SweetAlert เมื่อมีการแจ้งเตือน
<?php if ($show_alert): ?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?php echo $alert_type; ?>',
        title: '<?php echo $alert_type == "success" ? "สำเร็จ!" : "เกิดข้อผิดพลาด!"; ?>',
        text: '<?php echo $alert_message; ?>',
        confirmButtonText: 'ตกลง',
        timer: <?php echo $alert_type == "success" ? "3000" : "0"; ?>,
        timerProgressBar: <?php echo $alert_type == "success" ? "true" : "false"; ?>
    }).then((result) => {
        <?php if (!empty($redirect_url)): ?>
        if (result.isConfirmed || result.dismiss === Swal.DismissReason.timer) {
            window.location.href = '<?php echo $redirect_url; ?>';
        }
        <?php endif; ?>
    });
});
<?php endif; ?>

// เปิดแท็บตามที่ระบุใน URL
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash) {
        const hash = window.location.hash.substring(1);
        const tabEl = document.querySelector(`#editTabs button[data-bs-target="#${hash}"]`);
        if (tabEl) {
            const tab = new bootstrap.Tab(tabEl);
            tab.show();
        }
    }
});

// ยืนยันการบันทึกข้อมูลทั่วไป
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // ตรวจสอบข้อมูลก่อนส่ง
    if (!validateProfileForm()) {
        return;
    }
    
    Swal.fire({
        title: 'ยืนยันการบันทึก',
        text: 'คุณต้องการบันทึกการเปลี่ยนแปลงข้อมูลหรือไม่?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: '<i class="fas fa-save me-2"></i>บันทึก',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            // แสดง loading
            Swal.fire({
                title: 'กำลังบันทึกข้อมูล...',
                text: 'กรุณารอสักครู่',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // ส่งข้อมูล
            this.submit();
        }
    });
});

// ยืนยันการรีเซ็ตรหัสบัตรประชาชน
document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const newIdCard = document.getElementById('new_id_card').value;
    
    if (!validateIdCard(newIdCard)) {
        return;
    }
    
    Swal.fire({
        title: 'ยืนยันการรีเซ็ตรหัสผ่าน',
        html: `คุณต้องการรีเซ็ตรหัสบัตรประชาชนเป็น<br><strong>${newIdCard}</strong><br>หรือไม่?<br><br><small class="text-warning"><i class="fas fa-shield-alt me-1"></i><strong>หมายเหตุ:</strong> รหัสผ่านจะถูกเข้ารหัสเพื่อความปลอดภัย</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-key me-2"></i>รีเซ็ต',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            // แสดง loading
            Swal.fire({
                title: 'กำลังรีเซ็ตรหัสผ่าน...',
                text: 'กรุณารอสักครู่ ระบบกำลังเข้ารหัสรหัสผ่านใหม่',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // ส่งข้อมูล
            this.submit();
        }
    });
});

// ยืนยันการยกเลิกการเชื่อมต่อ Google
<?php if(!empty($student['google_id'])): ?>
document.getElementById('unlinkGoogleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    Swal.fire({
        title: 'ยืนยันการยกเลิกการเชื่อมต่อ',
        text: 'คุณต้องการยกเลิกการเชื่อมต่อบัญชี Google หรือไม่?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-unlink me-2"></i>ยกเลิกการเชื่อมต่อ',
        cancelButtonText: 'ไม่ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            // แสดง loading
            Swal.fire({
                title: 'กำลังยกเลิกการเชื่อมต่อ...',
                text: 'กรุณารอสักครู่',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // ส่งข้อมูล
            this.submit();
        }
    });
});
<?php endif; ?>

// ฟังก์ชันตรวจสอบข้อมูลในฟอร์มหลัก
function validateProfileForm() {
    const firstname = document.getElementById('firstname').value.trim();
    const lastname = document.getElementById('lastname').value.trim();
    const email = document.getElementById('email').value.trim();
    const faculty = document.getElementById('faculty').value.trim();
    const phone = document.getElementById('phone').value.trim();
    
    // ตรวจสอบช่องที่จำเป็น
    if (!firstname || !lastname || !email || !faculty) {
        Swal.fire({
            icon: 'error',
            title: 'ข้อมูลไม่ครบถ้วน',
            text: 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน'
        });
        return false;
    }
    
    // ตรวจสอบรูปแบบอีเมล
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        Swal.fire({
            icon: 'error',
            title: 'รูปแบบอีเมลไม่ถูกต้อง',
            text: 'กรุณากรอกอีเมลให้ถูกต้อง'
        });
        return false;
    }
    
    // ตรวจสอบเบอร์โทรศัพท์ (ถ้ามีการกรอก)
    if (phone && !validatePhoneNumber(phone)) {
        Swal.fire({
            icon: 'error',
            title: 'รูปแบบเบอร์โทรไม่ถูกต้อง',
            text: 'กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (10-15 หลัก)'
        });
        return false;
    }
    
    return true;
}

// ฟังก์ชันตรวจสอบเบอร์โทรศัพท์
function validatePhoneNumber(phone) {
    const phoneRegex = /^[0-9\-\+\s\(\)]{10,15}$/;
    return phoneRegex.test(phone);
}

// ฟังก์ชันตรวจสอบรหัสบัตรประชาชน
function validateIdCard(idCard) {
    if (!idCard) {
        Swal.fire({
            icon: 'error',
            title: 'กรุณากรอกรหัสบัตรประชาชน',
            text: 'รหัสบัตรประชาชนเป็นข้อมูลที่จำเป็น'
        });
        return false;
    }
    
    if (!/^[0-9]{13}$/.test(idCard)) {
        Swal.fire({
            icon: 'error',
            title: 'รูปแบบรหัสบัตรประชาชนไม่ถูกต้อง',
            text: 'รหัสบัตรประชาชนต้องเป็นตัวเลข 13 หลักเท่านั้น'
        });
        return false;
    }
    
    return true;
}

// เพิ่ม event listener สำหรับ validation แบบ real-time
document.getElementById('email').addEventListener('blur', function() {
    const email = this.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
        this.classList.add('is-invalid');
        
        // แสดง toast เตือน
        Swal.fire({
            icon: 'warning',
            title: 'รูปแบบอีเมลไม่ถูกต้อง',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    } else {
        this.classList.remove('is-invalid');
        if (email) this.classList.add('is-valid');
    }
});

document.getElementById('phone').addEventListener('blur', function() {
    const phone = this.value.trim();
    
    if (phone && !validatePhoneNumber(phone)) {
        this.classList.add('is-invalid');
        
        // แสดง toast เตือน
        Swal.fire({
            icon: 'warning',
            title: 'รูปแบบเบอร์โทรไม่ถูกต้อง',
            text: 'ตัวอย่าง: 0812345678 หรือ 081-234-5678',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    } else {
        this.classList.remove('is-invalid');
        if (phone) this.classList.add('is-valid');
    }
});

document.getElementById('new_id_card').addEventListener('input', function() {
    // ให้กรอกเฉพาะตัวเลข
    this.value = this.value.replace(/[^0-9]/g, '');
    
    // จำกัดที่ 13 หลัก
    if (this.value.length > 13) {
        this.value = this.value.substring(0, 13);
    }
});

document.getElementById('new_id_card').addEventListener('blur', function() {
    const idCard = this.value;
    
    if (idCard && idCard.length !== 13) {
        this.classList.add('is-invalid');
        
        // แสดง toast เตือน
        Swal.fire({
            icon: 'warning',
            title: 'รหัสบัตรประชาชนไม่ครบ',
            text: 'กรุณากรอกรหัสบัตรประชาชน 13 หลัก',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    } else {
        this.classList.remove('is-invalid');
        if (idCard) this.classList.add('is-valid');
    }
});

// เพิ่ม animation สำหรับปุ่มต่างๆ
document.querySelectorAll('.btn').forEach(button => {
    button.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
    });
    
    button.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = 'none';
    });
});

// Auto-save draft (ถ้าต้องการ)
let autoSaveTimer;
function autoSaveDraft() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(() => {
        // บันทึก draft ลง localStorage
        const formData = {
            firstname: document.getElementById('firstname').value,
            lastname: document.getElementById('lastname').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value,
            faculty: document.getElementById('faculty').value,
            department: document.getElementById('department').value,
            address: document.getElementById('address').value
        };
        
        localStorage.setItem('editUserDraft_<?php echo $id; ?>', JSON.stringify(formData));
    }, 2000);
}

// เพิ่ม event listener สำหรับ auto-save
document.querySelectorAll('#profileForm input, #profileForm textarea').forEach(input => {
    input.addEventListener('input', autoSaveDraft);
});

// โหลด draft เมื่อหน้าเว็บโหลดเสร็จ (ถ้ามี)
document.addEventListener('DOMContentLoaded', function() {
    const savedDraft = localStorage.getItem('editUserDraft_<?php echo $id; ?>');
    if (savedDraft) {
        const draftData = JSON.parse(savedDraft);
        
        // ถามว่าต้องการโหลด draft หรือไม่
        Swal.fire({
            title: 'พบข้อมูลที่บันทึกไว้',
            text: 'คุณต้องการใช้ข้อมูลที่บันทึกไว้ล่าสุดหรือไม่?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ใช้ข้อมูลที่บันทึกไว้',
            cancelButtonText: 'ใช้ข้อมูลปัจจุบัน'
        }).then((result) => {
            if (result.isConfirmed) {
                // โหลดข้อมูลจาก draft
                Object.keys(draftData).forEach(key => {
                    const element = document.getElementById(key);
                    if (element) {
                        element.value = draftData[key];
                    }
                });
                
                Swal.fire({
                    icon: 'success',
                    title: 'โหลดข้อมูลสำเร็จ',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                // ลบ draft
                localStorage.removeItem('editUserDraft_<?php echo $id; ?>');
            }
        });
    }
});

// ลบ draft เมื่อบันทึกสำเร็จ
<?php if ($show_alert && $alert_type == 'success'): ?>
localStorage.removeItem('editUserDraft_<?php echo $id; ?>');
<?php endif; ?>

// เพิ่มฟังก์ชัน confirm ก่อนออกจากหน้า (ถ้ามีการแก้ไขข้อมูล)
let isFormChanged = false;
document.querySelectorAll('#profileForm input, #profileForm textarea').forEach(input => {
    input.addEventListener('input', function() {
        isFormChanged = true;
    });
});

window.addEventListener('beforeunload', function(e) {
    if (isFormChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// รีเซ็ตสถานะเมื่อส่งฟอร์ม
document.getElementById('profileForm').addEventListener('submit', function() {
    isFormChanged = false;
});

// เพิ่มการ validate real-time สำหรับช่องที่จำเป็น
document.querySelectorAll('input[required]').forEach(input => {
    input.addEventListener('blur', function() {
        if (!this.value.trim()) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        }
    });
});

// เพิ่มฟังก์ชันสำหรับการ format เบอร์โทรศัพท์
document.getElementById('phone').addEventListener('input', function() {
    let value = this.value.replace(/[^0-9]/g, '');
    
    // Format เบอร์โทรศัพท์ (XXX-XXX-XXXX)
    if (value.length >= 6) {
        value = value.substring(0, 3) + '-' + value.substring(3, 6) + '-' + value.substring(6, 10);
    } else if (value.length >= 3) {
        value = value.substring(0, 3) + '-' + value.substring(3);
    }
    
    this.value = value;
});

// เพิ่มฟังก์ชันแสดงสถานะความปลอดภัยของรหัสผ่าน
function checkPasswordSecurity() {
    const hasPasswordHash = <?php echo !empty($student['password_hash']) ? 'true' : 'false'; ?>;
    
    if (!hasPasswordHash) {
        // แสดงแจ้งเตือนให้อัพเดตรหัสผ่าน
        const warningBadge = document.querySelector('.badge.bg-warning');
        if (warningBadge) {
            warningBadge.innerHTML += ' <button type="button" class="btn btn-sm btn-outline-warning ms-2" onclick="showPasswordUpdateRecommendation()">แนะนำ</button>';
        }
    }
}

function showPasswordUpdateRecommendation() {
    Swal.fire({
        title: 'แนะนำการปรับปรุงความปลอดภัย',
        html: `
            <div class="text-start">
                <p>ระบบตรวจพบว่านักศึกษาคนนี้ยังใช้รหัสผ่านแบบเก่า (ไม่เข้ารหัส)</p>
                <p><strong>แนะนำ:</strong></p>
                <ul>
                    <li>รีเซ็ตรหัสบัตรประชาชนเพื่อเข้ารหัสรหัสผ่าน</li>
                    <li>หรือรอให้นักศึกษาล็อกอินครั้งถัดไปเพื่อให้ระบบอัพเดตอัตโนมัติ</li>
                </ul>
                <div class="alert alert-info">
                    <small><i class="fas fa-info-circle me-1"></i>การรีเซ็ตจะทำให้รหัสผ่านถูกเข้ารหัสทันที</small>
                </div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'รีเซ็ตรหัสผ่านเลย',
        cancelButtonText: 'ปิด'
    }).then((result) => {
        if (result.isConfirmed) {
            // เปิดแท็บความปลอดภัยและ focus ที่ช่องรหัสบัตรประชาชน
            const securityTab = new bootstrap.Tab(document.querySelector('#security-tab'));
            securityTab.show();
            setTimeout(() => {
                document.getElementById('new_id_card').focus();
            }, 300);
        }
    });
}

// เรียกใช้ฟังก์ชันตรวจสอบความปลอดภัย
document.addEventListener('DOMContentLoaded', function() {
    checkPasswordSecurity();
});
</script>

<style>
/* เพิ่ม custom style สำหรับ SweetAlert */
.swal2-popup {
    font-family: 'Sarabun', sans-serif;
}

.swal2-title {
    font-size: 1.5rem;
    font-weight: 600;
}

.swal2-content {
    font-size: 1rem;
}

/* Animation สำหรับปุ่ม */
.btn {
    transition: all 0.3s ease;
}

/* Style สำหรับ form validation */
.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.is-valid {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

/* เพิ่ม style สำหรับ card headers */
.card-header h5 {
    font-weight: 600;
}

/* Style สำหรับ badges */
.badge.fs-6 {
    font-size: 0.9rem !important;
}

/* เพิ่ม hover effect สำหรับ tabs */
.nav-tabs .nav-link {
    transition: all 0.3s ease;
}

.nav-tabs .nav-link:hover {
    background-color: rgba(0, 123, 255, 0.1);
    border-color: transparent;
}

/* Style สำหรับ required fields */
.form-label .text-danger {
    font-weight: bold;
}

/* เพิ่ม style สำหรับ loading state */
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Style สำหรับ alert boxes */
.alert {
    border-left: 4px solid;
}

.alert-info {
    border-left-color: #17a2b8;
}

.alert-warning {
    border-left-color: #ffc107;
}

/* เพิ่ม responsive design */
@media (max-width: 768px) {
    .col-md-6 {
        margin-bottom: 1rem;
    }
    
    .btn-lg {
        width: 100%;
    }
    
    .text-end {
        text-align: center !important;
    }
}

/* Style สำหรับ form sections */
.card-body h5 {
    color: #495057;
    margin-bottom: 1rem;
}

.card-body hr {
    margin: 1rem 0;
    border-color: #dee2e6;
}

/* เพิ่ม style สำหรับ input focus */
.form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Style สำหรับ readonly inputs */
.form-control[readonly] {
    background-color: #f8f9fa;
    opacity: 1;
}

/* เพิ่ม style สำหรับ security status */
.badge.bg-warning {
    position: relative;
}

.badge.bg-success {
    background-color: #198754 !important;
}

/* เพิ่ม animation สำหรับการโหลด */
@keyframes pulse {
    0% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
    100% {
        opacity: 1;
    }
}

.loading {
    animation: pulse 1.5s ease-in-out infinite;
}

/* Style สำหรับ changed fields */
.field-changed {
    background-color: #fff3cd !important;
    border-color: #ffc107 !important;
}

/* เพิ่ม style สำหรับ success animation */
@keyframes checkmark {
    0% {
        stroke-dashoffset: 100;
    }
    100% {
        stroke-dashoffset: 0;
    }
}

.checkmark {
    stroke-dasharray: 100;
    animation: checkmark 0.6s ease-in-out;
}
</style>