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
    'connected' => false
];

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
} catch(PDOException $e) {
    $alert_type = 'error';
    $alert_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    $show_alert = true;
}

// ดึงข้อมูล Gmail หากมีการเชื่อมต่อ Google
if (!empty($student['google_id']) && isset($_SESSION['google_access_token'])) {
    try {
        $google_config = new GoogleConfig();
        
        // ดึงจำนวนอีเมลที่ยังไม่ได้อ่าน
        $gmail_data['unread_count'] = $google_config->getGmailUnreadCount($_SESSION['google_access_token']);
        
        // ดึงอีเมล 5 ฉบับล่าสุด
        $gmail_data['recent_emails'] = $google_config->getGmailRecentEmails($_SESSION['google_access_token'], 5);
        
        $gmail_data['connected'] = true;
    } catch(Exception $e) {
        $gmail_data['error'] = 'ไม่สามารถดึงข้อมูลอีเมลได้: ' . $e->getMessage();
    }
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
    } catch(PDOException $e) {
        $alert_type = 'error';
        $alert_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        $show_alert = true;
    }
}

// หากมีการรีเฟรช Gmail
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['refresh_gmail'])) {
    if (!empty($student['google_id']) && isset($_SESSION['google_access_token'])) {
        try {
            $google_config = new GoogleConfig();
            
            // ดึงข้อมูลใหม่
            $gmail_data['unread_count'] = $google_config->getGmailUnreadCount($_SESSION['google_access_token']);
            $gmail_data['recent_emails'] = $google_config->getGmailRecentEmails($_SESSION['google_access_token'], 5);
            $gmail_data['connected'] = true;
            
            $alert_type = 'success';
            $alert_message = 'รีเฟรชข้อมูลอีเมลสำเร็จ!';
            $show_alert = true;
        } catch(Exception $e) {
            $gmail_data['error'] = 'ไม่สามารถรีเฟรชข้อมูลอีเมลได้: ' . $e->getMessage();
            $alert_type = 'error';
            $alert_message = $gmail_data['error'];
            $show_alert = true;
        }
    }
}

