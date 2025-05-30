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

// ตรวจสอบว่ามีการส่ง ID และ type มาหรือไม่
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    $_SESSION['error_message'] = "ข้อมูลไม่ครบถ้วนสำหรับการแก้ไข";
    header("Location: index.php?page=admin_users");
    exit;
}

$user_db_id = base64_decode($_GET['id']);
$user_type = $_GET['type'];

if (!is_numeric($user_db_id) || !in_array($user_type, ['student', 'teacher'])) {
    $_SESSION['error_message'] = "รหัสผู้ใช้หรือประเภทผู้ใช้ไม่ถูกต้อง";
    header("Location: index.php?page=admin_users");
    exit;
}

// กำหนดตารางและฟิลด์ตามประเภทผู้ใช้
$table_name = ($user_type === 'student') ? 'students' : 'teachers';
$id_column_name = ($user_type === 'student') ? 'student_id' : 'teacher_id';
$system_table_name = ($user_type === 'student') ? 'student_systems' : 'teacher_systems'; // สมมติว่ามี teacher_systems

// ตัวแปรสำหรับเก็บสถานะการแจ้งเตือน
$alert_type = '';
$alert_message = '';
$show_alert = false;
$redirect_url = '';

// ฟังก์ชันสำหรับบันทึก log (เหมือนเดิมจากไฟล์ต้นฉบับของคุณ)
function saveAdminLog($db, $admin_id, $action, $old_data = null, $new_data = null) {
    try {
        if (is_array($old_data) || is_object($old_data)) $old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);
        if (is_array($new_data) || is_object($new_data)) $new_data = json_encode($new_data, JSON_UNESCAPED_UNICODE);

        $log_query = "INSERT INTO admin_logs (admin_id, action, old_data, new_data, created_at) VALUES (:admin_id, :action, :old_data, :new_data, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->bindParam(':admin_id', $admin_id);
        $log_stmt->bindParam(':action', $action);
        $log_stmt->bindParam(':old_data', $old_data);
        $log_stmt->bindParam(':new_data', $new_data);
        $log_stmt->execute();
    } catch (PDOException $e) {
        error_log("Failed to save admin log: " . $e->getMessage());
    }
}

