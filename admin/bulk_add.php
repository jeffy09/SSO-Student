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

// ตัวแปรสำหรับเก็บข้อมูล
$success_count = 0;
$error_rows = [];
$total_rows = 0;
$alert_type = '';
$alert_message = '';
$show_alert = false;

// เพิ่มฟังก์ชันสำหรับ log การทำงาน
function logBulkAdd($message) {
    $log_dir = 'logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/bulk_add_' . date('Y-m-d') . '.log';
    $log_message = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// หากมีการ submit form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    logBulkAdd("Bulk add process started by admin ID: " . $_SESSION['admin_id']);
    
    try {
        // ตรวจสอบไฟล์ที่อัปโหลด
        $file = $_FILES['csv_file'];
        
        logBulkAdd("File upload attempt - Name: " . $file['name'] . ", Size: " . $file['size'] . ", Error: " . $file['error']);
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่เกินที่กำหนดในเซิร์ฟเวอร์',
                UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินที่กำหนดในฟอร์ม',
                UPLOAD_ERR_PARTIAL => 'ไฟล์อัปโหลดไม่สมบูรณ์',
                UPLOAD_ERR_NO_FILE => 'ไม่มีไฟล์ถูกอัปโหลด',
                UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราว',
                UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ลงดิสก์ได้',
                UPLOAD_ERR_EXTENSION => 'การอัปโหลดถูกหยุดโดย extension'
            ];
            $error_msg = $error_messages[$file['error']] ?? "เกิดข้อผิดพลาดในการอัปโหลดไฟล์ (Error: " . $file['error'] . ")";
            logBulkAdd("Upload error: " . $error_msg);
            throw new Exception($error_msg);
        }
        
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            logBulkAdd("No file uploaded or invalid upload");
            throw new Exception("ไม่พบไฟล์ที่อัปโหลดหรือไฟล์ไม่ถูกต้อง");
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'csv') {
            logBulkAdd("Invalid file extension: " . $file_ext);
            throw new Exception("กรุณาอัปโหลดไฟล์ CSV เท่านั้น");
        }
        
        if ($file['size'] > 2 * 1024 * 1024) { // 2MB
            logBulkAdd("File too large: " . $file['size'] . " bytes");
            throw new Exception("ขนาดไฟล์เกิน 2MB");
        }
        
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            logBulkAdd("Cannot open uploaded file");
            throw new Exception("ไม่สามารถอ่านไฟล์ได้");
        }
        
        $header = fgetcsv($handle);
        if (!$header || empty($header)) {
            fclose($handle);
            logBulkAdd("Empty or invalid CSV header");
            throw new Exception("ไฟล์ CSV ไม่มีข้อมูลหรือรูปแบบไม่ถูกต้อง");
        }
        
        $header = array_map(function($col) {
            return trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $col));
        }, $header);
        
        logBulkAdd("CSV header found: " . implode(', ', $header));
        
        $required_columns = ['student_id', 'id_card', 'firstname', 'lastname', 'email', 'faculty'];
        $missing_columns = array_diff($required_columns, $header);
        
        if (!empty($missing_columns)) {
            fclose($handle);
            $error_msg = "ไฟล์ CSV ขาดคอลัมน์ที่จำเป็น: " . implode(', ', $missing_columns);
            logBulkAdd($error_msg);
            throw new Exception($error_msg);
        }
        
        $db->beginTransaction();
        logBulkAdd("Database transaction started");
        
        $row_number = 1;
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_number++;
            
            if (empty(array_filter($data))) continue;
            
            $total_rows++;
            
            if (count($data) !== count($header)) {
                $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "จำนวนคอลัมน์ไม่ตรงกับส่วนหัว"];
                continue;
            }
            
            $row_data = array_combine($header, array_map('trim', $data));
            
            $error = false;
            foreach ($required_columns as $column) {
                if (empty($row_data[$column])) {
                    $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "ข้อมูล {$column} ว่างเปล่า"];
                    $error = true;
                    break;
                }
            }
            if ($error) continue;
            
            if (!filter_var($row_data['email'], FILTER_VALIDATE_EMAIL)) {
                $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "รูปแบบอีเมลไม่ถูกต้อง"];
                continue;
            }
            
            if (!preg_match('/^[0-9]{13}$/', $row_data['id_card'])) {
                $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "รหัสบัตรประชาชนต้องเป็นตัวเลข 13 หลัก"];
                continue;
            }
            
            $check_query = "SELECT COUNT(*) as count FROM students WHERE student_id = :student_id OR id_card = :id_card";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':student_id', $row_data['student_id']);
            $check_stmt->bindParam(':id_card', $row_data['id_card']);
            $check_stmt->execute();
            
            if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "รหัสนักศึกษาหรือรหัสบัตรประชาชนมีอยู่ในระบบแล้ว"];
                continue;
            }

            // --- Insert into students table ---
            $password_hash = password_hash($row_data['id_card'], PASSWORD_DEFAULT);
            $insert_student_query = "INSERT INTO students (student_id, id_card, password_hash, firstname, lastname, email, phone, faculty, department, address, first_login, created_at) 
                                     VALUES (:student_id, :id_card, :password_hash, :firstname, :lastname, :email, :phone, :faculty, :department, :address, 1, NOW())";
            $insert_stmt = $db->prepare($insert_student_query);

            $insert_stmt->bindParam(':student_id', $row_data['student_id']);
            $insert_stmt->bindParam(':id_card', $row_data['id_card']);
            $insert_stmt->bindParam(':password_hash', $password_hash);
            $insert_stmt->bindParam(':firstname', $row_data['firstname']);
            $insert_stmt->bindParam(':lastname', $row_data['lastname']);
            $insert_stmt->bindParam(':email', $row_data['email']);
            $insert_stmt->bindValue(':phone', $row_data['phone'] ?? '');
            $insert_stmt->bindParam(':faculty', $row_data['faculty']);
            $insert_stmt->bindValue(':department', $row_data['department'] ?? '');
            $insert_stmt->bindValue(':address', $row_data['address'] ?? '');

            if ($insert_stmt->execute()) {
                $student_db_id = $db->lastInsertId();

                // --- Insert into student_systems table ---
                $systems_to_add = [
                    'Email' => ['user_col' => 'email_user', 'pass_col' => 'email_pass', 'url' => 'https://mail.google.com'],
                    'Office 365' => ['user_col' => 'office365_user', 'pass_col' => 'office365_pass', 'url' => 'https://www.office.com'],
                    'Portal' => ['user_col' => 'portal_user', 'pass_col' => 'portal_pass', 'url' => 'https://portal.mbu.ac.th']
                ];

                $insert_system_query = "INSERT INTO student_systems (student_id, system_name, username, initial_password, system_url, manual_url) VALUES (:student_id, :system_name, :username, :initial_password, :system_url, :manual_url)";
                $system_stmt = $db->prepare($insert_system_query);
                
                foreach ($systems_to_add as $name => $details) {
                    if (!empty($row_data[$details['user_col']]) && !empty($row_data[$details['pass_col']])) {
                        $system_stmt->bindParam(':student_id', $student_db_id);
                        $system_stmt->bindParam(':system_name', $name);
                        $system_stmt->bindParam(':username', $row_data[$details['user_col']]);
                        $system_stmt->bindParam(':initial_password', $row_data[$details['pass_col']]);
                        $system_stmt->bindParam(':system_url', $details['url']);
                        $system_stmt->bindValue(':manual_url', $row_data['manual_url'] ?? '#'); 
                        $system_stmt->execute();
                    }
                }
                
                $success_count++;
                logBulkAdd("Successfully inserted student and system info for: " . $row_data['student_id']);
            } else {
                $error_info = $insert_stmt->errorInfo();
                $error_rows[] = ['row' => $row_number, 'data' => implode(',', $data), 'error' => "ไม่สามารถบันทึกข้อมูลได้: " . $error_info[2]];
                logBulkAdd("Failed to insert student: " . $row_data['student_id'] . " - " . $error_info[2]);
            }
        }
        
        fclose($handle);
        
        $log_action = "นำเข้าข้อมูลนักศึกษาแบบกลุ่ม: สำเร็จ {$success_count} รายการ จากทั้งหมด {$total_rows} รายการ";
        $log_query = "INSERT INTO admin_logs (admin_id, action, created_at) VALUES (:admin_id, :action, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->bindParam(':admin_id', $_SESSION['admin_id']);
        $log_stmt->bindParam(':action', $log_action);
        $log_stmt->execute();
        
        logBulkAdd("Admin log saved: " . $log_action);
        
        $db->commit();
        logBulkAdd("Database transaction committed successfully");
        
        $import_success = true;
        $alert_type = 'success';
        $alert_message = "นำเข้าข้อมูลสำเร็จ {$success_count} รายการ จากทั้งหมด {$total_rows} รายการ";
        
        if (count($error_rows) > 0) {
            $alert_message .= " (มีข้อผิดพลาด " . count($error_rows) . " รายการ)";
        }
        
        $show_alert = true;
        logBulkAdd("Bulk add process completed successfully");
        
    } catch(Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
            logBulkAdd("Database transaction rolled back due to error");
        }
        
        $alert_type = 'error';
        $alert_message = $e->getMessage();
        $show_alert = true;
        
        logBulkAdd("Bulk add process failed: " . $e->getMessage());
    }
}

