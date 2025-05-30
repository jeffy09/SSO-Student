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

// หากมีการ submit form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $user_type = isset($_POST['user_type']) ? $database->sanitize($_POST['user_type']) : '';
        
        // รับค่าจาก form (ฟิลด์ร่วม)
        $id_card = isset($_POST['id_card']) ? $database->sanitize($_POST['id_card']) : '';
        $firstname = isset($_POST['firstname']) ? $database->sanitize($_POST['firstname']) : '';
        $lastname = isset($_POST['lastname']) ? $database->sanitize($_POST['lastname']) : '';
        $email = isset($_POST['email']) ? $database->sanitize($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? $database->sanitize($_POST['phone']) : '';
        
        // ตรวจสอบว่ามีข้อมูลที่จำเป็นครบหรือไม่ (ฟิลด์ร่วม)
        if (empty($user_type) || empty($id_card) || empty($firstname) || empty($lastname) || empty($email)) {
            throw new Exception("กรุณากรอกข้อมูลสำคัญให้ครบถ้วน (*)");
        }

        $password_hash = password_hash($id_card, PASSWORD_DEFAULT);
        $log_action_user_id = '';

        if ($user_type === 'student') {
            $student_id = isset($_POST['student_id']) ? $database->sanitize($_POST['student_id']) : '';
            $faculty = isset($_POST['faculty']) ? $database->sanitize($_POST['faculty']) : '';
            $department_student = isset($_POST['department_student']) ? $database->sanitize($_POST['department_student']) : '';
            $address_student = isset($_POST['address_student']) ? $database->sanitize($_POST['address_student']) : '';

            if (empty($student_id) || empty($faculty)) {
                throw new Exception("กรุณากรอกข้อมูลนักศึกษาให้ครบถ้วน (*)");
            }

            $check_query = "SELECT COUNT(*) as count FROM students WHERE student_id = :student_id OR email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':student_id', $student_id);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                throw new Exception("รหัสนักศึกษาหรืออีเมลนี้มีอยู่ในระบบแล้ว (นักศึกษา)");
            }

            $insert_query = "INSERT INTO students (student_id, id_card, password_hash, firstname, lastname, email, phone, faculty, department, address, first_login, created_at) 
                             VALUES (:student_id, :id_card, :password_hash, :firstname, :lastname, :email, :phone, :faculty, :department, :address, 1, NOW())";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':student_id', $student_id);
            $insert_stmt->bindParam(':id_card', $id_card);
            $insert_stmt->bindParam(':password_hash', $password_hash);
            $insert_stmt->bindParam(':firstname', $firstname);
            $insert_stmt->bindParam(':lastname', $lastname);
            $insert_stmt->bindParam(':email', $email);
            $insert_stmt->bindParam(':phone', $phone);
            $insert_stmt->bindParam(':faculty', $faculty);
            $insert_stmt->bindParam(':department', $department_student);
            $insert_stmt->bindParam(':address', $address_student);
            $log_action_user_id = $student_id;

        } elseif ($user_type === 'teacher') {
            $teacher_id = isset($_POST['teacher_id']) ? $database->sanitize($_POST['teacher_id']) : '';
            $department_teacher = isset($_POST['department_teacher']) ? $database->sanitize($_POST['department_teacher']) : '';
            $position = isset($_POST['position']) ? $database->sanitize($_POST['position']) : '';

            if (empty($teacher_id)) {
                throw new Exception("กรุณากรอกข้อมูลอาจารย์ให้ครบถ้วน (*)");
            }
            
            $check_query = "SELECT COUNT(*) as count FROM teachers WHERE teacher_id = :teacher_id OR email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':teacher_id', $teacher_id);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                throw new Exception("รหัสอาจารย์หรืออีเมลนี้มีอยู่ในระบบแล้ว (อาจารย์)");
            }

            $insert_query = "INSERT INTO teachers (teacher_id, id_card, password_hash, firstname, lastname, email, phone, department, position, first_login, created_at) 
                             VALUES (:teacher_id, :id_card, :password_hash, :firstname, :lastname, :email, :phone, :department, :position, 1, NOW())";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':teacher_id', $teacher_id);
            $insert_stmt->bindParam(':id_card', $id_card);
            $insert_stmt->bindParam(':password_hash', $password_hash);
            $insert_stmt->bindParam(':firstname', $firstname);
            $insert_stmt->bindParam(':lastname', $lastname);
            $insert_stmt->bindParam(':email', $email);
            $insert_stmt->bindParam(':phone', $phone);
            $insert_stmt->bindParam(':department', $department_teacher);
            $insert_stmt->bindParam(':position', $position);
            $log_action_user_id = $teacher_id;
        } else {
            throw new Exception("ประเภทผู้ใช้ไม่ถูกต้อง");
        }
        
        if ($insert_stmt->execute()) {
            $user_db_id = $db->lastInsertId();
            $log_action = "เพิ่มผู้ใช้ใหม่ ({$user_type}): " . $log_action_user_id . " - " . $firstname . " " . $lastname;
            
            $log_query = "INSERT INTO admin_logs (admin_id, action, created_at) VALUES (:admin_id, :action, NOW())";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':admin_id', $_SESSION['admin_id']);
            $log_stmt->bindParam(':action', $log_action);
            $log_stmt->execute();
            
            $success = true;
            $message = "เพิ่มข้อมูลผู้ใช้ ({$user_type}) สำเร็จ";
        } else {
            throw new Exception("ไม่สามารถเพิ่มข้อมูลได้");
        }
        
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>เพิ่มผู้ใช้งานใหม่</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=admin_users" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> กลับไปยังรายการผู้ใช้งาน</a>
    </div>
