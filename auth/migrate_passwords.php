<?php
// กำหนดค่าคงที่เพื่อบอกว่าการเข้าถึงไฟล์ถูกต้อง
define('SECURE_ACCESS', true);

// เปิดการแสดงข้อผิดพลาด
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// รวมไฟล์การเชื่อมต่อฐานข้อมูล
require_once '../config/db.php';

// สร้างการเชื่อมต่อฐานข้อมูล
$database = new Database();
$db = $database->getConnection();

// ตรวจสอบว่ามีการส่งพารามิเตอร์ confirm มาหรือไม่
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';
$force_update = isset($_GET['force']) && $_GET['force'] === 'yes';

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัพเดต Password Hash สำหรับข้อมูลเก่า</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
        }
        .result-box {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .progress-bar {
            transition: width 0.3s ease;
        }
        .log-entry {
            padding: 8px 12px;
            margin: 2px 0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .log-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .log-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .log-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="text-center mb-4">
                    <h1 class="display-6"><i class="fas fa-shield-alt text-primary"></i> อัพเดต Password Hash</h1>
                    <p class="lead">เครื่องมือสำหรับเข้ารหัสรหัสบัตรประชาชนของนักศึกษาในระบบ</p>
                </div>

                <?php if (!$confirm): ?>
                <!-- หน้าแรก: แสดงข้อมูลและขอยืนยัน -->
                <div class="result-box">
                    <h3><i class="fas fa-info-circle text-info"></i> ข้อมูลปัจจุบันในระบบ</h3>
                    
                    <?php
                    try {
                        // ตรวจสอบจำนวนนักศึกษาทั้งหมด
                        $total_query = "SELECT COUNT(*) as total FROM students";
                        $total_stmt = $db->prepare($total_query);
                        $total_stmt->execute();
                        $total_students = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        // ตรวจสอบจำนวนที่มี password_hash แล้ว
                        $hashed_query = "SELECT COUNT(*) as hashed FROM students WHERE password_hash IS NOT NULL AND password_hash != ''";
                        $hashed_stmt = $db->prepare($hashed_query);
                        $hashed_stmt->execute();
                        $hashed_students = $hashed_stmt->fetch(PDO::FETCH_ASSOC)['hashed'];
                        
                        // คำนวณจำนวนที่ต้องอัพเดต
                        $need_update = $total_students - $hashed_students;
                        
                        echo "<div class='row text-center mb-4'>";
                        echo "<div class='col-md-4'>";
                        echo "<div class='card border-primary'>";
                        echo "<div class='card-body'>";
                        echo "<h2 class='text-primary'>{$total_students}</h2>";
                        echo "<p class='card-text'>นักศึกษาทั้งหมด</p>";
                        echo "</div></div></div>";
                        
                        echo "<div class='col-md-4'>";
                        echo "<div class='card border-success'>";
                        echo "<div class='card-body'>";
                        echo "<h2 class='text-success'>{$hashed_students}</h2>";
                        echo "<p class='card-text'>เข้ารหัสแล้ว</p>";
                        echo "</div></div></div>";
                        
                        echo "<div class='col-md-4'>";
                        echo "<div class='card border-warning'>";
                        echo "<div class='card-body'>";
                        echo "<h2 class='text-warning'>{$need_update}</h2>";
                        echo "<p class='card-text'>ต้องอัพเดต</p>";
                        echo "</div></div></div>";
                        echo "</div>";
                        
                        if ($need_update > 0) {
                            echo "<div class='alert alert-warning'>";
                            echo "<h5><i class='fas fa-exclamation-triangle'></i> พบข้อมูลที่ต้องอัพเดต</h5>";
                            echo "<p>มีนักศึกษา <strong>{$need_update}</strong> รายการที่ยังไม่ได้เข้ารหัสรหัสผ่าน</p>";
                            echo "</div>";
                            
                            // แสดงตัวอย่างข้อมูลที่ต้องอัพเดต
                            $sample_query = "SELECT student_id, firstname, lastname, id_card FROM students WHERE password_hash IS NULL OR password_hash = '' LIMIT 5";
                            $sample_stmt = $db->prepare($sample_query);
                            $sample_stmt->execute();
                            $sample_data = $sample_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($sample_data) > 0) {
                                echo "<h6>ตัวอย่างข้อมูลที่จะอัพเดต:</h6>";
                                echo "<div class='table-responsive'>";
                                echo "<table class='table table-sm table-bordered'>";
                                echo "<thead class='table-light'>";
                                echo "<tr><th>รหัสนักศึกษา</th><th>ชื่อ-นามสกุล</th><th>รหัสบัตรประชาชน</th><th>สถานะ</th></tr>";
                                echo "</thead><tbody>";
                                
                                foreach ($sample_data as $row) {
                                    $masked_id = substr($row['id_card'], 0, 4) . 'XXXXXXXXX';
                                    echo "<tr>";
                                    echo "<td>{$row['student_id']}</td>";
                                    echo "<td>{$row['firstname']} {$row['lastname']}</td>";
                                    echo "<td>{$masked_id}</td>";
                                    echo "<td><span class='badge bg-warning'>ยังไม่เข้ารหัส</span></td>";
                                    echo "</tr>";
                                }
                                
                                echo "</tbody></table>";
                                echo "</div>";
                                
                                if (count($sample_data) < $need_update) {
                                    echo "<small class='text-muted'>และอีก " . ($need_update - count($sample_data)) . " รายการ...</small>";
                                }
                            }
                        } else {
                            echo "<div class='alert alert-success'>";
                            echo "<h5><i class='fas fa-check-circle'></i> ข้อมูลทุกรายการเข้ารหัสครบถ้วนแล้ว</h5>";
                            echo "<p>ไม่มีข้อมูลที่ต้องอัพเดต ระบบพร้อมใช้งาน</p>";
                            echo "</div>";
                        }
                        
                    } catch(PDOException $e) {
                        echo "<div class='alert alert-danger'>";
                        echo "<h5><i class='fas fa-exclamation-circle'></i> เกิดข้อผิดพลาด</h5>";
                        echo "<p>ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . htmlspecialchars($e->getMessage()) . "</p>";
                        echo "</div>";
                        $need_update = 0;
                    }
                    ?>
                </div>

                <?php if ($need_update > 0): ?>
                <div class="result-box">
                    <h3><i class="fas fa-cog text-warning"></i> ขั้นตอนการอัพเดต</h3>
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> สิ่งที่จะเกิดขึ้น:</h6>
                        <ol>
                            <li>ระบบจะอ่านรหัสบัตรประชาชนของนักศึกษาที่ยังไม่เข้ารหัส</li>
                            <li>เข้ารหัสด้วย PHP <code>password_hash()</code> algorithm</li>
                            <li>บันทึก hash ลงในคอลัมน์ <code>password_hash</code></li>
                            <li>รหัสบัตรประชาชนต้นฉบับจะยังคงอยู่ในคอลัมน์ <code>id_card</code> (เพื่อความปลอดภัยในระยะเปลี่ยนผ่าน)</li>
                        </ol>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle"></i> ข้อควรระวัง:</h6>
                        <ul class="mb-0">
                            <li><strong>สำรองข้อมูลก่อนดำเนินการ</strong></li>
                            <li>กระบวนการนี้ไม่สามารถย้อนกลับได้</li>
                            <li>หลังอัพเดตแล้วนักศึกษาสามารถล็อกอินได้ตามปกติ</li>
                            <li>ระบบจะรองรับทั้งข้อมูลเก่าและใหม่ในระหว่างการเปลี่ยนผ่าน</li>
                        </ul>
                    </div>
                    
                    <div class="text-center">
                        <a href="?confirm=yes" class="btn btn-warning btn-lg me-3">
                            <i class="fas fa-play"></i> เริ่มอัพเดต Password Hash
                        </a>
                        <a href="?confirm=yes&force=yes" class="btn btn-danger">
                            <i class="fas fa-sync"></i> อัพเดตทั้งหมด (รวมที่เข้ารหัสแล้ว)
                        </a>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> เวลาที่คาดการณ์: ประมาณ <?php echo ceil($need_update / 100); ?> นาที
                        </small>
                    </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <!-- หน้าที่สอง: ดำเนินการอัพเดต -->
                <div class="result-box">
                    <h3><i class="fas fa-cogs text-primary"></i> กำลังดำเนินการอัพเดต...</h3>
                    
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" 
                             role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            0%
                        </div>
                    </div>
                    
                    <div id="logContainer" style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; background: #f8f9fa;">
                        <div class="log-entry log-info">
                            <i class="fas fa-play"></i> เริ่มต้นกระบวนการอัพเดต...
                        </div>
                    </div>
                </div>

                <?php
                // ดำเนินการอัพเดต
                try {
                    // ล้าง output buffer และเปิด output buffering
                    if (ob_get_level()) {
                        ob_end_clean();
                    }
                    ob_start();
                    
                    // เริ่ม output ทันที
                    echo str_repeat(' ', 4096); // ส่ง whitespace เพื่อให้ browser เริ่ม render
                    flush();
                    
                    // ดึงข้อมูลนักศึกษาที่ต้องอัพเดต
                    if ($force_update) {
                        $query = "SELECT id, student_id, id_card FROM students";
                        $log_message = "โหมด Force: กำลังอัพเดตข้อมูลทั้งหมด...";
                    } else {
                        $query = "SELECT id, student_id, id_card FROM students WHERE password_hash IS NULL OR password_hash = ''";
                        $log_message = "โหมดปกติ: กำลังอัพเดตเฉพาะข้อมูลที่ยังไม่เข้ารหัส...";
                    }
                    
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $total_count = count($students);
                    $updated_count = 0;
                    $error_count = 0;
                    $skipped_count = 0;
                    
                    echo "<script>addLog('info', '{$log_message}');</script>";
                    echo "<script>addLog('info', 'พบข้อมูลที่ต้องประมวลผล: {$total_count} รายการ');</script>";
                    flush();
                    
                    if ($total_count > 0) {
                        // เริ่ม transaction
                        $db->beginTransaction();
                        
                        foreach ($students as $index => $student) {
                            try {
                                // คำนวณ progress
                                $progress = ($index + 1) / $total_count * 100;
                                $progress_rounded = round($progress, 1);
                                
                                // ตรวจสอบว่ามี password_hash อยู่แล้วหรือไม่ (สำหรับโหมด force)
                                if ($force_update) {
                                    $check_query = "SELECT password_hash FROM students WHERE id = :id";
                                    $check_stmt = $db->prepare($check_query);
                                    $check_stmt->bindParam(':id', $student['id']);
                                    $check_stmt->execute();
                                    $existing_hash = $check_stmt->fetch(PDO::FETCH_ASSOC)['password_hash'];
                                    
                                    if (!empty($existing_hash)) {
                                        echo "<script>addLog('info', 'ข้าม: {$student['student_id']} (มี hash อยู่แล้ว)');</script>";
                                        $skipped_count++;
                                        echo "<script>updateProgress({$progress_rounded});</script>";
                                        flush();
                                        continue;
                                    }
                                }
                                
                                // เข้ารหัสรหัสบัตรประชาชน
                                $password_hash = password_hash($student['id_card'], PASSWORD_DEFAULT);
                                
                                // อัพเดตฐานข้อมูล
                                $update_query = "UPDATE students SET password_hash = :password_hash WHERE id = :id";
                                $update_stmt = $db->prepare($update_query);
                                $update_stmt->bindParam(':password_hash', $password_hash);
                                $update_stmt->bindParam(':id', $student['id']);
                                
                                if ($update_stmt->execute()) {
                                    echo "<script>addLog('success', 'สำเร็จ: {$student['student_id']} - Hash ถูกสร้างและบันทึกแล้ว');</script>";
                                    $updated_count++;
                                } else {
                                    throw new Exception("ไม่สามารถอัพเดตฐานข้อมูลได้");
                                }
                                
                                // อัพเดต progress bar
                                echo "<script>updateProgress({$progress_rounded});</script>";
                                flush();
                                
                                // หน่วงเวลาเล็กน้อยเพื่อให้เห็น progress (ถอดออกได้ในการใช้งานจริง)
                                usleep(50000); // 0.05 วินาที
                                
                            } catch(Exception $e) {
                                echo "<script>addLog('error', 'ล้มเหลว: {$student['student_id']} - " . addslashes($e->getMessage()) . "');</script>";
                                $error_count++;
                                flush();
                            }
                        }
                        
                        // Commit transaction
                        $db->commit();
                        
                        echo "<script>updateProgress(100);</script>";
                        echo "<script>addLog('success', '--- เสร็จสิ้นการอัพเดต ---');</script>";
                        echo "<script>addLog('info', 'สรุปผลการดำเนินการ:');</script>";
                        echo "<script>addLog('success', '✓ อัพเดตสำเร็จ: {$updated_count} รายการ');</script>";
                        
                        if ($skipped_count > 0) {
                            echo "<script>addLog('info', '⊘ ข้ามรายการ: {$skipped_count} รายการ');</script>";
                        }
                        
                        if ($error_count > 0) {
                            echo "<script>addLog('error', '✗ เกิดข้อผิดพลาด: {$error_count} รายการ');</script>";
                        }
                        
                        echo "<script>addLog('info', 'รวมทั้งหมด: {$total_count} รายการ');</script>";
                        flush();
                        
                    } else {
                        echo "<script>addLog('info', 'ไม่มีข้อมูลที่ต้องอัพเดต');</script>";
                        echo "<script>updateProgress(100);</script>";
                        flush();
                    }
                    
                    // แสดงปุ่มดำเนินการต่อ
                    echo "<script>showCompletionButtons({$updated_count}, {$error_count});</script>";
                    
                } catch(PDOException $e) {
                    // Rollback transaction ในกรณีที่เกิดข้อผิดพลาด
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    
                    echo "<script>addLog('error', 'เกิดข้อผิดพลาดร้ายแรง: " . addslashes($e->getMessage()) . "');</script>";
                    echo "<script>addLog('error', 'การอัพเดตถูกยกเลิก (Rollback)');</script>";
                    flush();
                }
                
                ob_end_flush();
                ?>

                <?php endif; ?>

                <div class="result-box text-center">
                    <p class="mb-0">
                        <a href="../index.php" class="btn btn-outline-primary">
                            <i class="fas fa-home"></i> กลับไปยังระบบหลัก
                        </a>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i> รีเฟรชหน้านี้
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function addLog(type, message) {
        const logContainer = document.getElementById('logContainer');
        if (logContainer) {
            const logEntry = document.createElement('div');
            logEntry.className = `log-entry log-${type}`;
            
            let icon = '';
            switch(type) {
                case 'success':
                    icon = '<i class="fas fa-check"></i>';
                    break;
                case 'error':
                    icon = '<i class="fas fa-times"></i>';
                    break;
                case 'info':
                    icon = '<i class="fas fa-info"></i>';
                    break;
            }
            
            logEntry.innerHTML = icon + ' ' + message;
            logContainer.appendChild(logEntry);
            logContainer.scrollTop = logContainer.scrollHeight;
        }
    }
    
    function updateProgress(percentage) {
        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            progressBar.style.width = percentage + '%';
            progressBar.setAttribute('aria-valuenow', percentage);
            progressBar.textContent = percentage + '%';
            
            // เปลี่ยนสีตาม progress
            if (percentage >= 100) {
                progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
                progressBar.classList.add('bg-success');
            }
        }
    }
    
    function showCompletionButtons(successCount, errorCount) {
        const logContainer = document.getElementById('logContainer');
        if (logContainer) {
            const buttonContainer = document.createElement('div');
            buttonContainer.className = 'text-center mt-3';
            buttonContainer.innerHTML = `
                <div class="alert alert-${errorCount > 0 ? 'warning' : 'success'} mt-3">
                    <h6><i class="fas fa-${errorCount > 0 ? 'exclamation-triangle' : 'check-circle'}"></i> การอัพเดตเสร็จสิ้น</h6>
                    <p class="mb-0">
                        อัพเดตสำเร็จ: <strong>${successCount}</strong> รายการ
                        ${errorCount > 0 ? ` | เกิดข้อผิดพลาด: <strong>${errorCount}</strong> รายการ` : ''}
                    </p>
                </div>
                <a href="../index.php?page=admin_users" class="btn btn-primary me-2">
                    <i class="fas fa-users"></i> ไปยังรายการนักศึกษา
                </a>
                <a href="?" class="btn btn-outline-secondary">
                    <i class="fas fa-redo"></i> รันอีกครั้ง
                </a>
            `;
            logContainer.parentNode.appendChild(buttonContainer);
        }
    }
    
    // Auto-scroll log container
    document.addEventListener('DOMContentLoaded', function() {
        const logContainer = document.getElementById('logContainer');
        if (logContainer) {
            // เพิ่ม observer เพื่อ auto-scroll เมื่อมี log ใหม่
            const observer = new MutationObserver(function(mutations) {
                logContainer.scrollTop = logContainer.scrollHeight;
            });
            
            observer.observe(logContainer, {
                childList: true,
                subtree: true
            });
        }
    });
    
    // ป้องกันการปิดหน้าขณะอัพเดต
    <?php if ($confirm): ?>
    let updateInProgress = true;
    
    window.addEventListener('beforeunload', function(e) {
        if (updateInProgress) {
            e.preventDefault();
            e.returnValue = 'การอัพเดตกำลังดำเนินการอยู่ คุณแน่ใจหรือไม่ที่จะออกจากหน้านี้?';
        }
    });
    
    // ปิดการป้องกันเมื่ออัพเดตเสร็จ
    setTimeout(function() {
        updateInProgress = false;
    }, 10000); // ปิดหลังจาก 10 วินาที (ปรับตามความเหมาะสม)
    <?php endif; ?>
    </script>
</body>
</html>