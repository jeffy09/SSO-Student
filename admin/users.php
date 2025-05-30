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
$current_page_num = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$records_per_page = 10;
$offset = ($current_page_num - 1) * $records_per_page;

// ตัวแปรสำหรับการค้นหาและกรอง
$search_term = isset($_GET['search']) ? $database->sanitize($_GET['search']) : '';
$faculty_filter_val = isset($_GET['faculty']) ? $database->sanitize($_GET['faculty']) : '';
$user_type_filter_val = isset($_GET['user_type']) ? $database->sanitize($_GET['user_type']) : 'all'; // 'all', 'student', 'teacher'

$students_data = [];
$teachers_data = [];
$total_students = 0;
$total_teachers = 0;
$faculties_list = [];

try {
    // ดึงข้อมูลคณะทั้งหมดสำหรับ dropdown กรอง (จากตาราง students)
    $faculty_query = "SELECT DISTINCT faculty FROM students WHERE faculty IS NOT NULL AND faculty != '' ORDER BY faculty";
    $faculty_stmt = $db->prepare($faculty_query);
    $faculty_stmt->execute();
    $faculties_list = $faculty_stmt->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูลนักศึกษา
    if ($user_type_filter_val === 'all' || $user_type_filter_val === 'student') {
        $count_query_student = "SELECT COUNT(*) as total FROM students WHERE 1=1";
        $query_student = "SELECT id, student_id as user_code, firstname, lastname, email, faculty, department, google_id, 'student' as user_type FROM students WHERE 1=1";
        $params_student = [];

        if (!empty($search_term)) {
            $search_condition = " AND (student_id LIKE :search OR firstname LIKE :search OR lastname LIKE :search OR email LIKE :search)";
            $count_query_student .= $search_condition;
            $query_student .= $search_condition;
            $params_student[':search'] = "%" . $search_term . "%";
        }
        if (!empty($faculty_filter_val)) {
            $faculty_condition = " AND faculty = :faculty";
            $count_query_student .= $faculty_condition;
            $query_student .= $faculty_condition;
            $params_student[':faculty'] = $faculty_filter_val;
        }

        $count_stmt_student = $db->prepare($count_query_student);
        $count_stmt_student->execute($params_student);
        $total_students = $count_stmt_student->fetch(PDO::FETCH_ASSOC)['total'];

        $query_student .= " ORDER BY id DESC LIMIT :offset, :records_per_page";
        $stmt_student = $db->prepare($query_student);
        foreach ($params_student as $key => &$value) {
            $stmt_student->bindValue($key, $value);
        }
        unset($value);
        $stmt_student->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt_student->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
        $stmt_student->execute();
        $students_data = $stmt_student->fetchAll(PDO::FETCH_ASSOC);
    }

    // ดึงข้อมูลอาจารย์
    if ($user_type_filter_val === 'all' || $user_type_filter_val === 'teacher') {
        $count_query_teacher = "SELECT COUNT(*) as total FROM teachers WHERE 1=1";
        $query_teacher = "SELECT id, teacher_id as user_code, firstname, lastname, email, NULL as faculty, department, google_id, 'teacher' as user_type FROM teachers WHERE 1=1";
        $params_teacher = [];

        if (!empty($search_term)) {
            $search_condition = " AND (teacher_id LIKE :search OR firstname LIKE :search OR lastname LIKE :search OR email LIKE :search)";
            $count_query_teacher .= $search_condition;
            $query_teacher .= $search_condition;
            $params_teacher[':search'] = "%" . $search_term . "%";
        }
        //หมายเหตุ: อาจารย์ไม่มี filter ตามคณะในตัวอย่างนี้ ถ้าต้องการต้องเพิ่มฟิลด์คณะในตาราง teachers หรือ UI ที่ต่างออกไป

        $count_stmt_teacher = $db->prepare($count_query_teacher);
        $count_stmt_teacher->execute($params_teacher);
        $total_teachers = $count_stmt_teacher->fetch(PDO::FETCH_ASSOC)['total'];

        $query_teacher .= " ORDER BY id DESC LIMIT :offset, :records_per_page";
        $stmt_teacher = $db->prepare($query_teacher);
        foreach ($params_teacher as $key => &$value) {
            $stmt_teacher->bindValue($key, $value);
        }
        unset($value);
        $stmt_teacher->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt_teacher->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
        $stmt_teacher->execute();
        $teachers_data = $stmt_teacher->fetchAll(PDO::FETCH_ASSOC);
    }

    // คำนวณจำนวนหน้าทั้งหมดตาม filter ปัจจุบัน
    if ($user_type_filter_val === 'student') {
        $total_rows_for_pagination = $total_students;
    } elseif ($user_type_filter_val === 'teacher') {
        $total_rows_for_pagination = $total_teachers;
    } else { // 'all'
        // Pagination สำหรับ 'all' อาจซับซ้อนถ้าจะรวมผลลัพธ์ ถ้าแยกส่วนการแสดงผล อาจจะต้องมี pagination แยก
        // หรือใช้ total_rows ที่มากที่สุดสำหรับคำนวณ total_pages แต่การ query offset จะต้องปรับ
        // ในที่นี้จะใช้ total_students + total_teachers สำหรับ total_pages เมื่อเป็น 'all' และแสดงผลรวมกัน
        // แต่การ query แบบ LIMIT OFFSET สำหรับ 'all' จะต้องทำ UNION หรือ query แยกแล้ว merge ซึ่งซับซ้อน
        // วิธีที่ง่ายกว่าคือ ถ้าเป็น 'all' ให้ pagination dựa trên total_students ก่อน แล้วค่อยแสดง teachers หรือแยก pagination
        $total_rows_for_pagination = $total_students + $total_teachers; // อาจต้องปรับปรุง logic การ query เมื่อเป็น 'all' และมีการแบ่งหน้า
    }
    $total_pages_for_display = ceil($total_rows_for_pagination / $records_per_page);


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

<div class="card shadow mb-4">
    <div class="card-body">
        <form action="" method="get" class="row g-3">
            <input type="hidden" name="page" value="admin_users">
            
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="ค้นหา: รหัส, ชื่อ, อีเมล..." value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
            </div>
             <div class="col-md-3">
                <select name="user_type" class="form-select">
                    <option value="all" <?php echo ($user_type_filter_val == 'all') ? 'selected' : ''; ?>>-- ทุกประเภท --</option>
                    <option value="student" <?php echo ($user_type_filter_val == 'student') ? 'selected' : ''; ?>>นักศึกษา</option>
                    <option value="teacher" <?php echo ($user_type_filter_val == 'teacher') ? 'selected' : ''; ?>>อาจารย์</option>
                </select>
            </div>
            <div class="col-md-3" id="faculty_filter_div" style="<?php echo ($user_type_filter_val === 'teacher') ? 'display:none;' : ''; ?>">
                <select name="faculty" class="form-select">
                    <option value="">-- ทุกคณะ --</option>
                    <?php foreach($faculties_list as $faculty_item): ?>
                        <option value="<?php echo htmlspecialchars($faculty_item['faculty']); ?>" <?php echo ($faculty_filter_val == $faculty_item['faculty']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($faculty_item['faculty']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                 <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter"></i> กรอง</button>
            </div>
             <div class="col-12 text-end mt-2">
                <a href="?page=admin_users" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i> ล้างตัวกรอง</a>
            </div>
        </form>
    </div>
</div>

<?php
function render_user_table($users, $user_type_label, $is_student_table) {
    if (empty($users)) {
        echo "<div class='alert alert-info'>ไม่พบข้อมูล{$user_type_label}</div>";
        return;
    }
?>
    <div class="card shadow mb-4">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">รายการ<?php echo $user_type_label; ?></h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>รหัส<?php echo $user_type_label; ?></th>
                            <th>ชื่อ-นามสกุล</th>
                            <?php if ($is_student_table): ?>
                                <th>คณะ/สาขา</th>
                            <?php else: ?>
                                <th>ภาควิชา/ตำแหน่ง</th>
                            <?php endif; ?>
                            <th>อีเมล</th>
                            <th>เชื่อมต่อ Google</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user_row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user_row['user_code']); ?></td>
                                <td><?php echo htmlspecialchars($user_row['firstname'] . ' ' . $user_row['lastname']); ?></td>
                                <?php if ($is_student_table): ?>
                                    <td><?php echo htmlspecialchars($user_row['faculty'] . ($user_row['department'] ? ' / ' . $user_row['department'] : '')); ?></td>
                                <?php else: // Teacher ?>
                                     <td><?php echo htmlspecialchars(($user_row['department'] ?: 'N/A') . ($user_row['position'] ? ' / ' . $user_row['position'] : '')); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($user_row['email']); ?></td>
                                <td>
                                    <?php if(!empty($user_row['google_id'])): ?>
                                        <span class="badge bg-success"><i class="fab fa-google"></i> เชื่อมต่อแล้ว</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ยังไม่เชื่อมต่อ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $encoded_id = base64_encode($user_row['id']); ?>
                                    <a href="?page=admin_view_user&id=<?php echo $encoded_id; ?>&type=<?php echo $user_row['user_type']; ?>" class="btn btn-sm btn-info" title="ดูข้อมูล">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?page=admin_edit_user&id=<?php echo $encoded_id; ?>&type=<?php echo $user_row['user_type']; ?>" class="btn btn-sm btn-warning" title="แก้ไข">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
}

if ($user_type_filter_val === 'all' || $user_type_filter_val === 'student') {
    render_user_table($students_data, "นักศึกษา", true);
}
if ($user_type_filter_val === 'all' || $user_type_filter_val === 'teacher') {
    render_user_table($teachers_data, "อาจารย์", false);
}

// Pagination (อาจจะต้องปรับปรุงถ้าแสดงผลแบบรวม)
if($total_pages_for_display > 1 && ($user_type_filter_val !== 'all' || ($total_students > 0 || $total_teachers > 0))): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php
            $link_params_pagination = "&search=" . urlencode($search_term) . "&faculty=" . urlencode($faculty_filter_val) . "&user_type=" . urlencode($user_type_filter_val);
            if($current_page_num > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=admin_users&p=1<?php echo $link_params_pagination; ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=admin_users&p=<?php echo $current_page_num-1; ?><?php echo $link_params_pagination; ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                </li>
            <?php endif; ?>
            
            <?php
            $start_loop_page = max(1, $current_page_num - 2);
            $end_loop_page = min($total_pages_for_display, $current_page_num + 2);
            
            for($i = $start_loop_page; $i <= $end_loop_page; $i++): ?>
                <li class="page-item <?php echo ($i == $current_page_num) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=admin_users&p=<?php echo $i; ?><?php echo $link_params_pagination; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
            
            <?php if($current_page_num < $total_pages_for_display): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=admin_users&p=<?php echo $current_page_num+1; ?><?php echo $link_params_pagination; ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=admin_users&p=<?php echo $total_pages_for_display; ?><?php echo $link_params_pagination; ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userTypeSelect = document.querySelector('select[name="user_type"]');
    const facultyFilterDiv = document.getElementById('faculty_filter_div');

    function toggleFacultyFilter() {
        if (userTypeSelect.value === 'teacher') {
            facultyFilterDiv.style.display = 'none';
            facultyFilterDiv.querySelector('select').value = ''; // Clear faculty filter if teacher is selected
        } else {
            facultyFilterDiv.style.display = 'block';
        }
    }

    if(userTypeSelect) {
        userTypeSelect.addEventListener('change', toggleFacultyFilter);
    }
    // Initial check
    // toggleFacultyFilter(); // The PHP already handles initial display style
});
</script>