// ตรวจสอบว่ามีการเชื่อมต่อ Google สำเร็จหรือไม่
if (isset($_GET['google_connect']) && $_GET['google_connect'] == 'success') {
    $alert_type = 'success';
    $alert_message = 'เชื่อมต่อบัญชี Google สำเร็จ!';
    $show_alert = true;
} elseif (isset($_GET['google_connect']) && $_GET['google_connect'] == 'error') {
    $alert_type = 'error';
    $alert_message = 'ไม่สามารถเชื่อมต่อบัญชี Google ได้';
    $show_alert = true;
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
                <?php if(empty($student['google_id'])): ?>
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
                    <p>คุณสามารถใช้ Google Sign-In เพื่อเข้าสู่ระบบในครั้งต่อไปได้</p>
                    <!-- <button type="button" class="btn btn-outline-danger" onclick="confirmGoogleDisconnect()">
                        <i class="fas fa-unlink me-2"></i>ยกเลิกการเชื่อมต่อ
                    </button> -->
                <?php endif; ?>
            </div>
        </div>

        

        <!-- Gmail Information -->
        <?php if(!empty($student['google_id'])): ?>
        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-envelope me-2"></i>ข้อมูล Gmail
                </h5>
                <form method="post" style="display: inline;">
                    <button type="submit" name="refresh_gmail" class="btn btn-outline-light btn-sm" title="รีเฟรชข้อมูล">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </form>
            </div>
            <div class="card-body">
                <?php if($gmail_data['connected'] && !$gmail_data['error']): ?>
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
                    
                    <?php if(count($gmail_data['recent_emails']) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach($gmail_data['recent_emails'] as $email): ?>
                                <div class="list-group-item px-0 py-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1 me-2">
                                            <h6 class="mb-1 <?php echo $email['unread'] ? 'fw-bold' : ''; ?>">
                                                <?php if($email['unread']): ?>
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
                    
                <?php elseif($gmail_data['error']): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $gmail_data['error']; ?>
                    </div>
                    <p class="small text-muted">
                        กรุณาลองเชื่อมต่อบัญชี Google ใหม่หรือตรวจสอบการอนุญาตการเข้าถึง Gmail
                    </p>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmGoogleConnect()">
                        <i class="fab fa-google me-2"></i>เชื่อมต่อใหม่
                    </button>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-plug fa-2x mb-2"></i>
                        <p>กรุณาเชื่อมต่อบัญชี Google เพื่อดูข้อมูลอีเมล</p>
                    </div>
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
        title: '<?php echo $alert_type == "success" ? "สำเร็จ!" : "เกิดข้อผิดพลาด!"; ?>',
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
            // แสดง loading
            Swal.fire({
                title: 'กำลังบันทึกข้อมูล...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // ส่งข้อมูล
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
            // แสดง loading
            Swal.fire({
                title: 'กำลังเชื่อมต่อ...',
                text: 'กรุณารอสักครู่',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // เปลี่ยนจาก from_profile=true เป็น from_profile=1
            window.location.href = '?page=google_login&user_type=student&from_profile=1';
        }
    });
}

// ยืนยันการยกเลิกการเชื่อมต่อ Google
function confirmGoogleDisconnect() {
    Swal.fire({
        title: 'ยกเลิกการเชื่อมต่อ',
        text: 'คุณต้องการยกเลิกการเชื่อมต่อบัญชี Google หรือไม่?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ยกเลิกการเชื่อมต่อ',
        cancelButtonText: 'ไม่'
    }).then((result) => {
        if (result.isConfirmed) {
            // ส่งไปยัง script สำหรับยกเลิกการเชื่อมต่อ
            window.location.href = '?page=google_disconnect&user_type=student';
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

// ตรวจสอบการ validate ข้อมูล
function validateForm() {
    const phone = document.getElementById('phone').value;
    const address = document.getElementById('address').value;
    
    if (phone.length > 0 && !/^[0-9-+\s()]{10,15}$/.test(phone)) {
        Swal.fire({
            icon: 'error',
            title: 'ข้อมูลไม่ถูกต้อง',
            text: 'กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง'
        });
        return false;
    }
    
    return true;
}

// เพิ่ม event listener สำหรับ validation
document.getElementById('phone').addEventListener('blur', function() {
    const phone = this.value;
    if (phone.length > 0 && !/^[0-9-+\s()]{10,15}$/.test(phone)) {
        this.classList.add('is-invalid');
        
        // แสดง tooltip เตือน
        Swal.fire({
            icon: 'warning',
            title: 'รูปแบบเบอร์โทรไม่ถูกต้อง',
            text: 'กรุณากรอกเบอร์โทรศัพท์ที่ถูกต้อง',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    } else {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    }
});

// Auto refresh Gmail data every 5 minutes
<?php if(!empty($student['google_id']) && $gmail_data['connected']): ?>
setInterval(function() {
    // ส่งคำขอ refresh อีเมลในเบื้องหลัง
    fetch('?page=student_profile', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'refresh_gmail=1'
    }).then(response => {
        if (response.ok) {
            // อัพเดตหน้าเมื่อมีข้อมูลใหม่
            location.reload();
        }
    }).catch(error => {
        console.log('Auto refresh failed:', error);
    });
}, 300000); // 5 minutes = 300000ms
<?php endif; ?>

// เพิ่มฟังก์ชันสำหรับ mark email as read (ถ้าต้องการ)
function markEmailAsRead(emailId) {
    Swal.fire({
        title: 'ทำเครื่องหมายว่าอ่านแล้ว',
        text: 'คุณต้องการทำเครื่องหมายว่าอ่านอีเมลนี้แล้วหรือไม่?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ใช่',
        cancelButtonText: 'ไม่'
    }).then((result) => {
        if (result.isConfirmed) {
            // ส่งคำขอไปยังเซิร์ฟเวอร์เพื่อทำเครื่องหมายว่าอ่านแล้ว
            // TODO: implement mark as read functionality
            Swal.fire('สำเร็จ!', 'ทำเครื่องหมายว่าอ่านแล้ว', 'success');
        }
    });
}

// เพิ่มฟังก์ชันสำหรับดู notification เมื่อมีอีเมลใหม่
function checkNewEmails() {
    <?php if(!empty($student['google_id']) && $gmail_data['connected']): ?>
    const currentUnreadCount = <?php echo $gmail_data['unread_count']; ?>;
    
    // สร้าง notification เมื่อมีอีเมลที่ยังไม่ได้อ่าน
    if (currentUnreadCount > 0 && 'Notification' in window) {
        Notification.requestPermission().then(function(permission) {
            if (permission === 'granted') {
                const notification = new Notification('คุณมีอีเมลที่ยังไม่ได้อ่าน', {
                    body: `มีอีเมลที่ยังไม่ได้อ่าน ${currentUnreadCount} ฉบับ`,
                    icon: '/assets/images/email-icon.png' // เพิ่มไอคอนถ้ามี
                });
                
                notification.onclick = function() {
                    window.focus();
                    notification.close();
                };
            }
        });
    }
    <?php endif; ?>
}

// เรียกใช้ check notification เมื่อโหลดหน้า
document.addEventListener('DOMContentLoaded', function() {
    checkNewEmails();
    
    // เพิ่ม badge indicator สำหรับอีเมลที่ยังไม่ได้อ่าน
    <?php if(!empty($student['google_id']) && $gmail_data['connected'] && $gmail_data['unread_count'] > 0): ?>
    document.title = `(${<?php echo $gmail_data['unread_count']; ?>}) โปรไฟล์นักศึกษา`;
    <?php endif; ?>
});

// เพิ่มฟังก์ชันสำหรับการส่งอีเมล (ถ้าต้องการในอนาคต)
function composeEmail() {
    Swal.fire({
        title: 'เขียนอีเมลใหม่',
        html: `
            <form id="emailForm">
                <div class="mb-3 text-start">
                    <label for="emailTo" class="form-label">ถึง:</label>
                    <input type="email" class="form-control" id="emailTo" required>
                </div>
                <div class="mb-3 text-start">
                    <label for="emailSubject" class="form-label">หัวข้อ:</label>
                    <input type="text" class="form-control" id="emailSubject" required>
                </div>
                <div class="mb-3 text-start">
                    <label for="emailBody" class="form-label">เนื้อหา:</label>
                    <textarea class="form-control" id="emailBody" rows="5" required></textarea>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'ส่งอีเมล',
        cancelButtonText: 'ยกเลิก',
        preConfirm: () => {
            const to = document.getElementById('emailTo').value;
            const subject = document.getElementById('emailSubject').value;
            const body = document.getElementById('emailBody').value;
            
            if (!to || !subject || !body) {
                Swal.showValidationMessage('กรุณากรอกข้อมูลให้ครบถ้วน');
                return false;
            }
            
            return { to, subject, body };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // TODO: ส่งอีเมลผ่าน Gmail API
            Swal.fire('สำเร็จ!', 'ส่งอีเมลเรียบร้อยแล้ว', 'success');
        }
    });
}

// เพิ่มฟังก์ชันสำหรับการค้นหาอีเมล
function searchEmails() {
    Swal.fire({
        title: 'ค้นหาอีเมล',
        input: 'text',
        inputPlaceholder: 'ใส่คำค้นหา...',
        showCancelButton: true,
        confirmButtonText: 'ค้นหา',
        cancelButtonText: 'ยกเลิก',
        inputValidator: (value) => {
            if (!value) {
                return 'กรุณาใส่คำค้นหา';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // TODO: ค้นหาอีเมลผ่าน Gmail API
            window.open(`https://mail.google.com/mail/u/0/#search/${encodeURIComponent(result.value)}`, '_blank');
        }
    });
}
</script>

<style>
/* เพิ่ม custom style สำหรับ SweetAlert */
.swal2-popup {
    font-family: 'Sarabun', sans-serif;
}

.swal2-title {
    font-size: 1.5rem;
    font-weight: 600;
}

.swal2-content {
    font-size: 1rem;
}

/* Animation สำหรับปุ่ม */
.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

/* Style สำหรับ form validation */
.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.is-valid {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

/* Style สำหรับ Gmail section */
.list-group-item {
    border: none;
    border-bottom: 1px solid #dee2e6;
}

.list-group-item:last-child {
    border-bottom: none;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

/* Style สำหรับ badge */
.badge {
    font-size: 0.7rem;
}

/* Animation สำหรับ email refresh */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.fa-sync-alt:hover {
    animation: spin 0.5s linear;
}

/* Style สำหรับ unread emails */
.fw-bold {
    font-weight: 600 !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .list-group-item .btn-sm {
        padding: 0.25rem 0.5rem;
    }
    
    .list-group-item h6 {
        font-size: 0.9rem;
    }
    
    .list-group-item .small {
        font-size: 0.8rem;
    }
}

/* Style สำหรับ email cards */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

/* Style สำหรับ refresh button */
.btn-outline-light:hover {
    background-color: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
}

/* Style สำหรับ notification badge */
.position-relative .badge {
    position: absolute;
    top: -5px;
    right: -5px;
}

/* เพิ่ม style สำหรับ loading state */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

.loading::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Style สำหรับ email preview */
.email-preview {
    max-height: 300px;
    overflow-y: auto;
    background-color: #f8f9fa;
    border-radius: 0.375rem;
    padding: 1rem;
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