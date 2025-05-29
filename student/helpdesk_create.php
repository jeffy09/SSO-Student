<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}
if (!isset($_SESSION['student_id'])) {
    header("Location: index.php?page=student_login");
    exit;
}

// รวมไฟล์ config ของ Helpdesk
include_once 'config/helpdesk_config.php';

// กำหนดค่าสำหรับการอัปโหลดไฟล์
define('UPLOAD_DIR', 'uploads/ticket_attachments/'); // ตรวจสอบว่า path นี้ถูกต้องและ server มีสิทธิ์เขียน
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject = $database->sanitize($_POST['subject']);
    $category = $database->sanitize($_POST['category']);
    $priority = $database->sanitize($_POST['priority']);
    $message = $database->sanitize($_POST['message']);
    $student_id = $_SESSION['student_id'];

    $attachment_filename = null;
    $attachment_path = null;

    if (empty($subject) || empty($category) || empty($message)) {
        $error_message = "กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน";
    } else {
        try {
            // --- จัดการการอัปโหลดไฟล์ ---
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
                $file = $_FILES['attachment'];

                // ตรวจสอบขนาดไฟล์
                if ($file['size'] > MAX_FILE_SIZE) {
                    throw new Exception("ขนาดไฟล์เกินกำหนด (สูงสุด 5MB)");
                }

                // ตรวจสอบ MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
                    throw new Exception("อนุญาตเฉพาะไฟล์รูปภาพประเภท JPG, PNG, GIF เท่านั้น");
                }

                // สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกันเพื่อป้องกันการเขียนทับ
                $original_filename = basename($file['name']);
                $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                $safe_filename = preg_replace("/[^a-zA-Z0-9-_\.]/", "", $original_filename); // ทำความสะอาดชื่อไฟล์เบื้องต้น
                $new_filename = uniqid('ticket_' . $student_id . '_', true) . '.' . $file_extension;
                $upload_path = UPLOAD_DIR . $new_filename;

                // สร้าง directory ถ้ายังไม่มี
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $attachment_filename = $original_filename;
                    $attachment_path = $new_filename; // เก็บเฉพาะชื่อไฟล์ใหม่ (หรือ $upload_path ถ้าต้องการ path เต็ม)
                } else {
                    throw new Exception("ไม่สามารถอัปโหลดไฟล์ได้ กรุณาลองใหม่อีกครั้ง");
                }
            } elseif (isset($_FILES['attachment']) && $_FILES['attachment']['error'] != UPLOAD_ERR_NO_FILE) {
                // มีการพยายามอัปโหลดแต่เกิดข้อผิดพลาดอื่น
                throw new Exception("เกิดข้อผิดพลาดในการอัปโหลดไฟล์: " . $_FILES['attachment']['error']);
            }
            // --- สิ้นสุดการจัดการอัปโหลดไฟล์ ---

            $db->beginTransaction();

            $ticket_id_str = 'TICKET-' . time() . rand(100, 999);

            $query = "INSERT INTO helpdesk_tickets (ticket_id, student_id, subject, category, priority, message, status, attachment_filename, attachment_path)
                      VALUES (:ticket_id, :student_id, :subject, :category, :priority, :message, 'Open', :attachment_filename, :attachment_path)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':ticket_id', $ticket_id_str);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':priority', $priority);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':attachment_filename', $attachment_filename); // อาจเป็น null ถ้าไม่มีไฟล์แนบ
            $stmt->bindParam(':attachment_path', $attachment_path);       // อาจเป็น null ถ้าไม่มีไฟล์แนบ
            $stmt->execute();
            $new_ticket_db_id = $db->lastInsertId();

            $db->commit();

            $ticket_link = $base_url . "?page=admin_helpdesk_view&id=" . $new_ticket_db_id;
            HelpdeskConfig::sendGoogleChatNotification($ticket_id_str, $_SESSION['student_name'], $subject, $ticket_link);

            $_SESSION['success_message'] = "แจ้งปัญหาสำเร็จแล้ว Ticket ID ของคุณคือ " . $ticket_id_str;
            header("Location: index.php?page=helpdesk");
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // หากเกิดข้อผิดพลาดในการบันทึกข้อมูล และมีการอัปโหลดไฟล์แล้ว อาจจะต้องลบไฟล์ที่อัปโหลดไปแล้ว (ถ้าต้องการ)
            if (isset($upload_path) && file_exists($upload_path)) {
                // unlink($upload_path); // พิจารณาว่าจะลบหรือไม่
            }
            $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-plus-circle me-2"></i>เปิด Ticket ใหม่</h2>
        <p>กรุณากรอกรายละเอียดของปัญหาที่ท่านพบ</p>
    </div>
</div>

<?php if(isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<div class="card shadow">
    <div class="card-body">
        <form action="?page=helpdesk_create" method="post" enctype="multipart/form-data"> 
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="subject" class="form-label">หัวข้อปัญหา <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="subject" name="subject" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="category" class="form-label">หมวดหมู่ <span class="text-danger">*</span></label>
                    <select class="form-select" id="category" name="category" required>
                        <option value="">-- เลือกหมวดหมู่ --</option>
                        <option value="Login Issue" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Login Issue') ? 'selected' : ''; ?>>ปัญหาการเข้าสู่ระบบ</option>
                        <option value="Profile Issue" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Profile Issue') ? 'selected' : ''; ?>>ปัญหาข้อมูลส่วนตัว</option>
                        <option value="System Error" <?php echo (isset($_POST['category']) && $_POST['category'] == 'System Error') ? 'selected' : ''; ?>>ระบบขัดข้อง/Error</option>
                        <option value="General Inquiry" <?php echo (isset($_POST['category']) && $_POST['category'] == 'General Inquiry') ? 'selected' : ''; ?>>สอบถามทั่วไป</option>
                        <option value="Other" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Other') ? 'selected' : ''; ?>>อื่นๆ</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label for="priority" class="form-label">ระดับความสำคัญ</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="Low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'Low') ? 'selected' : ''; ?>>ต่ำ</option>
                    <option value="Medium" <?php echo (!isset($_POST['priority']) || (isset($_POST['priority']) && $_POST['priority'] == 'Medium')) ? 'selected' : ''; ?>>ปานกลาง</option>
                    <option value="High" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'High') ? 'selected' : ''; ?>>สูง</option>
                    <option value="Critical" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'Critical') ? 'selected' : ''; ?>>เร่งด่วน</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="message" class="form-label">รายละเอียดปัญหา <span class="text-danger">*</span></label>
                <textarea class="form-control" id="message" name="message" rows="6" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
            </div>

         
            <div class="mb-3">
                <label for="attachment" class="form-label">แนบรูปภาพ (ถ้ามี)</label>
                <input class="form-control" type="file" id="attachment" name="attachment" accept="image/jpeg,image/png,image/gif">
                <div class="form-text">อนุญาตเฉพาะไฟล์ JPG, PNG, GIF และขนาดไม่เกิน 5MB</div>
            </div>

            <hr>
            <div class="text-center">
                <a href="?page=helpdesk" class="btn btn-secondary"><i class="fas fa-times me-2"></i>ยกเลิก</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>ส่งเรื่อง</button>
            </div>
        </form>
    </div>
</div>
