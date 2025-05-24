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

// ดึงข้อมูลสถิติจากฐานข้อมูล
try {
    // จำนวนนักศึกษาทั้งหมด
    $student_query = "SELECT COUNT(*) as total FROM students";
    $student_stmt = $db->prepare($student_query);
    $student_stmt->execute();
    $student_count = $student_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // จำนวนนักศึกษาที่เชื่อมต่อ Google
    $google_query = "SELECT COUNT(*) as total FROM students WHERE google_id IS NOT NULL AND google_id != ''";
    $google_stmt = $db->prepare($google_query);
    $google_stmt->execute();
    $google_count = $google_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // จำนวนนักศึกษาตามคณะ
    $faculty_query = "SELECT faculty, COUNT(*) as count FROM students GROUP BY faculty ORDER BY count DESC";
    $faculty_stmt = $db->prepare($faculty_query);
    $faculty_stmt->execute();
    $faculty_stats = $faculty_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <h2>แดชบอร์ดผู้ดูแลระบบ</h2>
        <p>ยินดีต้อนรับ, <?php echo $_SESSION['admin_name']; ?></p>
    </div>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card border-primary mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">นักศึกษาทั้งหมด</h5>
                        <h2 class="mb-0"><?php echo $student_count; ?></h2>
                    </div>
                    <div>
                        <i class="fas fa-users fa-3x text-primary"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-primary">
                <a href="?page=admin_users" class="text-decoration-none">ดูรายชื่อทั้งหมด <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-success mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">เชื่อมต่อ Google</h5>
                        <h2 class="mb-0"><?php echo $google_count; ?></h2>
                    </div>
                    <div>
                        <i class="fab fa-google fa-3x text-success"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-success">
                <span class="text-muted">คิดเป็น <?php echo ($student_count > 0) ? round(($google_count / $student_count) * 100, 2) : 0; ?>% ของนักศึกษาทั้งหมด</span>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-info mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">เพิ่มนักศึกษา</h5>
                        <p class="mb-0">เพิ่มนักศึกษาใหม่เข้าสู่ระบบ</p>
                    </div>
                    <div>
                        <i class="fas fa-user-plus fa-3x text-info"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-info">
                <div class="d-flex justify-content-between">
                    <a href="?page=admin_add_user" class="text-decoration-none">เพิ่มรายบุคคล</a>
                    <a href="?page=admin_bulk_add" class="text-decoration-none">เพิ่มแบบกลุ่ม</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">จำนวนนักศึกษาตามคณะ</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>คณะ</th>
                            <th>จำนวนนักศึกษา</th>
                            <th>สัดส่วน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faculty_stats as $faculty): ?>
                            <tr>
                                <td><?php echo $faculty['faculty']; ?></td>
                                <td><?php echo $faculty['count']; ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo ($student_count > 0) ? ($faculty['count'] / $student_count) * 100 : 0; ?>%;" aria-valuenow="<?php echo ($student_count > 0) ? ($faculty['count'] / $student_count) * 100 : 0; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo ($student_count > 0) ? round(($faculty['count'] / $student_count) * 100, 2) : 0; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">เมนูลัด</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="?page=admin_users" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i> จัดการผู้ใช้งาน
                    </a>
                    <a href="?page=admin_add_user" class="list-group-item list-group-item-action">
                        <i class="fas fa-user-plus me-2"></i> เพิ่มผู้ใช้งานใหม่
                    </a>
                    <a href="?page=admin_bulk_add" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-upload me-2"></i> นำเข้าข้อมูลผู้ใช้งานแบบกลุ่ม
                    </a>
                    <!-- เพิ่มรายการเมนูใหม่ตรงนี้ -->
                    <a href="?page=admin_logs" class="list-group-item list-group-item-action">
                        <i class="fas fa-history me-2"></i> ประวัติการทำงาน (Logs)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
