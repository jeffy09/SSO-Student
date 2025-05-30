<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// ตรวจสอบว่ามี session ของอาจารย์หรือไม่
if (!isset($_SESSION['teacher_user_id'])) {
    header("Location: index.php?page=teacher_login");
    exit;
}

$teacher = [];
$teacher_systems = []; // เพิ่มตัวแปรสำหรับเก็บข้อมูลระบบของอาจารย์
$error_message = '';

// ดึงข้อมูลอาจารย์
try {
    $query_teacher = "SELECT * FROM teachers WHERE id = :id LIMIT 0,1";
    $stmt_teacher = $db->prepare($query_teacher);
    $stmt_teacher->bindParam(':id', $_SESSION['teacher_user_id']);
    $stmt_teacher->execute();
    
    if ($stmt_teacher->rowCount() > 0) {
        $teacher = $stmt_teacher->fetch(PDO::FETCH_ASSOC);

        // ---- เพิ่มการดึงข้อมูลระบบของอาจารย์ ----
        // ตรวจสอบว่าตาราง teacher_systems มีอยู่หรือไม่ (ป้องกัน error หากยังไม่ได้สร้างตาราง)
        $check_table_query = $db->query("SHOW TABLES LIKE 'teacher_systems'");
        if ($check_table_query->rowCount() > 0) {
            $query_teacher_systems = "SELECT * FROM teacher_systems WHERE teacher_id = :teacher_db_id ORDER BY system_name";
            $stmt_teacher_systems = $db->prepare($query_teacher_systems);
            $stmt_teacher_systems->bindParam(':teacher_db_id', $teacher['id']); // ใช้ $teacher['id'] ซึ่งคือ id จากตาราง teachers
            $stmt_teacher_systems->execute();
            $teacher_systems = $stmt_teacher_systems->fetchAll(PDO::FETCH_ASSOC);
        }
        // ---- สิ้นสุดการดึงข้อมูลระบบของอาจารย์ ----

    } else {
        // ไม่พบข้อมูล ให้ logout
        header("Location: index.php?page=logout");
        exit;
    }
} catch(PDOException $e) {
    $error_message = "เกิดข้อผิดพลาดในการดึงข้อมูลอาจารย์: " . $e->getMessage();
}
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <h2>แดชบอร์ดอาจารย์</h2>
        <p>ยินดีต้อนรับ, อาจารย์ <?php echo htmlspecialchars($_SESSION['teacher_name']); ?> (<?php echo htmlspecialchars($teacher['teacher_id'] ?? 'N/A'); ?>)</p>
    </div>
</div>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">ข้อมูลส่วนตัวอาจารย์</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($teacher)): ?>
                <table class="table">
                    <tr>
                        <th>รหัสอาจารย์:</th>
                        <td><?php echo htmlspecialchars($teacher['teacher_id']); ?></td>
                    </tr>
                    <tr>
                        <th>ชื่อ-นามสกุล:</th>
                        <td><?php echo htmlspecialchars($teacher['firstname'] . ' ' . $teacher['lastname']); ?></td>
                    </tr>
                    <tr>
                        <th>อีเมล:</th>
                        <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                    </tr>
                    <tr>
                        <th>ภาควิชา/แผนก:</th>
                        <td><?php echo htmlspecialchars($teacher['department'] ?: '-'); ?></td>
                    </tr>
                     <tr>
                        <th>ตำแหน่ง:</th>
                        <td><?php echo htmlspecialchars($teacher['position'] ?: '-'); ?></td>
                    </tr>
                </table>
                <a href="?page=teacher_profile" class="btn btn-primary">แก้ไขข้อมูลส่วนตัว</a>
                <?php else: ?>
                    <p class="text-danger">ไม่สามารถโหลดข้อมูลอาจารย์ได้</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">การเชื่อมต่อบัญชี</h5>
            </div>
            <div class="card-body">
                <?php if(empty($teacher['google_id'])): ?>
                    <div class="alert alert-warning">
                        คุณยังไม่ได้เชื่อมต่อบัญชี Google กับระบบ
                    </div>
                    <div class="mb-3">
                        <p>เชื่อมต่อกับบัญชี Google ของคุณเพื่อให้สามารถลงชื่อเข้าใช้ด้วย Google ได้ในครั้งต่อไป</p>
                        <a href="?page=google_login&user_type=teacher&from_profile=1" class="btn btn-danger"> <i class="fab fa-google me-2"></i>เชื่อมต่อบัญชี Google
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        คุณได้เชื่อมต่อบัญชี Google กับระบบแล้ว
                    </div>
                    <p>คุณสามารถใช้ Google Sign-In เพื่อเข้าสู่ระบบในครั้งต่อไปได้</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header bg-success text-white">
        <h5 class="card-title mb-0"><i class="fas fa-key me-2"></i>ข้อมูลการเข้าใช้งานระบบต่างๆ</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($teacher_systems)): ?>
            <div class="row">
                <?php foreach ($teacher_systems as $system): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <strong><?php echo htmlspecialchars($system['system_name']); ?></strong>
                            </div>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong>Username:</strong> <?php echo htmlspecialchars($system['username']); ?></li>
                                <li class="list-group-item"><strong>Password:</strong> <?php echo htmlspecialchars($system['initial_password']); ?></li>
                            </ul>
                            <div class="card-body text-center">
                                <a href="<?php echo htmlspecialchars($system['system_url'] ?: '#'); ?>" class="btn btn-sm btn-primary" target="_blank" <?php echo empty($system['system_url']) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-sign-in-alt me-1"></i> เข้าสู่ระบบ
                                </a>
                                <a href="<?php echo htmlspecialchars($system['manual_url'] ?: '#'); ?>" class="btn btn-sm btn-secondary" target="_blank" <?php echo empty($system['manual_url']) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-book me-1"></i> คู่มือ
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">ไม่พบข้อมูลการเข้าใช้งานระบบสำหรับอาจารย์</div>
        <?php endif; ?>
    </div>
</div>
<?php
// ส่วนแสดง pop-up ให้เชื่อม Google ถ้ายังไม่ได้เชื่อม (สำหรับอาจารย์)
if (isset($_SESSION['show_google_link_teacher']) && $_SESSION['show_google_link_teacher']):
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: 'เชื่อมต่อบัญชี Google',
            text: 'คุณต้องการเชื่อมต่อบัญชี Google กับระบบหรือไม่? การเชื่อมต่อจะช่วยให้คุณลงชื่อเข้าใช้ด้วย Google ได้ในครั้งต่อไป',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ใช่, เชื่อมต่อ',
            cancelButtonText: 'ไม่, ขอบคุณ'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?page=google_login&user_type=teacher&from_profile=1'; // ส่ง from_profile=1 เพื่อให้ callback ไปหน้า profile
            }
        });
    });
    <?php unset($_SESSION['show_google_link_teacher']); ?>
</script>
<?php endif; ?>