<?php
// ไฟล์ pages/change_password.php

if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['student_id'])) {
    header('Location: index.php?page=student_login');
    exit;
}

// ประมวลผลฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include_once 'auth/student_login_process.php';
    
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // ตรวจสอบข้อมูล
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        // เปลี่ยนรหัสผ่าน
        $result = changeStudentPassword($db, $_SESSION['student_code'], $old_password, $new_password);
        
        if ($result['success']) {
            $success_message = $result['message'];
        } else {
            $error_message = $result['message'];
        }
    }
}
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
                    </h5>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="changePasswordForm">
                        <div class="mb-3">
                            <label for="old_password" class="form-label">รหัสผ่านเดิม *</label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="old_password" 
                                       name="old_password" 
                                       required>
                                <button class="btn btn-outline-secondary" 
                                        type="button" 
                                        onclick="togglePassword('old_password')">
                                    <i class="fas fa-eye" id="old_password_icon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">รหัสผ่านใหม่ *</label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="new_password" 
                                       name="new_password" 
                                       required>
                                <button class="btn btn-outline-secondary" 
                                        type="button" 
                                        onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye" id="new_password_icon"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร มีตัวเลขและตัวอักษรภาษาอังกฤษ
                            </div>
                            <div id="password_strength" class="mt-2"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่ *</label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       required>
                                <button class="btn btn-outline-secondary" 
                                        type="button" 
                                        onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye" id="confirm_password_icon"></i>
                                </button>
                            </div>
                            <div id="password_match" class="mt-1"></div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> เปลี่ยนรหัสผ่าน
                            </button>
                            <a href="index.php?page=student_profile" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> กลับ
                            </a>
                        </div>
                    </form>
                    
                </div>
            </div>
            
            <!-- คำแนะนำความปลอดภัย -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-shield-alt"></i> ข้อแนะนำความปลอดภัย
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>ใช้รหัสผ่านที่แข็งแกร่งและไม่เคยใช้ที่อื่น</li>
                        <li>ไม่แชร์รหัสผ่านให้ผู้อื่น</li>
                        <li>เปลี่ยนรหัสผ่านเป็นระยะ ๆ</li>
                        <li>ออกจากระบบทุกครั้งหลังใช้งาน</li>
                        <li>ไม่บันทึกรหัสผ่านในเบราว์เซอร์สาธารณะ</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// แสดง/ซ่อนรหัสผ่าน
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// ตรวจสอบความแข็งแกร่งของรหัสผ่าน
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('password_strength');
    
    let score = 0;
    let feedback = [];
    
    // ตรวจสอบความยาว
    if (password.length >= 8) {
        score++;
    } else {
        feedback.push('อย่างน้อย 8 ตัวอักษร');
    }
    
    // ตรวจสอบตัวเลข
    if (/[0-9]/.test(password)) {
        score++;
    } else {
        feedback.push('มีตัวเลข');
    }
    
    // ตรวจสอบตัวอักษรภาษาอังกฤษ
    if (/[a-zA-Z]/.test(password)) {
        score++;
    } else {
        feedback.push('มีตัวอักษรภาษาอังกฤษ');
    }
    
    // ตรวจสอบตัวอักษรใหญ่และเล็ก
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
        score++;
    }
    
    // ตรวจสอบอักขระพิเศษ
    if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
        score++;
    }
    
    // แสดงผล
    let strengthText = '';
    let strengthClass = '';
    
    if (password.length === 0) {
        strengthDiv.innerHTML = '';
        return;
    }
    
    if (score < 2) {
        strengthText = 'อ่อนแอ';
        strengthClass = 'text-danger';
    } else if (score < 3) {
        strengthText = 'ปานกลาง';
        strengthClass = 'text-warning';
    } else if (score < 4) {
        strengthText = 'ดี';
        strengthClass = 'text-info';
    } else {
        strengthText = 'แข็งแกร่ง';
        strengthClass = 'text-success';
    }
    
    let html = `<small class="${strengthClass}">ความแข็งแกร่ง: ${strengthText}</small>`;
    
    if (feedback.length > 0) {
        html += `<br><small class="text-muted">ต้องการ: ${feedback.join(', ')}</small>`;
    }
    
    strengthDiv.innerHTML = html;
});

// ตรวจสอบการยืนยันรหัสผ่าน
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    const matchDiv = document.getElementById('password_match');
    
    if (confirmPassword.length === 0) {
        matchDiv.innerHTML = '';
        return;
    }
    
    if (newPassword === confirmPassword) {
        matchDiv.innerHTML = '<small class="text-success"><i class="fas fa-check"></i> รหัสผ่านตรงกัน</small>';
    } else {
        matchDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times"></i> รหัสผ่านไม่ตรงกัน</small>';
    }
});

// ตรวจสอบก่อนส่งฟอร์ม
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // ตรวจสอบความยาว
    if (newPassword.length < 8) {
        e.preventDefault();
        alert('รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร');
        return;
    }
    
    // ตรวจสอบตัวเลข
    if (!/[0-9]/.test(newPassword)) {
        e.preventDefault();
        alert('รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว');
        return;
    }
    
    // ตรวจสอบตัวอักษร
    if (!/[a-zA-Z]/.test(newPassword)) {
        e.preventDefault();
        alert('รหัสผ่านต้องมีตัวอักษรภาษาอังกฤษอย่างน้อย 1 ตัว');
        return;
    }
    
    // ตรวจสอบการยืนยัน
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน');
        return;
    }
});
</script>