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
        // รับค่าจาก form
        $student_id = isset($_POST['student_id']) ? $database->sanitize($_POST['student_id']) : '';
        $id_card = isset($_POST['id_card']) ? $database->sanitize($_POST['id_card']) : '';
        $firstname = isset($_POST['firstname']) ? $database->sanitize($_POST['firstname']) : '';
        $lastname = isset($_POST['lastname']) ? $database->sanitize($_POST['lastname']) : '';
        $email = isset($_POST['email']) ? $database->sanitize($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? $database->sanitize($_POST['phone']) : '';
        $faculty = isset($_POST['faculty']) ? $database->sanitize($_POST['faculty']) : '';
        $department = isset($_POST['department']) ? $database->sanitize($_POST['department']) : '';
        $address = isset($_POST['address']) ? $database->sanitize($_POST['address']) : '';
        
        // ตรวจสอบว่ามีข้อมูลที่จำเป็นครบหรือไม่
        if (empty($student_id) || empty($id_card) || empty($firstname) || empty($lastname) || empty($email) || empty($faculty)) {
            throw new Exception("กรุณากรอกข้อมูลสำคัญให้ครบถ้วน");
        }
        
        // ตรวจสอบว่ารหัสนักศึกษาซ้ำหรือไม่
        $check_query = "SELECT COUNT(*) as count FROM students WHERE student_id = :student_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':student_id', $student_id);
        $check_stmt->execute();
        
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            throw new Exception("รหัสนักศึกษานี้มีอยู่ในระบบแล้ว");
        }
        
        // เตรียมคำสั่ง SQL สำหรับเพิ่มข้อมูล
        $insert_query = "INSERT INTO students (student_id, id_card, firstname, lastname, email, phone, faculty, department, address, first_login, created_at) 
                        VALUES (:student_id, :id_card, :firstname, :lastname, :email, :phone, :faculty, :department, :address, 1, NOW())";
        
        $insert_stmt = $db->prepare($insert_query);
        
        // Bind parameters
        $insert_stmt->bindParam(':student_id', $student_id);
        $insert_stmt->bindParam(':id_card', $id_card);
        $insert_stmt->bindParam(':firstname', $firstname);
        $insert_stmt->bindParam(':lastname', $lastname);
        $insert_stmt->bindParam(':email', $email);
        $insert_stmt->bindParam(':phone', $phone);
        $insert_stmt->bindParam(':faculty', $faculty);
        $insert_stmt->bindParam(':department', $department);
        $insert_stmt->bindParam(':address', $address);
        
        // Execute
        if ($insert_stmt->execute()) {
            // เพิ่มข้อมูลสำเร็จ ให้บันทึก log
            $student_id_new = $db->lastInsertId();
            $log_action = "เพิ่มนักศึกษาใหม่: " . $student_id . " - " . $firstname . " " . $lastname;
            
            $log_query = "INSERT INTO admin_logs (admin_id, action, created_at) VALUES (:admin_id, :action, NOW())";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':admin_id', $_SESSION['admin_id']);
            $log_stmt->bindParam(':action', $log_action);
            $log_stmt->execute();
            
            // กำหนดข้อความแจ้งเตือนสำเร็จ
            $success = true;
            $message = "เพิ่มข้อมูลนักศึกษาสำเร็จ";
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

<!-- แบบฟอร์มเพิ่มผู้ใช้งาน -->
<div class="card shadow">
    <div class="card-body">
        <form action="?page=admin_add_user" method="post" id="addUserForm">
            <div class="row">
                <!-- ข้อมูลหลัก -->
                <div class="col-md-6">
                    <h5>ข้อมูลหลัก</h5>
                    <hr>
                    
                    <div class="mb-3">
                        <label for="student_id" class="form-label">รหัสนักศึกษา <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="student_id" name="student_id" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_card" class="form-label">รหัสบัตรประชาชน <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="id_card" name="id_card" required maxlength="13">
                        <div class="form-text">ใช้สำหรับการเข้าสู่ระบบครั้งแรก</div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="firstname" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="firstname" name="firstname" required>
                        </div>
                        <div class="col-md-6">
                            <label for="lastname" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lastname" name="lastname" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="form-text">ควรเป็นอีเมลมหาวิทยาลัยเพื่อใช้ในการเชื่อมต่อกับ Google</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                </div>
                
                <!-- ข้อมูลเพิ่มเติม -->
                <div class="col-md-6">
                    <h5>ข้อมูลการศึกษา</h5>
                    <hr>
                    
                    <div class="mb-3">
                        <label for="faculty" class="form-label">คณะ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="faculty" name="faculty" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="department" class="form-label">สาขา</label>
                        <input type="text" class="form-control" id="department" name="department">
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">ที่อยู่</label>
                        <textarea class="form-control" id="address" name="address" rows="4"></textarea>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<?php if(isset($success) && $success): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: 'สำเร็จ!',
        text: '<?php echo $message; ?>',
        icon: 'success',
        confirmButtonText: 'ตกลง'
    }).then((result) => {
        // ล้างฟอร์ม
        document.getElementById('addUserForm').reset();
    });
});
</script>
<?php endif; ?>