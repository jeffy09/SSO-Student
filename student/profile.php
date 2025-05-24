<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// รวมไฟล์ configuration ของ Google
include_once 'config/google_config.php';

// ตรวจสอบว่ามี session หรือไม่
if (!isset($_SESSION['student_id'])) {
    header("Location: index.php?page=student_login");
    exit;
}

// ตัวแปรสำหรับเก็บสถานะการแจ้งเตือน
$alert_type = '';
$alert_message = '';
$show_alert = false;

// ตัวแปรสำหรับข้อมูล Gmail
$gmail_data = [
    'unread_count' => 0,
    'recent_emails' => [],
    'error' => null,
    'connected' => false,
    'token_expired' => false,
    'needs_reconnect' => false
];

// ฟังก์ชันตรวจสอบ token หมดอายุ (แก้ไขใหม่)
function checkTokenExpiration($student)
{
    try {
        // ตรวจสอบว่ามี token หรือไม่
        if (empty($student['access_token'])) {
            return ['expired' => false, 'exists' => false, 'message' => 'ไม่มี token'];
        }

        // ตรวจสอบวันหมดอายุ
        if (!empty($student['token_expires_at'])) {
            $expires_at = new DateTime($student['token_expires_at']);
            $now = new DateTime();

            // ตรวจสอบว่าหมดอายุหรือใกล้หมดอายุ (เหลือน้อยกว่า 5 นาที)
            $time_diff = $expires_at->getTimestamp() - $now->getTimestamp();

            if ($time_diff <= 0) {
                return ['expired' => true, 'exists' => true, 'message' => 'Token หมดอายุแล้ว', 'time_left' => 0];
            } elseif ($time_diff <= 300) { // เหลือน้อยกว่า 5 นาที
                return ['expired' => false, 'exists' => true, 'message' => 'Token ใกล้หมดอายุ', 'near_expiry' => true, 'time_left' => $time_diff];
            }

            return ['expired' => false, 'exists' => true, 'message' => 'Token ยังใช้ได้', 'time_left' => $time_diff];
        }

        // ถ้าไม่มีข้อมูลวันหมดอายุ ให้ถือว่ายังใช้ได้ (ข้อมูลเก่า)
        return ['expired' => false, 'exists' => true, 'message' => 'Token ยังใช้ได้ (ไม่มีข้อมูลวันหมดอายุ)'];
    } catch (Exception $e) {
        return ['expired' => true, 'exists' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

// ดึงข้อมูลนักศึกษา
try {
    $query = "SELECT * FROM students WHERE id = :id LIMIT 0,1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_SESSION['student_id']);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // หากไม่พบข้อมูล ให้ logout
        header("Location: index.php?page=logout");
        exit;
    }
} catch (PDOException $e) {
    $alert_type = 'error';
    $alert_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    $show_alert = true;
}

// ตรวจสอบสถานะ token (แก้ไขใหม่)
$token_status = checkTokenExpiration($student);

// ดึงข้อมูล Gmail หากมีการเชื่อมต่อ Google (แก้ไขโลจิกใหม่)
if (!empty($student['google_id'])) {
    $gmail_data['connected'] = true; // มีการเชื่อมต่อ Google แล้ว

    // ตรวจสอบว่ามี token ในฐานข้อมูลหรือไม่
    if ($token_status['exists']) {
        if ($token_status['expired']) {
            // Token หมดอายุ - ต้องเชื่อมต่อใหม่
            $gmail_data['token_expired'] = true;
            $gmail_data['needs_reconnect'] = true;
            $gmail_data['error'] = 'Token หมดอายุแล้ว กรุณาเชื่อมต่อใหม่';
        } else {
            // Token ยังใช้ได้
            if (isset($token_status['near_expiry'])) {
                // ใกล้หมดอายุ - แจ้งเตือน แต่ยังใช้ได้
                $alert_type = 'warning';
                $alert_message = 'Token ใกล้หมดอายุ (' . round($token_status['time_left'] / 60) . ' นาที) ระบบจะรีเฟรชอัตโนมัติ';
                $show_alert = true;
            }

            // ตั้งค่า token ใน session
            $_SESSION['access_token'] = $student['access_token'];

            // ลองดึงข้อมูล Gmail
            try {
                $google_config = new GoogleConfig();

                // ดึงจำนวนอีเมลที่ยังไม่ได้อ่าน
                $gmail_data['unread_count'] = $google_config->getGmailUnreadCount($_SESSION['access_token']);

                // ดึงอีเมล 5 ฉบับล่าสุด
                $gmail_data['recent_emails'] = $google_config->getGmailRecentEmails($_SESSION['access_token'], 5);

                $gmail_data['error'] = null; // ไม่มีข้อผิดพลาด

            } catch (Exception $e) {
                $error_message = $e->getMessage();

                // ตรวจสอบว่าเป็นข้อผิดพลาดเกี่ยวกับ token หรือไม่
                if (
                    strpos($error_message, '401') !== false ||
                    strpos($error_message, 'unauthorized') !== false ||
                    strpos($error_message, 'invalid_token') !== false ||
                    strpos($error_message, 'access_denied') !== false
                ) {

                    $gmail_data['token_expired'] = true;
                    $gmail_data['needs_reconnect'] = true;
                    $gmail_data['error'] = 'Token ไม่ถูกต้องหรือหมดอายุ กรุณาเชื่อมต่อใหม่';

                    // ล้าง token ที่ไม่ถูกต้องออกจากฐานข้อมูล
                    $clear_query = "UPDATE students SET 
                                   access_token = NULL, 
                                   refresh_token = NULL, 
                                   token_expires_at = NULL 
                                   WHERE id = :id";
                    $clear_stmt = $db->prepare($clear_query);
                    $clear_stmt->bindParam(':id', $_SESSION['student_id']);
                    $clear_stmt->execute();

                    unset($_SESSION['access_token']);
                } else {
                    $gmail_data['error'] = 'ไม่สามารถดึงข้อมูลอีเมลได้: ' . $error_message;
                }
            }
        }
    } else {
        // ไม่มี token ในฐานข้อมูล แต่มี google_id (สถานการณ์แปลก)
        $gmail_data['needs_reconnect'] = true;
        $gmail_data['error'] = 'ไม่พบ token การเข้าถึง กรุณาเชื่อมต่อบัญชี Google ใหม่';
    }
} else {
    // ยังไม่ได้เชื่อมต่อ Google เลย
    $gmail_data['connected'] = false;
}

// หากมีการ submit form แก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['refresh_gmail'])) {
    try {
        // รับค่าจาก form
        $phone = isset($_POST['phone']) ? $database->sanitize($_POST['phone']) : '';
        $address = isset($_POST['address']) ? $database->sanitize($_POST['address']) : '';

        // เตรียมคำสั่ง SQL
        $update_query = "UPDATE students SET phone = :phone, address = :address WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':phone', $phone);
        $update_stmt->bindParam(':address', $address);
        $update_stmt->bindParam(':id', $_SESSION['student_id']);

        // Execute
        if ($update_stmt->execute()) {
            $alert_type = 'success';
            $alert_message = 'อัพเดตข้อมูลสำเร็จ!';
            $show_alert = true;

            // อัพเดตข้อมูลในตัวแปร $student
            $student['phone'] = $phone;
            $student['address'] = $address;
        } else {
            $alert_type = 'error';
            $alert_message = 'ไม่สามารถอัพเดตข้อมูลได้';
            $show_alert = true;
        }
    } catch (PDOException $e) {
        $alert_type = 'error';
        $alert_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        $show_alert = true;
    }
}

// หากมีการรีเฟรช Gmail
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['refresh_gmail'])) {
    if (!empty($student['google_id']) && !empty($student['access_token'])) {
        try {
            $google_config = new GoogleConfig();

            // ดึงข้อมูลใหม่
            $gmail_data['unread_count'] = $google_config->getGmailUnreadCount($student['access_token']);
            $gmail_data['recent_emails'] = $google_config->getGmailRecentEmails($student['access_token'], 5);
            $gmail_data['connected'] = true;
            $gmail_data['token_expired'] = false;
            $gmail_data['needs_reconnect'] = false;
            $gmail_data['error'] = null;

            $alert_type = 'success';
            $alert_message = 'รีเฟรชข้อมูลอีเมลสำเร็จ!';
            $show_alert = true;
        } catch (Exception $e) {
            $gmail_data['error'] = 'ไม่สามารถรีเฟรชข้อมูลอีเมลได้: ' . $e->getMessage();
            $alert_type = 'error';
            $alert_message = $gmail_data['error'];
            $show_alert = true;
        }
    } else {
        $alert_type = 'error';
        $alert_message = 'ไม่พบ token การเข้าถึง กรุณาเชื่อมต่อใหม่';
        $show_alert = true;
    }
}

