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

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id'])) {
    header("Location: index.php?page=admin_users");
    exit;
}

// ถอดรหัส ID
try {
    $id = base64_decode($_GET['id']);
    
    // ตรวจสอบว่า ID เป็นตัวเลขหรือไม่
    if (!is_numeric($id)) {
        throw new Exception("รหัสไม่ถูกต้อง");
    }
    
    // ดึงข้อมูลนักศึกษา
    $query = "SELECT * FROM students WHERE id = :id LIMIT 0,1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        throw new Exception("ไม่พบข้อมูลนักศึกษา");
    }
    
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลประวัติการแก้ไข
    $log_query = "SELECT al.*, a.name as admin_name 
                FROM admin_logs al 
                LEFT JOIN admins a ON al.admin_id = a.id 
                WHERE al.action LIKE :action 
                ORDER BY al.created_at DESC 
                LIMIT 10";
    $log_stmt = $db->prepare($log_query);
    $log_action = "%{$student['student_id']}%";
    $log_stmt->bindParam(':action', $log_action);
    $log_stmt->execute();
    $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: index.php?page=admin_users");
    exit;
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>ข้อมูลนักศึกษา</h2>
        <p>รหัสนักศึกษา: <?php echo $student['student_id']; ?></p>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> แก้ไขข้อมูล</a>
        <a href="?page=admin_users" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> กลับไปยังรายการผู้ใช้งาน</a>
    </div>
</div>

<div class="row">
    <!-- ข้อมูลนักศึกษา -->
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">ข้อมูลนักศึกษา</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>ข้อมูลหลัก</h6>
                        <hr>
                        <table class="table table-hover">
                            <tr>
                                <th style="width: 40%">รหัสนักศึกษา:</th>
                                <td><?php echo $student['student_id']; ?></td>
                            </tr>
                            <tr>
                                <th>ชื่อ-นามสกุล:</th>
                                <td><?php echo $student['firstname'] . ' ' . $student['lastname']; ?></td>
                            </tr>
                            <tr>
                                <th>อีเมล:</th>
                                <td><?php echo $student['email']; ?></td>
                            </tr>
                            <tr>
                                <th>เบอร์โทรศัพท์:</th>
                                <td><?php echo !empty($student['phone']) ? $student['phone'] : '<em class="text-muted">ไม่ระบุ</em>'; ?></td>
                            </tr>
                            <tr>
                                <th>คณะ:</th>
                                <td><?php echo $student['faculty']; ?></td>
                            </tr>
                            <tr>
                                <th>สาขา:</th>
                                <td><?php echo !empty($student['department']) ? $student['department'] : '<em class="text-muted">ไม่ระบุ</em>'; ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>ข้อมูลระบบ</h6>
                        <hr>
                        <table class="table table-hover">
                            <tr>
                                <th style="width: 40%">สถานะการเชื่อมต่อ Google:</th>
                                <td>
                                    <?php if(!empty($student['google_id'])): ?>
                                        <span class="badge bg-success"><i class="fab fa-google"></i> เชื่อมต่อแล้ว</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ยังไม่เชื่อมต่อ</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>สถานะการล็อกอิน:</th>
                                <td>
                                    <?php if($student['first_login'] == 1): ?>
                                        <span class="badge bg-warning text-dark">ยังไม่เคยล็อกอิน</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">เคยล็อกอินแล้ว</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>วันที่สร้าง:</th>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($student['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>อัพเดตล่าสุด:</th>
                                <td>
                                    <?php if(!empty($student['updated_at'])): ?>
                                        <?php echo date('d/m/Y H:i:s', strtotime($student['updated_at'])); ?>
                                    <?php else: ?>
                                        <em class="text-muted">ยังไม่มีการอัพเดต</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h6>ที่อยู่</h6>
                    <hr>
                    <?php if(!empty($student['address'])): ?>
                        <p><?php echo nl2br($student['address']); ?></p>
                    <?php else: ?>
                        <p class="text-muted"><em>ไม่ระบุที่อยู่</em></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ประวัติการแก้ไข -->
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">ประวัติการแก้ไข</h5>
            </div>
            <div class="card-body">
                <?php if(count($logs) > 0): ?>
                    <ul class="list-group">
                        <?php foreach($logs as $log): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-0"><?php echo $log['action']; ?></p>
                                        <small class="text-muted">โดย: <?php echo $log['admin_name']; ?></small>
                                    </div>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></small>
                                </div>
                                
                                <?php if(!empty($log['old_data']) && !empty($log['new_data'])): ?>
                                    <?php 
                                    $old_data = json_decode($log['old_data'], true);
                                    $new_data = json_decode($log['new_data'], true);
                                    $changes = [];
                                    
                                    foreach($new_data as $key => $value) {
                                        if(isset($old_data[$key]) && $old_data[$key] != $value) {
                                            $changes[] = [
                                                'field' => $key,
                                                'old' => $old_data[$key],
                                                'new' => $value
                                            ];
                                        }
                                    }
                                    
                                    if(count($changes) > 0):
                                    ?>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#changes-<?php echo $log['id']; ?>">
                                                แสดงการเปลี่ยนแปลง
                                            </button>
                                            
                                            <div class="collapse mt-2" id="changes-<?php echo $log['id']; ?>">
                                                <div class="card card-body">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>ฟิลด์</th>
                                                                <th>ค่าเดิม</th>
                                                                <th>ค่าใหม่</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach($changes as $change): ?>
                                                                <tr>
                                                                    <td><?php echo $change['field']; ?></td>
                                                                    <td><?php echo !empty($change['old']) ? $change['old'] : '<em>ไม่ระบุ</em>'; ?></td>
                                                                    <td><?php echo !empty($change['new']) ? $change['new'] : '<em>ไม่ระบุ</em>'; ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted text-center">ไม่พบประวัติการแก้ไข</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>