$max_file_size = ini_get('upload_max_filesize');
$max_post_size = ini_get('post_max_size');
?>

<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>เพิ่มผู้ใช้งานแบบกลุ่ม</h2>
        <p class="text-muted">อัปโหลดไฟล์ CSV เพื่อเพิ่มผู้ใช้งานและข้อมูลระบบหลายรายการพร้อมกัน</p>
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
                <form action="?page=admin_bulk_add" method="post" enctype="multipart/form-data" id="uploadForm">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">เลือกไฟล์ CSV <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <div class="form-text">
                            <strong>ขนาดไฟล์สูงสุด:</strong> <?php echo $max_file_size; ?><br>
                            <strong>หมายเหตุ:</strong> รหัสบัตรประชาชนจะถูกใช้เป็นรหัสผ่านเริ่มต้นและเข้ารหัสอัตโนมัติ
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-upload me-2"></i>อัปโหลดและนำเข้าข้อมูล</button>
                </form>
            </div>
        </div>
        
        <?php if(isset($import_success) && $import_success): ?>
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white"><h5 class="card-title mb-0"><i class="fas fa-check-circle me-2"></i>ผลการนำเข้าข้อมูล</h5></div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-md-4"><div class="border rounded p-3"><h3 class="text-primary"><?php echo $total_rows; ?></h3><small class="text-muted">ทั้งหมด</small></div></div>
                        <div class="col-md-4"><div class="border rounded p-3"><h3 class="text-success"><?php echo $success_count; ?></h3><small class="text-muted">สำเร็จ</small></div></div>
                        <div class="col-md-4"><div class="border rounded p-3"><h3 class="text-danger"><?php echo count($error_rows); ?></h3><small class="text-muted">ผิดพลาด</small></div></div>
                    </div>
                    
                    <?php if(count($error_rows) > 0): ?>
                        <div class="alert alert-warning alert-permanent">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>รายการที่เกิดข้อผิดพลาด:</h6>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-bordered mt-2">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>แถวที่</th>
                                            <th>ข้อมูล</th>
                                            <th>สาเหตุ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($error_rows as $row): ?>
                                            <tr>
                                                <td><?php echo $row['row']; ?></td>
                                                <td><code class="small"><?php echo htmlspecialchars($row['data']); ?></code></td>
                                                <td><span class="text-danger small"><?php echo $row['error']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                         <div class="text-center mt-3">
                            <button type="button" class="btn btn-warning" onclick="downloadErrorReport()">
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
            <div class="card-header bg-info text-white"><h5 class="card-title mb-0"><i class="fas fa-file-csv me-2"></i>ตัวอย่างและโครงสร้างไฟล์ CSV</h5></div>
            <div class="card-body">
                <p>ไฟล์ CSV ต้องมี Encoding เป็น <strong>UTF-8</strong> และมีโครงสร้างคอลัมน์ตามลำดับดังนี้:</p>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">ส่วนหัว (Header):</label>
                    <div class="bg-light p-3 rounded small">
                        <code>student_id,id_card,firstname,lastname,email,faculty,department,phone,address,email_user,email_pass,office365_user,office365_pass,portal_user,portal_pass</code>
                    </div>
                </div>
                
                <div class="alert alert-info small">
                    <h6><i class="fas fa-info-circle me-2"></i>คำอธิบายคอลัมน์:</h6>
                    <ul class="mb-0">
                        <li><strong>คอลัมน์จำเป็น (นักศึกษา):</strong> student_id, id_card, firstname, lastname, email, faculty</li>
                        <li><strong>คอลัมน์เพิ่มเติม (นักศึกษา):</strong> department, phone, address</li>
                        <li><strong>คอลัมน์ข้อมูลระบบ:</strong>
                            <ul>
                                <li><code>email_user</code> / <code>email_pass</code>: ข้อมูลเข้าระบบ Email</li>
                                <li><code>office365_user</code> / <code>office365_pass</code>: ข้อมูลเข้าระบบ Office 365</li>
                                <li><code>portal_user</code> / <code>portal_pass</code>: ข้อมูลเข้าระบบ Portal</li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div class="mb-3">
                    <button type="button" class="btn btn-outline-primary" onclick="downloadSample()">
                        <i class="fas fa-download me-2"></i>ดาวน์โหลดไฟล์ตัวอย่าง
                    </button>
                </div>
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
        confirmButtonText: 'ตกลง'
    });
});
<?php endif; ?>

