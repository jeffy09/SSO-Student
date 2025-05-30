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
    $teacher_id_form = isset($_POST['teacher_id']) ? $database->sanitize($_POST['teacher_id']) : '';
    $id_card_form = isset($_POST['id_card']) ? $database->sanitize($_POST['id_card']) : '';

    if (!empty($teacher_id_form) && !empty($id_card_form)) {
        try {
            $query = "SELECT * FROM teachers WHERE teacher_id = :teacher_id LIMIT 0,1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':teacher_id', $teacher_id_form);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $password_valid = false;

                if (!empty($row['password_hash'])) {
                    $password_valid = password_verify($id_card_form, $row['password_hash']);
                } else {
                    if ($row['id_card'] == $id_card_form) {
                        $password_valid = true;
                        $password_hash = password_hash($id_card_form, PASSWORD_DEFAULT);
                        $update_hash_query = "UPDATE teachers SET password_hash = :password_hash WHERE id = :id";
                        $update_hash_stmt = $db->prepare($update_hash_query);
                        $update_hash_stmt->bindParam(':password_hash', $password_hash);
                        $update_hash_stmt->bindParam(':id', $row['id']);
                        $update_hash_stmt->execute();
                    }
                }

                if ($password_valid) {
                    $_SESSION['teacher_user_id'] = $row['id']; // Changed from 'teacher_id' to avoid conflict with the actual teacher_id string
                    $_SESSION['teacher_code'] = $row['teacher_id'];
                    $_SESSION['teacher_name'] = $row['firstname'] . ' ' . $row['lastname'];
                    $_SESSION['teacher_email'] = $row['email'];

                    if ($row['first_login'] == 1 && !empty($row['email'])) {
                        $_SESSION['show_google_link_teacher'] = true;
                    }

                    $update_first_login_query = "UPDATE teachers SET first_login = 0 WHERE id = :id";
                    $update_first_login_stmt = $db->prepare($update_first_login_query);
                    $update_first_login_stmt->bindParam(':id', $row['id']);
                    $update_first_login_stmt->execute();

                    header("Location: index.php?page=teacher_dashboard");
                    exit;
                } else {
                    $login_error = "รหัสบัตรประชาชนไม่ถูกต้อง";
                }
            } else {
                $login_error = "ไม่พบรหัสอาจารย์นี้ในระบบ";
            }
        } catch(PDOException $e) {
            $login_error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    } else {
        $login_error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}

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
                    <h2 class="text-primary fw-bold">ระบบสำหรับอาจารย์</h2>
                </div>
                <div class="card mb-0 login-card">
                    <div class="card-body">
                        <p class="text-center fw-semibold">เข้าสู่ระบบสำหรับอาจารย์</p>
                        <?php if(isset($login_error)): ?>
                            <div class="alert alert-danger text-center py-2" role="alert">
                                <?php echo $login_error; ?>
                            </div>
                        <?php endif; ?>

                        <form action="?page=teacher_login" method="post">
                            <div class="mb-3">
                                <label for="teacher_id" class="form-label">รหัสอาจารย์</label>
                                <input type="text" class="form-control fs-6" id="teacher_id" name="teacher_id" required value="<?php echo isset($teacher_id_form) ? htmlspecialchars($teacher_id_form) : ''; ?>" placeholder="กรอกรหัสอาจารย์">
                            </div>
                            <div class="mb-4">
                                <label for="id_card" class="form-label">รหัสบัตรประชาชน</label>
                                <input type="password" class="form-control fs-6" id="id_card" name="id_card" required placeholder="กรอกรหัสบัตรประชาชน">
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2 fs-5 mb-4 rounded-2">เข้าสู่ระบบ</button>
                        </form>

                        <div class="d-flex align-items-center justify-content-center">
                            <p class="fs-4 mb-0 fw-semibold me-2">หรือ</p>
                            <a href="?page=google_login&user_type=teacher" class="btn btn-outline-danger d-flex align-items-center justify-content-center">
                                <i class="fab fa-google fs-5 me-2"></i>
                                <span class="fw-semibold">เข้าสู่ระบบด้วย Google</span>
                            </a>
                        </div>
                         <div class="text-center mt-4">
                            <small class="text-muted">ผู้ใช้งานประเภทอื่น กรุณาเข้าสู่ระบบผ่าน <a href="<?php echo $base_url; ?>?page=home" class="text-primary fw-semibold">หน้าหลัก</a></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
