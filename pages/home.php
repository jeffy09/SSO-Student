<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// ตรวจสอบสถานะการล็อกอิน และ redirect ไปยัง dashboard ของแต่ละประเภทผู้ใช้
if (isset($_SESSION['student_id'])) {
    header("Location: index.php?page=student_dashboard");
    exit;
} elseif (isset($_SESSION['admin_id'])) {
    header("Location: index.php?page=admin_dashboard");
    exit;
} elseif (isset($_SESSION['teacher_user_id'])) {
    header("Location: index.php?page=teacher_dashboard");
    exit;
}

// รวมไฟล์ configuration ของ Google
include_once 'config/google_config.php'; //
$google_config = new GoogleConfig(); //

$login_error = '';
$form_to_display = isset($_GET['login_as']) ? $_GET['login_as'] : ''; // 'student' or 'teacher'
$student_id_form_val = '';
$teacher_id_form_val = '';

// หากมีการ submit form (PHP logic เดิมจะถูกย้ายไปอยู่ในส่วนของแต่ละฟอร์ม)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $submitted_form_type = isset($_POST['form_type']) ? $_POST['form_type'] : '';
    $id_card_form_val = isset($_POST['id_card']) ? $_POST['id_card'] : '';

    if ($submitted_form_type === 'student') {
        $form_to_display = 'student'; // Keep student form visible on error
        $student_id_form_val = isset($_POST['student_id']) ? $database->sanitize($_POST['student_id']) : ''; //

        if (!empty($student_id_form_val) && !empty($id_card_form_val)) { //
            try {
                $query = "SELECT * FROM students WHERE student_id = :student_id LIMIT 1"; //
                $stmt = $db->prepare($query); //
                $stmt->bindParam(':student_id', $student_id_form_val); //
                $stmt->execute(); //

                if ($stmt->rowCount() > 0) { //
                    $row = $stmt->fetch(PDO::FETCH_ASSOC); //
                    $password_valid = false; //

                    if (!empty($row['password_hash'])) { //
                        $password_valid = password_verify($id_card_form_val, $row['password_hash']); //
                    } else {
                        if ($row['id_card'] == $id_card_form_val) { //
                            $password_valid = true; //
                            $password_hash = password_hash($id_card_form_val, PASSWORD_DEFAULT); //
                            $update_hash_query = "UPDATE students SET password_hash = :password_hash WHERE id = :id"; //
                            $update_hash_stmt = $db->prepare($update_hash_query); //
                            $update_hash_stmt->bindParam(':password_hash', $password_hash); //
                            $update_hash_stmt->bindParam(':id', $row['id']); //
                            $update_hash_stmt->execute(); //
                        }
                    }
                    
                    if ($password_valid) { //
                        $_SESSION['student_id'] = $row['id']; //
                        $_SESSION['student_code'] = $row['student_id']; //
                        $_SESSION['student_name'] = $row['firstname'] . ' ' . $row['lastname']; //
                        $_SESSION['student_email'] = $row['email']; //
                        
                        if ($row['first_login'] == 1 && !empty($row['email'])) { //
                            $_SESSION['show_google_link'] = true; //
                        }
                        
                        $update_first_login_query = "UPDATE students SET first_login = 0 WHERE id = :id"; //
                        $update_first_login_stmt = $db->prepare($update_first_login_query); //
                        $update_first_login_stmt->bindParam(':id', $row['id']); //
                        $update_first_login_stmt->execute(); //
                        
                        header("Location: index.php?page=student_dashboard"); //
                        exit;
                    } else {
                        $login_error = "รหัสบัตรประชาชนไม่ถูกต้อง"; //
                    }
                } else {
                    $login_error = "ไม่พบรหัสนักศึกษานี้ในระบบ"; //
                }
            } catch(PDOException $e) {
                $login_error = "เกิดข้อผิดพลาดฐานข้อมูล: " . $e->getMessage(); //
            }
        } else {
            $login_error = "กรุณากรอกข้อมูลนักศึกษาให้ครบถ้วน"; //
        }
    } elseif ($submitted_form_type === 'teacher') {
        $form_to_display = 'teacher'; // Keep teacher form visible on error
        $teacher_id_form_val = isset($_POST['teacher_id']) ? $database->sanitize($_POST['teacher_id']) : '';

        if (!empty($teacher_id_form_val) && !empty($id_card_form_val)) {
            try {
                $query = "SELECT * FROM teachers WHERE teacher_id = :teacher_id LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':teacher_id', $teacher_id_form_val);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $password_valid = false;

                    if (!empty($row['password_hash'])) {
                        $password_valid = password_verify($id_card_form_val, $row['password_hash']);
                    } else {
                         if (isset($row['id_card']) && $row['id_card'] == $id_card_form_val) {
                            $password_valid = true;
                            $password_hash = password_hash($id_card_form_val, PASSWORD_DEFAULT);
                            $update_hash_query = "UPDATE teachers SET password_hash = :password_hash WHERE id = :id";
                            $update_hash_stmt = $db->prepare($update_hash_query);
                            $update_hash_stmt->bindParam(':password_hash', $password_hash);
                            $update_hash_stmt->bindParam(':id', $row['id']);
                            $update_hash_stmt->execute();
                        }
                    }
                    
                    if ($password_valid) {
                        $_SESSION['teacher_user_id'] = $row['id'];
                        $_SESSION['teacher_code'] = $row['teacher_id'];
                        $_SESSION['teacher_name'] = $row['firstname'] . ' ' . $row['lastname'];
                        $_SESSION['teacher_email'] = $row['email'];
                        
                        if (isset($row['first_login']) && $row['first_login'] == 1 && !empty($row['email'])) {
                            $_SESSION['show_google_link_teacher'] = true;
                        }

                        if (isset($row['first_login'])) {
                            $update_first_login_query = "UPDATE teachers SET first_login = 0 WHERE id = :id";
                            $update_first_login_stmt = $db->prepare($update_first_login_query);
                            $update_first_login_stmt->bindParam(':id', $row['id']);
                            $update_first_login_stmt->execute();
                        }
                        
                        header("Location: index.php?page=teacher_dashboard");
                        exit;
                    } else {
                        $login_error = "รหัสบัตรประชาชนไม่ถูกต้อง";
                    }
                } else {
                    $login_error = "ไม่พบรหัสอาจารย์นี้ในระบบ";
                }
            } catch(PDOException $e) {
                $login_error = "เกิดข้อผิดพลาดฐานข้อมูล: " . $e->getMessage();
            }
        } else {
             $login_error = "กรุณากรอกข้อมูลอาจารย์ให้ครบถ้วน";
        }
    }
}


