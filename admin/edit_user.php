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
$redirect_url = ''; // สำหรับ redirect หลังจาก submit

// ฟังก์ชันสำหรับบันทึก log การทำงานของ Admin
function saveAdminLog($db, $admin_id, $action, $old_data = null, $new_data = null) {
    try {
        // ถ้า old_data หรือ new_data เป็น array/object ให้แปลงเป็น JSON string
        if (is_array($old_data) || is_object($old_data)) {
            $old_data = json_encode($old_data);
        }
        if (is_array($new_data) || is_object($new_data)) {
            $new_data = json_encode($new_data);
        }

        $log_query = "INSERT INTO admin_logs (admin_id, action, old_data, new_data, created_at) 
                      VALUES (:admin_id, :action, :old_data, :new_data, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->bindParam(':admin_id', $admin_id);
        $log_stmt->bindParam(':action', $action);
        $log_stmt->bindParam(':old_data', $old_data); // PDO จะจัดการเรื่อง null
        $log_stmt->bindParam(':new_data', $new_data); // PDO จะจัดการเรื่อง null
        $log_stmt->execute();
    } catch (PDOException $e) {
        error_log("Failed to save admin log: " . $e->getMessage());
    }
}


// ถอดรหัส ID
try {
    $id = base64_decode($_GET['id']);
    
    if (!is_numeric($id)) {
        throw new Exception("รหัสไม่ถูกต้อง");
    }
    
    $query_student = "SELECT * FROM students WHERE id = :id LIMIT 0,1";
    $stmt_student = $db->prepare($query_student);
    $stmt_student->bindParam(':id', $id);
    $stmt_student->execute();
    
    if ($stmt_student->rowCount() == 0) {
        throw new Exception("ไม่พบข้อมูลนักศึกษา");
    }
    $student_original_data = $stmt_student->fetch(PDO::FETCH_ASSOC); 
    $student = $student_original_data; 

    $query_systems = "SELECT * FROM student_systems WHERE student_id = :student_id ORDER BY system_name";
    $stmt_systems_db = $db->prepare($query_systems); // ใช้ชื่อตัวแปรใหม่สำหรับ statement
    $stmt_systems_db->bindParam(':student_id', $student['id']);
    $stmt_systems_db->execute();
    $student_systems_data_original = $stmt_systems_db->fetchAll(PDO::FETCH_ASSOC); 
    
    $systems_form_data = [];
    foreach ($student_systems_data_original as $sys) {
        $systems_form_data[$sys['system_name']] = $sys;
    }
    
} catch(Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: index.php?page=admin_users");
    exit;
}

// --- ส่วนของการจัดการ POST requests ---

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    try {
        $changed_fields_old = [];
        $changed_fields_new = [];

        $fields_to_check = ['firstname', 'lastname', 'email', 'phone', 'faculty', 'department', 'address'];
        
        foreach ($fields_to_check as $field) {
            $posted_value = $database->sanitize($_POST[$field] ?? '');
            if ($student_original_data[$field] != $posted_value) {
                $changed_fields_old[$field] = $student_original_data[$field];
                $changed_fields_new[$field] = $posted_value;
            }
        }
        
        $firstname = $database->sanitize($_POST['firstname']);
        $lastname = $database->sanitize($_POST['lastname']);
        $email = $database->sanitize($_POST['email']);
        $faculty = $database->sanitize($_POST['faculty']);
        
        if (empty($firstname) || empty($lastname) || empty($email) || empty($faculty)) {
            throw new Exception("กรุณากรอกข้อมูลสำคัญให้ครบถ้วน (*)");
        }
        
        if (!empty($changed_fields_new)) {
            $update_query = "UPDATE students SET firstname = :fn, lastname = :ln, email = :e, phone = :p, faculty = :fac, department = :dep, address = :add, updated_at = NOW() WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([
                ':fn' => $changed_fields_new['firstname'] ?? $student_original_data['firstname'], 
                ':ln' => $changed_fields_new['lastname'] ?? $student_original_data['lastname'], 
                ':e' => $changed_fields_new['email'] ?? $student_original_data['email'], 
                ':p' => $changed_fields_new['phone'] ?? $student_original_data['phone'],
                ':fac' => $changed_fields_new['faculty'] ?? $student_original_data['faculty'], 
                ':dep' => $changed_fields_new['department'] ?? $student_original_data['department'], 
                ':add' => $changed_fields_new['address'] ?? $student_original_data['address'], 
                ':id' => $id
            ]);

            saveAdminLog($db, $_SESSION['admin_id'], "แก้ไขข้อมูลนักศึกษา: " . $student_original_data['student_id'], $changed_fields_old, $changed_fields_new);
            
            // อัปเดตตัวแปร $student ที่ใช้แสดงผลในหน้า
            $student = array_merge($student, $changed_fields_new);
            $alert_message = 'อัพเดตข้อมูลนักศึกษาสำเร็จ!';
        } else {
            $alert_message = 'ไม่มีการเปลี่ยนแปลงข้อมูลนักศึกษา';
        }

        $alert_type = 'success';
        $show_alert = true;
        $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'];

    } catch(Exception $e) {
        $alert_type = 'error';
        $alert_message = $e->getMessage();
        $show_alert = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_id_card'])) {
     try {
        $old_id_card_data = ['id_card_old_masked' => substr($student_original_data['id_card'], 0, 4) . 'XXXXXXXXX'];
        $new_id_card = $database->sanitize($_POST['new_id_card']);

        if (empty($new_id_card) || !preg_match('/^[0-9]{13}$/', $new_id_card)) {
            throw new Exception("รหัสบัตรประชาชนต้องเป็นตัวเลข 13 หลัก");
        }

        if ($student_original_data['id_card'] == $new_id_card) {
            $alert_type = 'info';
            $alert_message = 'รหัสบัตรประชาชนใหม่ตรงกับรหัสเดิม ไม่มีการเปลี่ยนแปลง';
            $show_alert = true;
            $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'] . '#security';
        } else {
            $check_id_query = "SELECT COUNT(*) as count FROM students WHERE id_card = :id_card AND id != :id";
            $check_id_stmt = $db->prepare($check_id_query);
            $check_id_stmt->bindParam(':id_card', $new_id_card);
            $check_id_stmt->bindParam(':id', $id);
            $check_id_stmt->execute();
            
            if ($check_id_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                throw new Exception("รหัสบัตรประชาชนนี้มีผู้ใช้งานแล้ว");
            }

            $password_hash = password_hash($new_id_card, PASSWORD_DEFAULT);
            $update_query = "UPDATE students SET id_card = :idc, password_hash = :ph, first_login = 1, updated_at = NOW() WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([':idc' => $new_id_card, ':ph' => $password_hash, ':id' => $id]);

            $new_id_card_data = ['id_card_new_masked' => substr($new_id_card, 0, 4) . 'XXXXXXXXX', 'password_status' => 'hashed'];
            saveAdminLog($db, $_SESSION['admin_id'], "รีเซ็ตรหัสบัตรประชาชน: " . $student_original_data['student_id'], $old_id_card_data, $new_id_card_data);
            
            $student['id_card'] = $new_id_card; 
            $student['password_hash'] = $password_hash;
            $student['first_login'] = 1;

            $alert_type = 'success';
            $alert_message = 'รีเซ็ตรหัสบัตรประชาชนสำเร็จ!';
            $show_alert = true;
            $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'] . '#security';
        }

    } catch(Exception $e) {
        $alert_type = 'error';
        $alert_message = $e->getMessage();
        $show_alert = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unlink_google'])) {
    try {
        if (!empty($student_original_data['google_id'])) {
            $old_google_data = ['google_id_old' => $student_original_data['google_id']];

            $update_query = "UPDATE students SET google_id = NULL, access_token = NULL, refresh_token = NULL, token_expires_at = NULL, updated_at = NOW() WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([':id' => $id]);

            $new_google_data = ['google_id_new' => null];
            saveAdminLog($db, $_SESSION['admin_id'], "ยกเลิกการเชื่อมต่อ Google: " . $student_original_data['student_id'], $old_google_data, $new_google_data);

            $student['google_id'] = NULL; 

            $alert_type = 'success';
            $alert_message = 'ยกเลิกการเชื่อมต่อ Google สำเร็จ!';
        } else {
             $alert_type = 'info';
            $alert_message = 'นักศึกษายังไม่ได้เชื่อมต่อ Google ไม่มีการดำเนินการใดๆ';
        }
        $show_alert = true;
        $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'] . '#security';
    } catch(Exception $e) {
        $alert_type = 'error';
        $alert_message = $e->getMessage();
        $show_alert = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_systems'])) {
    try {
        $systems_input = $_POST['systems'] ?? [];
        $db->beginTransaction();

        $overall_changes_old = [];
        $overall_changes_new = [];
        $changes_made_for_any_system = false;

        foreach ($systems_input as $form_key => $data) {
            $system_name_from_form = $database->sanitize($data['system_name']);
            $username_from_form = $database->sanitize($data['username']);
            $password_from_form = $database->sanitize($data['initial_password']); 
            $system_url_from_form = $database->sanitize($data['system_url']);
            $manual_url_from_form = $database->sanitize($data['manual_url']);

            $original_system_entry = null;
            $existing_system_id = null;

            // หาข้อมูลเดิมจาก $student_systems_data_original โดยใช้ system_name
            foreach($student_systems_data_original as $orig_sys) {
                if ($orig_sys['system_name'] === $system_name_from_form) {
                    $original_system_entry = $orig_sys;
                    $existing_system_id = $orig_sys['id'];
                    break;
                }
            }
            
            $current_system_changes_old = [];
            $current_system_changes_new = [];

            if ($original_system_entry) { // มีข้อมูลเดิม, ตรวจสอบการเปลี่ยนแปลง
                if ($original_system_entry['username'] != $username_from_form) {
                    $current_system_changes_old['username'] = $original_system_entry['username'];
                    $current_system_changes_new['username'] = $username_from_form;
                }
                if ($original_system_entry['initial_password'] != $password_from_form) {
                    $current_system_changes_old['initial_password_masked'] = !empty($original_system_entry['initial_password']) ? substr($original_system_entry['initial_password'],0,1)."***".substr($original_system_entry['initial_password'],-1) : '';
                    $current_system_changes_new['initial_password_masked'] = !empty($password_from_form) ? substr($password_from_form,0,1)."***".substr($password_from_form,-1) : '';
                }
                if ($original_system_entry['system_url'] != $system_url_from_form) {
                    $current_system_changes_old['system_url'] = $original_system_entry['system_url'];
                    $current_system_changes_new['system_url'] = $system_url_from_form;
                }
                 if ($original_system_entry['manual_url'] != $manual_url_from_form) {
                    $current_system_changes_old['manual_url'] = $original_system_entry['manual_url'];
                    $current_system_changes_new['manual_url'] = $manual_url_from_form;
                }

                if (!empty($current_system_changes_new)) {
                    $changes_made_for_any_system = true;
                    $update_sys_query = "UPDATE student_systems SET username = :un, initial_password = :pw, system_url = :surl, manual_url = :murl, updated_at = NOW() WHERE id = :sys_id";
                    $update_sys_stmt = $db->prepare($update_sys_query);
                    $update_sys_stmt->execute([
                        ':un' => $username_from_form, ':pw' => $password_from_form,
                        ':surl' => $system_url_from_form, ':murl' => $manual_url_from_form, 
                        ':sys_id' => $existing_system_id
                    ]);
                    $overall_changes_old[$system_name_from_form] = $current_system_changes_old;
                    $overall_changes_new[$system_name_from_form] = $current_system_changes_new;
                }
            } else { // ไม่มีข้อมูลเดิม, เป็นการเพิ่มใหม่ (ถ้ามีข้อมูลครบ)
                if (!empty($system_name_from_form) && !empty($username_from_form) && !empty($password_from_form)) {
                    $changes_made_for_any_system = true;
                    $insert_sys_query = "INSERT INTO student_systems (student_id, system_name, username, initial_password, system_url, manual_url, created_at) VALUES (:student_id, :sn, :un, :pw, :surl, :murl, NOW())";
                    $insert_sys_stmt = $db->prepare($insert_sys_query);
                    $insert_sys_stmt->execute([
                        ':student_id' => $student['id'], ':sn' => $system_name_from_form, ':un' => $username_from_form, 
                        ':pw' => $password_from_form, ':surl' => $system_url_from_form, ':murl' => $manual_url_from_form
                    ]);
                    // สำหรับการเพิ่มใหม่, old data คือ null หรือ array ว่าง, new data คือข้อมูลที่เพิ่ม
                    $overall_changes_old[$system_name_from_form] = ['status' => 'newly_added'];
                    $overall_changes_new[$system_name_from_form] = [
                        'username' => $username_from_form, 
                        'initial_password_masked' => !empty($password_from_form) ? substr($password_from_form,0,1)."***".substr($password_from_form,-1) : '',
                        'system_url' => $system_url_from_form,
                        'manual_url' => $manual_url_from_form
                    ];
                }
            }
        }
        
        if ($changes_made_for_any_system) {
            saveAdminLog($db, $_SESSION['admin_id'], "แก้ไขข้อมูลการเข้าระบบ: " . $student_original_data['student_id'], $overall_changes_old, $overall_changes_new);
            $alert_message = 'อัปเดตข้อมูลการเข้าระบบสำเร็จ!';
        } else {
            $alert_message = 'ไม่มีการเปลี่ยนแปลงข้อมูลการเข้าระบบ';
        }
        
        $db->commit();
        $alert_type = 'success';
        $show_alert = true;
        
        // รีเฟรชข้อมูล systems_form_data หลังอัปเดต
        $stmt_systems_db->execute(); 
        $student_systems_data_original = $stmt_systems_db->fetchAll(PDO::FETCH_ASSOC);
        $systems_form_data = [];
        foreach ($student_systems_data_original as $sys) {
            $systems_form_data[$sys['system_name']] = $sys;
        }
        $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'] . '#systems';

    } catch (Exception $e) {
        if($db->inTransaction()) $db->rollBack();
        $alert_type = 'error';
        $alert_message = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูลระบบ: ' . $e->getMessage();
        $show_alert = true;
    }
}

$defined_systems = ['Email', 'Office 365', 'Portal'];
?>

<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>แก้ไขข้อมูลนักศึกษา</h2>
        <p>รหัสนักศึกษา: <strong><?php echo htmlspecialchars($student['student_id']); ?></strong></p>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=admin_users" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>กลับไปยังรายการผู้ใช้งาน
        </a>
         <a href="?page=admin_view_user&id=<?php echo $_GET['id']; ?>" class="btn btn-info">
            <i class="fas fa-eye me-2"></i>ดูข้อมูล
        </a>
    </div>
</div>

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
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="systems-tab" data-bs-toggle="tab" data-bs-target="#systems" type="button" role="tab" aria-controls="systems" aria-selected="false">
            <i class="fas fa-cogs me-2"></i>ข้อมูลการเข้าระบบ
        </button>
    </li>
</ul>

<div class="tab-content" id="editTabsContent">
    <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
        <div class="card shadow">
            <div class="card-header bg-primary text-white"><h5 class="card-title mb-0"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลส่วนตัว</h5></div>
            <div class="card-body">
                <form action="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>" method="post" id="profileForm">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="text-primary"><i class="fas fa-user me-2"></i>ข้อมูลหลัก</h5><hr>
                            <div class="mb-3">
                                <label class="form-label">รหัสนักศึกษา</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['student_id']); ?>" readonly>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="firstname" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo htmlspecialchars($student['firstname']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastname" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo htmlspecialchars($student['lastname']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($student['phone']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5 class="text-primary"><i class="fas fa-graduation-cap me-2"></i>ข้อมูลการศึกษา</h5><hr>
                            <div class="mb-3">
                                <label for="faculty" class="form-label">คณะ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="faculty" name="faculty" value="<?php echo htmlspecialchars($student['faculty']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="department" class="form-label">สาขา</label>
                                <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($student['department']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">ที่อยู่</label>
                                <textarea class="form-control" id="address" name="address" rows="4"><?php echo htmlspecialchars($student['address']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save me-2"></i>บันทึกข้อมูลส่วนตัว</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
        <div class="card shadow mb-4">
            <div class="card-header bg-warning text-dark"><h5 class="card-title mb-0"><i class="fas fa-key me-2"></i>รีเซ็ตรหัสบัตรประชาชน</h5></div>
            <div class="card-body">
                <form action="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>" method="post" id="resetPasswordForm">
                    <input type="hidden" name="reset_id_card" value="1">
                    <div class="mb-3">
                        <label for="new_id_card" class="form-label">รหัสบัตรประชาชนใหม่ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new_id_card" name="new_id_card" required maxlength="13" pattern="[0-9]{13}">
                        <div class="form-text">กรอกรหัส 13 หลัก (ระบบจะเข้ารหัสอัตโนมัติ)</div>
                    </div>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-key me-2"></i>รีเซ็ตรหัสบัตร</button>
                </form>
            </div>
        </div>
        <div class="card shadow">
            <div class="card-header bg-danger text-white"><h5 class="card-title mb-0"><i class="fas fa-unlink me-2"></i>การเชื่อมต่อ Google</h5></div>
            <div class="card-body">
                <?php if(!empty($student['google_id'])): ?>
                    <p>สถานะ: <span class="badge bg-success"><i class="fab fa-google"></i> เชื่อมต่อแล้ว</span></p>
                    <form action="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>" method="post" id="unlinkGoogleForm">
                        <input type="hidden" name="unlink_google" value="1">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-unlink me-2"></i>ยกเลิกการเชื่อมต่อ</button>
                    </form>
                <?php else: ?>
                    <p>สถานะ: <span class="badge bg-secondary">ยังไม่เชื่อมต่อ</span></p>
                    <p class="text-muted">นักศึกษาสามารถเชื่อมต่อบัญชี Google ได้จากหน้าโปรไฟล์ของตนเอง</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="systems" role="tabpanel" aria-labelledby="systems-tab">
        <div class="card shadow">
            <div class="card-header bg-info text-white"><h5 class="card-title mb-0"><i class="fas fa-cogs me-2"></i>แก้ไขข้อมูลการเข้าระบบ</h5></div>
            <div class="card-body">
                <form action="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>" method="post" id="systemsForm">
                    <input type="hidden" name="update_systems" value="1">
                    <?php 
                    $default_urls = [
                        'Email' => 'https://mail.google.com/a/mbu.ac.th',
                        'Office 365' => 'https://www.office.com/?auth=2&home=1&from=ShellLogo',
                        'Portal' => 'https://portal.mbu.ac.th'
                    ];
                    $default_manual_urls = [
                         'Email' => '#', 
                         'Office 365' => '#', 
                         'Portal' => '#' 
                    ];

                    foreach ($defined_systems as $system_key_name): 
                        $sys_data = $systems_form_data[$system_key_name] ?? null;
                        $username_val = $sys_data['username'] ?? ($student['student_id'] . ($system_key_name === 'Email' ? '@mbu.ac.th' : ($system_key_name === 'Office 365' ? '@mbu.asia' : '')));
                        if ($system_key_name === 'Portal' && empty($sys_data['username'])) {
                            $username_val = $student['student_id'];
                        }
                        $password_val = $sys_data['initial_password'] ?? '';
                        if ($system_key_name === 'Portal' && empty($sys_data['initial_password'])) {
                            $password_val = $student['id_card'];
                        }
                        $system_url_val = $sys_data['system_url'] ?? ($default_urls[$system_key_name] ?? '');
                        $manual_url_val = $sys_data['manual_url'] ?? ($default_manual_urls[$system_key_name] ?? '');
                        
                        $form_field_key = $sys_data['id'] ? 'existing_' . $sys_data['id'] : 'new_' . str_replace(' ', '_', $system_key_name);
                    ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <strong><?php echo htmlspecialchars($system_key_name); ?></strong>
                            </div>
                            <div class="card-body">
                                 <input type="hidden" name="systems[<?php echo htmlspecialchars($system_key_name); ?>][system_name]" value="<?php echo htmlspecialchars($system_key_name); ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" name="systems[<?php echo htmlspecialchars($system_key_name); ?>][username]" value="<?php echo htmlspecialchars($username_val); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password เริ่มต้น</label>
                                        <input type="text" class="form-control" name="systems[<?php echo htmlspecialchars($system_key_name); ?>][initial_password]" value="<?php echo htmlspecialchars($password_val); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">URL เข้าระบบ</label>
                                        <input type="url" class="form-control" name="systems[<?php echo htmlspecialchars($system_key_name); ?>][system_url]" value="<?php echo htmlspecialchars($system_url_val); ?>">
                                    </div>
                                     <div class="col-md-6 mb-3">
                                        <label class="form-label">URL คู่มือ</label>
                                        <input type="url" class="form-control" name="systems[<?php echo htmlspecialchars($system_key_name); ?>][manual_url]" value="<?php echo htmlspecialchars($manual_url_val); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                     <hr>
                    <div class="text-center">
                        <button type="submit" class="btn btn-info btn-lg px-5"><i class="fas fa-save me-2"></i>บันทึกข้อมูลระบบ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
<?php if ($show_alert): ?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?php echo $alert_type; ?>',
        title: '<?php echo $alert_type == "success" ? "สำเร็จ!" : ($alert_type == "info" ? "แจ้งเพื่อทราบ" : "เกิดข้อผิดพลาด!"); ?>',
        text: '<?php echo addslashes($alert_message); ?>',
        confirmButtonText: 'ตกลง',
        timer: <?php echo ($alert_type == "success" || $alert_type == "info") ? "3000" : "0"; ?>,
        timerProgressBar: <?php echo ($alert_type == "success" || $alert_type == "info") ? "true" : "false"; ?>
    }).then(() => {
        <?php if (($alert_type == 'success' || $alert_type == 'info') && !empty($redirect_url)): ?>
            setTimeout(() => { window.location.href = '<?php echo $redirect_url; ?>'; }, 500);
        <?php endif; ?>
    });
});
<?php endif; ?>

document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash) {
        const hash = window.location.hash; 
        const tabEl = document.querySelector(`.nav-tabs button[data-bs-target="${hash}"]`);
        if (tabEl) {
            const tab = new bootstrap.Tab(tabEl);
            tab.show();
            document.querySelector(hash)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
});

