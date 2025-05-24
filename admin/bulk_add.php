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
        
        // ตรวจสอบข้อผิดพลาด
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
            
            $error_msg = isset($error_messages[$file['error']]) ? $error_messages[$file['error']] : "เกิดข้อผิดพลาดในการอัปโหลดไฟล์ (Error: " . $file['error'] . ")";
            logBulkAdd("Upload error: " . $error_msg);
            throw new Exception($error_msg);
        }
        
        // ตรวจสอบว่ามีไฟล์ถูกอัปโหลดจริง
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            logBulkAdd("No file uploaded or invalid upload");
            throw new Exception("ไม่พบไฟล์ที่อัปโหลดหรือไฟล์ไม่ถูกต้อง");
        }
        
        // ตรวจสอบนามสกุลไฟล์
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'csv') {
            logBulkAdd("Invalid file extension: " . $file_ext);
            throw new Exception("กรุณาอัปโหลดไฟล์ CSV เท่านั้น");
        }
        
        // ตรวจสอบขนาดไฟล์ (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            logBulkAdd("File too large: " . $file['size'] . " bytes");
            throw new Exception("ขนาดไฟล์เกิน 2MB");
        }
        
        // ตรวจสอบว่าไฟล์เป็น CSV จริงๆ โดยลองอ่านบรรทัดแรก
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            logBulkAdd("Cannot open uploaded file");
            throw new Exception("ไม่สามารถอ่านไฟล์ได้");
        }
        
        // อ่านส่วนหัว (header) และทำความสะอาด
        $header = fgetcsv($handle);
        if (!$header || empty($header)) {
            fclose($handle);
            logBulkAdd("Empty or invalid CSV header");
            throw new Exception("ไฟล์ CSV ไม่มีข้อมูลหรือรูปแบบไม่ถูกต้อง");
        }
        
        // ทำความสะอาด header - ลบ whitespace และ BOM
        $header = array_map(function($col) {
            // ลบ BOM (Byte Order Mark) ถ้ามี
            $col = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $col);
            // ลบ whitespace
            return trim($col);
        }, $header);
        
        logBulkAdd("CSV header found: " . implode(', ', $header));
        
        // ตรวจสอบว่ามีคอลัมน์ที่จำเป็นหรือไม่
        $required_columns = ['student_id', 'id_card', 'firstname', 'lastname', 'email', 'faculty'];
        $missing_columns = [];
        
        foreach ($required_columns as $column) {
            if (!in_array($column, $header)) {
                $missing_columns[] = $column;
            }
        }
        
        if (!empty($missing_columns)) {
            fclose($handle);
            $error_msg = "ไฟล์ CSV ขาดคอลัมน์ที่จำเป็น: " . implode(', ', $missing_columns) . "\nคอลัมน์ที่พบ: " . implode(', ', $header);
            logBulkAdd($error_msg);
            throw new Exception($error_msg);
        }
        
        // เริ่ม transaction
        $db->beginTransaction();
        logBulkAdd("Database transaction started");
        
        $row_number = 1; // เริ่มนับจากแถวที่ 2 (แถวแรกเป็น header)
        
        // ประมวลผลข้อมูลในแต่ละแถว
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_number++;
            
            // ข้ามบรรทัดว่าง
            if (empty(array_filter($data))) {
                continue;
            }
            
            $total_rows++;
            
            // ตรวจสอบว่าจำนวนคอลัมน์ตรงกับ header หรือไม่
            if (count($data) !== count($header)) {
                $error_rows[] = [
                    'row' => $row_number,
                    'data' => implode(',', $data),
                    'error' => "จำนวนคอลัมน์ไม่ตรงกับส่วนหัว (พบ " . count($data) . " คาดหวัง " . count($header) . ")"
                ];
                continue;
            }
            
            // สร้าง associative array จาก header และข้อมูล
            $row_data = array_combine($header, $data);
            
            // ทำความสะอาดข้อมูล
            $row_data = array_map('trim', $row_data);
            
            // ตรวจสอบข้อมูลที่จำเป็น
            $error = false;
            foreach ($required_columns as $column) {
                if (empty($row_data[$column])) {
                    $error_rows[] = [
                        'row' => $row_number,
                        'data' => implode(',', $data),
                        'error' => "ข้อมูล {$column} ว่างเปล่า"
                    ];
                    $error = true;
                    break;
                }
            }
            
            if ($error) {
                continue;
            }
            
            // ตรวจสอบรูปแบบอีเมล
            if (!filter_var($row_data['email'], FILTER_VALIDATE_EMAIL)) {
                $error_rows[] = [
                    'row' => $row_number,
                    'data' => implode(',', $data),
                    'error' => "รูปแบบอีเมลไม่ถูกต้อง: {$row_data['email']}"
                ];
                continue;
            }
            
            // ตรวจสอบรูปแบบรหัสบัตรประชาชน
            if (!preg_match('/^[0-9]{13}$/', $row_data['id_card'])) {
                $error_rows[] = [
                    'row' => $row_number,
                    'data' => implode(',', $data),
                    'error' => "รหัสบัตรประชาชนต้องเป็นตัวเลข 13 หลัก: {$row_data['id_card']}"
                ];
                continue;
            }
            
            // ตรวจสอบว่ารหัสนักศึกษาซ้ำหรือไม่
            $check_query = "SELECT COUNT(*) as count FROM students WHERE student_id = :student_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':student_id', $row_data['student_id']);
            $check_stmt->execute();
            
            if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                $error_rows[] = [
                    'row' => $row_number,
                    'data' => implode(',', $data),
                    'error' => "รหัสนักศึกษา {$row_data['student_id']} มีอยู่ในระบบแล้ว"
                ];
                continue;
            }
            
            // ตรวจสอบว่ารหัสบัตรประชาชนซ้ำหรือไม่
            $check_id_query = "SELECT COUNT(*) as count FROM students WHERE id_card = :id_card";
            $check_id_stmt = $db->prepare($check_id_query);
            $check_id_stmt->bindParam(':id_card', $row_data['id_card']);
            $check_id_stmt->execute();
            
            if ($check_id_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                $error_rows[] = [
                    'row' => $row_number,
                    'data' => implode(',', $data),
                    'error' => "รหัสบัตรประชาชน {$row_data['id_card']} มีอยู่ในระบบแล้ว"
                ];
                continue;
            }
            
            // เตรียมข้อมูลสำหรับบันทึก
            $student_id = $database->sanitize($row_data['student_id']);
            $id_card = $database->sanitize($row_data['id_card']);
            $firstname = $database->sanitize($row_data['firstname']);
            $lastname = $database->sanitize($row_data['lastname']);
            $email = $database->sanitize($row_data['email']);
            $faculty = $database->sanitize($row_data['faculty']);
            
            // ข้อมูลที่อาจไม่มี
            $phone = isset($row_data['phone']) ? $database->sanitize($row_data['phone']) : '';
            $department = isset($row_data['department']) ? $database->sanitize($row_data['department']) : '';
            $address = isset($row_data['address']) ? $database->sanitize($row_data['address']) : '';
            
            // เข้ารหัสรหัสบัตรประชาชน
            $password_hash = password_hash($id_card, PASSWORD_DEFAULT);
            
            // เตรียมคำสั่ง SQL สำหรับเพิ่มข้อมูล
            $insert_query = "INSERT INTO students (student_id, id_card, password_hash, firstname, lastname, email, phone, faculty, department, address, first_login, created_at) 
                            VALUES (:student_id, :id_card, :password_hash, :firstname, :lastname, :email, :phone, :faculty, :department, :address, 1, NOW())";
            
            $insert_stmt = $db->prepare($insert_query);
            
            // Bind parameters
            $insert_stmt->bindParam(':student_id', $student_id);
            $insert_stmt->bindParam(':id_card', $id_card);
            $insert_stmt->bindParam(':password_hash', $password_hash);
            $insert_stmt->bindParam(':firstname', $firstname);
            $insert_stmt->bindParam(':lastname', $lastname);
            $insert_stmt->bindParam(':email', $email);
            $insert_stmt->bindParam(':phone', $phone);
            $insert_stmt->bindParam(':faculty', $faculty);
            $insert_stmt->bindParam(':department', $department);
            $insert_stmt->bindParam(':address', $address);
            
            // Execute
            if ($insert_stmt->execute()) {
                $success_count++;
                logBulkAdd("Successfully inserted student: " . $student_id);
            } else {
                $error_info = $insert_stmt->errorInfo();
                $error_rows[] = [
                    'row' => $row_number,
                    'data' => implode(',', $data),
                    'error' => "ไม่สามารถบันทึกข้อมูลได้: " . $error_info[2]
                ];
                logBulkAdd("Failed to insert student: " . $student_id . " - " . $error_info[2]);
            }
        }
        
        fclose($handle);
        
        // บันทึก log
        $log_action = "นำเข้าข้อมูลนักศึกษาแบบกลุ่ม: สำเร็จ {$success_count} รายการ จากทั้งหมด {$total_rows} รายการ";
        $log_query = "INSERT INTO admin_logs (admin_id, action, created_at) VALUES (:admin_id, :action, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->bindParam(':admin_id', $_SESSION['admin_id']);
        $log_stmt->bindParam(':action', $log_action);
        $log_stmt->execute();
        
        logBulkAdd("Admin log saved: " . $log_action);
        
        // Commit transaction
        $db->commit();
        logBulkAdd("Database transaction committed successfully");
        
        // กำหนดข้อความแจ้งเตือนสำเร็จ
        $import_success = true;
        $alert_type = 'success';
        $alert_message = "นำเข้าข้อมูลสำเร็จ {$success_count} รายการ จากทั้งหมด {$total_rows} รายการ";
        
        if (count($error_rows) > 0) {
            $alert_message .= " (มีข้อผิดพลาด " . count($error_rows) . " รายการ)";
        }
        
        $show_alert = true;
        
        logBulkAdd("Bulk add process completed successfully");
        
    } catch(Exception $e) {
        // Rollback transaction ในกรณีที่เกิดข้อผิดพลาด
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
            logBulkAdd("Database transaction rolled back due to error");
        }
        
        $alert_type = 'error';
        $alert_message = $e->getMessage();
        $show_alert = true;
        
        logBulkAdd("Bulk add process failed: " . $e->getMessage());
    }
}