// ดึงข้อมูลผู้ใช้
try {
    $query_user = "SELECT * FROM {$table_name} WHERE id = :id LIMIT 1";
    $stmt_user = $db->prepare($query_user);
    $stmt_user->bindParam(':id', $user_db_id);
    $stmt_user->execute();

    if ($stmt_user->rowCount() == 0) {
        throw new Exception("ไม่พบข้อมูลผู้ใช้ ({$user_type})");
    }
    $user_original_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $user_data = $user_original_data; // ใช้ user_data ในฟอร์ม และ user_original_data สำหรับเปรียบเทียบ

    // ดึงข้อมูลระบบ (ถ้ามี)
    $user_systems_data_original = [];
    if ( ($user_type === 'student' && $db->query("SHOW TABLES LIKE 'student_systems'")->rowCount() > 0) ||
         ($user_type === 'teacher' && $db->query("SHOW TABLES LIKE 'teacher_systems'")->rowCount() > 0) ) {
        $query_systems = "SELECT * FROM {$system_table_name} WHERE {$user_type}_id = :user_id ORDER BY system_name";
        $stmt_systems_db = $db->prepare($query_systems);
        $stmt_systems_db->bindParam(':user_id', $user_data['id']);
        $stmt_systems_db->execute();
        $user_systems_data_original = $stmt_systems_db->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $systems_form_data = [];
    foreach ($user_systems_data_original as $sys) {
        $systems_form_data[$sys['system_name']] = $sys;
    }

} catch(Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: index.php?page=admin_users");
    exit;
}


// --- ส่วนของการจัดการ POST requests ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db->beginTransaction(); // Start transaction for all POST operations
    try {
        if (isset($_POST['update_profile'])) {
            $changed_fields_old = [];
            $changed_fields_new = [];

            $fields_to_check_common = ['firstname', 'lastname', 'email', 'phone'];
            $fields_to_check_specific = [];
            if ($user_type === 'student') {
                $fields_to_check_specific = ['faculty', 'department', 'address'];
            } elseif ($user_type === 'teacher') {
                $fields_to_check_specific = ['department', 'position']; // 'address' for teacher can be added if needed
            }
            $fields_to_check = array_merge($fields_to_check_common, $fields_to_check_specific);

            $update_set_parts = [];
            $execute_params = [':id' => $user_db_id];

            foreach ($fields_to_check as $field) {
                $posted_value = $database->sanitize($_POST[$field] ?? '');
                 if (!isset($user_original_data[$field]) || $user_original_data[$field] != $posted_value) {
                    $changed_fields_old[$field] = $user_original_data[$field] ?? null;
                    $changed_fields_new[$field] = $posted_value;
                    $update_set_parts[] = "{$field} = :{$field}";
                    $execute_params[":{$field}"] = $posted_value;
                }
            }
            
            // Validate required fields
            if (empty($changed_fields_new['firstname'] ?? $user_original_data['firstname']) ||
                empty($changed_fields_new['lastname'] ?? $user_original_data['lastname']) ||
                empty($changed_fields_new['email'] ?? $user_original_data['email']) ||
                ($user_type === 'student' && empty($changed_fields_new['faculty'] ?? $user_original_data['faculty']))
            ) {
                 throw new Exception("กรุณากรอกข้อมูลสำคัญให้ครบถ้วน (*)");
            }


            if (!empty($update_set_parts)) {
                $update_set_parts[] = "updated_at = NOW()";
                $update_query_sql = "UPDATE {$table_name} SET " . implode(', ', $update_set_parts) . " WHERE id = :id";
                $update_stmt = $db->prepare($update_query_sql);
                $update_stmt->execute($execute_params);

                saveAdminLog($db, $_SESSION['admin_id'], "แก้ไขข้อมูล{$user_type}: " . $user_original_data[$id_column_name], $changed_fields_old, $changed_fields_new);
                $user_data = array_merge($user_data, $changed_fields_new); // Update local data for display
                $alert_message = "อัพเดตข้อมูล{$user_type}สำเร็จ!";
            } else {
                $alert_message = "ไม่มีการเปลี่ยนแปลงข้อมูล{$user_type}";
            }
            $alert_type = 'success';
            $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'] . '&type=' . $user_type;

        } elseif (isset($_POST['reset_id_card'])) {
            $old_id_card_data = ['id_card_old_masked' => substr($user_original_data['id_card'], 0, 4) . 'XXXXXXXXX'];
            $new_id_card = $database->sanitize($_POST['new_id_card']);

            if (empty($new_id_card) || !preg_match('/^[0-9]{13}$/', $new_id_card)) {
                throw new Exception("รหัสบัตรประชาชนต้องเป็นตัวเลข 13 หลัก");
            }

            if ($user_original_data['id_card'] == $new_id_card) {
                 $alert_type = 'info';
                 $alert_message = 'รหัสบัตรประชาชนใหม่ตรงกับรหัสเดิม ไม่มีการเปลี่ยนแปลง';
            } else {
                // Check if new ID card already exists for *this user type*
                $check_id_query = "SELECT COUNT(*) as count FROM {$table_name} WHERE id_card = :id_card AND id != :id";
                $check_id_stmt = $db->prepare($check_id_query);
                $check_id_stmt->bindParam(':id_card', $new_id_card);
                $check_id_stmt->bindParam(':id', $user_db_id);
                $check_id_stmt->execute();
                
                if ($check_id_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                    throw new Exception("รหัสบัตรประชาชนนี้มีผู้ใช้งาน ({$user_type}) แล้ว");
                }

                $password_hash = password_hash($new_id_card, PASSWORD_DEFAULT);
                $update_query_sql = "UPDATE {$table_name} SET id_card = :idc, password_hash = :ph, first_login = 1, updated_at = NOW() WHERE id = :id";
                $update_stmt = $db->prepare($update_query_sql);
                $update_stmt->execute([':idc' => $new_id_card, ':ph' => $password_hash, ':id' => $user_db_id]);

                $new_id_card_data = ['id_card_new_masked' => substr($new_id_card, 0, 4) . 'XXXXXXXXX', 'password_status' => 'hashed'];
                saveAdminLog($db, $_SESSION['admin_id'], "รีเซ็ตรหัสบัตรประชาชน ({$user_type}): " . $user_original_data[$id_column_name], $old_id_card_data, $new_id_card_data);
                
                $user_data['id_card'] = $new_id_card;
                $user_data['password_hash'] = $password_hash;
                $user_data['first_login'] = 1;

                $alert_type = 'success';
                $alert_message = 'รีเซ็ตรหัสบัตรประชาชนสำเร็จ!';
            }
            $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'] . '&type=' . $user_type . '#security';

        } elseif (isset($_POST['unlink_google'])) {
             if (!empty($user_original_data['google_id'])) {
                $old_google_data = ['google_id_old' => $user_original_data['google_id']];
                $update_query_sql = "UPDATE {$table_name} SET google_id = NULL, access_token = NULL, refresh_token = NULL, token_expires_at = NULL, updated_at = NOW() WHERE id = :id";
                $update_stmt = $db->prepare($update_query_sql);
                $update_stmt->execute([':id' => $user_db_id]);

                $new_google_data = ['google_id_new' => null];
                saveAdminLog($db, $_SESSION['admin_id'], "ยกเลิก Google ({$user_type}): " . $user_original_data[$id_column_name], $old_google_data, $new_google_data);
                $user_data['google_id'] = NULL;
                $alert_type = 'success';
                $alert_message = 'ยกเลิกการเชื่อมต่อ Google สำเร็จ!';
            } else {
                $alert_type = 'info';
                $alert_message = "ผู้ใช้ ({$user_type}) ยังไม่ได้เชื่อมต่อ Google";
            }
            $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'] . '&type=' . $user_type . '#security';

        } elseif (isset($_POST['update_systems'])) {
             $systems_input = $_POST['systems'] ?? [];
             $overall_changes_old_sys = [];
             $overall_changes_new_sys = [];
             $changes_made_for_any_system_flag = false;

            // Delete systems not present in the form submission for this user
            $submitted_system_names = array_map(function($sys_data) use ($database) {
                return $database->sanitize($sys_data['system_name']);
            }, $systems_input);

            foreach($user_systems_data_original as $orig_sys) {
                if (!in_array($orig_sys['system_name'], $submitted_system_names)) {
                    $delete_sys_query = "DELETE FROM {$system_table_name} WHERE id = :sys_id";
                    $delete_sys_stmt = $db->prepare($delete_sys_query);
                    $delete_sys_stmt->execute([':sys_id' => $orig_sys['id']]);
                    $changes_made_for_any_system_flag = true;
                    $overall_changes_old_sys[$orig_sys['system_name']] = $orig_sys; // Log deleted system
                    $overall_changes_new_sys[$orig_sys['system_name']] = ['status' => 'deleted'];
                }
            }


             foreach ($systems_input as $form_key => $data) {
                $system_name_from_form = $database->sanitize($data['system_name']);
                $username_from_form = $database->sanitize($data['username']);
                $password_from_form = $database->sanitize($data['initial_password']);
                $system_url_from_form = $database->sanitize($data['system_url']);
                $manual_url_from_form = $database->sanitize($data['manual_url']);

                if (empty($system_name_from_form)) continue; // Skip if system name is empty

                $original_system_entry = null;
                $existing_system_id = null;

                foreach($user_systems_data_original as $orig_sys) {
                    if ($orig_sys['system_name'] === $system_name_from_form) {
                        $original_system_entry = $orig_sys;
                        $existing_system_id = $orig_sys['id'];
                        break;
                    }
                }
                
                $current_system_changes_old = [];
                $current_system_changes_new = [];

                if ($original_system_entry) { // Existing system, check for updates
                    if ($original_system_entry['username'] != $username_from_form) { $current_system_changes_old['username'] = $original_system_entry['username']; $current_system_changes_new['username'] = $username_from_form; }
                    if ($original_system_entry['initial_password'] != $password_from_form) { $current_system_changes_old['initial_password_masked'] = "******"; $current_system_changes_new['initial_password_masked'] = "******"; }
                    if ($original_system_entry['system_url'] != $system_url_from_form) { $current_system_changes_old['system_url'] = $original_system_entry['system_url']; $current_system_changes_new['system_url'] = $system_url_from_form; }
                    if ($original_system_entry['manual_url'] != $manual_url_from_form) { $current_system_changes_old['manual_url'] = $original_system_entry['manual_url']; $current_system_changes_new['manual_url'] = $manual_url_from_form; }

                    if (!empty($current_system_changes_new)) {
                        $changes_made_for_any_system_flag = true;
                        $update_sys_query = "UPDATE {$system_table_name} SET username = :un, initial_password = :pw, system_url = :surl, manual_url = :murl, updated_at = NOW() WHERE id = :sys_id";
                        $update_sys_stmt = $db->prepare($update_sys_query);
                        $update_sys_stmt->execute([':un' => $username_from_form, ':pw' => $password_from_form, ':surl' => $system_url_from_form, ':murl' => $manual_url_from_form, ':sys_id' => $existing_system_id]);
                        $overall_changes_old_sys[$system_name_from_form] = $current_system_changes_old;
                        $overall_changes_new_sys[$system_name_from_form] = $current_system_changes_new;
                    }
                } elseif (!empty($username_from_form) || !empty($password_from_form)) { // New system entry, only add if username or password is provided
                    $changes_made_for_any_system_flag = true;
                    $insert_sys_query = "INSERT INTO {$system_table_name} ({$user_type}_id, system_name, username, initial_password, system_url, manual_url, created_at) VALUES (:user_id, :sn, :un, :pw, :surl, :murl, NOW())";
                    $insert_sys_stmt = $db->prepare($insert_sys_query);
                    $insert_sys_stmt->execute([':user_id' => $user_data['id'], ':sn' => $system_name_from_form, ':un' => $username_from_form, ':pw' => $password_from_form, ':surl' => $system_url_from_form, ':murl' => $manual_url_from_form]);
                    $overall_changes_old_sys[$system_name_from_form] = ['status' => 'newly_added'];
                    $overall_changes_new_sys[$system_name_from_form] = ['username' => $username_from_form, 'initial_password_masked' => "******", 'system_url' => $system_url_from_form, 'manual_url' => $manual_url_from_form];
                }
             }
            
            if ($changes_made_for_any_system_flag) {
                saveAdminLog($db, $_SESSION['admin_id'], "แก้ไขข้อมูลระบบ ({$user_type}): " . $user_original_data[$id_column_name], $overall_changes_old_sys, $overall_changes_new_sys);
                $alert_message = 'อัปเดตข้อมูลการเข้าระบบสำเร็จ!';
            } else {
                $alert_message = 'ไม่มีการเปลี่ยนแปลงข้อมูลการเข้าระบบ';
            }
            $alert_type = 'success';
            $redirect_url = '?page=admin_edit_user&id=' . $_GET['id'] . '&type=' . $user_type . '#systems';
            
            // Refresh system data for display
            if (isset($stmt_systems_db)) {
                $stmt_systems_db->execute();
                $user_systems_data_original = $stmt_systems_db->fetchAll(PDO::FETCH_ASSOC);
                $systems_form_data = [];
                foreach ($user_systems_data_original as $sys) {
                    $systems_form_data[$sys['system_name']] = $sys;
                }
            }
        }
        $db->commit();
        $show_alert = true;

    } catch (Exception $e) {
        if($db->inTransaction()) $db->rollBack();
        $alert_type = 'error';
        $alert_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        $show_alert = true;
    }
}


