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

// ตรวจสอบว่ามีการส่ง ID และ type มาหรือไม่
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    $_SESSION['error_message'] = "ข้อมูลไม่ครบถ้วนสำหรับการดูข้อมูล";
    header("Location: index.php?page=admin_users");
    exit;
}

$user_db_id_encoded = $_GET['id'];
$user_db_id = base64_decode($user_db_id_encoded);
$user_type = $_GET['type'];

if (!is_numeric($user_db_id) || !in_array($user_type, ['student', 'teacher'])) {
    $_SESSION['error_message'] = "รหัสผู้ใช้หรือประเภทผู้ใช้ไม่ถูกต้อง";
    header("Location: index.php?page=admin_users");
    exit;
}

// กำหนดตารางและฟิลด์ตามประเภทผู้ใช้
$table_name = ($user_type === 'student') ? 'students' : 'teachers';
$id_column_name = ($user_type === 'student') ? 'student_id' : 'teacher_id'; // รหัสประจำตัวนักศึกษา/อาจารย์
$user_type_label = ($user_type === 'student') ? 'นักศึกษา' : 'อาจารย์';
$system_table_name = ($user_type === 'student') ? 'student_systems' : 'teacher_systems';
$system_user_id_column = ($user_type === 'student') ? 'student_id' : 'teacher_id'; // คอลัมน์ foreign key ในตาราง systems

$user_data = null;
$user_systems_data = [];
$logs = [];

