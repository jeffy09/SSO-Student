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
$records_per_page = 20; // แสดง log จำนวนมากขึ้นในแต่ละหน้า
$offset = ($current_page - 1) * $records_per_page;

// ตัวแปรสำหรับการค้นหาและกรอง
$search = isset($_GET['search']) ? $database->sanitize($_GET['search']) : '';
$admin_filter = isset($_GET['admin']) ? (int)$_GET['admin'] : '';
$date_start = isset($_GET['date_start']) ? $database->sanitize($_GET['date_start']) : '';
$date_end = isset($_GET['date_end']) ? $database->sanitize($_GET['date_end']) : '';
$action_type = isset($_GET['action_type']) ? $database->sanitize($_GET['action_type']) : '';

try {
    // ดึงรายชื่อผู้ดูแลระบบทั้งหมดสำหรับ dropdown
    $admin_query = "SELECT id, name FROM admins ORDER BY name";
    $admin_stmt = $db->prepare($admin_query);
    $admin_stmt->execute();
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // คำสั่ง SQL สำหรับนับจำนวนทั้งหมด
    $count_query = "SELECT COUNT(*) as total FROM admin_logs al 
                    LEFT JOIN admins a ON al.admin_id = a.id
                    WHERE 1=1";
    
    // คำสั่ง SQL สำหรับดึงข้อมูล log
    $query = "SELECT al.*, a.name as admin_name 
              FROM admin_logs al 
              LEFT JOIN admins a ON al.admin_id = a.id
              WHERE 1=1";
    
    $params = [];
    
    // เพิ่มเงื่อนไขการค้นหา (ถ้ามี)
    if (!empty($search)) {
        $search_condition = " AND (al.action LIKE :search OR a.name LIKE :search)";
        $count_query .= $search_condition;
        $query .= $search_condition;
        $params[':search'] = "%" . $search . "%";
    }
    
    // เพิ่มเงื่อนไขกรองตาม admin (ถ้ามี)
    if (!empty($admin_filter)) {
        $admin_condition = " AND al.admin_id = :admin_id";
        $count_query .= $admin_condition;
        $query .= $admin_condition;
        $params[':admin_id'] = $admin_filter;
    }
    
    // เพิ่มเงื่อนไขกรองตามวันที่เริ่มต้น (ถ้ามี)
    if (!empty($date_start)) {
        $date_start_condition = " AND al.created_at >= :date_start";
        $count_query .= $date_start_condition;
        $query .= $date_start_condition;
        $params[':date_start'] = $date_start . " 00:00:00";
    }
    
    // เพิ่มเงื่อนไขกรองตามวันที่สิ้นสุด (ถ้ามี)
    if (!empty($date_end)) {
        $date_end_condition = " AND al.created_at <= :date_end";
        $count_query .= $date_end_condition;
        $query .= $date_end_condition;
        $params[':date_end'] = $date_end . " 23:59:59";
    }
    
    // เพิ่มเงื่อนไขกรองตามประเภทการกระทำ (ถ้ามี)
    if (!empty($action_type)) {
        switch($action_type) {
            case 'add':
                $action_condition = " AND al.action LIKE '%เพิ่ม%'";
                break;
            case 'edit':
                $action_condition = " AND al.action LIKE '%แก้ไข%'";
                break;
            case 'import':
                $action_condition = " AND al.action LIKE '%นำเข้า%'";
                break;
            case 'reset':
                $action_condition = " AND al.action LIKE '%รีเซ็ต%'";
                break;
            case 'unlink':
                $action_condition = " AND al.action LIKE '%ยกเลิกการเชื่อมต่อ%'";
                break;
            default:
                $action_condition = "";
        }
        
        if (!empty($action_condition)) {
            $count_query .= $action_condition;
            $query .= $action_condition;
        }
    }
    
    // เพิ่มการเรียงลำดับ
    $query .= " ORDER BY al.created_at DESC";
    
    // เพิ่ม LIMIT
    $query .= " LIMIT :offset, :records_per_page";
    
    // ดึงจำนวนทั้งหมด
    $count_stmt = $db->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_rows / $records_per_page);
    
    // ดึงข้อมูล log
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    
    // ดึงประเภทการกระทำสำหรับ dropdown กรอง
    $action_types = [
        'add' => 'เพิ่มข้อมูล',
        'edit' => 'แก้ไขข้อมูล',
        'import' => 'นำเข้าข้อมูล',
        'reset' => 'รีเซ็ตรหัสผ่าน',
        'unlink' => 'ยกเลิกการเชื่อมต่อ'
    ];
    
} catch(PDOException $e) {
    $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// ฟังก์ชันจัดรูปแบบข้อมูล JSON สำหรับแสดงผล
function formatJsonData($jsonData) {
    if (empty($jsonData)) {
        return "<em class='text-muted'>ไม่มีข้อมูล</em>";
    }
    
    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return "<em class='text-muted'>ข้อมูลไม่ถูกต้อง</em>";
    }
    
    $html = "<table class='table table-sm table-bordered mb-0'>";
    
    foreach ($data as $key => $value) {
        $html .= "<tr>";
        $html .= "<th>" . htmlspecialchars($key) . "</th>";
        
        // ถ้าค่าเป็น array หรือ object
        if (is_array($value) || is_object($value)) {
            $html .= "<td><pre class='mb-0'>" . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre></td>";
        } else {
            $html .= "<td>" . htmlspecialchars($value) . "</td>";
        }
        
        $html .= "</tr>";
    }
    
    $html .= "</table>";
    return $html;
}

