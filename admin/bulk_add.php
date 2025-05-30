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

$success_count = 0;
$error_rows = [];
$total_rows_processed = 0; // Changed from total_rows to avoid conflict
$alert_type = '';
$alert_message = '';
$show_alert = false;

function logBulkAddActivity($message) { // Renamed function to avoid conflict
    $log_dir = 'logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/bulk_add_' . date('Y-m-d') . '.log';
    $log_message = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    logBulkAddActivity("Bulk add process started by admin ID: " . $_SESSION['admin_id']);
    
    try {
        $file = $_FILES['csv_file'];
        logBulkAddActivity("File upload attempt - Name: " . $file['name'] . ", Size: " . $file['size'] . ", Error: " . $file['error']);
        
        if ($file['error'] !== UPLOAD_ERR_OK) { /* ... error handling ... */ } //
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) { /* ... error handling ... */ } //
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'csv') { throw new Exception("กรุณาอัปโหลดไฟล์ CSV เท่านั้น"); } //
        if ($file['size'] > 5 * 1024 * 1024) { throw new Exception("ขนาดไฟล์เกิน 5MB"); } // Increased limit slightly

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) { throw new Exception("ไม่สามารถอ่านไฟล์ได้"); } //
        
        $header = fgetcsv($handle);
        if (!$header || empty($header)) { fclose($handle); throw new Exception("ไฟล์ CSV ไม่มีข้อมูลหรือรูปแบบไม่ถูกต้อง"); } //
        
        $header = array_map(function($col) { return trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $col)); }, $header);
        logBulkAddActivity("CSV header found: " . implode(', ', $header));
        
        // คอลัมน์ที่จำเป็นพื้นฐาน
        $required_base_columns = ['user_type', 'id_card', 'firstname', 'lastname', 'email'];
        // คอลัมน์ที่จำเป็นสำหรับนักศึกษา
        $required_student_columns = ['student_id', 'faculty'];
        // คอลัมน์ที่จำเป็นสำหรับอาจารย์
        $required_teacher_columns = ['teacher_id']; // 'department' และ 'position' เป็น optional

        $missing_base_columns = array_diff($required_base_columns, $header);
        if (!empty($missing_base_columns)) {
            fclose($handle);
            throw new Exception("ไฟล์ CSV ขาดคอลัมน์พื้นฐานที่จำเป็น: " . implode(', ', $missing_base_columns));
        }

        $db->beginTransaction();
        logBulkAddActivity("Database transaction started");
        
        $row_number = 1; // For header
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_number++;
            if (empty(array_filter($data))) continue;
            $total_rows_processed++;

            if (count($data) !== count($header)) {
                $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "จำนวนคอลัมน์ไม่ตรงกับส่วนหัว (" . count($data) . " vs " . count($header) . ")"];
                continue;
            }
            $row_data = array_combine($header, array_map('trim', $data));

            // Validate base required columns
            $current_user_type = strtolower($row_data['user_type'] ?? '');
            $error_in_row = false;
            foreach ($required_base_columns as $column) {
                if (empty($row_data[$column])) {
                    $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "ข้อมูล {$column} ว่างเปล่า"];
                    $error_in_row = true;
                    break;
                }
            }
            if ($error_in_row) continue;

            if (!in_array($current_user_type, ['student', 'teacher'])) {
                $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "user_type ไม่ถูกต้อง (ต้องเป็น student หรือ teacher)"];
                continue;
            }

            if (!filter_var($row_data['email'], FILTER_VALIDATE_EMAIL)) { /* ... email validation ... */ } //
            if (!preg_match('/^[0-9]{13}$/', $row_data['id_card'])) { /* ... id_card validation ... */ } //

            $password_hash = password_hash($row_data['id_card'], PASSWORD_DEFAULT);

            if ($current_user_type === 'student') {
                foreach ($required_student_columns as $column) {
                    if (empty($row_data[$column])) {
                        $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "ข้อมูลนักศึกษา {$column} ว่างเปล่า"];
                        $error_in_row = true; break;
                    }
                }
                if ($error_in_row) continue;

                $check_query = "SELECT COUNT(*) as count FROM students WHERE student_id = :user_code OR email = :email OR id_card = :id_card";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':user_code', $row_data['student_id']);
                $check_stmt->bindParam(':email', $row_data['email']);
                $check_stmt->bindParam(':id_card', $row_data['id_card']);
                $check_stmt->execute();
                if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                     $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "รหัสนักศึกษา, อีเมล, หรือรหัสบัตรประชาชน มีอยู่ในระบบแล้ว (นักศึกษา)"];
                     continue;
                }

                $insert_query = "INSERT INTO students (student_id, id_card, password_hash, firstname, lastname, email, phone, faculty, department, address, first_login, created_at) 
                                 VALUES (:student_id, :id_card, :password_hash, :firstname, :lastname, :email, :phone, :faculty, :department, :address, 1, NOW())";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':student_id', $row_data['student_id']);
                $insert_stmt->bindParam(':id_card', $row_data['id_card']);
                // ... bind other student params ...
                 $insert_stmt->bindParam(':password_hash', $password_hash);
                 $insert_stmt->bindParam(':firstname', $row_data['firstname']);
                 $insert_stmt->bindParam(':lastname', $row_data['lastname']);
                 $insert_stmt->bindParam(':email', $row_data['email']);
                 $insert_stmt->bindValue(':phone', $row_data['phone'] ?? '');
                 $insert_stmt->bindParam(':faculty', $row_data['faculty']);
                 $insert_stmt->bindValue(':department', $row_data['department'] ?? '');
                 $insert_stmt->bindValue(':address', $row_data['address'] ?? '');


            } elseif ($current_user_type === 'teacher') {
                 foreach ($required_teacher_columns as $column) {
                    if (empty($row_data[$column])) {
                        $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "ข้อมูลอาจารย์ {$column} ว่างเปล่า"];
                        $error_in_row = true; break;
                    }
                }
                if ($error_in_row) continue;

                $check_query = "SELECT COUNT(*) as count FROM teachers WHERE teacher_id = :user_code OR email = :email OR id_card = :id_card";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':user_code', $row_data['teacher_id']);
                $check_stmt->bindParam(':email', $row_data['email']);
                $check_stmt->bindParam(':id_card', $row_data['id_card']);
                $check_stmt->execute();
                 if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                     $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "รหัสอาจารย์, อีเมล, หรือรหัสบัตรประชาชน มีอยู่ในระบบแล้ว (อาจารย์)"];
                     continue;
                }
                
                $insert_query = "INSERT INTO teachers (teacher_id, id_card, password_hash, firstname, lastname, email, phone, department, position, first_login, created_at) 
                                 VALUES (:teacher_id, :id_card, :password_hash, :firstname, :lastname, :email, :phone, :department, :position, 1, NOW())";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bindParam(':teacher_id', $row_data['teacher_id']);
                $insert_stmt->bindParam(':id_card', $row_data['id_card']);
                // ... bind other teacher params ...
                $insert_stmt->bindParam(':password_hash', $password_hash);
                $insert_stmt->bindParam(':firstname', $row_data['firstname']);
                $insert_stmt->bindParam(':lastname', $row_data['lastname']);
                $insert_stmt->bindParam(':email', $row_data['email']);
                $insert_stmt->bindValue(':phone', $row_data['phone'] ?? '');
                $insert_stmt->bindValue(':department', $row_data['department'] ?? ''); // CSV column name for teacher dept
                $insert_stmt->bindValue(':position', $row_data['position'] ?? '');


            }

            if ($insert_stmt->execute()) {
                $user_db_id = $db->lastInsertId();
                // --- Insert into student_systems or teacher_systems table ---
                $system_table_to_use = ($current_user_type === 'student') ? 'student_systems' : 'teacher_systems';
                $system_user_id_column = ($current_user_type === 'student') ? 'student_id' : 'teacher_id';

                $systems_to_add_details = [ /* ... as before ... */ ]; //
                $insert_system_q = "INSERT INTO {$system_table_to_use} ({$system_user_id_column}, system_name, username, initial_password, system_url, manual_url) VALUES (:user_id, :system_name, :username, :initial_password, :system_url, :manual_url)";
                $system_stmt_exec = $db->prepare($insert_system_q);

                // Example for 'Email' system - adapt for others
                if (isset($row_data['email_user']) && isset($row_data['email_pass'])) {
                     if (!empty($row_data['email_user']) || !empty($row_data['email_pass'])) { // Add only if user/pass is present
                        $system_stmt_exec->execute([
                            ':user_id' => $user_db_id,
                            ':system_name' => 'Email',
                            ':username' => $row_data['email_user'],
                            ':initial_password' => $row_data['email_pass'],
                            ':system_url' => $systems_to_add_details['Email']['url'] ?? 'https://mail.google.com',
                            ':manual_url' => $row_data['email_manual_url'] ?? '#' // Assuming you add manual_url columns in CSV
                        ]);
                    }
                }
                // Repeat for Office 365, Portal, etc.

                $success_count++;
                logBulkAddActivity("Successfully inserted {$current_user_type}: " . ($row_data[$required_base_columns[0]] ?? 'N/A'));
            } else {
                $error_info = $insert_stmt->errorInfo();
                $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "DB Error: " . $error_info[2]];
                logBulkAddActivity("Failed to insert {$current_user_type}: " . ($row_data[$required_base_columns[0]] ?? 'N/A') . " - " . $error_info[2]);
            }
        }
        
        fclose($handle);
        $log_action = "นำเข้าข้อมูลผู้ใช้แบบกลุ่ม: สำเร็จ {$success_count} รายการ จากทั้งหมด {$total_rows_processed} รายการ"; //
        // ... (save admin log) ...
        saveAdminLog($db, $_SESSION['admin_id'], $log_action);


        $db->commit();
        logBulkAddActivity("Database transaction committed");
        
        $import_success_flag = true; // Renamed to avoid conflict
        $alert_type = 'success';
        $alert_message = "นำเข้าข้อมูลสำเร็จ {$success_count} รายการ จากทั้งหมด {$total_rows_processed} รายการ";
        if (count($error_rows) > 0) { $alert_message .= " (มีข้อผิดพลาด " . count($error_rows) . " รายการ)"; } //
        $show_alert = true;
        logBulkAddActivity("Bulk add process completed.");

    } catch(Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); logBulkAddActivity("Transaction rolled back."); } //
        $alert_type = 'error'; $alert_message = $e->getMessage(); $show_alert = true; //
        logBulkAddActivity("Bulk add process failed: " . $e->getMessage());
    }
}
// ... (HTML part, similar to original, but update sample CSV header and instructions)
?>
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>เพิ่มผู้ใช้งานแบบกลุ่ม</h2>
        <p class="text-muted">อัปโหลดไฟล์ CSV เพื่อเพิ่มนักศึกษาและอาจารย์พร้อมข้อมูลระบบ</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=admin_users" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>กลับไปยังรายการผู้ใช้งาน</a>
    </div>
