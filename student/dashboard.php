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

// ดึงข้อมูลนักศึกษาและข้อมูลระบบ
try {
    // ดึงข้อมูลนักศึกษา
    $query_student = "SELECT * FROM students WHERE id = :id LIMIT 0,1";
    $stmt_student = $db->prepare($query_student);
    $stmt_student->bindParam(':id', $_SESSION['student_id']);
    $stmt_student->execute();
    
    if ($stmt_student->rowCount() > 0) {
        $student = $stmt_student->fetch(PDO::FETCH_ASSOC);
    } else {
        header("Location: index.php?page=logout");
        exit;
    }

    // ดึงข้อมูลระบบ
    $query_systems = "SELECT * FROM student_systems WHERE student_id = :student_id ORDER BY system_name";
    $stmt_systems = $db->prepare($query_systems);
    $stmt_systems->bindParam(':student_id', $student['id']);
    $stmt_systems->execute();
    $systems = $stmt_systems->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <h2>แดชบอร์ด</h2>
        <p>ยินดีต้อนรับ, <?php echo htmlspecialchars($_SESSION['student_name']); ?></p>
    </div>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">ข้อมูลนักศึกษา</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <th>รหัสนักศึกษา:</th>
                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                    </tr>
                    <tr>
                        <th>ชื่อ-นามสกุล:</th>
                        <td><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></td>
                    </tr>
                    <tr>
                        <th>อีเมล:</th>
                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                    </tr>
                    <tr>
                        <th>คณะ:</th>
                        <td><?php echo htmlspecialchars($student['faculty']); ?></td>
                    </tr>
                    <tr>
                        <th>สาขา:</th>
                        <td><?php echo htmlspecialchars($student['department']); ?></td>
                    </tr>
                </table>
                <a href="?page=student_profile" class="btn btn-primary">แก้ไขข้อมูลส่วนตัว</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">การเชื่อมต่อบัญชี</h5>
            </div>
            <div class="card-body">
                <?php if(empty($student['google_id'])): ?>
                    <div class="alert alert-warning">
                        คุณยังไม่ได้เชื่อมต่อบัญชี Google กับระบบ
                    </div>
                    <div class="mb-3">
                        <p>เชื่อมต่อกับบัญชี Gmail มหาวิทยาลัยของคุณเพื่อให้สามารถลงชื่อเข้าใช้ด้วย Google ได้ในครั้งต่อไป</p>
                        <a href="?page=google_login&user_type=student" class="btn btn-danger">
                            <i class="fab fa-google me-2"></i>เชื่อมต่อบัญชี Google
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
        <?php if (!empty($systems)): ?>
            <div class="row">
                <?php foreach ($systems as $system): ?>
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
            <div class="alert alert-info">ไม่พบข้อมูลการเข้าใช้งานระบบ</div>
        <?php endif; ?>
    </div>
</div>


<?php if(isset($_SESSION['show_google_link']) && $_SESSION['show_google_link']): ?>
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
                window.location.href = '?page=google_login&user_type=student';
            }
        });
    });
    
    <?php unset($_SESSION['show_google_link']); ?>
</script>
<?php endif; ?>
