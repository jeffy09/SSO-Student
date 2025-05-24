<?php
// ไฟล์ migrate_passwords.php - รันครั้งเดียวเพื่อเข้ารหัสข้อมูลเก่า

// เชื่อมต่อฐานข้อมูล
include_once 'config/db.php';

// ฟังก์ชันเข้ารหัส
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// ฟังก์ชัน Migration
function migrateStudentPasswords($db) {
    try {
        echo "เริ่มต้นการ Migration รหัสผ่าน...\n";
        
        // ตรวจสอบว่าคอลัมน์ password_hash มีอยู่หรือไม่
        $check_column = $db->query("SHOW COLUMNS FROM students LIKE 'password_hash'");
        if ($check_column->rowCount() == 0) {
            echo "กำลังเพิ่มคอลัมน์ password_hash...\n";
            $db->exec("ALTER TABLE students ADD COLUMN password_hash VARCHAR(255) AFTER id_card");
            echo "เพิ่มคอลัมน์สำเร็จ\n";
        }
        
        // ดึงข้อมูลนักศึกษาที่ยังไม่ได้เข้ารหัส
        $query = "SELECT id, student_id, id_card FROM students 
                 WHERE (password_hash IS NULL OR password_hash = '') 
                 AND id_card IS NOT NULL AND id_card != ''";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $total_records = $stmt->rowCount();
        $updated_count = 0;
        $error_count = 0;
        
        echo "พบข้อมูลที่ต้องอัพเดต: $total_records รายการ\n";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            try {
                // เข้ารหัส ID Card
                $hashed_password = hashPassword($row['id_card']);
                
                // อัพเดตลงฐานข้อมูล
                $update_query = "UPDATE students SET password_hash = :password_hash WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':password_hash', $hashed_password);
                $update_stmt->bindParam(':id', $row['id']);
                
                if ($update_stmt->execute()) {
                    $updated_count++;
                    echo "อัพเดตสำเร็จ: {$row['student_id']}\n";
                } else {
                    $error_count++;
                    echo "ข้อผิดพลาด: {$row['student_id']} - " . implode(', ', $update_stmt->errorInfo()) . "\n";
                }
                
            } catch (Exception $e) {
                $error_count++;
                echo "ข้อผิดพลาด: {$row['student_id']} - " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n=== สรุปผลการ Migration ===\n";
        echo "ทั้งหมด: $total_records รายการ\n";
        echo "อัพเดตสำเร็จ: $updated_count รายการ\n";
        echo "ข้อผิดพลาด: $error_count รายการ\n";
        
        return [
            'total' => $total_records,
            'success' => $updated_count,
            'errors' => $error_count
        ];
        
    } catch (Exception $e) {
        echo "เกิดข้อผิดพลาดร้ายแรง: " . $e->getMessage() . "\n";
        return false;
    }
}

// รัน Migration (ถ้าเรียกไฟล์นี้โดยตรง)
if (basename($_SERVER['PHP_SELF']) == 'migrate_passwords.php') {
    echo "=== การ Migration รหัสผ่านนักศึกษา ===\n";
    echo "วันที่: " . date('Y-m-d H:i:s') . "\n\n";
    
    $result = migrateStudentPasswords($db);
    
    if ($result) {
        echo "\nการ Migration เสร็จสิ้น!\n";
        
        // สร้างไฟล์ log
        $log_content = "Password Migration Report\n";
        $log_content .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $log_content .= "Total Records: " . $result['total'] . "\n";
        $log_content .= "Successfully Updated: " . $result['success'] . "\n";
        $log_content .= "Errors: " . $result['errors'] . "\n";
        
        file_put_contents('logs/password_migration_' . date('Y-m-d_H-i-s') . '.log', $log_content);
        echo "บันทึก log ไว้ในโฟลเดอร์ logs/\n";
        
    } else {
        echo "\nการ Migration ล้มเหลว!\n";
    }
}

// ฟังก์ชันสำหรับทดสอบหลัง Migration
function testPasswordMigration($db, $student_id, $id_card) {
    $query = "SELECT password_hash FROM students WHERE student_id = :student_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify($id_card, $row['password_hash'])) {
        return "✅ ทดสอบสำเร็จ: $student_id";
    } else {
        return "❌ ทดสอบล้มเหลว: $student_id";
    }
}

// ตัวอย่างการทดสอบ
if (isset($_GET['test']) && $_GET['test'] == '1') {
    echo "\n=== การทดสอบรหัสผ่าน ===\n";
    
    // ทดสอบกับข้อมูลตัวอย่าง
    $test_cases = [
        ['student_id' => '65001', 'id_card' => '1234567890123'],
        ['student_id' => '65002', 'id_card' => '1234567890124'],
        // เพิ่มข้อมูลทดสอบตามต้องการ
    ];
    
    foreach ($test_cases as $test) {
        echo testPasswordMigration($db, $test['student_id'], $test['id_card']) . "\n";
    }
}
?>