$defined_systems = ['Email', 'Office 365', 'Portal']; // You might want a more dynamic way to manage this list
?>

<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>แก้ไขข้อมูล<?php echo $user_type === 'student' ? 'นักศึกษา' : 'อาจารย์'; ?></h2>
        <p>รหัส: <strong><?php echo htmlspecialchars($user_data[$id_column_name]); ?></strong></p>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=admin_users" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>กลับไปยังรายการผู้ใช้งาน
        </a>
         <a href="?page=admin_view_user&id=<?php echo $_GET['id']; ?>&type=<?php echo $user_type; ?>" class="btn btn-info">
            <i class="fas fa-eye me-2"></i>ดูข้อมูล
        </a>
    </div>
</div>

<ul class="nav nav-tabs mb-4" id="editUserTabs" role="tablist">
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

<div class="tab-content" id="editUserTabsContent">
    <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
        <div class="card shadow">
            <div class="card-header bg-primary text-white"><h5 class="card-title mb-0"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลส่วนตัว</h5></div>
            <div class="card-body">
                <form action="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>&type=<?php echo $user_type; ?>" method="post" id="profileForm">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="text-primary"><i class="fas fa-id-card me-2"></i>ข้อมูลหลัก</h5><hr>
                            <div class="mb-3">
                                <label class="form-label">รหัส<?php echo $user_type === 'student' ? 'นักศึกษา' : 'อาจารย์'; ?></label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data[$id_column_name]); ?>" readonly>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="firstname" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user_data['firstname']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastname" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user_data['lastname']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <?php if ($user_type === 'student'): ?>
                                <h5 class="text-primary"><i class="fas fa-graduation-cap me-2"></i>ข้อมูลการศึกษา</h5><hr>
                                <div class="mb-3">
                                    <label for="faculty" class="form-label">คณะ <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="faculty" name="faculty" value="<?php echo htmlspecialchars($user_data['faculty']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="department" class="form-label">สาขา</label>
                                    <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($user_data['department'] ?? ''); ?>">
                                </div>
                                 <div class="mb-3">
                                    <label for="address" class="form-label">ที่อยู่</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                                </div>
                            <?php elseif ($user_type === 'teacher'): ?>
                                <h5 class="text-primary"><i class="fas fa-building me-2"></i>ข้อมูลการทำงาน</h5><hr>
                                <div class="mb-3">
                                    <label for="department" class="form-label">ภาควิชา/แผนก</label>
                                    <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($user_data['department'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="position" class="form-label">ตำแหน่ง</label>
                                    <input type="text" class="form-control" id="position" name="position" value="<?php echo htmlspecialchars($user_data['position'] ?? ''); ?>">
                                </div>
                            <?php endif; ?>
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
                <form action="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>&type=<?php echo $user_type; ?>" method="post" id="resetPasswordForm">
                    <input type="hidden" name="reset_id_card" value="1">
                    <div class="mb-3">
                        <label class="form-label">รหัสบัตรประชาชนปัจจุบัน (แสดงบางส่วน):</label>
                        <input type="text" class="form-control" value="<?php echo substr($user_data['id_card'], 0, 4) . 'XXXXXXXXX'; ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="new_id_card" class="form-label">รหัสบัตรประชาชนใหม่ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new_id_card" name="new_id_card" required maxlength="13" pattern="[0-9]{13}">
                        <div class="form-text">กรอกรหัส 13 หลัก (ระบบจะเข้ารหัสและใช้เป็นรหัสผ่านเริ่มต้นใหม่)</div>
                    </div>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-key me-2"></i>รีเซ็ตรหัสบัตร</button>
                </form>
            </div>
        </div>
        <div class="card shadow">
            <div class="card-header bg-danger text-white"><h5 class="card-title mb-0"><i class="fas fa-unlink me-2"></i>การเชื่อมต่อ Google</h5></div>
            <div class="card-body">
                <?php if(!empty($user_data['google_id'])): ?>
                    <p>สถานะ: <span class="badge bg-success"><i class="fab fa-google"></i> เชื่อมต่อแล้ว</span> (Google ID: <?php echo htmlspecialchars($user_data['google_id']); ?>)</p>
                    <form action="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>&type=<?php echo $user_type; ?>" method="post" id="unlinkGoogleForm">
                        <input type="hidden" name="unlink_google" value="1">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-unlink me-2"></i>ยกเลิกการเชื่อมต่อ</button>
                    </form>
                <?php else: ?>
                    <p>สถานะ: <span class="badge bg-secondary">ยังไม่เชื่อมต่อ</span></p>
                    <p class="text-muted">ผู้ใช้ (<?php echo $user_type; ?>) สามารถเชื่อมต่อบัญชี Google ได้จากหน้าโปรไฟล์ของตนเอง</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="systems" role="tabpanel" aria-labelledby="systems-tab">
        <div class="card shadow">
             <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-cogs me-2"></i>แก้ไขข้อมูลการเข้าระบบ</h5>
                <button type="button" class="btn btn-sm btn-light" id="addSystemRowBtn"><i class="fas fa-plus-circle"></i> เพิ่มระบบ</button>
            </div>
            <div class="card-body">
                <form action="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>&type=<?php echo $user_type; ?>" method="post" id="systemsForm">
                    <input type="hidden" name="update_systems" value="1">
                    <div id="systemRowsContainer">
                        <?php
                        $default_urls = [
                            'Email' => 'https://mail.google.com/a/mbu.ac.th', // Example
                            'Office 365' => 'https://www.office.com/?auth=2&home=1&from=ShellLogo', // Example
                            'Portal' => 'https://portal.mbu.ac.th' // Example
                        ];
                        $default_manual_urls = []; // Can be populated if there are defaults

                        $current_system_index = 0;
                        foreach ($defined_systems as $system_key_name):
                            $sys_data = $systems_form_data[$system_key_name] ?? null;
                            $username_val = $sys_data['username'] ?? '';
                            $password_val = $sys_data['initial_password'] ?? '';
                            $system_url_val = $sys_data['system_url'] ?? ($default_urls[$system_key_name] ?? '');
                            $manual_url_val = $sys_data['manual_url'] ?? ($default_manual_urls[$system_key_name] ?? '');
                        ?>
                            <div class="card mb-3 system-entry">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <input type="text" class="form-control form-control-sm d-inline-block w-auto" name="systems[<?php echo $current_system_index; ?>][system_name]" value="<?php echo htmlspecialchars($system_key_name); ?>" placeholder="ชื่อระบบ">
                                    <button type="button" class="btn btn-xs btn-outline-danger remove-system-row-btn" title="ลบระบบนี้"><i class="fas fa-trash"></i></button>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" name="systems[<?php echo $current_system_index; ?>][username]" value="<?php echo htmlspecialchars($username_val); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Password เริ่มต้น</label>
                                            <input type="text" class="form-control" name="systems[<?php echo $current_system_index; ?>][initial_password]" value="<?php echo htmlspecialchars($password_val); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">URL เข้าระบบ</label>
                                            <input type="url" class="form-control" name="systems[<?php echo $current_system_index; ?>][system_url]" value="<?php echo htmlspecialchars($system_url_val); ?>">
                                        </div>
                                         <div class="col-md-6 mb-3">
                                            <label class="form-label">URL คู่มือ</label>
                                            <input type="url" class="form-control" name="systems[<?php echo $current_system_index; ?>][manual_url]" value="<?php echo htmlspecialchars($manual_url_val); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php
                        $current_system_index++;
                        endforeach;
                        // Display any other systems from DB not in defined_systems
                        foreach ($systems_form_data as $db_sys_name => $db_sys_data) {
                            if (!in_array($db_sys_name, $defined_systems)) {
                                ?>
                                 <div class="card mb-3 system-entry">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                         <input type="text" class="form-control form-control-sm d-inline-block w-auto" name="systems[<?php echo $current_system_index; ?>][system_name]" value="<?php echo htmlspecialchars($db_sys_name); ?>" placeholder="ชื่อระบบ">
                                         <button type="button" class="btn btn-xs btn-outline-danger remove-system-row-btn" title="ลบระบบนี้"><i class="fas fa-trash"></i></button>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3"><label class="form-label">Username</label><input type="text" class="form-control" name="systems[<?php echo $current_system_index; ?>][username]" value="<?php echo htmlspecialchars($db_sys_data['username']); ?>"></div>
                                            <div class="col-md-6 mb-3"><label class="form-label">Password เริ่มต้น</label><input type="text" class="form-control" name="systems[<?php echo $current_system_index; ?>][initial_password]" value="<?php echo htmlspecialchars($db_sys_data['initial_password']); ?>"></div>
                                            <div class="col-md-6 mb-3"><label class="form-label">URL เข้าระบบ</label><input type="url" class="form-control" name="systems[<?php echo $current_system_index; ?>][system_url]" value="<?php echo htmlspecialchars($db_sys_data['system_url']); ?>"></div>
                                            <div class="col-md-6 mb-3"><label class="form-label">URL คู่มือ</label><input type="url" class="form-control" name="systems[<?php echo $current_system_index; ?>][manual_url]" value="<?php echo htmlspecialchars($db_sys_data['manual_url']); ?>"></div>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                $current_system_index++;
                            }
                        }
                        ?>
                    </div>
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
let systemRowCounter = <?php echo $current_system_index; ?>;
document.getElementById('addSystemRowBtn')?.addEventListener('click', function() {
    const container = document.getElementById('systemRowsContainer');
    const newRow = document.createElement('div');
    newRow.classList.add('card', 'mb-3', 'system-entry');
    newRow.innerHTML = `
        <div class="card-header d-flex justify-content-between align-items-center">
            <input type="text" class="form-control form-control-sm d-inline-block w-auto" name="systems[${systemRowCounter}][system_name]" placeholder="ชื่อระบบใหม่" required>
            <button type="button" class="btn btn-xs btn-outline-danger remove-system-row-btn" title="ลบระบบนี้"><i class="fas fa-trash"></i></button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="systems[${systemRowCounter}][username]">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password เริ่มต้น</label>
                    <input type="text" class="form-control" name="systems[${systemRowCounter}][initial_password]">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">URL เข้าระบบ</label>
                    <input type="url" class="form-control" name="systems[${systemRowCounter}][system_url]">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">URL คู่มือ</label>
                    <input type="url" class="form-control" name="systems[${systemRowCounter}][manual_url]">
                </div>
            </div>
        </div>
    `;
    container.appendChild(newRow);
    attachRemoveSystemListeners();
    systemRowCounter++;
});

function attachRemoveSystemListeners() {
    document.querySelectorAll('.remove-system-row-btn').forEach(button => {
        button.removeEventListener('click', handleRemoveSystemRow); // Remove previous to avoid duplicates
        button.addEventListener('click', handleRemoveSystemRow);
    });
}

function handleRemoveSystemRow(event) {
    const systemEntryCard = event.target.closest('.system-entry');
    if (systemEntryCard) {
        Swal.fire({
            title: 'ยืนยันการลบ',
            text: "คุณแน่ใจหรือไม่ที่จะลบระบบนี้? การเปลี่ยนแปลงจะมีผลเมื่อบันทึก",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                 // Instead of removing, clear the system_name input to signify deletion on backend if it was an existing system.
                 // For newly added client-side rows, just remove.
                 const systemNameInput = systemEntryCard.querySelector('input[name$="[system_name]"]');
                 // A more robust way would be to add a hidden field like systems[index][deleted] = 1
                 // For simplicity here, we'll just remove from DOM. The backend needs to handle systems not submitted.
                systemEntryCard.remove();
                Swal.fire('ลบแล้ว!', 'ระบบถูกนำออกจากฟอร์ม (จะมีผลเมื่อกดบันทึกข้อมูลระบบ)', 'success');
            }
        });
    }
}
document.addEventListener('DOMContentLoaded', attachRemoveSystemListeners);


<?php if ($show_alert): ?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?php echo $alert_type; ?>',
        title: '<?php echo $alert_type == "success" ? "สำเร็จ!" : ($alert_type == "info" ? "แจ้งเพื่อทราบ" : "เกิดข้อผิดพลาด!"); ?>',
        html: '<?php echo addslashes(nl2br($alert_message)); ?>', // Use nl2br for multiline messages
        confirmButtonText: 'ตกลง',
        timer: <?php echo ($alert_type == "success" || $alert_type == "info") ? "4000" : "0"; ?>,
        timerProgressBar: <?php echo ($alert_type == "success" || $alert_type == "info") ? "true" : "false"; ?>
    }).then(() => {
        <?php if (($alert_type == 'success' || $alert_type == 'info') && !empty($redirect_url)): ?>
             // Delay redirect slightly to allow user to read message
             setTimeout(() => { window.location.href = '<?php echo $redirect_url; ?>'; }, 500);
        <?php endif; ?>
    });
});
<?php endif; ?>

