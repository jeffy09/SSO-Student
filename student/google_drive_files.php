<?php
// ไฟล์ pages/google_drive_files.php
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// ตรวจสอบการเชื่อมต่อ Google
if (!isset($_SESSION['google_access_token'])) {
    echo '<div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            กรุณาเชื่อมต่อบัญชี Google ก่อนใช้งาน
            <a href="index.php?page=student_profile" class="btn btn-sm btn-primary ms-2">เชื่อมต่อ Google</a>
          </div>';
    return;
}

// รวมไฟล์ Google Config
include_once 'config/google_config.php';

$google_config = new GoogleConfig();
$access_token = $_SESSION['google_access_token'];

// ดึงไฟล์จาก Google Drive
$drive_files = $google_config->getRecentDriveFiles($access_token, 5);

if ($drive_files === false) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            ไม่สามารถเชื่อมต่อ Google Drive ได้ กรุณาลองใหม่อีกครั้ง
          </div>';
    return;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fab fa-google-drive text-success"></i>
                        ไฟล์ล่าสุดจาก Google Drive
                    </h5>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> รีเฟรช
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($drive_files)): ?>
                        <div class="text-center py-4">
                            <i class="fab fa-google-drive fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">ไม่พบไฟล์ใน Google Drive</h6>
                            <p class="text-muted">หรือยังไม่ได้ให้สิทธิ์เข้าถึง Google Drive</p>
                            <a href="https://drive.google.com" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-external-link-alt"></i> เปิด Google Drive
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($drive_files as $index => $file): ?>
                                <div class="col-12 mb-3">
                                    <div class="card h-100 shadow-sm">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-auto">
                                                    <?php if (isset($file['thumbnailLink'])): ?>
                                                        <img src="<?= htmlspecialchars($file['thumbnailLink']) ?>" 
                                                             alt="Thumbnail" 
                                                             class="rounded" 
                                                             style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php elseif (isset($file['iconLink'])): ?>
                                                        <img src="<?= htmlspecialchars($file['iconLink']) ?>" 
                                                             alt="Icon" 
                                                             style="width: 50px; height: 50px;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                             style="width: 50px; height: 50px;">
                                                            <i class="fas fa-file fa-lg text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col">
                                                    <h6 class="mb-1">
                                                        <a href="<?= htmlspecialchars($file['webViewLink']) ?>" 
                                                           target="_blank" 
                                                           class="text-decoration-none">
                                                            <?= htmlspecialchars($file['name']) ?>
                                                        </a>
                                                    </h6>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <span class="badge bg-light text-dark">
                                                            <?= $google_config->getMimeTypeDescription($file['mimeType']) ?>
                                                        </span>
                                                        <?php if (isset($file['size'])): ?>
                                                            <span class="badge bg-secondary">
                                                                <?= $google_config->formatFileSize($file['size']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <span class="badge bg-info">
                                                            แก้ไขล่าสุด: <?= date('d/m/Y H:i', strtotime($file['modifiedTime'])) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <div class="btn-group" role="group">
                                                        <a href="<?= htmlspecialchars($file['webViewLink']) ?>" 
                                                           target="_blank" 
                                                           class="btn btn-outline-primary btn-sm"
                                                           title="เปิดดู">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if (isset($file['webContentLink'])): ?>
                                                            <a href="<?= htmlspecialchars($file['webContentLink']) ?>" 
                                                               class="btn btn-outline-success btn-sm"
                                                               title="ดาวน์โหลด">
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <button type="button" 
                                                                class="btn btn-outline-secondary btn-sm"
                                                                onclick="copyToClipboard('<?= htmlspecialchars($file['webViewLink']) ?>')"
                                                                title="คัดลอกลิงก์">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="https://drive.google.com" target="_blank" class="btn btn-success">
                                <i class="fab fa-google-drive"></i> เปิด Google Drive ทั้งหมด
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // แสดง toast notification
        showToast('คัดลอกลิงก์สำเร็จ', 'success');
    }, function(err) {
        console.error('ไม่สามารถคัดลอกได้: ', err);
        showToast('ไม่สามารถคัดลอกได้', 'error');
    });
}

function showToast(message, type) {
    // สร้าง toast notification
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(toast);
    
    // ลบ toast หลังจาก 3 วินาที
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>