// ตรวจสอบว่ามีการเชื่อมต่อ Google สำเร็จหรือไม่
if (isset($_GET['google_connect']) && $_GET['google_connect'] == 'success') {
    $alert_type = 'success';
    $alert_message = 'เชื่อมต่อบัญชี Google สำเร็จ!';
    $show_alert = true;

    // รีโหลดข้อมูล Gmail หลังจากเชื่อมต่อสำเร็จ
    header("Location: index.php?page=student_profile");
    exit;
} elseif (isset($_GET['google_connect']) && $_GET['google_connect'] == 'error') {
    $alert_type = 'error';
    $alert_message = 'ไม่สามารถเชื่อมต่อบัญชี Google ได้';
    $show_alert = true;
}

// Debug information (เอาออกเมื่อแก้ไขเสร็จ)
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo '<div class="alert alert-info">';
    echo '<h6>Debug Information:</h6>';
    echo '<pre>';
    echo "Google ID: " . ($student['google_id'] ?? 'null') . "\n";
    echo "Access Token: " . (empty($student['access_token']) ? 'empty' : 'exists (' . strlen($student['access_token']) . ' chars)') . "\n";
    echo "Token Expires: " . ($student['token_expires_at'] ?? 'null') . "\n";
    echo "Token Status: " . json_encode($token_status, JSON_PRETTY_PRINT) . "\n";
    echo "Gmail Data: " . json_encode($gmail_data, JSON_PRETTY_PRINT) . "\n";
    echo '</pre>';
    echo '</div>';
}
?>

