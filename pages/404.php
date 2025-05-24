<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}
?>

<div class="row justify-content-center">
    <div class="col-md-8 text-center">
        <div class="mt-5 mb-5">
            <h1 class="display-1">404</h1>
            <h2 class="mb-4">ไม่พบหน้าที่คุณต้องการ</h2>
            <p class="lead mb-5">หน้าที่คุณพยายามเข้าถึงอาจถูกย้าย ลบ หรือไม่มีอยู่ในระบบ</p>
            <a href="index.php" class="btn btn-primary btn-lg"><i class="fas fa-home"></i> กลับไปยังหน้าหลัก</a>
        </div>
    </div>
</div>