try {
    // ดึงข้อมูลผู้ใช้
    $query_user = "SELECT * FROM {$table_name} WHERE id = :id LIMIT 1";
    $stmt_user = $db->prepare($query_user);
    $stmt_user->bindParam(':id', $user_db_id, PDO::PARAM_INT);
    $stmt_user->execute();

    if ($stmt_user->rowCount() == 0) {
        throw new Exception("ไม่พบข้อมูล{$user_type_label}");
    }
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // ดึงข้อมูลระบบ (ถ้าตาราง student_systems หรือ teacher_systems มีอยู่)
    $check_system_table_query = $db->query("SHOW TABLES LIKE '{$system_table_name}'");
    if ($check_system_table_query->rowCount() > 0) {
        $query_systems = "SELECT * FROM {$system_table_name} WHERE {$system_user_id_column} = :user_db_id ORDER BY system_name";
        $stmt_systems = $db->prepare($query_systems);
        $stmt_systems->bindParam(':user_db_id', $user_data['id'], PDO::PARAM_INT); // ใช้ id จากตาราง users หลัก
        $stmt_systems->execute();
        $user_systems_data = $stmt_systems->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ดึงข้อมูลประวัติการแก้ไข
    // ค้นหา log ที่ action มี user_code (student_id หรือ teacher_id)
    $user_code_for_log = $user_data[$id_column_name];
    $log_query = "SELECT al.*, a.name as admin_name 
                  FROM admin_logs al 
                  LEFT JOIN admins a ON al.admin_id = a.id 
                  WHERE (al.action LIKE :user_action OR al.action LIKE :system_action_specific)
                  ORDER BY al.created_at DESC 
                  LIMIT 10";
    $log_stmt = $db->prepare($log_query);
    $user_action_pattern = "%" . $user_code_for_log . "%";
    $system_action_specific_pattern = "แก้ไขข้อมูลการเข้าระบบ ({$user_type}): {$user_code_for_log}";

    $log_stmt->bindParam(':user_action', $user_action_pattern);
    $log_stmt->bindParam(':system_action_specific', $system_action_specific_pattern);
    $log_stmt->execute();
    $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: index.php?page=admin_users");
    exit;
}

function getActionIconForViewPage($action) { // Renamed to avoid conflict if included elsewhere
    if (strpos(strtolower($action), 'เพิ่ม') !== false || strpos(strtolower($action), 'import') !== false) {
        return "<i class='fas fa-plus-circle text-success'></i>";
    } elseif (strpos(strtolower($action), 'แก้ไข') !== false) {
        return "<i class='fas fa-edit text-warning'></i>";
    } elseif (strpos(strtolower($action), 'รีเซ็ต') !== false) {
        return "<i class='fas fa-key text-danger'></i>";
    } elseif (strpos(strtolower($action), 'ยกเลิก') !== false) {
        return "<i class='fas fa-unlink text-secondary'></i>";
    } else {
        return "<i class='fas fa-history text-primary'></i>";
    }
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>ข้อมูล<?php echo $user_type_label; ?></h2>
        <p>รหัส<?php echo $user_type_label; ?>: <strong><?php echo htmlspecialchars($user_data[$id_column_name]); ?></strong></p>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=admin_edit_user&id=<?php echo $user_db_id_encoded; ?>&type=<?php echo $user_type; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> แก้ไขข้อมูล</a>
        <a href="?page=admin_users" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> กลับไปยังรายการผู้ใช้งาน</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">ข้อมูล<?php echo $user_type_label; ?></h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>ข้อมูลหลัก</h6>
                        <hr>
                        <table class="table table-hover">
                            <tr>
                                <th style="width: 40%">รหัส<?php echo $user_type_label; ?>:</th>
                                <td><?php echo htmlspecialchars($user_data[$id_column_name]); ?></td>
                            </tr>
                            <tr>
                                <th>ชื่อ-นามสกุล:</th>
                                <td><?php echo htmlspecialchars($user_data['firstname'] . ' ' . $user_data['lastname']); ?></td>
                            </tr>
                            <tr>
                                <th>อีเมล:</th>
                                <td><?php echo htmlspecialchars($user_data['email']); ?></td>
                            </tr>
                            <tr>
                                <th>เบอร์โทรศัพท์:</th>
                                <td><?php echo !empty($user_data['phone']) ? htmlspecialchars($user_data['phone']) : '<em class="text-muted">ไม่ระบุ</em>'; ?></td>
                            </tr>
                            <?php if ($user_type === 'student'): ?>
                            <tr>
                                <th>คณะ:</th>
                                <td><?php echo htmlspecialchars($user_data['faculty']); ?></td>
                            </tr>
                            <tr>
                                <th>สาขา:</th>
                                <td><?php echo !empty($user_data['department']) ? htmlspecialchars($user_data['department']) : '<em class="text-muted">ไม่ระบุ</em>'; ?></td>
                            </tr>
                             <tr>
                                <th>ที่อยู่:</th>
                                <td><?php echo !empty($user_data['address']) ? nl2br(htmlspecialchars($user_data['address'])) : '<em class="text-muted">ไม่ระบุ</em>'; ?></td>
                            </tr>
                            <?php elseif ($user_type === 'teacher'): ?>
                            <tr>
                                <th>ภาควิชา/แผนก:</th>
                                <td><?php echo !empty($user_data['department']) ? htmlspecialchars($user_data['department']) : '<em class="text-muted">ไม่ระบุ</em>'; ?></td>
                            </tr>
                            <tr>
                                <th>ตำแหน่ง:</th>
                                <td><?php echo !empty($user_data['position']) ? htmlspecialchars($user_data['position']) : '<em class="text-muted">ไม่ระบุ</em>'; ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>ข้อมูลระบบ</h6>
                        <hr>
                        <table class="table table-hover">
                             <tr>
                                <th style="width: 40%">รหัสบัตรประชาชน:</th>
                                <td><?php echo substr($user_data['id_card'],0,4) . "XXXXXXXXX"; ?></td>
                            </tr>
                            <tr>
                                <th style="width: 40%">สถานะ Google:</th>
                                <td>
                                    <?php if(!empty($user_data['google_id'])): ?>
                                        <span class="badge bg-success"><i class="fab fa-google"></i> เชื่อมต่อแล้ว</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ยังไม่เชื่อมต่อ</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>สถานะล็อกอิน:</th>
                                <td>
                                    <?php if(isset($user_data['first_login']) && $user_data['first_login'] == 1): ?>
                                        <span class="badge bg-warning text-dark">ยังไม่เคยล็อกอิน</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">เคยล็อกอินแล้ว</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr><th>วันที่สร้าง:</th><td><?php echo date('d/m/Y H:i', strtotime($user_data['created_at'])); ?></td></tr>
                            <tr><th>อัพเดตล่าสุด:</th><td><?php echo !empty($user_data['updated_at']) ? date('d/m/Y H:i', strtotime($user_data['updated_at'])) : '<em class="text-muted">ยังไม่มี</em>'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0"><i class="fas fa-key me-2"></i>ข้อมูลการเข้าใช้งานระบบต่างๆ</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($user_systems_data)): ?>
                    <div class="row">
                        <?php foreach ($user_systems_data as $system): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-header"><strong><?php echo htmlspecialchars($system['system_name']); ?></strong></div>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item"><strong>Username:</strong> <?php echo htmlspecialchars($system['username']); ?></li>
                                        <li class="list-group-item"><strong>Password:</strong> <?php echo htmlspecialchars($system['initial_password']); ?></li>
                                        <li class="list-group-item"><strong>URL ระบบ:</strong> <?php echo !empty($system['system_url']) ? '<a href="'.htmlspecialchars($system['system_url']).'" target="_blank">'.htmlspecialchars($system['system_url']).'</a>' : '<em class="text-muted">N/A</em>'; ?></li>
                                        <li class="list-group-item"><strong>URL คู่มือ:</strong> <?php echo !empty($system['manual_url']) ? '<a href="'.htmlspecialchars($system['manual_url']).'" target="_blank">'.htmlspecialchars($system['manual_url']).'</a>' : '<em class="text-muted">N/A</em>'; ?></li>
                                    </ul>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">ไม่พบข้อมูลการเข้าใช้งานระบบของ<?php echo $user_type_label; ?>คนนี้</div>
                <?php endif; ?>
                 <div class="mt-3 text-center">
                    <a href="?page=admin_edit_user&id=<?php echo $user_db_id_encoded; ?>&type=<?php echo $user_type; ?>#systems" class="btn btn-outline-primary"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลระบบ</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0"><i class="fas fa-history"></i> ประวัติการแก้ไขล่าสุด (10 รายการ)</h5>
            </div>
            <div class="card-body p-0">
                <?php if(count($logs) > 0): ?>
                    <ul class="list-group list-group-flush" style="max-height: 80vh; overflow-y: auto;">
                        <?php foreach($logs as $log_item): ?>
                            <li class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 small"><?php echo getActionIconForViewPage($log_item['action']); ?> <?php echo htmlspecialchars($log_item['action']); ?></h6>
                                    <small class="text-muted"><?php echo date('d/m/y H:i', strtotime($log_item['created_at'])); ?></small>
                                </div>
                                <p class="mb-1 small text-muted">โดย: <?php echo htmlspecialchars($log_item['admin_name']); ?></p>
                                
                                <?php if(!empty($log_item['old_data']) || !empty($log_item['new_data'])): ?>
                                    <button class="btn btn-xs btn-outline-info mt-1" type="button" data-bs-toggle="collapse" data-bs-target="#logCollapseView<?php echo $log_item['id']; ?>" aria-expanded="false" aria-controls="logCollapseView<?php echo $log_item['id']; ?>">
                                        <i class="fas fa-eye"></i> ดูรายละเอียด
                                    </button>
                                    <div class="collapse mt-2" id="logCollapseView<?php echo $log_item['id']; ?>">
                                        <div class="card card-body p-2 small bg-light">
                                            <div id="log-changes-view-<?php echo $log_item['id']; ?>"></div>
                                            <script>
                                                document.addEventListener('DOMContentLoaded', function() {
                                                    const oldDataStr_v<?php echo $log_item['id']; ?> = <?php echo json_encode($log_item['old_data'] ?? null, JSON_UNESCAPED_UNICODE); ?>;
                                                    const newDataStr_v<?php echo $log_item['id']; ?> = <?php echo json_encode($log_item['new_data'] ?? null, JSON_UNESCAPED_UNICODE); ?>;
                                                    let oldD_v<?php echo $log_item['id']; ?> = null;
                                                    let newD_v<?php echo $log_item['id']; ?> = null;

                                                    try { oldD_v<?php echo $log_item['id']; ?> = oldDataStr_v<?php echo $log_item['id']; ?> ? JSON.parse(oldDataStr_v<?php echo $log_item['id']; ?>) : null; } catch(e) { console.warn("Error parsing oldData for log <?php echo $log_item['id']; ?>");}
                                                    try { newD_v<?php echo $log_item['id']; ?> = newDataStr_v<?php echo $log_item['id']; ?> ? JSON.parse(newDataStr_v<?php echo $log_item['id']; ?>) : null; } catch(e) { console.warn("Error parsing newData for log <?php echo $log_item['id']; ?>");}
                                                    
                                                    const changesHtml_v<?php echo $log_item['id']; ?> = generateDetailedChangesTableForViewPage(oldD_v<?php echo $log_item['id']; ?>, newD_v<?php echo $log_item['id']; ?>);
                                                    const container_v<?php echo $log_item['id']; ?> = document.getElementById('log-changes-view-<?php echo $log_item['id']; ?>');
                                                    if (changesHtml_v<?php echo $log_item['id']; ?>) {
                                                        container_v<?php echo $log_item['id']; ?>.innerHTML = changesHtml_v<?php echo $log_item['id']; ?>;
                                                    } else {
                                                        container_v<?php echo $log_item['id']; ?>.innerHTML = '<p class="text-muted mb-0">ไม่มีรายละเอียดการเปลี่ยนแปลงฟิลด์</p>';
                                                    }
                                                });
                                            </script>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                     <div class="p-2 text-center">
                        <a href="?page=admin_logs&search=<?php echo urlencode($user_data[$id_column_name]); ?>" class="btn btn-sm btn-outline-primary">ดูประวัติทั้งหมดของ <?php echo $user_type_label; ?> นี้</a>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center p-3">ไม่พบประวัติการแก้ไขสำหรับ<?php echo $user_type_label; ?>คนนี้</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// ฟังก์ชันนี้ควรจะเหมือนกับใน admin_logs.php หรือเป็นฟังก์ชันที่ใช้ร่วมกัน
function generateDetailedChangesTableForViewPage(oldData, newData) {
    let html = '<table class="table table-sm table-bordered table-striped mb-0" style="font-size: 0.8rem;">';
    html += '<thead class="table-light"><tr><th>รายการ/ฟิลด์</th><th>ค่าเดิม</th><th>ค่าใหม่</th></tr></thead><tbody>';
    let hasChanges = false;
    
    const allKeys = new Set([...(oldData ? Object.keys(oldData) : []), ...(newData ? Object.keys(newData) : [])]);

    allKeys.forEach(key => {
        const oldValue = oldData ? oldData[key] : undefined;
        const newValue = newData ? newData[key] : undefined;

        if (JSON.stringify(oldValue) !== JSON.stringify(newValue)) {
            hasChanges = true;
            if (typeof newValue === 'object' && newValue !== null && typeof oldValue === 'object' && oldValue !== null && !Array.isArray(newValue) && !Array.isArray(oldValue)) {
                html += `<tr><td colspan="3" class="bg-secondary-subtle text-dark p-1"><strong>${escapeHtmlForViewPage(key)}</strong></td></tr>`;
                const subKeys = new Set([...Object.keys(oldValue), ...Object.keys(newValue)]);
                subKeys.forEach(subKey => {
                    const oldSubValue = oldValue[subKey];
                    const newSubValue = newValue[subKey];
                    if (JSON.stringify(oldSubValue) !== JSON.stringify(newSubValue)) {
                         html += `<tr>`;
                         html += `<td class="ps-2">&nbsp;&nbsp;${escapeHtmlForViewPage(subKey)}</td>`;
                         html += `<td>${formatValueForDisplayForViewPage(oldSubValue)}</td>`;
                         html += `<td>${formatValueForDisplayForViewPage(newSubValue)}</td>`;
                         html += `</tr>`;
                    }
                });
            } else if (typeof newValue === 'object' && newValue !== null && (oldValue === undefined || oldValue === null) && !Array.isArray(newValue)) { // Added new system object
                html += `<tr><td colspan="3" class="bg-success-subtle text-dark p-1"><strong>${escapeHtmlForViewPage(key)} (เพิ่มใหม่)</strong></td></tr>`;
                Object.keys(newValue).forEach(subKey => {
                     html += `<tr>`;
                     html += `<td class="ps-2">&nbsp;&nbsp;${escapeHtmlForViewPage(subKey)}</td>`;
                     html += `<td><em>ไม่มี</em></td>`;
                     html += `<td>${formatValueForDisplayForViewPage(newValue[subKey])}</td>`;
                     html += `</tr>`;
                });
            } else { // Simple field change or non-object system change
                html += `<tr>`;
                html += `<td><strong>${escapeHtmlForViewPage(key)}</strong></td>`;
                html += `<td>${formatValueForDisplayForViewPage(oldValue)}</td>`;
                html += `<td>${formatValueForDisplayForViewPage(newValue)}</td>`;
                html += `</tr>`;
            }
        }
    });

    html += '</tbody></table>';
    return hasChanges ? html : '<p class="text-muted mb-0">ไม่มีการเปลี่ยนแปลงรายละเอียดฟิลด์ หรือเป็นการดำเนินการที่ไม่ติดตามฟิลด์ย่อย</p>';
}

function formatValueForDisplayForViewPage(value) {
    if (value === undefined || value === null) {
        return '<em class="text-muted">ไม่มี</em>';
    }
    if (typeof value === 'object' && !Array.isArray(value)) {
        return '<pre class="mb-0 small p-1 bg-white border rounded">' + escapeHtmlForViewPage(JSON.stringify(value, null, 2)) + '</pre>';
    }
    if (String(value).length > 50) {
         return '<span title="' + escapeHtmlForViewPage(String(value)) + '">' + escapeHtmlForViewPage(String(value).substring(0, 47)) + '...</span>';
    }
    return escapeHtmlForViewPage(String(value));
}

function escapeHtmlForViewPage(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return String(unsafe)
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}
</script>
<style>
    .btn-xs { padding: 0.1rem 0.3rem; font-size: 0.7rem; }
    .bg-success-subtle { background-color: #d1e7dd !important; } /* Bootstrap 5.3+ class name */
    .bg-secondary-subtle { background-color: #e2e3e5 !important; } /* Bootstrap 5.3+ class name */
</style>
