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
    
    if (!is_numeric($id)) {
        throw new Exception("รหัสไม่ถูกต้อง");
    }
    
    $query_student = "SELECT * FROM students WHERE id = :id LIMIT 0,1";
    $stmt_student = $db->prepare($query_student);
    $stmt_student->bindParam(':id', $id);
    $stmt_student->execute();
    
    if ($stmt_student->rowCount() == 0) {
        throw new Exception("ไม่พบข้อมูลนักศึกษา");
    }
    $student = $stmt_student->fetch(PDO::FETCH_ASSOC);

    $query_systems = "SELECT * FROM student_systems WHERE student_id = :student_id ORDER BY system_name";
    $stmt_systems = $db->prepare($query_systems);
    $stmt_systems->bindParam(':student_id', $student['id']);
    $stmt_systems->execute();
    $student_systems = $stmt_systems->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลประวัติการแก้ไขเฉพาะที่เกี่ยวข้องกับ student_id นี้ และ action ที่ชัดเจน
    // หรืออาจจะดึงเฉพาะ log ที่มีการเปลี่ยนแปลง old_data, new_data ที่เป็น JSON ของข้อมูลนักศึกษา
    $log_query = "SELECT al.*, a.name as admin_name 
                FROM admin_logs al 
                LEFT JOIN admins a ON al.admin_id = a.id 
                WHERE (al.action LIKE :student_action OR al.action LIKE :system_action)
                ORDER BY al.created_at DESC 
                LIMIT 10";
    $log_stmt = $db->prepare($log_query);
    $student_action_pattern = "%" . $student['student_id'] . "%"; // สำหรับ action ที่ระบุ student_id
    $system_action_pattern = "แก้ไขข้อมูลการเข้าระบบ: " . $student['student_id']; // สำหรับ action แก้ไขระบบ

    $log_stmt->bindParam(':student_action', $student_action_pattern);
    $log_stmt->bindParam(':system_action', $system_action_pattern);
    $log_stmt->execute();
    $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: index.php?page=admin_users");
    exit;
}

