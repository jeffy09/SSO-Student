<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// รวมไฟล์ configuration ของ Google
include_once 'config/google_config.php';

// ตรวจสอบว่ามี session ของอาจารย์หรือไม่
if (!isset($_SESSION['teacher_user_id'])) {
    header("Location: index.php?page=teacher_login");
    exit;
}

$alert_type = '';
$alert_message = '';
$show_alert = false;

// ดึงข้อมูลอาจารย์
try {
    $query = "SELECT * FROM teachers WHERE id = :id LIMIT 0,1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_SESSION['teacher_user_id']);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        header("Location: index.php?page=logout"); // ไม่พบข้อมูล, logout
        exit;
    }
} catch (PDOException $e) {
    $alert_type = 'error';
    $alert_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    $show_alert = true;
    $teacher = []; // Set $teacher to empty array to avoid errors in form display
}

// หากมีการ submit form แก้ไขข้อมูล (ไม่รวมการเปลี่ยนรหัสผ่าน หรือ Google unlink ในตัวอย่างนี้)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_teacher_profile'])) {
    try {
        $phone = isset($_POST['phone']) ? $database->sanitize($_POST['phone']) : '';
        // เพิ่มฟิลด์อื่นๆ ที่อาจารย์สามารถแก้ไขได้ เช่น department, position
        $department_form = isset($_POST['department']) ? $database->sanitize($_POST['department']) : '';
        $position_form = isset($_POST['position']) ? $database->sanitize($_POST['position']) : '';

        $update_query = "UPDATE teachers SET phone = :phone, department = :department, position = :position, updated_at = NOW() WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':phone', $phone);
        $update_stmt->bindParam(':department', $department_form);
        $update_stmt->bindParam(':position', $position_form);
        $update_stmt->bindParam(':id', $_SESSION['teacher_user_id']);

        if ($update_stmt->execute()) {
            $alert_type = 'success';
            $alert_message = 'อัพเดตข้อมูลสำเร็จ!';
            $show_alert = true;
            // อัพเดตข้อมูลในตัวแปร $teacher
            $teacher['phone'] = $phone;
            $teacher['department'] = $department_form;
            $teacher['position'] = $position_form;
        } else {
            $alert_type = 'error';
            $alert_message = 'ไม่สามารถอัพเดตข้อมูลได้';
            $show_alert = true;
        }
    } catch (PDOException $e) {
        $alert_type = 'error';
        $alert_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        $show_alert = true;
    }
}

// ตรวจสอบการเชื่อมต่อ Google สำเร็จ
if (isset($_GET['google_connect']) && $_GET['google_connect'] == 'success') {
    if(isset($_SESSION['google_connect_success'])) {
        $alert_type = 'success';
        $alert_message = $_SESSION['google_connect_success'];
        unset($_SESSION['google_connect_success']);
    } else {
        $alert_type = 'success';
        $alert_message = 'เชื่อมต่อบัญชี Google สำเร็จ!';
    }
    $show_alert = true;
    // รีเฟรชข้อมูล teacher เพื่อดึง google_id ล่าสุด
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (isset($_GET['google_connect']) && $_GET['google_connect'] == 'error') {
     if(isset($_SESSION['google_connect_error'])) {
        $alert_type = 'error';
        $alert_message = $_SESSION['google_connect_error'];
        unset($_SESSION['google_connect_error']);
    } else {
        $alert_type = 'error';
        $alert_message = 'ไม่สามารถเชื่อมต่อบัญชี Google ได้ กรุณาลองใหม่';
    }
    $show_alert = true;
}


// Logic สำหรับ unlink Google (คล้าย admin/profile.php)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unlink_google_teacher'])) {
    try {
        $unlink_query = "UPDATE teachers SET google_id = NULL, access_token = NULL, refresh_token = NULL, token_expires_at = NULL WHERE id = :id";
        $unlink_stmt = $db->prepare($unlink_query);
        $unlink_stmt->bindParam(':id', $_SESSION['teacher_user_id']);
        
        if ($unlink_stmt->execute()) {
            $teacher['google_id'] = NULL; // Update local variable
            $teacher['access_token'] = NULL;
            // Log action (optional)
            // saveAdminLog($db, $_SESSION['teacher_user_id'], "Teacher unlinked Google: " . $teacher['teacher_id']);
            $alert_type = 'success';
            $alert_message = "ยกเลิกการเชื่อมต่อ Google สำเร็จ";
            $show_alert = true;
        } else {
            $alert_type = 'error';
            $alert_message = "ไม่สามารถยกเลิกการเชื่อมต่อ Google ได้";
            $show_alert = true;
        }
    } catch(PDOException $e) {
        $alert_type = 'error';
        $alert_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $show_alert = true;
    }
}