document.getElementById('profileForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'ยืนยันการบันทึก', text: 'ต้องการบันทึกข้อมูลส่วนตัวหรือไม่?', icon: 'question',
        showCancelButton: true, confirmButtonText: 'บันทึก', cancelButtonText: 'ยกเลิก'
    }).then(result => { if (result.isConfirmed) {this.submit();} });
});

document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'ยืนยันการรีเซ็ต', text: 'ต้องการรีเซ็ตรหัสบัตรประชาชนหรือไม่? การดำเนินการนี้จะกำหนดรหัสผ่านเริ่มต้นใหม่', icon: 'warning',
        showCancelButton: true, confirmButtonText: 'รีเซ็ต', cancelButtonText: 'ยกเลิก'
    }).then(result => { if (result.isConfirmed) {this.submit();} });
});

document.getElementById('unlinkGoogleForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'ยืนยัน', text: 'ต้องการยกเลิกการเชื่อมต่อ Google ของนักศึกษาคนนี้หรือไม่?', icon: 'warning',
        showCancelButton: true, confirmButtonText: 'ยกเลิกเชื่อมต่อ', cancelButtonText: 'ไม่'
    }).then(result => { if (result.isConfirmed) {this.submit();} });
});

document.getElementById('systemsForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'ยืนยันการบันทึก', text: 'ต้องการบันทึกข้อมูลการเข้าระบบหรือไม่?', icon: 'question',
        showCancelButton: true, confirmButtonText: 'บันทึก', cancelButtonText: 'ยกเลิก'
    }).then(result => { if (result.isConfirmed) {this.submit();} });
});

</script>