function getActionIconForView($action) { // สร้างฟังก์ชันใหม่หรือใช้ร่วมกับของ logs.php
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
        <h2>ข้อมูลนักศึกษา</h2>
        <p>รหัสนักศึกษา: <strong><?php echo htmlspecialchars($student['student_id']); ?></strong></p>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> แก้ไขข้อมูล</a>
        <a href="?page=admin_users" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> กลับไปยังรายการผู้ใช้งาน</a>
    </div>
</div>

<div class="row">
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
                                <th>เบอร์โทรศัพท์:</th>
                                <td><?php echo !empty($student['phone']) ? htmlspecialchars($student['phone']) : '<em class="text-muted">ไม่ระบุ</em>'; ?></td>
                            </tr>
                            <tr>
                                <th>คณะ:</th>
                                <td><?php echo htmlspecialchars($student['faculty']); ?></td>
                            </tr>
                            <tr>
                                <th>สาขา:</th>
                                <td><?php echo !empty($student['department']) ? htmlspecialchars($student['department']) : '<em class="text-muted">ไม่ระบุ</em>'; ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>ข้อมูลระบบ</h6>
                        <hr>
                        <table class="table table-hover">
                            <tr>
                                <th style="width: 40%">สถานะ Google:</th>
                                <td>
                                    <?php if(!empty($student['google_id'])): ?>
                                        <span class="badge bg-success"><i class="fab fa-google"></i> เชื่อมต่อแล้ว</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ยังไม่เชื่อมต่อ</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>สถานะล็อกอิน:</th>
                                <td>
                                    <?php if($student['first_login'] == 1): ?>
                                        <span class="badge bg-warning text-dark">ยังไม่เคยล็อกอิน</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">เคยล็อกอินแล้ว</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr><th>วันที่สร้าง:</th><td><?php echo date('d/m/Y H:i', strtotime($student['created_at'])); ?></td></tr>
                            <tr><th>อัพเดตล่าสุด:</th><td><?php echo !empty($student['updated_at']) ? date('d/m/Y H:i', strtotime($student['updated_at'])) : '<em class="text-muted">ยังไม่มี</em>'; ?></td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h6>ที่อยู่</h6> <hr>
                    <p><?php echo !empty($student['address']) ? nl2br(htmlspecialchars($student['address'])) : '<em class="text-muted">ไม่ระบุที่อยู่</em>'; ?></p>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0"><i class="fas fa-key me-2"></i>ข้อมูลการเข้าใช้งานระบบต่างๆ</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($student_systems)): ?>
                    <div class="row">
                        <?php foreach ($student_systems as $system): ?>
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
                    <div class="alert alert-info">ไม่พบข้อมูลการเข้าใช้งานระบบของนักศึกษาคนนี้</div>
                <?php endif; ?>
                 <div class="mt-3 text-center">
                    <a href="?page=admin_edit_user&id=<?php echo $_GET['id']; ?>#systems" class="btn btn-outline-primary"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลระบบ</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0"><i class="fas fa-history"></i> ประวัติการแก้ไขล่าสุด</h5>
            </div>
            <div class="card-body p-0">
                <?php if(count($logs) > 0): ?>
                    <ul class="list-group list-group-flush" style="max-height: 80vh; overflow-y: auto;">
                        <?php foreach($logs as $log_item): ?>
                            <li class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 small"><?php echo getActionIconForView($log_item['action']); ?> <?php echo htmlspecialchars($log_item['action']); ?></h6>
                                    <small class="text-muted"><?php echo date('d/m/y H:i', strtotime($log_item['created_at'])); ?></small>
                                </div>
                                <p class="mb-1 small text-muted">โดย: <?php echo htmlspecialchars($log_item['admin_name']); ?></p>
                                
                                <?php if(!empty($log_item['old_data']) || !empty($log_item['new_data'])): ?>
                                    <button class="btn btn-xs btn-outline-info mt-1" type="button" data-bs-toggle="collapse" data-bs-target="#logCollapse<?php echo $log_item['id']; ?>" aria-expanded="false" aria-controls="logCollapse<?php echo $log_item['id']; ?>">
                                        <i class="fas fa-eye"></i> ดูรายละเอียด
                                    </button>
                                    <div class="collapse mt-2" id="logCollapse<?php echo $log_item['id']; ?>">
                                        <div class="card card-body p-2 small bg-light">
                                            <div id="log-changes-<?php echo $log_item['id']; ?>"></div>
                                            <script>
                                                document.addEventListener('DOMContentLoaded', function() {
                                                    const oldDataStr_<?php echo $log_item['id']; ?> = <?php echo json_encode($log_item['old_data'] ?? null); ?>;
                                                    const newDataStr_<?php echo $log_item['id']; ?> = <?php echo json_encode($log_item['new_data'] ?? null); ?>;
                                                    let oldD_<?php echo $log_item['id']; ?> = null;
                                                    let newD_<?php echo $log_item['id']; ?> = null;

                                                    try { oldD_<?php echo $log_item['id']; ?> = oldDataStr_<?php echo $log_item['id']; ?> ? JSON.parse(oldDataStr_<?php echo $log_item['id']; ?>) : null; } catch(e) { console.warn("Error parsing oldData for log <?php echo $log_item['id']; ?>");}
                                                    try { newD_<?php echo $log_item['id']; ?> = newDataStr_<?php echo $log_item['id']; ?> ? JSON.parse(newDataStr_<?php echo $log_item['id']; ?>) : null; } catch(e) { console.warn("Error parsing newData for log <?php echo $log_item['id']; ?>");}
                                                    
                                                    const changesHtml_<?php echo $log_item['id']; ?> = generateDetailedChangesTableForView(oldD_<?php echo $log_item['id']; ?>, newD_<?php echo $log_item['id']; ?>);
                                                    const container_<?php echo $log_item['id']; ?> = document.getElementById('log-changes-<?php echo $log_item['id']; ?>');
                                                    if (changesHtml_<?php echo $log_item['id']; ?>) {
                                                        container_<?php echo $log_item['id']; ?>.innerHTML = changesHtml_<?php echo $log_item['id']; ?>;
                                                    } else {
                                                        container_<?php echo $log_item['id']; ?>.innerHTML = '<p class="text-muted mb-0">ไม่มีรายละเอียดการเปลี่ยนแปลงฟิลด์</p>';
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
                        <a href="?page=admin_logs&search=<?php echo urlencode($student['student_id']); ?>" class="btn btn-sm btn-outline-primary">ดูประวัติทั้งหมด</a>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center p-3">ไม่พบประวัติการแก้ไขสำหรับนักศึกษาคนนี้</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// ฟังก์ชันนี้ควรจะเหมือนกับใน admin_logs.php หรือเป็นฟังก์ชันที่ใช้ร่วมกัน
function generateDetailedChangesTableForView(oldData, newData) {
    let html = '<table class="table table-sm table-bordered table-striped mb-0" style="font-size: 0.8rem;">'; // Smaller font
    html += '<thead class="table-light"><tr><th>รายการ/ฟิลด์</th><th>ค่าเดิม</th><th>ค่าใหม่</th></tr></thead><tbody>';
    let hasChanges = false;
    
    const allKeys = new Set([...(oldData ? Object.keys(oldData) : []), ...(newData ? Object.keys(newData) : [])]);

    allKeys.forEach(key => {
        const oldValue = oldData ? oldData[key] : undefined;
        const newValue = newData ? newData[key] : undefined;

        if (JSON.stringify(oldValue) !== JSON.stringify(newValue)) {
            hasChanges = true;
            if (typeof newValue === 'object' && newValue !== null && typeof oldValue === 'object' && oldValue !== null && !Array.isArray(newValue) && !Array.isArray(oldValue)) { // Check if not array
                html += `<tr><td colspan="3" class="bg-secondary text-white p-1"><strong>${escapeHtmlForView(key)}</strong></td></tr>`; // System Name as header
                const subKeys = new Set([...Object.keys(oldValue), ...Object.keys(newValue)]);
                subKeys.forEach(subKey => {
                    const oldSubValue = oldValue[subKey];
                    const newSubValue = newValue[subKey];
                    if (JSON.stringify(oldSubValue) !== JSON.stringify(newSubValue)) {
                         html += `<tr>`;
                         html += `<td class="ps-2">&nbsp;&nbsp;${escapeHtmlForView(subKey)}</td>`; // Indent subkey
                         html += `<td>${formatValueForDisplayForView(oldSubValue)}</td>`;
                         html += `<td>${formatValueForDisplayForView(newSubValue)}</td>`;
                         html += `</tr>`;
                    }
                });
            } else if (typeof newValue === 'object' && newValue !== null && (oldValue === undefined || oldValue === null) && !Array.isArray(newValue)) { // Added new system object
                html += `<tr><td colspan="3" class="bg-success-light text-dark p-1"><strong>${escapeHtmlForView(key)} (เพิ่มใหม่)</strong></td></tr>`;
                Object.keys(newValue).forEach(subKey => {
                     html += `<tr>`;
                     html += `<td class="ps-2">&nbsp;&nbsp;${escapeHtmlForView(subKey)}</td>`;
                     html += `<td><em>ไม่มี</em></td>`;
                     html += `<td>${formatValueForDisplayForView(newValue[subKey])}</td>`;
                     html += `</tr>`;
                });
            } else { // Simple field change or non-object system change
                html += `<tr>`;
                html += `<td><strong>${escapeHtmlForView(key)}</strong></td>`;
                html += `<td>${formatValueForDisplayForView(oldValue)}</td>`;
                html += `<td>${formatValueForDisplayForView(newValue)}</td>`;
                html += `</tr>`;
            }
        }
    });

    html += '</tbody></table>';
    return hasChanges ? html : '';
}

function formatValueForDisplayForView(value) {
    if (value === undefined || value === null) {
        return '<em class="text-muted">ไม่มี</em>';
    }
    if (typeof value === 'object' && !Array.isArray(value)) { // Ensure it's not an array before stringifying as object detail
        return '<pre class="mb-0 small p-1 bg-white border rounded">' + escapeHtmlForView(JSON.stringify(value, null, 2)) + '</pre>';
    }
    if (String(value).length > 50) { // Truncate long strings for better display in this compact view
         return '<span title="' + escapeHtmlForView(String(value)) + '">' + escapeHtmlForView(String(value).substring(0, 47)) + '...</span>';
    }
    return escapeHtmlForView(String(value));
}

function escapeHtmlForView(unsafe) {
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
    .bg-success-light { background-color: #e6ffed !important; }
</style>