// เพิ่มการตรวจสอบ PHP settings
$max_file_size = ini_get('upload_max_filesize');
$max_post_size = ini_get('post_max_size');
$memory_limit = ini_get('memory_limit');

logBulkAdd("PHP Settings - upload_max_filesize: $max_file_size, post_max_size: $max_post_size, memory_limit: $memory_limit");
?>

<!-- เพิ่ม SweetAlert2 CSS และ JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>เพิ่มผู้ใช้งานแบบกลุ่ม</h2>
        <p class="text-muted">อัปโหลดไฟล์ CSV เพื่อเพิ่มผู้ใช้งานหลายรายการพร้อมกัน</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=admin_users" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>กลับไปยังรายการผู้ใช้งาน
        </a>
    </div>
</div>

<!-- แสดง PHP Configuration Info -->
<div class="row mb-3">
    <div class="col-12">
        <div class="alert alert-info">
            <small>
                <strong>การตั้งค่าเซิร์ฟเวอร์:</strong> 
                ขนาดไฟล์สูงสุด: <?php echo $max_file_size; ?> | 
                ขนาด POST สูงสุด: <?php echo $max_post_size; ?> | 
                หน่วยความจำ: <?php echo $memory_limit; ?>
            </small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-7">
        <!-- แบบฟอร์มอัปโหลดไฟล์ CSV -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-upload me-2"></i>อัปโหลดไฟล์ CSV
                </h5>
            </div>
            <div class="card-body">
                <form action="?page=admin_bulk_add" method="post" enctype="multipart/form-data" id="uploadForm">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">เลือกไฟล์ CSV <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <div class="form-text">
                            <strong>คอลัมน์ที่จำเป็น:</strong> student_id, id_card, firstname, lastname, email, faculty<br>
                            <strong>คอลัมน์เพิ่มเติม:</strong> department, phone, address<br>
                            <strong>ขนาดไฟล์สูงสุด:</strong> <?php echo $max_file_size; ?><br>
                            <strong>หมายเหตุ:</strong> รหัสบัตรประชาชนจะถูกเข้ารหัสอัตโนมัติ
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-upload me-2"></i>อัปโหลดและนำเข้าข้อมูล
                    </button>
                </form>
            </div>
        </div>
        
        <?php if(isset($import_success) && $import_success): ?>
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-check-circle me-2"></i>ผลการนำเข้าข้อมูล
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <h3 class="text-primary"><?php echo $total_rows; ?></h3>
                                <small class="text-muted">จำนวนทั้งหมด</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <h3 class="text-success"><?php echo $success_count; ?></h3>
                                <small class="text-muted">นำเข้าสำเร็จ</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <h3 class="text-danger"><?php echo count($error_rows); ?></h3>
                                <small class="text-muted">เกิดข้อผิดพลาด</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if(count($error_rows) > 0): ?>
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>รายการที่เกิดข้อผิดพลาด:</h6>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-bordered mt-2">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th width="10%">แถวที่</th>
                                            <th width="50%">ข้อมูล</th>
                                            <th width="40%">สาเหตุ</th>
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
                        
                        <div class="text-center">
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
        <!-- แสดงตัวอย่างไฟล์ CSV -->
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-file-csv me-2"></i>ตัวอย่างไฟล์ CSV
                </h5>
            </div>
            <div class="card-body">
                <p>ไฟล์ CSV ต้องมีรูปแบบดังนี้:</p>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">ส่วนหัว (Header):</label>
                    <div class="bg-light p-3 rounded">
                        <code>student_id,id_card,firstname,lastname,email,faculty,department,phone,address</code>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">ตัวอย่างข้อมูล:</label>
                    <div class="bg-light p-3 rounded">
                        <code class="small">
                            631234567,1234567890123,สมชาย,มีสุข,somchai@email.com,วิศวกรรมศาสตร์,วิศวกรรมคอมพิวเตอร์,0812345678,กรุงเทพฯ<br>
                            631234568,1234567890124,สมหญิง,จริงใจ,somying@email.com,บริหารธุรกิจ,การจัดการ,0898765432,กรุงเทพฯ
                        </code>
                    </div>
                </div>
                
                <div class="mb-3">
                    <button type="button" class="btn btn-outline-primary" onclick="downloadSample()">
                        <i class="fas fa-download me-2"></i>ดาวน์โหลดไฟล์ตัวอย่าง
                    </button>
                </div>
                
                <hr>
                
                <div class="alert alert-info" role="alert">
                    <h6><i class="fas fa-info-circle me-2"></i>คำแนะนำ:</h6>
                    <ul class="mb-0 small">
                        <li><strong>คอลัมน์จำเป็น:</strong> student_id, id_card, firstname, lastname, email, faculty</li>
                        <li><strong>คอลัมน์เพิ่มเติม:</strong> department, phone, address</li>
                        <li><strong>รหัสบัตรประชาชน:</strong> ต้องเป็นตัวเลข 13 หลัก (จะถูกเข้ารหัสอัตโนมัติ)</li>
                        <li><strong>อีเมล:</strong> ต้องมีรูปแบบที่ถูกต้อง</li>
                        <li><strong>Encoding:</strong> UTF-8 สำหรับภาษาไทย</li>
                        <li><strong>ขนาดไฟล์:</strong> สูงสุด <?php echo $max_file_size; ?></li>
                    </ul>
                </div>
                
                <div class="alert alert-warning" role="alert">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>ข้อควรระวัง:</h6>
                    <ul class="mb-0 small">
                        <li>ห้ามมีรหัสนักศึกษาซ้ำในระบบ</li>
                        <li>ห้ามมีรหัสบัตรประชาชนซ้ำในระบบ</li>
                        <li>ข้อมูลที่มีข้อผิดพลาดจะไม่ถูกนำเข้า</li>
                        <li>ตรวจสอบข้อมูลให้ถูกต้องก่อนอัปโหลด</li>
                        <li><strong>รหัสบัตรประชาชนจะถูกเข้ารหัสและไม่สามารถดูข้อความต้นฉบับได้</strong></li>
                    </ul>
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
        <?php if (isset($import_success) && $import_success && count($error_rows) > 0): ?>
        html: '<?php echo $alert_message; ?><br><small class="text-warning">มีข้อมูลบางส่วนที่ไม่สามารถนำเข้าได้ กรุณาตรวจสอบรายละเอียดด้านล่าง</small>',
        <?php else: ?>
        text: '<?php echo $alert_message; ?>',
        <?php endif; ?>
        confirmButtonText: 'ตกลง',
        timer: <?php echo $alert_type == "success" ? "5000" : "0"; ?>,
        timerProgressBar: <?php echo $alert_type == "success" ? "true" : "false"; ?>
    });
});
<?php endif; ?>