// ดึงข้อความแจ้งเตือนจาก session (ถ้ามี)
if (isset($_SESSION['auth_error'])) {
    $login_error = $_SESSION['auth_error']; //
    unset($_SESSION['auth_error']); //
}
?>

<div class="login-background-wrapper">
    <div class="d-flex align-items-center justify-content-center w-100">
        <div class="row justify-content-center w-100">
            <div class="col-md-8 col-lg-6 col-xxl-3">
                <div class="login-logo-container text-center mb-4">
                    <h2 class="text-primary fw-bold">ระบบ Single Sign-On</h2>
                </div>
                <div class="card mb-0 login-card">
                    <div class="card-body">
                        <a href="<?php echo $base_url; ?>?page=home" class="text-nowrap logo-img text-center d-block py-3 w-100 mb-2">
                           </a>
                        
                        <?php if(empty($form_to_display)): ?>
                            <p class="text-center fw-semibold">กรุณาเลือกประเภทผู้ใช้งานเพื่อเข้าสู่ระบบ:</p>
                            <div class="d-grid gap-2 mb-4">
                                <button type="button" class="btn btn-lg btn-outline-primary" onclick="showLoginForm('student')">
                                    <i class="fas fa-user-graduate me-2"></i>นักศึกษา
                                </button>
                                <button type="button" class="btn btn-lg btn-outline-success" onclick="showLoginForm('teacher')">
                                    <i class="fas fa-chalkboard-teacher me-2"></i>อาจารย์
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if(!empty($login_error)): ?>
                            <div class="alert alert-danger text-center py-2" role="alert">
                                <?php echo htmlspecialchars($login_error); ?>
                            </div>
                        <?php endif; ?>

                        <div id="studentLoginForm" style="<?php echo ($form_to_display === 'student') ? 'display:block;' : 'display:none;'; ?>">
                            <p class="text-center fw-semibold">เข้าสู่ระบบสำหรับนักศึกษา 
                                <a href="?page=home" class="btn btn-sm btn-outline-secondary float-end" title="กลับไปเลือกประเภท"><i class="fas fa-arrow-left"></i></a>
                            </p>
                            <form action="?page=home&login_as=student" method="post">
                                <input type="hidden" name="form_type" value="student">
                                <div class="mb-3">
                                    <label for="student_id" class="form-label">รหัสนักศึกษา</label>
                                    <input type="text" class="form-control fs-6" id="student_id" name="student_id" required value="<?php echo htmlspecialchars($student_id_form_val); ?>" placeholder="กรอกรหัสนักศึกษา">
                                </div>
                                <div class="mb-4">
                                    <label for="student_id_card" class="form-label">รหัสบัตรประชาชน</label>
                                    <input type="password" class="form-control fs-6" id="student_id_card" name="id_card" required placeholder="กรอกรหัสบัตรประชาชน">
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2 fs-5 mb-4 rounded-2">เข้าสู่ระบบ (นักศึกษา)</button>
                                <div class="d-flex align-items-center justify-content-center">
                                    <p class="fs-4 mb-0 fw-semibold me-2">หรือ</p>
                                    <a href="?page=google_login&user_type=student" class="btn btn-outline-danger d-flex align-items-center justify-content-center">
                                        <i class="fab fa-google fs-5 me-2"></i>
                                        <span class="fw-semibold">Google (นักศึกษา)</span>
                                    </a>
                                </div>
                            </form>
                        </div>

                        <div id="teacherLoginForm" style="<?php echo ($form_to_display === 'teacher') ? 'display:block;' : 'display:none;'; ?>">
                             <p class="text-center fw-semibold">เข้าสู่ระบบสำหรับอาจารย์
                                <a href="?page=home" class="btn btn-sm btn-outline-secondary float-end" title="กลับไปเลือกประเภท"><i class="fas fa-arrow-left"></i></a>
                             </p>
                            <form action="?page=home&login_as=teacher" method="post">
                                <input type="hidden" name="form_type" value="teacher">
                                <div class="mb-3">
                                    <label for="teacher_id" class="form-label">รหัสอาจารย์</label>
                                    <input type="text" class="form-control fs-6" id="teacher_id" name="teacher_id" required value="<?php echo htmlspecialchars($teacher_id_form_val); ?>" placeholder="กรอกรหัสอาจารย์">
                                </div>
                                <div class="mb-4">
                                    <label for="teacher_id_card" class="form-label">รหัสบัตรประชาชน</label>
                                    <input type="password" class="form-control fs-6" id="teacher_id_card" name="id_card" required placeholder="กรอกรหัสบัตรประชาชน">
                                </div>
                                <button type="submit" class="btn btn-success w-100 py-2 fs-5 mb-4 rounded-2">เข้าสู่ระบบ (อาจารย์)</button>
                                 <div class="d-flex align-items-center justify-content-center">
                                    <p class="fs-4 mb-0 fw-semibold me-2">หรือ</p>
                                    <a href="?page=google_login&user_type=teacher" class="btn btn-outline-danger d-flex align-items-center justify-content-center">
                                        <i class="fab fa-google fs-5 me-2"></i>
                                        <span class="fw-semibold">Google (อาจารย์)</span>
                                    </a>
                                </div>
                            </form>
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

<script>
function showLoginForm(userType) {
    const studentForm = document.getElementById('studentLoginForm');
    const teacherForm = document.getElementById('teacherLoginForm');
    const selectionButtons = document.querySelector('.d-grid.gap-2.mb-4'); // The div containing selection buttons

    if (selectionButtons) {
        selectionButtons.style.display = 'none';
    }

    if (userType === 'student') {
        studentForm.style.display = 'block';
        teacherForm.style.display = 'none';
        // Update URL without reloading to remember choice if user refreshes or error occurs
        history.replaceState(null, '', '?page=home&login_as=student');
    } else if (userType === 'teacher') {
        studentForm.style.display = 'none';
        teacherForm.style.display = 'block';
        history.replaceState(null, '', '?page=home&login_as=teacher');
    }
}

// Check URL on page load if a form was previously selected
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const loginAs = urlParams.get('login_as');
    if (loginAs === 'student' || loginAs === 'teacher') {
        showLoginForm(loginAs);
    }
});
</script>