</div>

<div class="row">
    <div class="col-md-7">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="fas fa-upload me-2"></i>อัปโหลดไฟล์ CSV</h5>
            </div>
            <div class="card-body">
                <form action="?page=admin_bulk_add" method="post" enctype="multipart/form-data" id="uploadFormBulk">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">เลือกไฟล์ CSV <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <div class="form-text">
                             ขนาดไฟล์สูงสุด: <?php echo ini_get('upload_max_filesize'); ?>.
                            รหัสบัตรประชาชนจะถูกใช้เป็นรหัสผ่านเริ่มต้นและเข้ารหัสอัตโนมัติ.
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-upload me-2"></i>อัปโหลดและนำเข้า</button>
                </form>
            </div>
        </div>
        
        <?php if(isset($import_success_flag) && $import_success_flag): ?>
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white"><h5 class="card-title mb-0"><i class="fas fa-check-circle me-2"></i>ผลการนำเข้า</h5></div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-md-4"><div class="border rounded p-3"><h3 class="text-primary"><?php echo $total_rows_processed; ?></h3><small class="text-muted">ทั้งหมดในไฟล์</small></div></div>
                        <div class="col-md-4"><div class="border rounded p-3"><h3 class="text-success"><?php echo $success_count; ?></h3><small class="text-muted">สำเร็จ</small></div></div>
                        <div class="col-md-4"><div class="border rounded p-3"><h3 class="text-danger"><?php echo count($error_rows); ?></h3><small class="text-muted">ผิดพลาด</small></div></div>
                    </div>
                    
                    <?php if(count($error_rows) > 0): ?>
                        <div class="alert alert-warning alert-permanent">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>รายการที่เกิดข้อผิดพลาด:</h6>
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-bordered mt-2">
                                    <thead class="table-light sticky-top"><tr><th>แถวที่</th><th>ข้อมูล</th><th>สาเหตุ</th></tr></thead>
                                    <tbody>
                                        <?php foreach($error_rows as $row_err): ?>
                                            <tr><td><?php echo $row_err['row']; ?></td><td><code class="small"><?php echo htmlspecialchars($row_err['data']); ?></code></td><td><span class="text-danger small"><?php echo htmlspecialchars($row_err['error']); ?></span></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                         <div class="text-center mt-3">
                            <button type="button" class="btn btn-warning" onclick="downloadErrorCsvReport()">
                                <i class="fas fa-download me-2"></i>ดาวน์โหลดรายงานข้อผิดพลาด
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-5">
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white"><h5 class="card-title mb-0"><i class="fas fa-file-csv me-2"></i>โครงสร้างไฟล์ CSV</h5></div>
            <div class="card-body">
                <p>ไฟล์ CSV ต้องมี Encoding เป็น <strong>UTF-8</strong>. คอลัมน์ <code>user_type</code> ต้องเป็น 'student' หรือ 'teacher'.</p>
                <div class="mb-3">
                    <label class="form-label fw-bold">ส่วนหัว (Header) ตัวอย่าง:</label>
                    <div class="bg-light p-2 rounded small" style="overflow-x: auto; white-space: nowrap;">
                        <code>user_type,student_id,teacher_id,id_card,firstname,lastname,email,faculty,department,phone,address,position,email_user,email_pass,office365_user,office365_pass,portal_user,portal_pass,email_manual_url,office365_manual_url,portal_manual_url</code>
                    </div>
                </div>
                <div class="alert alert-info small">
                    <h6><i class="fas fa-info-circle me-2"></i>คำอธิบายคอลัมน์สำคัญ:</h6>
                    <ul class="mb-0">
                        <li><strong>user_type<span class="text-danger">*</span>:</strong> <code>student</code> หรือ <code>teacher</code></li>
                        <li><strong>id_card<span class="text-danger">*</span>, firstname<span class="text-danger">*</span>, lastname<span class="text-danger">*</span>, email<span class="text-danger">*</span>:</strong> จำเป็นสำหรับทุกประเภท</li>
                        <li><strong>สำหรับ Student<span class="text-danger">*</span>:</strong> <code>student_id</code>, <code>faculty</code></li>
                        <li><strong>สำหรับ Teacher<span class="text-danger">*</span>:</strong> <code>teacher_id</code></li>
                        <li><code>department</code>, <code>phone</code>, <code>address</code>, <code>position</code>: เป็น Optional</li>
                        <li><code>*_user</code>, <code>*_pass</code>, <code>*_manual_url</code>: สำหรับข้อมูลเข้าระบบย่อย (ถ้ามี)</li>
                    </ul>
                </div>
                <button type="button" class="btn btn-outline-primary" onclick="downloadSampleCsv()">
                    <i class="fas fa-download me-2"></i>ดาวน์โหลดไฟล์ตัวอย่าง (รวม Student & Teacher)
                </button>
            </div>
        </div>
    </div>
