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
$records_per_page = 10;
$offset = ($current_page - 1) * $records_per_page;

// ตัวแปรสำหรับการค้นหา
$search = isset($_GET['search']) ? $database->sanitize($_GET['search']) : '';
$faculty_filter = isset($_GET['faculty']) ? $database->sanitize($_GET['faculty']) : '';

try {
    // คำสั่ง SQL สำหรับนับจำนวนทั้งหมด (ใช้เงื่อนไขเดียวกับการดึงข้อมูล)
    $count_query = "SELECT COUNT(*) as total FROM students WHERE 1=1";
    
    // คำสั่ง SQL สำหรับดึงข้อมูลนักศึกษา
    $query = "SELECT * FROM students WHERE 1=1";
    
    // เพิ่มเงื่อนไขการค้นหา (ถ้ามี)
    if (!empty($search)) {
        $search_condition = " AND (student_id LIKE :search OR firstname LIKE :search OR lastname LIKE :search OR email LIKE :search)";
        $count_query .= $search_condition;
        $query .= $search_condition;
    }
    
    // เพิ่มเงื่อนไขตามคณะ (ถ้ามี)
    if (!empty($faculty_filter)) {
        $faculty_condition = " AND faculty = :faculty";
        $count_query .= $faculty_condition;
        $query .= $faculty_condition;
    }
    
    // เตรียมและ execute คำสั่ง SQL สำหรับนับจำนวน
    $count_stmt = $db->prepare($count_query);
    
    // bind ค่าสำหรับการค้นหา (ถ้ามี)
    if (!empty($search)) {
        $search_param = "%" . $search . "%";
        $count_stmt->bindParam(':search', $search_param);
    }
    
    // bind ค่าสำหรับการกรองตามคณะ (ถ้ามี)
    if (!empty($faculty_filter)) {
        $count_stmt->bindParam(':faculty', $faculty_filter);
    }
    
    $count_stmt->execute();
    $total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_rows / $records_per_page);
    
    // เพิ่ม ORDER BY และ LIMIT ในคำสั่ง SQL สำหรับดึงข้อมูล
    $query .= " ORDER BY id DESC LIMIT :offset, :records_per_page";
    
    // เตรียมและ execute คำสั่ง SQL สำหรับดึงข้อมูล
    $stmt = $db->prepare($query);
    
    // bind ค่าสำหรับการค้นหา (ถ้ามี)
    if (!empty($search)) {
        $stmt->bindParam(':search', $search_param);
    }
    
    // bind ค่าสำหรับการกรองตามคณะ (ถ้ามี)
    if (!empty($faculty_filter)) {
        $stmt->bindParam(':faculty', $faculty_filter);
    }
    
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
    
    $stmt->execute();
    
    // ดึงข้อมูลคณะทั้งหมดสำหรับ dropdown กรอง
    $faculty_query = "SELECT DISTINCT faculty FROM students ORDER BY faculty";
    $faculty_stmt = $db->prepare($faculty_query);
    $faculty_stmt->execute();
    $faculties = $faculty_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>จัดการผู้ใช้งาน</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="?page=admin_add_user" class="btn btn-primary"><i class="fas fa-user-plus"></i> เพิ่มผู้ใช้งาน</a>
        <a href="?page=admin_bulk_add" class="btn btn-success"><i class="fas fa-file-upload"></i> เพิ่มแบบกลุ่ม</a>
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
            <input type="hidden" name="page" value="admin_users">
            
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="ค้นหา: รหัสนักศึกษา, ชื่อ, นามสกุล, อีเมล" value="<?php echo $search; ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> ค้นหา</button>
                </div>
            </div>
            
            <div class="col-md-4">
                <select name="faculty" class="form-select" onchange="this.form.submit()">
                    <option value="">-- ทุกคณะ --</option>
                    <?php foreach($faculties as $faculty): ?>
                        <option value="<?php echo $faculty['faculty']; ?>" <?php echo ($faculty_filter == $faculty['faculty']) ? 'selected' : ''; ?>>
                            <?php echo $faculty['faculty']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <a href="?page=admin_users" class="btn btn-secondary w-100"><i class="fas fa-sync-alt"></i> ล้างตัวกรอง</a>
            </div>
        </form>
    </div>
</div>

<!-- ตารางแสดงข้อมูลนักศึกษา -->
<div class="card shadow">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>รหัสนักศึกษา</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>คณะ/สาขา</th>
                        <th>อีเมล</th>
                        <th>เชื่อมต่อ Google</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($stmt->rowCount() > 0): ?>
                        <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo $row['student_id']; ?></td>
                                <td><?php echo $row['firstname'] . ' ' . $row['lastname']; ?></td>
                                <td><?php echo $row['faculty'] . '<br><small class="text-muted">' . $row['department'] . '</small>'; ?></td>
                                <td><?php echo $row['email']; ?></td>
                                <td>
                                    <?php if(!empty($row['google_id'])): ?>
                                        <span class="badge bg-success"><i class="fab fa-google"></i> เชื่อมต่อแล้ว</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ยังไม่เชื่อมต่อ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    // เข้ารหัส ID เพื่อซ่อนใน URL
                                    $encoded_id = base64_encode($row['id']);
                                    ?>
                                    <a href="?page=admin_view_user&id=<?php echo $encoded_id; ?>" class="btn btn-sm btn-info" title="ดูข้อมูล">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?page=admin_edit_user&id=<?php echo $encoded_id; ?>" class="btn btn-sm btn-warning" title="แก้ไข">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">ไม่พบข้อมูล</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=admin_users&p=1<?php echo (!empty($search)) ? '&search='.$search : ''; ?><?php echo (!empty($faculty_filter)) ? '&faculty='.$faculty_filter : ''; ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=admin_users&p=<?php echo $current_page-1; ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?><?php echo (!empty($faculty_filter)) ? '&faculty='.$faculty_filter : ''; ?>">
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
                            <a class="page-link" href="?page=admin_users&p=<?php echo $i; ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?><?php echo (!empty($faculty_filter)) ? '&faculty='.$faculty_filter : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=admin_users&p=<?php echo $current_page+1; ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?><?php echo (!empty($faculty_filter)) ? '&faculty='.$faculty_filter : ''; ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=admin_users&p=<?php echo $total_pages; ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?><?php echo (!empty($faculty_filter)) ? '&faculty='.$faculty_filter : ''; ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>