</div>

<?php if(isset($error_message)): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<div class="card shadow">
    <div class="card-body">
        <form action="?page=admin_add_user" method="post" id="addUserForm">
            <div class="mb-3">
                <label for="user_type" class="form-label">ประเภทผู้ใช้งาน <span class="text-danger">*</span></label>
                <select class="form-select" id="user_type" name="user_type" required onchange="toggleUserFields()">
                    <option value="">-- เลือกประเภท --</option>
                    <option value="student" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'student') ? 'selected' : ''; ?>>นักศึกษา</option>
                    <option value="teacher" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'teacher') ? 'selected' : ''; ?>>อาจารย์</option>
                </select>
            </div>
            <hr>

            <h5>ข้อมูลทั่วไป (สำหรับทุกประเภท)</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_card" class="form-label">รหัสบัตรประชาชน <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="id_card" name="id_card" required maxlength="13" value="<?php echo isset($_POST['id_card']) ? htmlspecialchars($_POST['id_card']) : ''; ?>">
                    <div class="form-text">ใช้สำหรับการเข้าสู่ระบบครั้งแรก (จะถูกเข้ารหัสในฐานข้อมูล)</div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="firstname" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="firstname" name="firstname" required value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="lastname" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="lastname" name="lastname" required value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>">
                </div>
            </div>
             <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>
            </div>
            <hr>

            <div id="student_fields" style="display:none;">
                <h5>ข้อมูลเฉพาะนักศึกษา</h5>
                <div class="mb-3">
                    <label for="student_id" class="form-label">รหัสนักศึกษา <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="student_id" name="student_id" value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>">
                </div>
                <div class="mb-3">
                    <label for="faculty" class="form-label">คณะ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="faculty" name="faculty" value="<?php echo isset($_POST['faculty']) ? htmlspecialchars($_POST['faculty']) : ''; ?>">
                </div>
                <div class="mb-3">
                    <label for="department_student" class="form-label">สาขา (นักศึกษา)</label>
                    <input type="text" class="form-control" id="department_student" name="department_student" value="<?php echo isset($_POST['department_student']) ? htmlspecialchars($_POST['department_student']) : ''; ?>">
                </div>
                <div class="mb-3">
                    <label for="address_student" class="form-label">ที่อยู่ (นักศึกษา)</label>
                    <textarea class="form-control" id="address_student" name="address_student" rows="3"><?php echo isset($_POST['address_student']) ? htmlspecialchars($_POST['address_student']) : ''; ?></textarea>
                </div>
            </div>

            <div id="teacher_fields" style="display:none;">
                <h5>ข้อมูลเฉพาะอาจารย์</h5>
                 <div class="mb-3">
                    <label for="teacher_id" class="form-label">รหัสอาจารย์ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="teacher_id" name="teacher_id" value="<?php echo isset($_POST['teacher_id']) ? htmlspecialchars($_POST['teacher_id']) : ''; ?>">
                </div>
                <div class="mb-3">
                    <label for="department_teacher" class="form-label">ภาควิชา/แผนก (อาจารย์)</label>
                    <input type="text" class="form-control" id="department_teacher" name="department_teacher" value="<?php echo isset($_POST['department_teacher']) ? htmlspecialchars($_POST['department_teacher']) : ''; ?>">
                </div>
                <div class="mb-3">
                    <label for="position" class="form-label">ตำแหน่ง</label>
                    <input type="text" class="form-control" id="position" name="position" value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>">
                </div>
            </div>
            
            <hr>
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleUserFields() {
    const userType = document.getElementById('user_type').value;
    const studentFields = document.getElementById('student_fields');
    const teacherFields = document.getElementById('teacher_fields');
    
    // Reset required attributes first
    document.getElementById('student_id').required = false;
    document.getElementById('faculty').required = false;
    document.getElementById('teacher_id').required = false;

    if (userType === 'student') {
        studentFields.style.display = 'block';
        teacherFields.style.display = 'none';
        document.getElementById('student_id').required = true;
        document.getElementById('faculty').required = true;
    } else if (userType === 'teacher') {
        studentFields.style.display = 'none';
        teacherFields.style.display = 'block';
        document.getElementById('teacher_id').required = true;
    } else {
        studentFields.style.display = 'none';
        teacherFields.style.display = 'none';
    }
}
// Call on page load to set initial state based on POST data (if any)
document.addEventListener('DOMContentLoaded', toggleUserFields);
</script>

<?php if(isset($success) && $success): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: 'สำเร็จ!',
        text: '<?php echo $message; ?>',
        icon: 'success',
        confirmButtonText: 'ตกลง'
    }).then((result) => {
        document.getElementById('addUserForm').reset();
        toggleUserFields(); // Reset field visibility
    });
});
</script>
<?php endif; ?>