?>
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="row">
    <div class="col-md-12 mb-4">
        <h2>โปรไฟล์อาจารย์</h2>
        <p>จัดการข้อมูลส่วนตัวและบัญชีของคุณ</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="fas fa-user-edit me-2"></i>ข้อมูลส่วนตัว</h5>
            </div>
            <div class="card-body">
                <form action="?page=teacher_profile" method="post" id="teacherProfileForm">
                    <input type="hidden" name="update_teacher_profile" value="1">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="teacher_id" class="form-label">รหัสอาจารย์</label>
                            <input type="text" class="form-control" id="teacher_id" value="<?php echo htmlspecialchars($teacher['teacher_id'] ?? ''); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">รหัสบัตรประชาชน</label>
                            <input type="text" class="form-control" value="<?php echo isset($teacher['id_card']) ? substr($teacher['id_card'], 0, 4) . 'XXXXXXXXX' : ''; ?>" readonly>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="firstname" class="form-label">ชื่อ</label>
                            <input type="text" class="form-control" id="firstname" value="<?php echo htmlspecialchars($teacher['firstname'] ?? ''); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="lastname" class="form-label">นามสกุล</label>
                            <input type="text" class="form-control" id="lastname" value="<?php echo htmlspecialchars($teacher['lastname'] ?? ''); ?>" readonly>
                        </div>
                    </div>
                     <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">อีเมล</label>
                            <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($teacher['email'] ?? ''); ?>" readonly>
                             <div class="form-text">อีเมลใช้สำหรับการเชื่อมต่อ Google</div>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>">
                        </div>
                    </div>
                     <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="department" class="form-label">ภาควิชา/แผนก</label>
                            <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($teacher['department'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="position" class="form-label">ตำแหน่ง</label>
                            <input type="text" class="form-control" id="position" name="position" value="<?php echo htmlspecialchars($teacher['position'] ?? ''); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>บันทึกข้อมูล</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0"><i class="fab fa-google me-2"></i>การเชื่อมต่อ Google</h5>
            </div>
            <div class="card-body">
                <?php if(empty($teacher['google_id'])): ?>
                    <div class="alert alert-warning alert-permanent">คุณยังไม่ได้เชื่อมต่อบัญชี Google</div>
                    <p>เชื่อมต่อกับบัญชี Google ของคุณเพื่อใช้ในการลงชื่อเข้าใช้และเข้าถึงบริการอื่นๆ ของ Google</p>
                    <button type="button" class="btn btn-danger w-100" onclick="confirmTeacherGoogleConnect()">
                        <i class="fab fa-google me-2"></i>เชื่อมต่อบัญชี Google
                    </button>
                <?php else: ?>
                    <div class="alert alert-success alert-permanent"><i class="fas fa-check-circle"></i> เชื่อมต่อบัญชี Google แล้ว</div>
                    <p>คุณสามารถใช้ Google Sign-In ในครั้งต่อไป</p>
                     <form action="?page=teacher_profile" method="post" id="unlinkGoogleTeacherForm">
                        <input type="hidden" name="unlink_google_teacher" value="1">
                        <button type="button" class="btn btn-outline-danger w-100" onclick="confirmUnlinkGoogleTeacher()">
                            <i class="fas fa-unlink me-2"></i>ยกเลิกการเชื่อมต่อ Google
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
<?php if ($show_alert): ?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?php echo $alert_type; ?>',
        title: '<?php echo ($alert_type == "success" ? "สำเร็จ!" : ($alert_type == "warning" ? "คำเตือน" : "เกิดข้อผิดพลาด!")); ?>',
        text: '<?php echo addslashes($alert_message); ?>',
        confirmButtonText: 'ตกลง'
    });
});
<?php endif; ?>

document.getElementById('teacherProfileForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'ยืนยันการบันทึก',
        text: 'คุณต้องการบันทึกข้อมูลโปรไฟล์หรือไม่?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'บันทึก',
        cancelButtonText: 'ยกเลิก'
    }).then(result => { if (result.isConfirmed) {this.submit();} });
});

function confirmTeacherGoogleConnect() {
    Swal.fire({
        title: 'เชื่อมต่อบัญชี Google',
        html: 'คุณต้องการเชื่อมต่อบัญชี Google กับระบบหรือไม่?<br><small>อีเมล Google ของคุณต้องตรงกับอีเมลในโปรไฟล์ (<?php echo htmlspecialchars($teacher['email'] ?? ''); ?>)</small>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: '<i class="fab fa-google me-2"></i>เชื่อมต่อ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            // เพิ่ม from_profile=1 เพื่อให้ google_callback รู้ว่ามาจากหน้า profile
            window.location.href = '?page=google_login&user_type=teacher&from_profile=1';
        }
    });
}

function confirmUnlinkGoogleTeacher() {
    Swal.fire({
        title: 'ยืนยันการยกเลิก',
        text: 'คุณแน่ใจหรือไม่ที่จะยกเลิกการเชื่อมต่อบัญชี Google?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'ใช่, ยกเลิก',
        cancelButtonText: 'ไม่'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('unlinkGoogleTeacherForm').submit();
        }
    });
}
</script>