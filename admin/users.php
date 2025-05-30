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
$error_message = ''; // Initialize error message

try {
    // ดึงข้อมูลคณะทั้งหมดสำหรับ dropdown กรอง (จากตาราง students)
    $faculty_query = "SELECT DISTINCT faculty FROM students WHERE faculty IS NOT NULL AND faculty != '' ORDER BY faculty";
    $faculty_stmt = $db->prepare($faculty_query);
    $faculty_stmt->execute();
    $faculties_list = $faculty_stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Logic for Students ---
    if ($user_type_filter_val === 'all' || $user_type_filter_val === 'student') {
        $count_query_student = "SELECT COUNT(*) as total FROM students WHERE 1=1";
        $query_student = "SELECT id, student_id as user_code, firstname, lastname, email, faculty, department, google_id, 'student' as user_type FROM students WHERE 1=1";
        $params_student = [];

        if (!empty($search_term)) {
            $search_condition_student = " AND (student_id LIKE :search OR firstname LIKE :search OR lastname LIKE :search OR email LIKE :search OR faculty LIKE :search OR department LIKE :search)";
            $count_query_student .= $search_condition_student;
            $query_student .= $search_condition_student;
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
        $total_students = (int)$count_stmt_student->fetch(PDO::FETCH_ASSOC)['total'];

        // Apply pagination only if student filter is active or 'all'
        if ($user_type_filter_val === 'student' || ($user_type_filter_val === 'all' && $total_students > 0)) {
            $query_student .= " ORDER BY id DESC LIMIT :offset, :records_per_page";
            $stmt_student = $db->prepare($query_student);
            foreach ($params_student as $key => &$value) { $stmt_student->bindValue($key, $value); } unset($value);
            $stmt_student->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt_student->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
        } else if ($user_type_filter_val === 'all') { // If 'all' but no students, prepare statement to avoid errors
             $query_student .= " ORDER BY id DESC"; // No limit if not primary display for pagination
             $stmt_student = $db->prepare($query_student);
             foreach ($params_student as $key => &$value) { $stmt_student->bindValue($key, $value); } unset($value);
        }


        if (isset($stmt_student)) {
            $stmt_student->execute();
            $students_data = $stmt_student->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // --- Logic for Teachers ---
    if ($user_type_filter_val === 'all' || $user_type_filter_val === 'teacher') {
        $count_query_teacher = "SELECT COUNT(*) as total FROM teachers WHERE 1=1";
        $query_teacher = "SELECT id, teacher_id as user_code, firstname, lastname, email, NULL as faculty, department, google_id, 'teacher' as user_type, position FROM teachers WHERE 1=1";
        $params_teacher = [];

        if (!empty($search_term)) {
            $search_condition_teacher = " AND (teacher_id LIKE :search OR firstname LIKE :search OR lastname LIKE :search OR email LIKE :search OR department LIKE :search OR position LIKE :search)";
            $count_query_teacher .= $search_condition_teacher;
            $query_teacher .= $search_condition_teacher;
            $params_teacher[':search'] = "%" . $search_term . "%";
        }
        
        $count_stmt_teacher = $db->prepare($count_query_teacher);
        $count_stmt_teacher->execute($params_teacher);
        $total_teachers = (int)$count_stmt_teacher->fetch(PDO::FETCH_ASSOC)['total'];

        // Apply pagination only if teacher filter is active OR (all filter AND no students were primary for pagination)
         if ($user_type_filter_val === 'teacher' || ($user_type_filter_val === 'all' && $total_students == 0 && $total_teachers > 0) ) {
            $query_teacher .= " ORDER BY id DESC LIMIT :offset, :records_per_page";
            $stmt_teacher = $db->prepare($query_teacher);
            foreach ($params_teacher as $key => &$value) { $stmt_teacher->bindValue($key, $value); } unset($value);
            $stmt_teacher->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt_teacher->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
        } else if ($user_type_filter_val === 'all') {
             $query_teacher .= " ORDER BY id DESC"; // No limit if not primary display for pagination
             $stmt_teacher = $db->prepare($query_teacher);
             foreach ($params_teacher as $key => &$value) { $stmt_teacher->bindValue($key, $value); } unset($value);
        }

        if (isset($stmt_teacher)) {
            $stmt_teacher->execute();
            $teachers_data = $stmt_teacher->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch(PDOException $e) {
    $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// Function to render pagination
function render_pagination_controls($current_page, $total_items, $records_per_page, $base_link_params) {
    if ($total_items <= 0) return;
    $total_pages = ceil($total_items / $records_per_page);
    if ($total_pages <= 1) return;
?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center mt-4">
            <?php if($current_page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=admin_users&p=1<?php echo $base_link_params; ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=admin_users&p=<?php echo $current_page-1; ?><?php echo $base_link_params; ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                </li>
            <?php endif; ?>
            
            <?php
            $start_loop_page = max(1, $current_page - 2);
            $end_loop_page = min($total_pages, $current_page + 2);
            
            for($i = $start_loop_page; $i <= $end_loop_page; $i++): ?>
                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=admin_users&p=<?php echo $i; ?><?php echo $base_link_params; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
            
            <?php if($current_page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=admin_users&p=<?php echo $current_page+1; ?><?php echo $base_link_params; ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=admin_users&p=<?php echo $total_pages; ?><?php echo $base_link_params; ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php
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

<?php if(!empty($error_message)): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-body">
        <form action="" method="get" class="row g-3">
            <input type="hidden" name="page" value="admin_users">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="ค้นหา: รหัส, ชื่อ, อีเมล..." value="<?php echo htmlspecialchars($search_term); ?>">
            </div>
            <div class="col-md-3">
                <select name="user_type" class="form-select" id="userTypeFilterSelect">
                    <option value="all" <?php echo ($user_type_filter_val == 'all') ? 'selected' : ''; ?>>-- ทุกประเภท --</option>
                    <option value="student" <?php echo ($user_type_filter_val == 'student') ? 'selected' : ''; ?>>นักศึกษา</option>
                    <option value="teacher" <?php echo ($user_type_filter_val == 'teacher') ? 'selected' : ''; ?>>อาจารย์</option>
                </select>
            </div>
            <div class="col-md-3" id="facultyFilterDiv" style="<?php echo ($user_type_filter_val === 'teacher') ? 'display:none;' : 'display:block;'; ?>">
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
function render_user_table_content($users, $user_type_label, $is_student_table) {
    if (empty($users) && ($GLOBALS['user_type_filter_val'] == strtolower($user_type_label) || $GLOBALS['user_type_filter_val'] == 'all')) {
         echo "<div class='alert alert-info mt-3'>ไม่พบข้อมูล{$user_type_label}ที่ตรงกับเงื่อนไขการค้นหา</div>";
        return;
    }
    if (empty($users)) return; // Don't render if no users for this type and filter is not specifically for it
?>
    <div class="card shadow mb-4">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">รายการ<?php echo $user_type_label; ?> (<?php echo ($is_student_table ? $GLOBALS['total_students'] : $GLOBALS['total_teachers']); ?> รายการ)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>รหัสผู้ใช้</th>
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
                                    <td><?php echo htmlspecialchars($user_row['faculty'] . (!empty($user_row['department']) ? ' / ' . $user_row['department'] : '')); ?></td>
                                <?php else: // Teacher ?>
                                     <td><?php echo htmlspecialchars((!empty($user_row['department']) ? $user_row['department'] : 'N/A') . (!empty($user_row['position']) ? ' / ' . $user_row['position'] : '')); ?></td>
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
            <?php
            // Render pagination for this specific table if it's the primary one being displayed or 'all' with content
            $link_params_for_pagination = "&search=" . urlencode($GLOBALS['search_term']) .
                                          "&faculty=" . urlencode($GLOBALS['faculty_filter_val']) .
                                          "&user_type=" . urlencode($GLOBALS['user_type_filter_val']);
            if ($is_student_table && ($GLOBALS['user_type_filter_val'] === 'student' || ($GLOBALS['user_type_filter_val'] === 'all' && $GLOBALS['total_students'] > 0))) {
                render_pagination_controls($GLOBALS['current_page_num'], $GLOBALS['total_students'], $GLOBALS['records_per_page'], $link_params_for_pagination);
            } elseif (!$is_student_table && ($GLOBALS['user_type_filter_val'] === 'teacher' || ($GLOBALS['user_type_filter_val'] === 'all' && $GLOBALS['total_students'] == 0 && $GLOBALS['total_teachers'] > 0) )) {
                 render_pagination_controls($GLOBALS['current_page_num'], $GLOBALS['total_teachers'], $GLOBALS['records_per_page'], $link_params_for_pagination);
            }
            ?>
        </div>
    </div>
<?php
}

// Render tables based on filter
if ($user_type_filter_val === 'all' || $user_type_filter_val === 'student') {
    render_user_table_content($students_data, "นักศึกษา", true);
}
if ($user_type_filter_val === 'all' || $user_type_filter_val === 'teacher') {
    render_user_table_content($teachers_data, "อาจารย์", false);
}

// If 'all' and both tables are empty after filtering, show a general message
if ($user_type_filter_val === 'all' && empty($students_data) && empty($teachers_data) && (!empty($search_term) || !empty($faculty_filter_val)) ) {
    echo "<div class='alert alert-warning text-center'>ไม่พบข้อมูลผู้ใช้งานที่ตรงกับเงื่อนไขการค้นหา</div>";
} elseif ($user_type_filter_val === 'all' && $total_students == 0 && $total_teachers == 0) {
    echo "<div class='alert alert-info text-center'>ยังไม่มีข้อมูลผู้ใช้งานในระบบ</div>";
}


?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userTypeSelect = document.getElementById('userTypeFilterSelect');
    const facultyFilterDiv = document.getElementById('facultyFilterDiv');

    function toggleFacultyFilterVisibility() {
        if (userTypeSelect.value === 'teacher') {
            facultyFilterDiv.style.display = 'none';
            // Optionally clear faculty filter when teacher is selected
            // facultyFilterDiv.querySelector('select').value = '';
        } else {
            facultyFilterDiv.style.display = 'block';
        }
    }

    if(userTypeSelect) {
        userTypeSelect.addEventListener('change', toggleFacultyFilterVisibility);
    }
    // Call on load to set initial state
    // toggleFacultyFilterVisibility(); // PHP already handles initial display state via style attribute
});
</script>