// ฟังก์ชันแสดงไอคอนตามประเภทการกระทำ
function getActionIcon($action) {
    if (strpos($action, 'เพิ่ม') !== false) {
        return "<i class='fas fa-plus-circle text-success'></i>";
    } elseif (strpos($action, 'แก้ไข') !== false) {
        return "<i class='fas fa-edit text-warning'></i>";
    } elseif (strpos($action, 'นำเข้า') !== false) {
        return "<i class='fas fa-file-import text-info'></i>";
    } elseif (strpos($action, 'รีเซ็ต') !== false) {
        return "<i class='fas fa-key text-danger'></i>";
    } elseif (strpos($action, 'ยกเลิกการเชื่อมต่อ') !== false) {
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
    <div class="alert alert-danger" role="alert">
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- ส่วนค้นหาและกรอง -->
<div class="card shadow mb-4">
    <div class="card-body">
        <form action="" method="get" class="row g-3">
            <input type="hidden" name="page" value="admin_logs">
            
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="ค้นหา..." value="<?php echo $search; ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </div>
            
            <div class="col-md-2">
                <select name="admin" class="form-select">
                    <option value="">-- ทุกผู้ดูแลระบบ --</option>
                    <?php foreach($admins as $admin): ?>
                        <option value="<?php echo $admin['id']; ?>" <?php echo ($admin_filter == $admin['id']) ? 'selected' : ''; ?>>
                            <?php echo $admin['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <select name="action_type" class="form-select">
                    <option value="">-- ทุกประเภท --</option>
                    <?php foreach($action_types as $type => $label): ?>
                        <option value="<?php echo $type; ?>" <?php echo ($action_type == $type) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_start" placeholder="วันที่เริ่มต้น" value="<?php echo $date_start; ?>">
            </div>
            
            <div class="col-md-2">
                <div class="input-group">
                    <input type="date" class="form-control" name="date_end" placeholder="วันที่สิ้นสุด" value="<?php echo $date_end; ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i></button>
                </div>
            </div>
            
            <div class="col-12 text-end">
                <a href="?page=admin_logs" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times"></i> ล้างตัวกรอง
                </a>
                
                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#helpModal">
                    <i class="fas fa-question-circle"></i> วิธีใช้งาน
                </button>
            </div>
        </form>
    </div>
</div>

<!-- แสดงสถิติสรุป -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">จำนวนข้อมูลทั้งหมด</h6>
                        <h2 class="mb-0 text-primary"><?php echo number_format($total_rows); ?></h2>
                    </div>
                    <i class="fas fa-history fa-2x text-primary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">วันที่มีการทำงานล่าสุด</h6>
                        <?php
                        $latest_date_query = "SELECT created_at FROM admin_logs ORDER BY created_at DESC LIMIT 1";
                        $latest_date_stmt = $db->prepare($latest_date_query);
                        $latest_date_stmt->execute();
                        $latest_date = $latest_date_stmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <h5 class="mb-0 text-success">
                            <?php echo !empty($latest_date) ? date('d/m/Y H:i', strtotime($latest_date['created_at'])) : '-'; ?>
                        </h5>
                    </div>
                    <i class="fas fa-calendar-check fa-2x text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">จำนวนการทำงานวันนี้</h6>
                        <?php
                        $today_query = "SELECT COUNT(*) as count FROM admin_logs WHERE DATE(created_at) = CURDATE()";
                        $today_stmt = $db->prepare($today_query);
                        $today_stmt->execute();
                        $today_count = $today_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        ?>
                        <h2 class="mb-0 text-info"><?php echo number_format($today_count); ?></h2>
                    </div>
                    <i class="fas fa-calendar-day fa-2x text-info opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ตารางแสดงข้อมูล logs -->
<div class="card shadow">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">
            <i class="fas fa-history me-2"></i>ประวัติการทำงานทั้งหมด
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px">#</th>
                        <th style="width: 150px">วันที่</th>
                        <th style="width: 150px">ผู้ดูแลระบบ</th>
                        <th>การกระทำ</th>
                        <th style="width: 100px">ข้อมูล</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($stmt->rowCount() > 0): ?>
                        <?php $counter = $offset + 1; ?>
                        <?php while($log = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td><?php echo $log['admin_name']; ?></td>
                                <td>
                                    <?php echo getActionIcon($log['action']); ?> 
                                    <?php echo $log['action']; ?>
                                </td>
                                <td class="text-center">
                                    <?php if(!empty($log['old_data']) || !empty($log['new_data'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#logDetailModal" 
                                                data-log-id="<?php echo $log['id']; ?>"
                                                data-log-action="<?php echo htmlspecialchars($log['action']); ?>"
                                                data-log-admin="<?php echo htmlspecialchars($log['admin_name']); ?>"
                                                data-log-date="<?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>"
                                                data-log-old='<?php echo htmlspecialchars($log['old_data']); ?>'
                                                data-log-new='<?php echo htmlspecialchars($log['new_data']); ?>'>
                                            <i class="fas fa-search"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ไม่มีข้อมูล</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">ไม่พบข้อมูล</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=admin_logs&p=1<?php echo !empty($search) ? '&search='.$search : ''; ?><?php echo !empty($admin_filter) ? '&admin='.$admin_filter : ''; ?><?php echo !empty($date_start) ? '&date_start='.$date_start : ''; ?><?php echo !empty($date_end) ? '&date_end='.$date_end : ''; ?><?php echo !empty($action_type) ? '&action_type='.$action_type : ''; ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=admin_logs&p=<?php echo $current_page-1; ?><?php echo !empty($search) ? '&search='.$search : ''; ?><?php echo !empty($admin_filter) ? '&admin='.$admin_filter : ''; ?><?php echo !empty($date_start) ? '&date_start='.$date_start : ''; ?><?php echo !empty($date_end) ? '&date_end='.$date_end : ''; ?><?php echo !empty($action_type) ? '&action_type='.$action_type : ''; ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    // แสดงลิงก์หน้า
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    for($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=admin_logs&p=<?php echo $i; ?><?php echo !empty($search) ? '&search='.$search : ''; ?><?php echo !empty($admin_filter) ? '&admin='.$admin_filter : ''; ?><?php echo !empty($date_start) ? '&date_start='.$date_start : ''; ?><?php echo !empty($date_end) ? '&date_end='.$date_end : ''; ?><?php echo !empty($action_type) ? '&action_type='.$action_type : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=admin_logs&p=<?php echo $current_page+1; ?><?php echo !empty($search) ? '&search='.$search : ''; ?><?php echo !empty($admin_filter) ? '&admin='.$admin_filter : ''; ?><?php echo !empty($date_start) ? '&date_start='.$date_start : ''; ?><?php echo !empty($date_end) ? '&date_end='.$date_end : ''; ?><?php echo !empty($action_type) ? '&action_type='.$action_type : ''; ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=admin_logs&p=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search='.$search : ''; ?><?php echo !empty($admin_filter) ? '&admin='.$admin_filter : ''; ?><?php echo !empty($date_start) ? '&date_start='.$date_start : ''; ?><?php echo !empty($date_end) ? '&date_end='.$date_end : ''; ?><?php echo !empty($action_type) ? '&action_type='.$action_type : ''; ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="text-center">
                <span class="text-muted">หน้า <?php echo $current_page; ?> จาก <?php echo $total_pages; ?> (รายการทั้งหมด: <?php echo $total_rows; ?>)</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal แสดงรายละเอียด Log -->
<div class="modal fade" id="logDetailModal" tabindex="-1" aria-labelledby="logDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="logDetailModalLabel">รายละเอียดการทำงาน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="mb-1"><strong>การกระทำ:</strong> <span id="modal-action"></span></p>
                            <p class="mb-1"><strong>ผู้ดูแลระบบ:</strong> <span id="modal-admin"></span></p>
                        </div>
                        <div>
                            <p class="mb-1"><strong>วันที่:</strong> <span id="modal-date"></span></p>
                            <p class="mb-1"><strong>รหัส Log:</strong> <span id="modal-id"></span></p>
                        </div>
                    </div>
                </div>
                
                <ul class="nav nav-tabs" id="logTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="changes-tab" data-bs-toggle="tab" data-bs-target="#changes" type="button" role="tab" aria-controls="changes" aria-selected="true">
                            <i class="fas fa-exchange-alt me-1"></i>การเปลี่ยนแปลง
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="old-data-tab" data-bs-toggle="tab" data-bs-target="#old-data" type="button" role="tab" aria-controls="old-data" aria-selected="false">
                            <i class="fas fa-history me-1"></i>ข้อมูลเดิม
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="new-data-tab" data-bs-toggle="tab" data-bs-target="#new-data" type="button" role="tab" aria-controls="new-data" aria-selected="false">
                            <i class="fas fa-save me-1"></i>ข้อมูลใหม่
                        </button>
                    </li>
                </ul>
                <div class="tab-content p-3 border border-top-0 rounded-bottom" id="logTabsContent">
                    <div class="tab-pane fade show active" id="changes" role="tabpanel" aria-labelledby="changes-tab">
                        <div id="changes-content" class="bg-light p-3 rounded">
                            <div id="no-changes" class="text-center text-muted py-3 d-none">
                                <i class="fas fa-info-circle fa-2x mb-2"></i>
                                <p class="mb-0">ไม่พบข้อมูลการเปลี่ยนแปลง</p>
                            </div>
                            <div id="changes-table"></div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="old-data" role="tabpanel" aria-labelledby="old-data-tab">
                        <div id="old-data-content" class="bg-light p-3 rounded">
                            กำลังโหลดข้อมูล...
                        </div>
                    </div>
                    <div class="tab-pane fade" id="new-data" role="tabpanel" aria-labelledby="new-data-tab">
                        <div id="new-data-content" class="bg-light p-3 rounded">
                            กำลังโหลดข้อมูล...
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal วิธีใช้งาน -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="helpModalLabel">วิธีใช้งานหน้า Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6><i class="fas fa-search me-2"></i>การค้นหาและกรอง</h6>
                    <ul>
                        <li>ใช้ช่องค้นหาเพื่อค้นหาข้อความในการกระทำหรือชื่อผู้ดูแลระบบ</li>
                        <li>เลือกผู้ดูแลระบบจาก dropdown เพื่อดูเฉพาะการกระทำของผู้ดูแลคนนั้น</li>
                        <li>เลือกประเภทการกระทำเพื่อกรองเฉพาะการกระทำนั้น</li>
                        <li>กำหนดช่วงวันที่เพื่อดูข้อมูลเฉพาะช่วงเวลานั้น</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <h6><i class="fas fa-table me-2"></i>การดูรายละเอียด</h6>
                    <ul>
                        <li>คลิกที่ปุ่ม <button class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></button> เพื่อดูรายละเอียดของการเปลี่ยนแปลง</li>
                        <li>ในหน้าต่างรายละเอียด คุณสามารถดู:
                            <ul>
                                <li><strong>การเปลี่ยนแปลง</strong> - แสดงข้อมูลที่มีการเปลี่ยนแปลง</li>
                                <li><strong>ข้อมูลเดิม</strong> - แสดงข้อมูลก่อนการเปลี่ยนแปลง</li>
                                <li><strong>ข้อมูลใหม่</strong> - แสดงข้อมูลหลังการเปลี่ยนแปลง</li>
                            </ul>
                        </li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <h6><i class="fas fa-info-circle me-2"></i>ประเภทการกระทำ</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><i class="fas fa-plus-circle text-success me-1"></i> <strong>เพิ่มข้อมูล</strong> - การเพิ่มผู้ใช้งานใหม่</p>
                            <p><i class="fas fa-edit text-warning me-1"></i> <strong>แก้ไขข้อมูล</strong> - การแก้ไขข้อมูลผู้ใช้งาน</p>
                            <p><i class="fas fa-file-import text-info me-1"></i> <strong>นำเข้าข้อมูล</strong> - การนำเข้าข้อมูลแบบกลุ่ม</p>
                        </div>
                        <div class="col-md-6">
                            <p><i class="fas fa-key text-danger me-1"></i> <strong>รีเซ็ตรหัสผ่าน</strong> - การรีเซ็ตรหัสผ่าน</p>
                            <p><i class="fas fa-unlink text-secondary me-1"></i> <strong>ยกเลิกการเชื่อมต่อ</strong> - ยกเลิกการเชื่อมต่อ Google</p>
                            <p><i class="fas fa-history text-primary me-1"></i> <strong>อื่นๆ</strong> - การกระทำอื่นๆ</p>
                        </div>
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
// แสดงรายละเอียด Log
document.querySelectorAll('[data-bs-target="#logDetailModal"]').forEach(button => {
    button.addEventListener('click', function() {
        const logId = this.getAttribute('data-log-id');
        const logAction = this.getAttribute('data-log-action');
        const logAdmin = this.getAttribute('data-log-admin');
        const logDate = this.getAttribute('data-log-date');
        const oldData = this.getAttribute('data-log-old');
        const newData = this.getAttribute('data-log-new');
        
        // แสดงข้อมูลพื้นฐาน
        document.getElementById('modal-id').textContent = logId;
        document.getElementById('modal-action').textContent = logAction;
        document.getElementById('modal-admin').textContent = logAdmin;
        document.getElementById('modal-date').textContent = logDate;
        
        // แสดงข้อมูลเดิม
        try {
            if (oldData && oldData !== 'null') {
                const oldDataObj = JSON.parse(oldData);
                const oldDataHtml = formatJsonForDisplay(oldDataObj);
                document.getElementById('old-data-content').innerHTML = oldDataHtml;
            } else {
                document.getElementById('old-data-content').innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-info-circle fa-2x mb-2"></i><p class="mb-0">ไม่มีข้อมูลเดิม</p></div>';
            }
        } catch (e) {
            console.error('Error parsing old data:', e);
            document.getElementById('old-data-content').innerHTML = '<div class="alert alert-danger">ข้อมูลไม่ถูกต้อง</div>';
        }
        
        // แสดงข้อมูลใหม่
        try {
            if (newData && newData !== 'null') {
                const newDataObj = JSON.parse(newData);
                const newDataHtml = formatJsonForDisplay(newDataObj);
                document.getElementById('new-data-content').innerHTML = newDataHtml;
            } else {
                document.getElementById('new-data-content').innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-info-circle fa-2x mb-2"></i><p class="mb-0">ไม่มีข้อมูลใหม่</p></div>';
            }
        } catch (e) {
            console.error('Error parsing new data:', e);
            document.getElementById('new-data-content').innerHTML = '<div class="alert alert-danger">ข้อมูลไม่ถูกต้อง</div>';
        }
        
        // แสดงการเปลี่ยนแปลง
        try {
            if (oldData && oldData !== 'null' && newData && newData !== 'null') {
                const oldDataObj = JSON.parse(oldData);
                const newDataObj = JSON.parse(newData);
                const changesHtml = generateChangesTable(oldDataObj, newDataObj);
                
                if (changesHtml === '') {
                    document.getElementById('no-changes').classList.remove('d-none');
                    document.getElementById('changes-table').innerHTML = '';
                } else {
                    document.getElementById('no-changes').classList.add('d-none');
                    document.getElementById('changes-table').innerHTML = changesHtml;
                }
            } else {
                document.getElementById('no-changes').classList.remove('d-none');
                document.getElementById('changes-table').innerHTML = '';
            }
        } catch (e) {
            console.error('Error generating changes:', e);
            document.getElementById('changes-table').innerHTML = '<div class="alert alert-danger">ไม่สามารถวิเคราะห์การเปลี่ยนแปลงได้</div>';
            document.getElementById('no-changes').classList.add('d-none');
        }
    });
});

// ฟังก์ชันสำหรับแสดงข้อมูล JSON
function formatJsonForDisplay(jsonObj) {
    if (!jsonObj || Object.keys(jsonObj).length === 0) {
        return '<div class="text-center text-muted py-3"><i class="fas fa-info-circle fa-2x mb-2"></i><p class="mb-0">ไม่มีข้อมูล</p></div>';
    }
    
    let html = '<table class="table table-sm table-bordered">';
    html += '<thead class="table-light"><tr><th>คุณสมบัติ</th><th>ค่า</th></tr></thead><tbody>';
    
    for (const key in jsonObj) {
        html += '<tr>';
        html += `<td><strong>${key}</strong></td>`;
        
        // ถ้าค่าเป็น object หรือ array
        if (typeof jsonObj[key] === 'object' && jsonObj[key] !== null) {
            html += `<td><pre class="mb-0">${JSON.stringify(jsonObj[key], null, 2)}</pre></td>`;
        } else {
            html += `<td>${jsonObj[key] !== null ? jsonObj[key] : '<em class="text-muted">ไม่มีข้อมูล</em>'}</td>`;
        }
        
        html += '</tr>';
    }
    
    html += '</tbody></table>';
    return html;
}

// ฟังก์ชันสร้างตารางเปรียบเทียบการเปลี่ยนแปลง
function generateChangesTable(oldData, newData) {
    if (!oldData || !newData) {
        return '';
    }
    
    let html = '<table class="table table-sm table-bordered table-hover">';
    html += '<thead class="table-light"><tr><th>คุณสมบัติ</th><th>ค่าเดิม</th><th>ค่าใหม่</th></tr></thead><tbody>';
    
    let hasChanges = false;
    
    // ตรวจสอบการเปลี่ยนแปลงในทุก key ของ newData
    for (const key in newData) {
        const oldValue = oldData[key];
        const newValue = newData[key];
        
        // ตรวจสอบว่ามีการเปลี่ยนแปลงหรือไม่
        if (JSON.stringify(oldValue) !== JSON.stringify(newValue)) {
            hasChanges = true;
            
            html += '<tr>';
            html += `<td><strong>${key}</strong></td>`;
            
            // แสดงค่าเดิม
            if (typeof oldValue === 'object' && oldValue !== null) {
                html += `<td><pre class="mb-0">${JSON.stringify(oldValue, null, 2)}</pre></td>`;
            } else {
                html += `<td>${oldValue !== undefined && oldValue !== null ? oldValue : '<em class="text-muted">ไม่มีข้อมูล</em>'}</td>`;
            }
            
            // แสดงค่าใหม่
            if (typeof newValue === 'object' && newValue !== null) {
                html += `<td><pre class="mb-0">${JSON.stringify(newValue, null, 2)}</pre></td>`;
            } else {
                html += `<td>${newValue !== undefined && newValue !== null ? newValue : '<em class="text-muted">ไม่มีข้อมูล</em>'}</td>`;
            }
            
            html += '</tr>';
        }
    }
    
    // ตรวจสอบ key ที่มีในข้อมูลเดิมแต่ไม่มีในข้อมูลใหม่ (กรณีลบข้อมูล)
    for (const key in oldData) {
        if (newData[key] === undefined) {
            hasChanges = true;
            
            html += '<tr>';
            html += `<td><strong>${key}</strong></td>`;
            
            // แสดงค่าเดิม
            if (typeof oldData[key] === 'object' && oldData[key] !== null) {
                html += `<td><pre class="mb-0">${JSON.stringify(oldData[key], null, 2)}</pre></td>`;
            } else {
                html += `<td>${oldData[key] !== null ? oldData[key] : '<em class="text-muted">ไม่มีข้อมูล</em>'}</td>`;
            }
            
            // แสดงว่าถูกลบ
            html += '<td><span class="badge bg-danger">ถูกลบ</span></td>';
            
            html += '</tr>';
        }
    }
    
    html += '</tbody></table>';
    
    return hasChanges ? html : '';
}

// ตั้งค่า datepicker สำหรับช่องวันที่
document.addEventListener('DOMContentLoaded', function() {
    // เพิ่ม event listener สำหรับการ reset form
    document.querySelector('a[href="?page=admin_logs"]').addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = '?page=admin_logs';
    });
});
</script>

<style>
/* Style สำหรับหน้า logs */
.card {
    margin-bottom: 20px;
}

.table th {
    font-weight: 600;
}

pre {
    background-color: #f8f9fa;
    padding: 8px;
    border-radius: 4px;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 0.875rem;
}

.badge {
    font-weight: 500;
}

/* Animation สำหรับไอคอนใน card */
.card i.fa-2x {
    transition: all 0.3s ease;
}

.card:hover i.fa-2x {
    transform: scale(1.1);
}

/* Style สำหรับ tabs ใน modal */
.nav-tabs .nav-link {
    font-weight: 500;
    padding: 10px 15px;
}

.nav-tabs .nav-link.active {
    background-color: #f8f9fa;
    border-bottom-color: #f8f9fa;
}

/* Style สำหรับตารางเปรียบเทียบการเปลี่ยนแปลง */
#changes-table tr:hover {
    background-color: #fff8e1;
}

/* Style สำหรับ responsive table */
@media (max-width: 768px) {
    .table {
        font-size: 0.875rem;
    }
    
    .modal-dialog {
        margin: 0.5rem;
    }
}
</style>
