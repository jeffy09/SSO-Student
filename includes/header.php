<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}
$current_page_for_sidebar = isset($_GET['page']) ? $_GET['page'] : 'home';
$is_login_page = ($current_page_for_sidebar === 'home' || $current_page_for_sidebar === 'student_login' || $current_page_for_sidebar === 'admin_login');

// สร้างตัวแปรสำหรับ data attribute เพื่อบอกสถานะการล็อกอินให้ JavaScript
$user_session_attributes = "";
if (isset($_SESSION['student_id'])) {
    $user_session_attributes = 'data-user-id="' . htmlspecialchars($_SESSION['student_id']) . '"';
} elseif (isset($_SESSION['admin_id'])) {
    $user_session_attributes = 'data-admin-id="' . htmlspecialchars($_SESSION['admin_id']) . '"';
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการนักศึกษา</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>

<body class="bg-light <?php echo $is_login_page ? 'login-page-active' : ''; ?>">
    <div class="page-wrapper" id="main-wrapper" <?php echo $user_session_attributes; ?> data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
        data-sidebar-position="fixed" data-header-position="fixed">

        <?php // Sidebar จะแสดงเฉพาะหน้าที่ล็อกอินแล้ว และไม่ใช่หน้า login
        if (!$is_login_page && (isset($_SESSION['student_id']) || isset($_SESSION['admin_id']))) : ?>
            <aside class="left-sidebar">
                <div>
                    <div class="brand-logo d-flex align-items-center justify-content-between">
                        <a href="<?php echo (isset($_SESSION['student_id'])) ? '?page=student_dashboard' : '?page=admin_dashboard'; ?>" class="text-nowrap logo-img">
                            <h4 class="text-primary fw-bold ps-2 pt-2">ระบบนักศึกษา</h4>
                        </a>
                        <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse">
                            <i class="fas fa-times fs-4"></i>
                        </div>
                    </div>
                    <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
                        <ul id="sidebarnav">
                            <li class="nav-small-cap">
                                <i class="fas fa-ellipsis-h nav-small-cap-icon fs-4"></i>
                                <span class="hide-menu">เมนูหลัก</span>
                            </li>
                            <?php if (isset($_SESSION['student_id'])): ?>
                                <li class="sidebar-item">
                                    <a class="sidebar-link <?php echo ($current_page_for_sidebar === 'student_dashboard') ? 'active' : ''; ?>" href="?page=student_dashboard" aria-expanded="false">
                                        <span><i class="fas fa-tachometer-alt"></i></span>
                                        <span class="hide-menu">แดชบอร์ด</span>
                                    </a>
                                </li>
                                <li class="sidebar-item">
                                    <a class="sidebar-link <?php echo ($current_page_for_sidebar === 'student_profile') ? 'active' : ''; ?>" href="?page=student_profile" aria-expanded="false">
                                        <span><i class="fas fa-user"></i></span>
                                        <span class="hide-menu">โปรไฟล์</span>
                                    </a>
                                </li>
                                <li class="sidebar-item">
                                    <a class="sidebar-link <?php echo ($current_page_for_sidebar === 'helpdesk' || $current_page_for_sidebar === 'helpdesk_create' || $current_page_for_sidebar === 'helpdesk_view') ? 'active' : ''; ?>" href="?page=helpdesk" aria-expanded="false">
                                        <span><i class="fas fa-life-ring"></i></span>
                                        <span class="hide-menu">Helpdesk</span>
                                    </a>
                                </li>
                                <li class="sidebar-item">
                                    <a class="sidebar-link <?php echo ($current_page_for_sidebar === 'google_drive_files') ? 'active' : ''; ?>" href="?page=google_drive_files" aria-expanded="false">
                                        <span><i class="fab fa-google-drive"></i></span>
                                        <span class="hide-menu">Drive</span>
                                    </a>
                                </li>
                            <?php elseif (isset($_SESSION['admin_id'])): ?>
                                <li class="sidebar-item">
                                    <a class="sidebar-link <?php echo ($current_page_for_sidebar === 'admin_dashboard') ? 'active' : ''; ?>" href="?page=admin_dashboard" aria-expanded="false">
                                        <span><i class="fas fa-tachometer-alt"></i></span>
                                        <span class="hide-menu">แดชบอร์ด</span>
                                    </a>
                                </li>
                                <li class="sidebar-item">
                                    <a class="sidebar-link <?php echo ($current_page_for_sidebar === 'admin_users' || $current_page_for_sidebar === 'admin_view_user' || $current_page_for_sidebar === 'admin_edit_user') ? 'active' : ''; ?>" href="?page=admin_users" aria-expanded="false">
                                        <span><i class="fas fa-users-cog"></i></span>
                                        <span class="hide-menu">จัดการผู้ใช้งาน</span>
                                    </a>
                                </li>
                                <li class="sidebar-item">
                                    <a class="sidebar-link <?php echo ($current_page_for_sidebar === 'admin_add_user' || $current_page_for_sidebar === 'admin_bulk_add') ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#addUserSubmenu" aria-expanded="false" aria-controls="addUserSubmenu">
                                        <span><i class="fas fa-user-plus"></i></span>
                                        <span class="hide-menu">เพิ่มผู้ใช้งาน</span>
                                        <i class="fas fa-chevron-down float-end"></i>
                                    </a>
                                    <ul id="addUserSubmenu" class="collapse list-unstyled <?php echo ($current_page_for_sidebar === 'admin_add_user' || $current_page_for_sidebar === 'admin_bulk_add') ? 'show' : ''; ?>">
                                        <li class="sidebar-item ms-3">
                                            <a class="sidebar-link <?php echo ($current_page_for_sidebar === 'admin_add_user') ? 'active' : ''; ?>" href="?page=admin_add_user">
                                                <i class="fas fa-user me-2"></i>เพิ่มรายบุคคล
                                            </a>
                                        </li>
                                        <li class="sidebar-item ms-3">
                                            <a class="sidebar-link <?php echo ($current_page_for_sidebar === 'admin_bulk_add') ? 'active' : ''; ?>" href="?page=admin_bulk_add">
                                                <i class="fas fa-file-csv me-2"></i>เพิ่มแบบกลุ่ม
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                                <li class="sidebar-item">
                                    <a class="sidebar-link <?php echo ($current_page_for_sidebar === 'admin_helpdesk' || $current_page_for_sidebar === 'admin_helpdesk_view') ? 'active' : ''; ?>" href="?page=admin_helpdesk" aria-expanded="false">
                                        <span><i class="fas fa-headset"></i></span>
                                        <span class="hide-menu">Helpdesk</span>
                                    </a>
                                </li>
                                <li class="sidebar-item">
                                    <a class="sidebar-link <?php echo ($current_page_for_sidebar === 'admin_logs') ? 'active' : ''; ?>" href="?page=admin_logs" aria-expanded="false">
                                        <span><i class="fas fa-clipboard-list"></i></span>
                                        <span class="hide-menu">ประวัติการทำงาน</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </aside>
        <?php endif; ?>

        <div class="body-wrapper">
            <?php // Navbar จะไม่แสดงในหน้า login
            if (!$is_login_page): ?>
            <header class="app-header">
                <nav class="navbar navbar-expand-lg navbar-light">
                    <ul class="navbar-nav">
                        <?php if ((isset($_SESSION['student_id']) || isset($_SESSION['admin_id']))) : // Hamburger icon shows if logged in and not login page ?>
                            <li class="nav-item d-block d-xl-none">
                                <a class="nav-link sidebartoggler nav-icon-hover" id="headerCollapse" href="javascript:void(0)">
                                    <i class="fas fa-bars fs-4"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <div class="navbar-collapse justify-content-end px-0" id="navbarNav">
                        <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-end">
                            <?php if (isset($_SESSION['student_id'])): ?>
                                <li class="nav-item dropdown">
                                    <a class="nav-link nav-icon-hover" href="javascript:void(0)" id="drop2" data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                        <img src="assets/images/profile/user-1.jpg" alt="" width="35" height="35" class="rounded-circle">
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop2">
                                        <div class="message-body">
                                            <h6 class="dropdown-header text-muted"><?php echo htmlspecialchars($_SESSION['student_name']); ?></h6>
                                            <a href="?page=student_profile" class="dropdown-item">
                                                <i class="fas fa-user-edit me-2"></i> แก้ไขโปรไฟล์
                                            </a>
                                            <a href="?page=logout" class="btn btn-outline-primary mx-3 mt-2 d-block">ออกจากระบบ</a>
                                        </div>
                                    </div>
                                </li>
                            <?php elseif (isset($_SESSION['admin_id'])): ?>
                                <li class="nav-item dropdown">
                                    <a class="nav-link nav-icon-hover" href="javascript:void(0)" id="drop2" data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                        <img src="assets/images/profile/admin-avatar.png" alt="" width="35" height="35" class="rounded-circle">
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop2">
                                        <div class="message-body">
                                            <h6 class="dropdown-header text-muted"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h6>
                                            <a href="?page=admin_profile" class="dropdown-item">
                                                <i class="fas fa-user-shield me-2"></i> โปรไฟล์
                                            </a>
                                            <a href="?page=logout" class="btn btn-outline-primary mx-3 mt-2 d-block">ออกจากระบบ</a>
                                        </div>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </nav>
            </header>
            <?php endif; // end if for !is_login_page ?>

            <div class="container-fluid <?php echo $is_login_page ? 'is-login-page' : ''; ?>">
                <?php // โค้ดแสดง Alert messages
                    if (!$is_login_page && (isset($_SESSION['student_id']) || isset($_SESSION['admin_id']))) :
                        if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $_SESSION['success_message']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['success_message']); ?>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $_SESSION['error_message']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['error_message']); ?>
                        <?php endif; ?>
                    <?php endif; ?>
