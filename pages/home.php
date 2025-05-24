<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// ตรวจสอบสถานะการล็อกอิน และ redirect ไปยัง dashboard ของแต่ละประเภทผู้ใช้
if (isset($_SESSION['student_id'])) {
    // หากเป็นนักศึกษาที่ล็อกอินแล้ว ให้ไปยังหน้า dashboard ของนักศึกษา
    header("Location: index.php?page=student_dashboard");
    exit;
} elseif (isset($_SESSION['admin_id'])) {
    // หากเป็นผู้ดูแลระบบที่ล็อกอินแล้ว ให้ไปยังหน้า dashboard ของผู้ดูแลระบบ
    header("Location: index.php?page=admin_dashboard");
    exit;
}

// หากไม่ได้ล็อกอิน ให้แสดงหน้า home ปกติ
?>

<div class="row justify-content-center">
    <div class="col-md-8 text-center">
        <h1 class="display-4 mt-5 mb-4">ระบบจัดการนักศึกษา</h1>
        <p class="lead mb-5">ยินดีต้อนรับเข้าสู่ระบบจัดการข้อมูลนักศึกษา</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">สำหรับนักศึกษา</h5>
            </div>
            <div class="card-body">
                <p>เข้าสู่ระบบด้วยรหัสนักศึกษาและรหัสบัตรประชาชน</p>
                <p>หลังจากเข้าสู่ระบบครั้งแรก คุณสามารถเชื่อมต่อกับบัญชี Google เพื่อให้เข้าสู่ระบบได้สะดวกในครั้งต่อไป</p>
                <div class="text-center">
                    <a href="?page=student_login" class="btn btn-primary"><i class="fas fa-user-graduate"></i> เข้าสู่ระบบสำหรับนักศึกษา</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-5">
        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">สำหรับผู้ดูแลระบบ</h5>
            </div>
            <div class="card-body">
                <p>เข้าสู่ระบบด้วยชื่อผู้ใช้และรหัสผ่าน</p>
                <p>ผู้ดูแลระบบสามารถจัดการข้อมูลนักศึกษา เพิ่ม แก้ไข และดูข้อมูลต่างๆ ในระบบได้</p>
                <div class="text-center">
                    <a href="?page=admin_login" class="btn btn-success"><i class="fas fa-user-shield"></i> เข้าสู่ระบบสำหรับผู้ดูแลระบบ</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mt-5">
    <div class="col-md-10">
        <div class="alert alert-info alert-permanent">
            <h5><i class="fas fa-info-circle"></i> วิธีการใช้งานเบื้องต้น</h5>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <h6>สำหรับนักศึกษา</h6>
                    <ol>
                        <li>เข้าสู่ระบบด้วยรหัสนักศึกษาและรหัสบัตรประชาชน</li>
                        <li>ในการเข้าสู่ระบบครั้งแรก ระบบจะแนะนำให้เชื่อมต่อกับบัญชี Google</li>
                        <li>เมื่อเชื่อมต่อแล้ว ครั้งต่อไปสามารถล็อกอินผ่าน Google ได้ทันที</li>
                        <li>สามารถดูและแก้ไขข้อมูลส่วนตัวได้ในหน้าโปรไฟล์</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h6>สำหรับผู้ดูแลระบบ</h6>
                    <ol>
                        <li>เข้าสู่ระบบด้วยชื่อผู้ใช้และรหัสผ่าน</li>
                        <li>สามารถเพิ่มนักศึกษาได้ทั้งแบบรายบุคคลและแบบกลุ่ม</li>
                        <li>ดูและแก้ไขข้อมูลนักศึกษาได้ในหน้าจัดการผู้ใช้งาน</li>
                        <li>สามารถรีเซ็ตรหัสบัตรประชาชนและยกเลิกการเชื่อมต่อ Google ได้</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- แสดงข้อความแจ้งเตือนสำหรับผู้ใช้ที่ยังไม่ได้ล็อกอิน -->
<div class="row justify-content-center mt-4">
    <div class="col-md-8 text-center">
        <div class="alert alert-light border">
            <p class="mb-0"><i class="fas fa-lightbulb text-warning"></i> <strong>เริ่มต้นใช้งาน:</strong> เลือกประเภทผู้ใช้ข้างต้นเพื่อเข้าสู่ระบบ หากมีปัญหาการเข้าสู่ระบบ กรุณาติดต่อผู้ดูแลระบบ</p>
        </div>
    </div>
</div>