<!-- เพิ่ม SweetAlert2 CSS และ JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="row">
    <div class="col-md-12 mb-4">
        <h2>โปรไฟล์นักศึกษา</h2>
        <p>แก้ไขข้อมูลส่วนตัวของคุณ</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user me-2"></i>ข้อมูลส่วนตัว
                </h5>
            </div>
            <div class="card-body">
                <form action="?page=student_profile" method="post" id="profileForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">รหัสนักศึกษา</label>
                            <input type="text" class="form-control" id="student_id" value="<?php echo $student['student_id']; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="id_card" class="form-label">รหัสบัตรประชาชน</label>
                            <input type="text" class="form-control" id="id_card" value="<?php echo substr($student['id_card'], 0, 4) . 'XXXXXXXXX'; ?>" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="firstname" class="form-label">ชื่อ</label>
                            <input type="text" class="form-control" id="firstname" value="<?php echo $student['firstname']; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="lastname" class="form-label">นามสกุล</label>
                            <input type="text" class="form-control" id="lastname" value="<?php echo $student['lastname']; ?>" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">อีเมล</label>
                            <input type="email" class="form-control" id="email" value="<?php echo $student['email']; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $student['phone']; ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="faculty" class="form-label">คณะ</label>
                            <input type="text" class="form-control" id="faculty" value="<?php echo $student['faculty']; ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="department" class="form-label">สาขา</label>
                            <input type="text" class="form-control" id="department" value="<?php echo $student['department']; ?>" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">ที่อยู่</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo $student['address']; ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>บันทึกข้อมูล
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- การเชื่อมต่อบัญชี -->
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">
                    <i class="fab fa-google me-2"></i>การเชื่อมต่อบัญชี
                </h5>
            </div>
            <div class="card-body">
                <?php if (!$gmail_data['connected']): ?>
                    <div class="alert alert-warning alert-permanent">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        คุณยังไม่ได้เชื่อมต่อบัญชี Google กับระบบ
                    </div>
                    <div class="mb-3">
                        <p>เชื่อมต่อกับบัญชี Gmail มหาวิทยาลัยของคุณเพื่อให้สามารถลงชื่อเข้าใช้ด้วย Google ได้และดูอีเมลในระบบ</p>
                        <button type="button" class="btn btn-danger" onclick="confirmGoogleConnect()">
                            <i class="fab fa-google me-2"></i>เชื่อมต่อบัญชี Google
                        </button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success alert-permanent">
                        <i class="fas fa-check-circle me-2"></i>
                        คุณได้เชื่อมต่อบัญชี Google กับระบบแล้ว
                    </div>

                    <?php if ($gmail_data['needs_reconnect'] || $gmail_data['token_expired']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            การเชื่อมต่อหมดอายุแล้ว กรุณาเชื่อมต่อใหม่
                        </div>
                        <button type="button" class="btn btn-warning" onclick="confirmGoogleReconnect()">
                            <i class="fas fa-sync-alt me-2"></i>เชื่อมต่อใหม่
                        </button>
                    <?php else: ?>
                        <p>คุณสามารถใช้ Google Sign-In เพื่อเข้าสู่ระบบในครั้งต่อไปได้</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Gmail Information -->
        <?php if ($gmail_data['connected']): ?>
            <div class="card shadow mb-4 <?php echo ($gmail_data['needs_reconnect'] || $gmail_data['token_expired']) ? 'token-expired' : ''; ?>">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-envelope me-2"></i>ข้อมูล Gmail
                    </h5>
                    <?php if (!$gmail_data['needs_reconnect'] && !$gmail_data['token_expired'] && !$gmail_data['error']): ?>
                        <form method="post" style="display: inline;">
                            <button type="submit" name="refresh_gmail" class="btn btn-outline-light btn-sm" title="รีเฟรชข้อมูล">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$gmail_data['error'] && !$gmail_data['needs_reconnect'] && !$gmail_data['token_expired']): ?>
                        <!-- สถิติอีเมล -->
                        <div class="row text-center mb-3">
                            <div class="col-12">
                                <div class="bg-primary text-white rounded p-3 mb-2">
                                    <h3 class="mb-1"><?php echo $gmail_data['unread_count']; ?></h3>
                                    <small>อีเมลที่ยังไม่ได้อ่าน</small>
                                </div>
                            </div>
                        </div>

                        <!-- รายการอีเมลล่าสุด -->
                        <h6 class="border-bottom pb-2 mb-3">
                            <i class="fas fa-clock me-2"></i>อีเมล 5 ฉบับล่าสุด
                        </h6>

                        <?php if (count($gmail_data['recent_emails']) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($gmail_data['recent_emails'] as $email): ?>
                                    <div class="list-group-item px-0 py-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1 me-2">
                                                <h6 class="mb-1 <?php echo $email['unread'] ? 'fw-bold' : ''; ?>">
                                                    <?php if ($email['unread']): ?>
                                                        <span class="badge bg-primary me-1">ใหม่</span>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars(mb_strimwidth($email['subject'] ?: '(ไม่มีหัวข้อ)', 0, 30, '...')); ?>
                                                </h6>
                                                <p class="mb-1 small text-muted">
                                                    จาก: <?php echo htmlspecialchars(mb_strimwidth($email['from'], 0, 25, '...')); ?>
                                                </p>
                                                <small class="text-muted">
                                                    <?php
                                                    $date = new DateTime($email['date']);
                                                    echo $date->format('d/m/Y H:i');
                                                    ?>
                                                </small>
                                            </div>
                                            <button class="btn btn-outline-primary btn-sm" onclick="showEmailDetail('<?php echo addslashes($email['subject']); ?>', '<?php echo addslashes($email['from']); ?>', '<?php echo addslashes($email['snippet']); ?>', '<?php echo $email['date']; ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="text-center mt-3">
                                <a href="https://mail.google.com" target="_blank" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-external-link-alt me-2"></i>เปิด Gmail
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>ไม่มีอีเมลในกล่องจดหมาย</p>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $gmail_data['error'] ?: 'การเชื่อมต่อมีปัญหา กรุณาเชื่อมต่อใหม่'; ?>
                        </div>
                        <p class="small text-muted">
                            กรุณาลองเชื่อมต่อบัญชี Google ใหม่หรือตรวจสอบการอนุญาตการเข้าถึง Gmail
                        </p>
                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmGoogleReconnect()">
                            <i class="fab fa-google me-2"></i>เชื่อมต่อใหม่
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // แสดง SweetAlert เมื่อมีการแจ้งเตือน
    <?php if ($show_alert): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?php echo $alert_type; ?>',
                title: '<?php echo $alert_type == "success" ? "สำเร็จ!" : ($alert_type == "warning" ? "คำเตือน!" : "เกิดข้อผิดพลาด!"); ?>',
                text: '<?php echo $alert_message; ?>',
                confirmButtonText: 'ตกลง',
                timer: <?php echo $alert_type == "success" ? "3000" : "0"; ?>,
                timerProgressBar: <?php echo $alert_type == "success" ? "true" : "false"; ?>
            });
        });
    <?php endif; ?>

    // ยืนยันการบันทึกข้อมูล
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();

        Swal.fire({
            title: 'ยืนยันการบันทึก',
            text: 'คุณต้องการบันทึกการเปลี่ยนแปลงข้อมูลหรือไม่?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'บันทึก',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });

    // ยืนยันการเชื่อมต่อ Google
    function confirmGoogleConnect() {
        Swal.fire({
            title: 'เชื่อมต่อบัญชี Google',
            html: 'คุณต้องการเชื่อมต่อบัญชี Google กับระบบหรือไม่?<br><small class="text-muted">ระบบจะขออนุญาตเข้าถึงอีเมลในบัญชี Gmail ของคุณ</small>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fab fa-google me-2"></i>เชื่อมต่อ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?page=google_login&user_type=student&from_profile=1';
            }
        });
    }

    // ยืนยันการเชื่อมต่อใหม่ (เมื่อ token หมดอายุ)
    function confirmGoogleReconnect() {
        Swal.fire({
            title: 'เชื่อมต่อบัญชี Google ใหม่',
            html: 'การเชื่อมต่อของคุณหมดอายุแล้ว<br>คุณต้องการเชื่อมต่อใหม่หรือไม่?<br><small class="text-muted">ระบบจะขออนุญาตเข้าถึงอีเมลในบัญชี Gmail ของคุณอีกครั้ง</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-sync-alt me-2"></i>เชื่อมต่อใหม่',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?page=google_login&user_type=student&from_profile=1&reconnect=1';
            }
        });
    }

    // แสดงรายละเอียดอีเมล
    function showEmailDetail(subject, from, snippet, date) {
        const emailDate = new Date(date);
        const formattedDate = emailDate.toLocaleString('th-TH');

        Swal.fire({
            title: subject || '(ไม่มีหัวข้อ)',
            html: `
            <div class="text-start">
                <p><strong>จาก:</strong> ${from}</p>
                <p><strong>วันที่:</strong> ${formattedDate}</p>
                <hr>
                <p><strong>เนื้อหาย่อ:</strong></p>
                <p class="text-muted">${snippet || 'ไม่มีเนื้อหาตัวอย่าง'}</p>
            </div>
        `,
            icon: 'info',
            confirmButtonText: 'ปิด',
            footer: '<a href="https://mail.google.com" target="_blank">เปิดใน Gmail</a>'
        });
    }
</script>

<style>
    /* Style สำหรับ Gmail card เมื่อ token หมดอายุ */
    .card.token-expired {
        border-left: 4px solid #ffc107;
        box-shadow: 0 0.125rem 0.25rem rgba(255, 193, 7, 0.075);
    }

    .card.token-expired .card-header {
        background-color: #ffc107 !important;
        color: #212529 !important;
    }

    /* Style สำหรับ alert permanent */
    .alert-permanent {
        border: none;
        border-left: 4px solid;
    }

    .alert-warning.alert-permanent {
        border-left-color: #ffc107;
    }

    .alert-success.alert-permanent {
        border-left-color: #198754;
    }
</style>