// Handle tab switching with URL hash
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash) {
        const hash = window.location.hash; 
        const tabEl = document.querySelector(`.nav-tabs button[data-bs-target="${hash}"]`);
        if (tabEl) {
            const tab = new bootstrap.Tab(tabEl);
            tab.show();
             // Scroll to the tab content area after it's shown
            setTimeout(() => {
                const elementToScroll = document.querySelector(hash);
                if (elementToScroll) {
                     elementToScroll.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 100); // Small delay to ensure tab content is visible
        }
    }

    // Add hash to URL when tab is shown
    const tabLinks = document.querySelectorAll('#editUserTabs button[data-bs-toggle="tab"]');
    tabLinks.forEach(link => {
        link.addEventListener('shown.bs.tab', function(event) {
            history.replaceState(null, null, event.target.dataset.bsTarget);
             // Optional: Smooth scroll to the top of the tab content
            const targetEl = document.querySelector(event.target.dataset.bsTarget);
            if(targetEl) {
                // targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});

// Confirmation dialogs for forms
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
        showCancelButton: true, confirmButtonText: 'รีเซ็ต', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#ffc107',
    }).then(result => { if (result.isConfirmed) {this.submit();} });
});

document.getElementById('unlinkGoogleForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'ยืนยัน', text: 'ต้องการยกเลิกการเชื่อมต่อ Google ของผู้ใช้คนนี้หรือไม่?', icon: 'warning',
        showCancelButton: true, confirmButtonText: 'ยกเลิกเชื่อมต่อ', cancelButtonText: 'ไม่', confirmButtonColor: '#dc3545',
    }).then(result => { if (result.isConfirmed) {this.submit();} });
});

document.getElementById('systemsForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'ยืนยันการบันทึก', text: 'ต้องการบันทึกข้อมูลการเข้าระบบหรือไม่?', icon: 'question',
        showCancelButton: true, confirmButtonText: 'บันทึก', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#17a2b8',
    }).then(result => { if (result.isConfirmed) {this.submit();} });
});

</script>
<style>
    .btn-xs { padding: 0.1rem 0.3rem; font-size: 0.7rem; }
</style>
