<?php
// กำหนดค่าคงที่เพื่อบอกว่าการเข้าถึงไฟล์ถูกต้อง
define('SECURE_ACCESS', true);

// เปิดการแสดงข้อผิดพลาด (เฉพาะในหน้านี้)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// รวมไฟล์การเชื่อมต่อฐานข้อมูล
include_once '../config/db.php';

// รวมไฟล์ configuration ของ Google
include_once '../config/google_config.php';

// สร้าง instance ของ GoogleConfig
$google_config = new GoogleConfig();

// รับค่า Protocol ที่ใช้
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Configuration Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
        }
        h1 {
            color: #333;
        }
        h2 {
            color: #555;
            margin-top: 20px;
        }
        .card {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        code {
            background: #f5f5f5;
            padding: 2px 5px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <h1>Google Configuration Check</h1>
    <p>หน้านี้แสดงการตั้งค่า Google OAuth ปัจจุบันของคุณเพื่อการตรวจสอบ</p>
    
    <div class="warning">
        <strong>คำเตือน!</strong> หน้านี้แสดงข้อมูลการตั้งค่าที่ละเอียดอ่อน ควรปิดหรือป้องกันการเข้าถึงหน้านี้ในระบบที่ใช้งานจริง
    </div>
    
    <div class="card">
        <h2>การตั้งค่า Client</h2>
        <p><strong>Client ID:</strong> 
            <code><?php echo htmlspecialchars($google_config->getClientId()); ?></code>
        </p>
        
        <p><strong>Redirect URI:</strong>
            <code><?php echo htmlspecialchars($google_config->getRedirectUri()); ?></code>
        </p>
    </div>
    
    <div class="card">
        <h2>ข้อมูลเซิร์ฟเวอร์</h2>
        <p><strong>Protocol:</strong> <?php echo htmlspecialchars($protocol); ?></p>
        <p><strong>HTTP_HOST:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?></p>
        <p><strong>PHP_SELF:</strong> <?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?></p>
        <p><strong>SCRIPT_NAME:</strong> <?php echo htmlspecialchars($_SERVER['SCRIPT_NAME']); ?></p>
        <p><strong>REQUEST_URI:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?></p>
        <p><strong>dirname(PHP_SELF):</strong> <?php echo htmlspecialchars(dirname($_SERVER['PHP_SELF'])); ?></p>
    </div>
    
    <div class="card">
        <h2>คำแนะนำการตั้งค่า Google OAuth</h2>
        <ol>
            <li>ไปที่ <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
            <li>เลือกโปรเจคของคุณ</li>
            <li>ไปที่ 'APIs & Services' > 'Credentials'</li>
            <li>คลิกที่ OAuth client ID ที่คุณใช้</li>
            <li>ในส่วน "Authorized redirect URIs" ให้เพิ่ม:
                <br>
                <code><?php echo htmlspecialchars($google_config->getRedirectUri()); ?></code>
            </li>
            <li>คลิก "SAVE" เพื่อบันทึกการเปลี่ยนแปลง</li>
        </ol>
    </div>
    
    <p><a href="../index.php">กลับไปยังหน้าหลัก</a></p>
</body>
</html>