</div>

<script>
<?php if ($show_alert): ?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?php echo $alert_type; ?>',
        title: '<?php echo $alert_type == "success" ? "สำเร็จ!" : "เกิดข้อผิดพลาด!"; ?>',
        html: '<?php echo addslashes(nl2br($alert_message)); ?>', // ใช้ nl2br เผื่อข้อความยาว
        confirmButtonText: 'ตกลง'
    });
});
<?php endif; ?>

document.getElementById('uploadFormBulk')?.addEventListener('submit', function(e) { /* ... SweetAlert confirmation ... */ }); //

function downloadSampleCsv() {
    const csvHeader = "user_type,student_id,teacher_id,id_card,firstname,lastname,email,faculty,department,phone,address,position,email_user,email_pass,office365_user,office365_pass,portal_user,portal_pass,email_manual_url,office365_manual_url,portal_manual_url\n";
    const studentSample = "student,S0001,,1111111111111,สมชาย,เรียนดี,somchai.s@mbu.ac.th,วิศวกรรมศาสตร์,คอมพิวเตอร์,0810001111,123 กทม.,,somchai.s@mbu.ac.th,StudentPass1,somchai.s@mbu.asia,StudentPass1,S0001,1111111111111,#,#,#\n";
    const teacherSample = "teacher,,T001,2222222222222,สมศรี,สอนเก่ง,somsri.t@mbu.ac.th,,ศึกษาศาสตร์,0820002222,456 เชียงใหม่,อาจารย์,somsri.t@mbu.ac.th,TeacherPass1,somsri.t@mbu.asia,TeacherPass1,somsri.t,2222222222222,#,#,#\n";
    const csvContent = csvHeader + studentSample + teacherSample;
    
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'sample_users_import.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

function downloadErrorCsvReport() {
    <?php if (!empty($error_rows)): ?>
    let csvContentErr = "แถวที่ในไฟล์CSV,ข้อมูล,สาเหตุข้อผิดพลาด\n";
    <?php foreach($error_rows as $row_item): ?>
    csvContentErr += "<?php echo $row_item['row']; ?>," + 
                     "\"<?php echo str_replace('"', '""', htmlspecialchars($row_item['data'])); ?>\"," + 
                     "\"<?php echo str_replace('"', '""', htmlspecialchars($row_item['error'])); ?>\"\n";
    <?php endforeach; ?>
    
    const blobErr = new Blob(['\ufeff' + csvContentErr], { type: 'text/csv;charset=utf-8;' });
    const linkErr = document.createElement('a');
    linkErr.href = URL.createObjectURL(blobErr);
    linkErr.download = 'import_error_report_<?php echo date('Ymd_His'); ?>.csv';
    linkErr.click();
    URL.revokeObjectURL(linkErr.href);
    <?php endif; ?>
}
</script>
