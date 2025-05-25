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

// ตัวแปรสำหรับการจัดการหน้า
$current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$records_per_page = 20; 
$offset = ($current_page - 1) * $records_per_page;

$search = isset($_GET['search']) ? $database->sanitize($_GET['search']) : '';
$admin_filter = isset($_GET['admin']) ? (int)$_GET['admin'] : '';
$date_start = isset($_GET['date_start']) ? $database->sanitize($_GET['date_start']) : '';
$date_end = isset($_GET['date_end']) ? $database->sanitize($_GET['date_end']) : '';
$action_type = isset($_GET['action_type']) ? $database->sanitize($_GET['action_type']) : '';

try {
    $admin_query = "SELECT id, name FROM admins ORDER BY name";
    $admin_stmt = $db->prepare($admin_query);
    $admin_stmt->execute();
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count_query = "SELECT COUNT(*) as total FROM admin_logs al LEFT JOIN admins a ON al.admin_id = a.id WHERE 1=1";
    $query = "SELECT al.*, a.name as admin_name FROM admin_logs al LEFT JOIN admins a ON al.admin_id = a.id WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $search_condition = " AND (al.action LIKE :search OR a.name LIKE :search OR al.old_data LIKE :search OR al.new_data LIKE :search)";
        $count_query .= $search_condition;
        $query .= $search_condition;
        $params[':search'] = "%" . $search . "%";
    }
    if (!empty($admin_filter)) {
        $admin_condition = " AND al.admin_id = :admin_id";
        $count_query .= $admin_condition;
        $query .= $admin_condition;
        $params[':admin_id'] = $admin_filter;
    }
    if (!empty($date_start)) {
        $date_start_condition = " AND al.created_at >= :date_start";
        $count_query .= $date_start_condition;
        $query .= $date_start_condition;
        $params[':date_start'] = $date_start . " 00:00:00";
    }
    if (!empty($date_end)) {
        $date_end_condition = " AND al.created_at <= :date_end";
        $count_query .= $date_end_condition;
        $query .= $date_end_condition;
        $params[':date_end'] = $date_end . " 23:59:59";
    }
    if (!empty($action_type)) {
        $action_map = [
            'add_student' => " AND al.action LIKE 'เพิ่มนักศึกษาใหม่:%'",
            'edit_student' => " AND al.action LIKE 'แก้ไขข้อมูลนักศึกษา:%'",
            'import_student' => " AND al.action LIKE 'นำเข้าข้อมูลนักศึกษาแบบกลุ่ม:%'",
            'reset_id_card' => " AND al.action LIKE 'รีเซ็ตรหัสบัตรประชาชน:%'",
            'unlink_google_student' => " AND al.action LIKE 'ยกเลิกการเชื่อมต่อ Google: %' AND al.action NOT LIKE '%ผู้ดูแลระบบ%'",
            'edit_system_info' => " AND al.action LIKE 'แก้ไขข้อมูลการเข้าระบบ:%'",
            'unlink_google_admin' => " AND al.action LIKE 'ยกเลิกการเชื่อมต่อ Google: ผู้ดูแลระบบ%'",
        ];
        $action_condition = $action_map[$action_type] ?? "";
        
        if (!empty($action_condition)) {
            $count_query .= $action_condition;
            $query .= $action_condition;
        }
    }
    
    $query .= " ORDER BY al.created_at DESC";
    
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_rows / $records_per_page);
    
    $query .= " LIMIT :offset, :records_per_page";
    $stmt = $db->prepare($query);
    foreach ($params as $key => &$value) { // Pass by reference for bindValue
        $stmt->bindValue($key, $value);
    }
    unset($value); // Unset reference
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    
    $action_types = [
        'add_student' => 'เพิ่มนักศึกษาใหม่',
        'edit_student' => 'แก้ไขข้อมูลนักศึกษา',
        'import_student' => 'นำเข้าข้อมูลนักศึกษา',
        'reset_id_card' => 'รีเซ็ตรหัสบัตรประชาชน',
        'unlink_google_student' => 'ยกเลิก Google (นักศึกษา)',
        'edit_system_info' => 'แก้ไขข้อมูลระบบ',
        'unlink_google_admin' => 'ยกเลิก Google (ผู้ดูแล)'
    ];
    
} catch(PDOException $e) {
    $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

function getActionIcon($action) {
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
        <h2>ประวัติการทำงาน (Logs)</h2>
        <p class="text-muted">แสดงประวัติการทำงานของผู้ดูแลระบบทั้งหมด</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=admin_dashboard" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>กลับไปยังแดชบอร์ด
        </a>
    </div>
</div>

<?php if(isset($error_message)): ?>
    <div class="alert alert-danger" role="alert"><?php echo $error_message; ?></div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-body">
        <form action="" method="get" class="row g-3">
            <input type="hidden" name="page" value="admin_logs">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="ค้นหา Action, Name, Data..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select name="admin" class="form-select">
                    <option value="">-- ทุกผู้ดูแล --</option>
                    <?php foreach($admins as $admin): ?>
                        <option value="<?php echo $admin['id']; ?>" <?php echo ($admin_filter == $admin['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($admin['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="action_type" class="form-select">
                    <option value="">-- ทุกประเภท Action --</option>
                    <?php foreach($action_types as $type => $label): ?>
                        <option value="<?php echo $type; ?>" <?php echo ($action_type == $type) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_start" value="<?php echo htmlspecialchars($date_start); ?>" title="วันที่เริ่มต้น">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_end" value="<?php echo htmlspecialchars($date_end); ?>" title="วันที่สิ้นสุด">
            </div>
             <div class="col-md-1">
                <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter"></i></button>
            </div>
            <div class="col-12 text-end mt-2">
                <a href="?page=admin_logs" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i> ล้างตัวกรอง</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>ประวัติการทำงาน (<?php echo number_format($total_rows); ?> รายการ)</h5>
        <small>แสดงผลหน้า <?php echo $current_page; ?> / <?php echo $total_pages; ?></small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>วันที่</th>
                        <th>ผู้ดูแล</th>
                        <th>การกระทำ</th>
                        <th class="text-center">ข้อมูล</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($stmt->rowCount() > 0): ?>
                        <?php $counter = $offset + 1; ?>
                        <?php while($log = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td class="text-nowrap"><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['admin_name']); ?></td>
                                <td>
                                    <?php echo getActionIcon($log['action']); ?> 
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </td>
                                <td class="text-center">
                                    <?php if(!empty($log['old_data']) || !empty($log['new_data'])): ?>
                                        <button type="button" class="btn btn-xs btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#logDetailModal" 
                                                data-log-id="<?php echo $log['id']; ?>"
                                                data-log-action="<?php echo htmlspecialchars($log['action']); ?>"
                                                data-log-admin="<?php echo htmlspecialchars($log['admin_name']); ?>"
                                                data-log-date="<?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>"
                                                data-log-old='<?php echo htmlspecialchars($log['old_data'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'
                                                data-log-new='<?php echo htmlspecialchars($log['new_data'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'>
                                            <i class="fas fa-search"></i> ดู
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">ไม่มี</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">ไม่พบข้อมูลประวัติการทำงาน</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                        $link_params = "&search=" . urlencode($search) . "&admin=" . $admin_filter . "&action_type=" . $action_type . "&date_start=" . $date_start . "&date_end=" . $date_end;
                    ?>
                    <?php if($current_page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=admin_logs&p=1<?php echo $link_params; ?>"><i class="fas fa-angle-double-left"></i></a></li>
                        <li class="page-item"><a class="page-link" href="?page=admin_logs&p=<?php echo $current_page-1; ?><?php echo $link_params; ?>"><i class="fas fa-angle-left"></i></a></li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    for($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=admin_logs&p=<?php echo $i; ?><?php echo $link_params; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if($current_page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="?page=admin_logs&p=<?php echo $current_page+1; ?><?php echo $link_params; ?>"><i class="fas fa-angle-right"></i></a></li>
                        <li class="page-item"><a class="page-link" href="?page=admin_logs&p=<?php echo $total_pages; ?><?php echo $link_params; ?>"><i class="fas fa-angle-double-right"></i></a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="logDetailModal" tabindex="-1" aria-labelledby="logDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl"> <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="logDetailModalLabel">รายละเอียดการทำงาน</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div class="mb-3 row">
                    <div class="col-md-6"><strong>การกระทำ:</strong> <span id="modal-action"></span></div>
                    <div class="col-md-3"><strong>ผู้ดูแล:</strong> <span id="modal-admin"></span></div>
                    <div class="col-md-3"><strong>วันที่:</strong> <span id="modal-date"></span></div>
                </div>
                
                <ul class="nav nav-tabs" id="logTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="changes-tab" data-bs-toggle="tab" data-bs-target="#changes" type="button" role="tab" aria-controls="changes" aria-selected="true">
                            <i class="fas fa-exchange-alt me-1"></i>การเปลี่ยนแปลง
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="old-data-tab" data-bs-toggle="tab" data-bs-target="#old-data" type="button" role="tab" aria-controls="old-data" aria-selected="false">
                            <i class="fas fa-history me-1"></i>ข้อมูลเดิม (Raw)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="new-data-tab" data-bs-toggle="tab" data-bs-target="#new-data" type="button" role="tab" aria-controls="new-data" aria-selected="false">
                            <i class="fas fa-save me-1"></i>ข้อมูลใหม่ (Raw)
                        </button>
                    </li>
                </ul>
                <div class="tab-content p-3 border border-top-0 rounded-bottom" id="logTabsContent">
                    <div class="tab-pane fade show active" id="changes" role="tabpanel" aria-labelledby="changes-tab">
                        <div id="changes-content" class="bg-light p-3 rounded">
                             <div id="no-changes" class="text-center text-muted py-3 d-none">
                                <i class="fas fa-info-circle fa-2x mb-2"></i>
                                <p class="mb-0">ไม่พบข้อมูลการเปลี่ยนแปลงที่เฉพาะเจาะจง หรือเป็นการดำเนินการที่ไม่ติดตามรายละเอียดฟิลด์ (เช่น ยกเลิก Google, รีเซ็ตรหัสผ่าน)</p>
                            </div>
                            <div id="changes-table-container"></div> </div>
                    </div>
                    <div class="tab-pane fade" id="old-data" role="tabpanel" aria-labelledby="old-data-tab">
                        <pre id="old-data-content-raw" class="bg-light p-3 rounded" style="white-space: pre-wrap; word-break: break-all;"></pre>
                    </div>
                    <div class="tab-pane fade" id="new-data" role="tabpanel" aria-labelledby="new-data-tab">
                        <pre id="new-data-content-raw" class="bg-light p-3 rounded" style="white-space: pre-wrap; word-break: break-all;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const logDetailModal = document.getElementById('logDetailModal');
    logDetailModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const logId = button.getAttribute('data-log-id');
        const logAction = button.getAttribute('data-log-action');
        const logAdmin = button.getAttribute('data-log-admin');
        const logDate = button.getAttribute('data-log-date');
        let oldDataRaw = button.getAttribute('data-log-old');
        let newDataRaw = button.getAttribute('data-log-new');

        document.getElementById('modal-action').textContent = logAction;
        document.getElementById('modal-admin').textContent = logAdmin;
        document.getElementById('modal-date').textContent = logDate;
        
        let oldDataObj, newDataObj;
        try {
            oldDataObj = oldDataRaw ? JSON.parse(oldDataRaw) : null;
        } catch (e) {
            console.warn('Error parsing oldData JSON for log ID ' + logId + ':', e, oldDataRaw);
            oldDataObj = null; 
        }
        try {
            newDataObj = newDataRaw ? JSON.parse(newDataRaw) : null;
        } catch (e) {
            console.warn('Error parsing newData JSON for log ID ' + logId + ':', e, newDataRaw);
            newDataObj = null;
        }

        // แสดง Raw Data
        document.getElementById('old-data-content-raw').textContent = oldDataRaw || 'ไม่มีข้อมูลเดิม';
        document.getElementById('new-data-content-raw').textContent = newDataRaw || 'ไม่มีข้อมูลใหม่';
        
        // แสดงการเปลี่ยนแปลง
        const changesTableContainer = document.getElementById('changes-table-container');
        const noChangesDiv = document.getElementById('no-changes');
        changesTableContainer.innerHTML = ''; // Clear previous content
        noChangesDiv.classList.add('d-none'); // Hide no changes message by default

        if (oldDataObj && newDataObj) {
            const changesHtml = generateDetailedChangesTable(oldDataObj, newDataObj);
            if (changesHtml) {
                changesTableContainer.innerHTML = changesHtml;
            } else {
                noChangesDiv.classList.remove('d-none');
            }
        } else if (newDataObj && !oldDataObj) { // กรณีเป็นการเพิ่มใหม่ทั้งหมด
             changesTableContainer.innerHTML = generateDetailedChangesTable(null, newDataObj); // แสดงข้อมูลใหม่ทั้งหมด
        } 
         else if (!newDataObj && oldDataObj) { // กรณีเป็นการลบทั้งหมด
             changesTableContainer.innerHTML = generateDetailedChangesTable(oldDataObj, null); // แสดงข้อมูลเก่าทั้งหมด
        }
        else {
             noChangesDiv.classList.remove('d-none');
        }
    });
});

function generateDetailedChangesTable(oldData, newData) {
    let html = '<table class="table table-sm table-bordered table-striped mb-0">';
    html += '<thead class="table-light"><tr><th>รายการ / ฟิลด์</th><th>ค่าเดิม</th><th>ค่าใหม่</th></tr></thead><tbody>';
    let hasChanges = false;
    
    const allKeys = new Set([...(oldData ? Object.keys(oldData) : []), ...(newData ? Object.keys(newData) : [])]);

    allKeys.forEach(key => {
        const oldValue = oldData ? oldData[key] : undefined;
        const newValue = newData ? newData[key] : undefined;

        if (JSON.stringify(oldValue) !== JSON.stringify(newValue)) {
            hasChanges = true;
            // ถ้าเป็น object (เช่น ข้อมูล system ย่อยๆ) ให้แสดงชื่อ key หลัก และวน loop แสดง field ย่อย
            if (typeof newValue === 'object' && newValue !== null && typeof oldValue === 'object' && oldValue !== null) {
                html += `<tr><td colspan="3" class="bg-secondary text-white"><strong>${escapeHtml(key)}</strong></td></tr>`;
                const subKeys = new Set([...Object.keys(oldValue), ...Object.keys(newValue)]);
                subKeys.forEach(subKey => {
                    const oldSubValue = oldValue[subKey];
                    const newSubValue = newValue[subKey];
                    if (JSON.stringify(oldSubValue) !== JSON.stringify(newSubValue)) {
                         html += `<tr>`;
                         html += `<td>&nbsp;&nbsp;&nbsp;${escapeHtml(subKey)}</td>`;
                         html += `<td>${formatValueForDisplay(oldSubValue)}</td>`;
                         html += `<td>${formatValueForDisplay(newSubValue)}</td>`;
                         html += `</tr>`;
                    }
                });
            } else if (typeof newValue === 'object' && newValue !== null && oldValue === undefined) { // เพิ่ม object ใหม่
                html += `<tr><td colspan="3" class="bg-success text-white"><strong>${escapeHtml(key)} (เพิ่มใหม่)</strong></td></tr>`;
                Object.keys(newValue).forEach(subKey => {
                     html += `<tr>`;
                     html += `<td>&nbsp;&nbsp;&nbsp;${escapeHtml(subKey)}</td>`;
                     html += `<td><em>ไม่มี</em></td>`;
                     html += `<td>${formatValueForDisplay(newValue[subKey])}</td>`;
                     html += `</tr>`;
                });
            }
            else { // การเปลี่ยนแปลงค่าธรรมดา หรือการเพิ่ม/ลบค่า
                html += `<tr>`;
                html += `<td><strong>${escapeHtml(key)}</strong></td>`;
                html += `<td>${formatValueForDisplay(oldValue)}</td>`;
                html += `<td>${formatValueForDisplay(newValue)}</td>`;
                html += `</tr>`;
            }
        }
    });

    html += '</tbody></table>';
    return hasChanges ? html : '';
}

function formatValueForDisplay(value) {
    if (value === undefined || value === null) {
        return '<em class="text-muted">ไม่มี</em>';
    }
    if (typeof value === 'object') {
        return '<pre class="mb-0 small">' + escapeHtml(JSON.stringify(value, null, 2)) + '</pre>';
    }
    return escapeHtml(String(value));
}

function escapeHtml(unsafe) {
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
.table-sm th, .table-sm td { padding: 0.4rem; font-size: 0.85rem; }
.btn-xs { padding: 0.15rem 0.4rem; font-size: 0.75rem; }
pre.small { font-size: 0.8rem; }
</style>