// ยืนยันการอัปโหลด
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('csv_file');
    if (!fileInput.files[0]) {
        Swal.fire('ข้อผิดพลาด', 'กรุณาเลือกไฟล์ CSV', 'error');
        return;
    }
    
    Swal.fire({
        title: 'ยืนยันการนำเข้าข้อมูล',
        text: 'คุณต้องการอัปโหลดและนำเข้าข้อมูลจากไฟล์นี้ใช่หรือไม่?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'กำลังประมวลผล...',
                text: 'กรุณารอสักครู่ ระบบกำลังนำเข้าข้อมูล',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            this.submit();
        }
    });
});

// ฟังก์ชันดาวน์โหลดไฟล์ตัวอย่าง
function downloadSample() {
    const csvContent = "student_id,id_card,firstname,lastname,email,faculty,department,phone,address,email_user,email_pass,office365_user,office365_pass,portal_user,portal_pass\n" +
                      "651234567,1234567890123,สมชาย,ตัวอย่าง,somchai.t@mbu.ac.th,วิศวกรรมศาสตร์,คอมพิวเตอร์,0812345678,กรุงเทพ,somchai.t@mbu.ac.th,Pass@1234,somchai.t@mbu.asia,Pass@1234,651234567,1234567890123\n" +
                      "651234568,1234567890124,สมหญิง,ทดสอบ,somying.t@mbu.ac.th,บริหารธุรกิจ,การตลาด,0898765432,นนทบุรี,somying.t@mbu.ac.th,Pass@5678,somying.t@mbu.asia,Pass@5678,651234568,1234567890124";
    
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'sample_students_import.csv';
    link.click();
}

// ฟังก์ชันดาวน์โหลดรายงานข้อผิดพลาด
function downloadErrorReport() {
    <?php if (!empty($error_rows)): ?>
    let csvContent = "แถวที่,ข้อมูล,สาเหตุ\n";
    
    <?php foreach($error_rows as $row): ?>
    csvContent += "<?php echo $row['row']; ?>," + 
                  "\"<?php echo str_replace('"', '""', $row['data']); ?>\"," + 
                  "\"<?php echo str_replace('"', '""', $row['error']); ?>\"\n";
    <?php endforeach; ?>
    
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'error_report_<?php echo date('Ymd_His'); ?>.csv';
    link.click();
    <?php endif; ?>
}
</script>