// ยืนยันการอัปโหลด
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('csv_file');
    const file = fileInput.files[0];
    
    if (!file) {
        Swal.fire({
            icon: 'error',
            title: 'กรุณาเลือกไฟล์',
            text: 'กรุณาเลือกไฟล์ CSV ที่ต้องการอัปโหลด'
        });
        return;
    }
    
    // ตรวจสอบนามสกุลไฟล์
    const fileName = file.name.toLowerCase();
    if (!fileName.endsWith('.csv')) {
        Swal.fire({
            icon: 'error',
            title: 'ไฟล์ไม่ถูกต้อง',
            text: 'กรุณาเลือกไฟล์ CSV เท่านั้น'
        });
        return;
    }
    
    // ตรวจสอบขนาดไฟล์ (2MB = 2097152 bytes)
    if (file.size > 2097152) {
        Swal.fire({
            icon: 'error',
            title: 'ไฟล์ใหญ่เกินไป',
            text: 'ขนาดไฟล์ต้องไม่เกิน 2MB'
        });
        return;
    }
    
    // ตรวจสอบว่าไฟล์ไม่ว่าง
    if (file.size === 0) {
        Swal.fire({
            icon: 'error',
            title: 'ไฟล์ว่าง',
            text: 'ไฟล์ที่เลือกไม่มีข้อมูล'
        });
        return;
    }
    
    Swal.fire({
        title: 'ยืนยันการอัปโหลด',
        html: `คุณต้องการอัปโหลดและนำเข้าข้อมูลจากไฟล์ <strong>${file.name}</strong> หรือไม่?<br><br>
               <small class="text-muted">ขนาดไฟล์: ${(file.size / 1024).toFixed(2)} KB</small><br>
               <small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i><strong>หมายเหตุ:</strong> รหัสบัตรประชาชนจะถูกเข้ารหัสอัตโนมัติเพื่อความปลอดภัย</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: '<i class="fas fa-upload me-2"></i>อัปโหลด',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            // แสดง loading
            Swal.fire({
                title: 'กำลังประมวลผลไฟล์...',
                html: `
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">กำลังโหลด...</span>
                        </div>
                        <br><br>
                        <p>กรุณารอสักครู่ ระบบกำลังอ่านและนำเข้าข้อมูล</p>
                        <small class="text-muted">รวมทั้งเข้ารหัสรหัสบัตรประชาชน</small>
                    </div>
                `,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    // เพิ่ม progress bar simulation
                    let progress = 0;
                    const progressBar = document.createElement('div');
                    progressBar.className = 'progress mt-3';
                    progressBar.innerHTML = '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>';
                    
                    const popup = Swal.getPopup();
                    popup.querySelector('.swal2-html-container').appendChild(progressBar);
                    
                    const interval = setInterval(() => {
                        progress += Math.random() * 10;
                        if (progress > 90) progress = 90;
                        
                        const bar = progressBar.querySelector('.progress-bar');
                        bar.style.width = progress + '%';
                        bar.setAttribute('aria-valuenow', progress);
                        
                        if (progress >= 90) {
                            clearInterval(interval);
                        }
                    }, 200);
                }
            });
            
            // ส่งฟอร์ม
            this.submit();
        }
    });
});

// ฟังก์ชันดาวน์โหลดไฟล์ตัวอย่าง
function downloadSample() {
    const csvContent = "student_id,id_card,firstname,lastname,email,faculty,department,phone,address\n" +
                      "631234567,1234567890123,สมชาย,มีสุข,somchai@email.com,วิศวกรรมศาสตร์,วิศวกรรมคอมพิวเตอร์,0812345678,กรุงเทพฯ\n" +
                      "631234568,1234567890124,สมหญิง,จริงใจ,somying@email.com,บริหารธุรกิจ,การจัดการ,0898765432,กรุงเทพฯ\n" +
                      "631234569,1234567890125,สมปอง,ใจดี,sompong@email.com,ครุศาสตร์,การศึกษา,0876543210,นนทบุรี";
    
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'sample_students.csv';
    link.click();
    
    Swal.fire({
        icon: 'success',
        title: 'ดาวน์โหลดสำเร็จ',
        text: 'ไฟล์ตัวอย่างถูกดาวน์โหลดแล้ว',
        timer: 2000,
        timerProgressBar: true,
        toast: true,
        position: 'top-end',
        showConfirmButton: false
    });
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
    link.download = 'error_report_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
    
    Swal.fire({
        icon: 'success',
        title: 'ดาวน์โหลดสำเร็จ',
        text: 'รายงานข้อผิดพลาดถูกดาวน์โหลดแล้ว',
        timer: 2000,
        timerProgressBar: true,
        toast: true,
        position: 'top-end',
        showConfirmButton: false
    });
    <?php endif; ?>
}

// ตรวจสอบไฟล์ก่อนอัพโหลด
document.getElementById('csv_file').addEventListener('change', function() {
    const file = this.files[0];
    
    if (file) {
        // ตรวจสอบนามสกุลไฟล์
        const fileExt = file.name.split('.').pop().toLowerCase();
        if (fileExt !== 'csv') {
            Swal.fire({
                icon: 'error',
                title: 'ไฟล์ไม่ถูกต้อง',
                text: 'กรุณาเลือกไฟล์ CSV เท่านั้น'
            });
            this.value = '';
            return;
        }
        
        // ตรวจสอบขนาดไฟล์
        if (file.size > 2 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'ไฟล์ใหญ่เกินไป',
                text: 'ขนาดไฟล์ต้องไม่เกิน 2MB'
            });
            this.value = '';
            return;
        }
        
        // ตรวจสอบว่าไฟล์ไม่ว่าง
        if (file.size === 0) {
            Swal.fire({
                icon: 'error',
                title: 'ไฟล์ว่าง',
                text: 'ไฟล์ที่เลือกไม่มีข้อมูล'
            });
            this.value = '';
            return;
        }
        
        // แสดงข้อมูลไฟล์
        const fileSize = (file.size / 1024).toFixed(2);
        Swal.fire({
            icon: 'info',
            title: 'ไฟล์ที่เลือก',
            html: `<strong>ชื่อไฟล์:</strong> ${file.name}<br>
                   <strong>ขนาด:</strong> ${fileSize} KB<br>
                   <small class="text-muted">คลิกอัปโหลดเพื่อดำเนินการต่อ</small><br>
                   <small class="text-warning"><i class="fas fa-shield-alt me-1"></i>รหัสบัตรประชาชนจะถูกเข้ารหัสอัตโนมัติ</small>`,
            timer: 3000,
            timerProgressBar: true
        });
    }
});

// เพิ่มการตรวจสอบ drag & drop
const uploadForm = document.getElementById('uploadForm');
const fileInput = document.getElementById('csv_file');

// เพิ่ม drag & drop functionality
uploadForm.addEventListener('dragover', function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.add('border-primary');
});

uploadForm.addEventListener('dragleave', function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.remove('border-primary');
});

uploadForm.addEventListener('drop', function(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.remove('border-primary');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const file = files[0];
        if (file.name.toLowerCase().endsWith('.csv')) {
            fileInput.files = files;
            
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ไฟล์ไม่ถูกต้อง',
                text: 'กรุณาใช้ไฟล์ CSV เท่านั้น'
            });
        }
    }
});

// แสดงสถานะการประมวลผล
function showProcessingStatus() {
    const processingSteps = [
        'กำลังอ่านไฟล์ CSV...',
        'กำลังตรวจสอบข้อมูล...',
        'กำลังเข้ารหัสรหัสผ่าน...',
        'กำลังบันทึกลงฐานข้อมูล...',
        'กำลังตรวจสอบข้อมูลซ้ำ...'
    ];
    
    let currentStep = 0;
    const stepInterval = setInterval(() => {
        if (currentStep < processingSteps.length) {
            const popup = Swal.getPopup();
            const statusElement = popup.querySelector('#processing-status');
            if (statusElement) {
                statusElement.textContent = processingSteps[currentStep];
            }
            currentStep++;
        } else {
            clearInterval(stepInterval);
        }
    }, 1000);
}

// เพิ่ม validation สำหรับฟอร์ม
function validateCSVFile(file) {
    const errors = [];
    
    if (!file) {
        errors.push('กรุณาเลือกไฟล์');
    } else {
        if (!file.name.toLowerCase().endsWith('.csv')) {
            errors.push('ไฟล์ต้องเป็นนามสกุล .csv เท่านั้น');
        }
        
        if (file.size > 2 * 1024 * 1024) {
            errors.push('ขนาดไฟล์ต้องไม่เกิน 2MB');
        }
        
        if (file.size === 0) {
            errors.push('ไฟล์ต้องไม่เป็นไฟล์ว่าง');
        }
    }
    
    return errors;
}

// เพิ่มการตรวจสอบ browser compatibility
document.addEventListener('DOMContentLoaded', function() {
    // ตรวจสอบว่า browser รองรับ File API หรือไม่
    if (!window.File || !window.FileReader || !window.FileList || !window.Blob) {
        Swal.fire({
            icon: 'warning',
            title: 'เบราว์เซอร์ไม่รองรับ',
            text: 'เบราว์เซอร์ของคุณไม่รองรับการอัปโหลดไฟล์ กรุณาใช้เบราว์เซอร์ที่ใหม่กว่า'
        });
    }
});
</script>
