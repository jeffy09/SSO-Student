<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการนักศึกษา</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Google Sign-In API -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <?php
            // กำหนดลิงก์ของ navbar brand ตามสถานะการล็อกอิน
            if (isset($_SESSION['student_id'])) {
                $brand_link = "?page=student_dashboard";
            } elseif (isset($_SESSION['admin_id'])) {
                $brand_link = "?page=admin_dashboard";
            } else {
                $brand_link = "index.php";
            }
            ?>
            <a class="navbar-brand" href="<?php echo $brand_link; ?>">ระบบจัดการนักศึกษา</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php
                    // กำหนดลิงก์หน้าหลักตามสถานะการล็อกอิน
                    if (isset($_SESSION['student_id'])) {
                        $home_link = "?page=student_dashboard";
                        $home_text = "แดชบอร์ด";
                    } elseif (isset($_SESSION['admin_id'])) {
                        $home_link = "?page=admin_dashboard";
                        $home_text = "แดชบอร์ด";
                    } else {
                        $home_link = "index.php";
                        $home_text = "หน้าหลัก";
                    }
                    ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $home_link; ?>"><?php echo $home_text; ?></a>
                    </li>

                    <?php if (isset($_SESSION['student_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=student_profile">โปรไฟล์</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=helpdesk">Helpdesk</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=google_drive_files">Drive</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="studentDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?php echo $_SESSION['student_name']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?page=student_dashboard"><i class="fas fa-tachometer-alt me-2"></i>แดชบอร์ด</a></li>
                                <li><a class="dropdown-item" href="?page=student_profile"><i class="fas fa-user-edit me-2"></i>โปรไฟล์</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="?page=logout"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a></li>
                            </ul>
                        </li>

                    <?php elseif (isset($_SESSION['admin_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=admin_users">จัดการผู้ใช้งาน</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="addUserDropdown" role="button" data-bs-toggle="dropdown">
                                เพิ่มผู้ใช้งาน
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?page=admin_add_user"><i class="fas fa-user-plus me-2"></i>เพิ่มรายบุคคล</a></li>
                                <li><a class="dropdown-item" href="?page=admin_bulk_add"><i class="fas fa-file-upload me-2"></i>เพิ่มแบบกลุ่ม</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="?page=admin_helpdesk">Helpdesk</a>
                        </li>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-shield"></i> <?php echo $_SESSION['admin_name']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?page=admin_dashboard"><i class="fas fa-tachometer-alt me-2"></i>แดชบอร์ด</a></li>
                                <li><a class="dropdown-item" href="?page=admin_profile"><i class="fas fa-user-edit me-2"></i>โปรไฟล์</a></li>
                                <!-- เพิ่มรายการเมนูใหม่ตรงนี้ -->
                                <li><a class="dropdown-item" href="?page=admin_logs"><i class="fas fa-history me-2"></i>ประวัติการทำงาน</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="?page=logout"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a></li>
                            </ul>
                        </li>

                    <?php else: ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                เข้าสู่ระบบ
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?page=student_login"><i class="fas fa-user-graduate me-2"></i>สำหรับนักศึกษา</a></li>
                                <li><a class="dropdown-item" href="?page=admin_login"><i class="fas fa-user-shield me-2"></i>สำหรับผู้ดูแลระบบ</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- แสดงข้อความแจ้งเตือนจาก Session -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="container mt-3">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="container mt-3">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container my-4">
