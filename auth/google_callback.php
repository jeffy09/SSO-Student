<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

// เปิดการแสดงข้อผิดพลาดเฉพาะในส่วนนี้ (เอาออกเมื่อใช้งานจริง)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// รวมไฟล์ configuration ของ Google
include_once 'config/google_config.php';

// ฟังก์ชันสำหรับบันทึก log
function write_log($message)
{
    $log_dir = 'logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $log_file = $log_dir . '/google_auth_' . date('Y-m-d') . '.log';
    $log_message = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";

    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// ตรวจสอบว่ามีการส่ง code มาจาก Google OAuth2 หรือไม่
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $user_type = isset($_SESSION['google_auth_type']) ? $_SESSION['google_auth_type'] : '';

    write_log("Received code from Google, user_type: $user_type");

    try {
        // สร้าง instance ของ GoogleConfig
        $google_config = new GoogleConfig();

        // แลกเปลี่ยน code เพื่อรับ access token
        $token_data = $google_config->getAccessToken($code);

        if (!$token_data || !isset($token_data['access_token'])) {
            write_log("Failed to get access token: " . json_encode($token_data));
            throw new Exception("ไม่สามารถรับ access token ได้");
        }

        write_log("Received access token successfully");

        // ใช้ access token เพื่อรับข้อมูลผู้ใช้
        $user_info = $google_config->getUserInfo($token_data['access_token']);

        if (!$user_info || !isset($user_info['email'])) {
            write_log("Failed to get user info: " . json_encode($user_info));
            throw new Exception("ไม่สามารถรับข้อมูลผู้ใช้ได้");
        }

        $email = $user_info['email'];
        $google_id = $user_info['sub'];

        write_log("User info received: email=$email, google_id=$google_id");

        // ตรวจสอบประเภทของผู้ใช้
        if ($user_type === 'student') {
            // ตรวจสอบว่ามีอีเมลในฐานข้อมูลนักศึกษาหรือไม่
            $query = "SELECT * FROM students WHERE email = :email LIMIT 0,1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // พบข้อมูลนักศึกษา
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                // อัพเดต google_id ถ้ายังไม่มี
                if (empty($row['google_id'])) {
                    $update_query = "UPDATE students SET google_id = :google_id WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':google_id', $google_id);
                    $update_stmt->bindParam(':id', $row['id']);
                    $update_stmt->execute();

                    write_log("Updated student google_id: student_id={$row['student_id']}");
                }

                // สร้าง session สำหรับ student
                $_SESSION['student_id'] = $row['id'];
                $_SESSION['student_code'] = $row['student_id'];
                $_SESSION['student_name'] = $row['firstname'] . ' ' . $row['lastname'];
                $_SESSION['student_email'] = $row['email'];

                // เก็บ access token และ refresh token สำหรับใช้กับ Gmail API
                $_SESSION['google_access_token'] = $token_data['access_token'];
                if (isset($token_data['refresh_token'])) {
                    $_SESSION['google_refresh_token'] = $token_data['refresh_token'];
                    write_log("Stored refresh token for student: {$row['student_id']}");
                }

                // เก็บข้อมูล token ลงฐานข้อมูลเพื่อใช้ภายหลัง (optional)
                try {
                    // ตรวจสอบว่าตาราง students มีคอลัมน์ token หรือไม่
                    $check_columns = $db->query("SHOW COLUMNS FROM students LIKE 'access_token'");
                    if ($check_columns->rowCount() > 0) {
                        $token_query = "UPDATE students SET 
                                        access_token = :access_token, 
                                        refresh_token = :refresh_token, 
                                        token_expires_at = :expires_at 
                                        WHERE id = :id";
                        $token_stmt = $db->prepare($token_query);

                        // คำนวณเวลาหมดอายุ
                        $expires_at = null;
                        if (isset($token_data['expires_in'])) {
                            $expires_at = date('Y-m-d H:i:s', time() + $token_data['expires_in']);
                        }

                        $token_stmt->bindValue(':access_token', $token_data['access_token']);
                        $token_stmt->bindValue(':refresh_token', $token_data['refresh_token'] ?? null);
                        $token_stmt->bindValue(':expires_at', $expires_at);
                        $token_stmt->bindValue(':id', $row['id']);

                        $result = $token_stmt->execute();

                        if ($result) {
                            write_log("Stored tokens in database for student: {$row['student_id']}");
                        } else {
                            write_log("Failed to store tokens: " . print_r($token_stmt->errorInfo(), true));
                        }
                    } else {
                        write_log("Token columns not found in students table, skipping database storage");
                    }
                } catch (Exception $e) {
                    // ไม่ให้ error ของการเก็บ token ทำให้ login ล้มเหลว
                    write_log("Warning: Could not store tokens in database: " . $e->getMessage());
                }

                write_log("Student login successful: id={$row['id']}, name={$row['firstname']} {$row['lastname']}");

                // ตรวจสอบว่าเป็นการเชื่อมต่อจากหน้าโปรไฟล์หรือไม่
                if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
                    $_SESSION['google_connect_success'] = "เชื่อมต่อบัญชี Google และ Gmail สำเร็จ";
                    unset($_SESSION['connecting_from_profile']);
                    header("Location: index.php?page=student_profile&google_connect=success");
                } else {
                    // Redirect ไปยังหน้า profile
                    header("Location: index.php?page=student_profile");
                }
                exit;
            } else {
                // ไม่พบอีเมลในระบบ
                write_log("Student email not found: $email");

                // ตรวจสอบว่าเป็นการเชื่อมต่อจากหน้าโปรไฟล์หรือไม่
                if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
                    $_SESSION['google_connect_error'] = "ไม่พบอีเมลนี้ในระบบนักศึกษา กรุณาตรวจสอบอีเมลในโปรไฟล์ให้ตรงกับบัญชี Google";
                    unset($_SESSION['connecting_from_profile']);
                    header("Location: index.php?page=student_profile&google_connect=error");
                } else {
                    $_SESSION['auth_error'] = "ไม่พบอีเมลนี้ในระบบนักศึกษา กรุณาลงชื่อเข้าใช้ด้วยรหัสนักศึกษาและรหัสบัตรประชาชนก่อน";
                    header("Location: index.php?page=student_login");
                }
                exit;
            }
        } elseif ($user_type === 'admin') {
            // ตรวจสอบว่าอีเมลนี้มีในฐานข้อมูลผู้ดูแลระบบหรือไม่
            $query = "SELECT * FROM admins WHERE email = :email LIMIT 0,1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            write_log("Admin email check: $email, rows found: " . $stmt->rowCount());

            if ($stmt->rowCount() > 0) {
                // พบข้อมูลผู้ดูแลระบบ
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                write_log("Admin found: id={$row['id']}, name={$row['name']}");

                // อัพเดต google_id ถ้ายังไม่มี
                if (empty($row['google_id'])) {
                    $update_query = "UPDATE admins SET google_id = :google_id WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':google_id', $google_id);
                    $update_stmt->bindParam(':id', $row['id']);
                    $update_stmt->execute();

                    write_log("Updated admin google_id: id={$row['id']}");
                }

                // สร้าง session สำหรับ admin
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin_name'] = $row['name'];
                $_SESSION['admin_email'] = $row['email'];

                // เก็บ access token และ refresh token สำหรับใช้กับ Gmail API
                $_SESSION['google_access_token'] = $token_data['access_token'];
                if (isset($token_data['refresh_token'])) {
                    $_SESSION['google_refresh_token'] = $token_data['refresh_token'];
                    write_log("Stored refresh token for admin: {$row['id']}");
                }

                // เก็บข้อมูล token ลงฐานข้อมูลเพื่อใช้ภายหลัง (optional)
                try {
                    // ตรวจสอบว่าตาราง admins มีคอลัมน์ token หรือไม่
                    $check_columns = $db->query("SHOW COLUMNS FROM admins LIKE 'access_token'");
                    if ($check_columns->rowCount() > 0) {
                        $token_query = "UPDATE admins SET 
                                        access_token = :access_token, 
                                        refresh_token = :refresh_token, 
                                        token_expires_at = :expires_at 
                                        WHERE id = :id";
                        $token_stmt = $db->prepare($token_query);

                        // คำนวณเวลาหมดอายุ
                        $expires_at = null;
                        if (isset($token_data['expires_in'])) {
                            $expires_at = date('Y-m-d H:i:s', time() + $token_data['expires_in']);
                        }

                        $token_stmt->bindValue(':access_token', $token_data['access_token']);
                        $token_stmt->bindValue(':refresh_token', $token_data['refresh_token'] ?? null);
                        $token_stmt->bindValue(':expires_at', $expires_at);
                        $token_stmt->bindValue(':id', $row['id']);

                        $result = $token_stmt->execute();

                        if ($result) {
                            write_log("Stored tokens in database for admin: {$row['id']}");
                        } else {
                            write_log("Failed to store tokens: " . print_r($token_stmt->errorInfo(), true));
                        }
                    } else {
                        write_log("Token columns not found in admins table, skipping database storage");
                    }
                } catch (Exception $e) {
                    // ไม่ให้ error ของการเก็บ token ทำให้ login ล้มเหลว
                    write_log("Warning: Could not store tokens in database: " . $e->getMessage());
                }

                // อัพเดตเวลาเข้าสู่ระบบล่าสุด
                try {
                    $update_login = "UPDATE admins SET last_login = NOW() WHERE id = :id";
                    $update_login_stmt = $db->prepare($update_login);
                    $update_login_stmt->bindParam(':id', $row['id']);
                    $update_login_stmt->execute();
                } catch (Exception $e) {
                    write_log("Warning: Could not update last_login: " . $e->getMessage());
                }

                write_log("Admin login successful, checking if connecting from profile");

                // ตรวจสอบว่าเป็นการเชื่อมต่อจากหน้าโปรไฟล์หรือไม่
                if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
                    $_SESSION['google_connect_success'] = "เชื่อมต่อบัญชี Google และ Gmail สำเร็จ";
                    unset($_SESSION['connecting_from_profile']);
                    header("Location: index.php?page=admin_profile&google_connect=success");
                } else {
                    // Redirect ไปยังหน้า dashboard
                    header("Location: index.php?page=admin_dashboard");
                }
                exit;
            } else {
                // ไม่พบอีเมลในระบบ
                write_log("Admin email not found: $email");

                // เพิ่มการทดสอบเพื่อเช็คว่ามีข้อมูลในตาราง admins หรือไม่
                $test_query = "SELECT COUNT(*) as count FROM admins";
                $test_stmt = $db->prepare($test_query);
                $test_stmt->execute();
                $admin_count = $test_stmt->fetch(PDO::FETCH_ASSOC)['count'];

                write_log("Total admin count in database: $admin_count");

                // ตรวจสอบว่าเป็นการเชื่อมต่อจากหน้าโปรไฟล์หรือไม่
                if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
                    $_SESSION['google_connect_error'] = "ไม่พบอีเมลนี้ในระบบผู้ดูแล กรุณาตรวจสอบอีเมลในโปรไฟล์ให้ตรงกับบัญชี Google";
                    unset($_SESSION['connecting_from_profile']);
                    header("Location: index.php?page=admin_profile&google_connect=error");
                } else {
                    $_SESSION['auth_error'] = "ไม่พบอีเมลนี้ ($email) ในระบบผู้ดูแล กรุณาลงชื่อเข้าใช้ด้วยชื่อผู้ใช้และรหัสผ่านก่อน หรือตรวจสอบข้อมูลในฐานข้อมูล";
                    header("Location: index.php?page=admin_login");
                }
                exit;
            }
        } else {
            write_log("Invalid user type: $user_type");
            throw new Exception("ประเภทผู้ใช้ไม่ถูกต้อง");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        write_log("Error: $error_message");

        // ตรวจสอบว่าเป็นการเชื่อมต่อจากหน้าโปรไฟล์หรือไม่
        if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
            $_SESSION['google_connect_error'] = "เกิดข้อผิดพลาด: " . $error_message;
            unset($_SESSION['connecting_from_profile']);

            // กลับไปยังหน้าโปรไฟล์ตามประเภทผู้ใช้
            if ($user_type === 'student') {
                header("Location: index.php?page=student_profile&google_connect=error");
            } elseif ($user_type === 'admin') {
                header("Location: index.php?page=admin_profile&google_connect=error");
            } else {
                header("Location: index.php");
            }
        } else {
            $_SESSION['auth_error'] = "เกิดข้อผิดพลาด: " . $error_message;
            header("Location: index.php");
        }
        exit;
    }
} else {
    // ไม่มีรหัส authorization code จาก Google
    write_log("No authorization code received from Google");

    // ตรวจสอบว่าเป็นการเชื่อมต่อจากหน้าโปรไฟล์หรือไม่
    if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
        $_SESSION['google_connect_error'] = "ไม่ได้รับรหัสยืนยันจาก Google";
        unset($_SESSION['connecting_from_profile']);

        // กลับไปยังหน้าโปรไฟล์ตามประเภทผู้ใช้
        $user_type = isset($_SESSION['google_auth_type']) ? $_SESSION['google_auth_type'] : '';
        if ($user_type === 'student') {
            header("Location: index.php?page=student_profile&google_connect=error");
        } elseif ($user_type === 'admin') {
            header("Location: index.php?page=admin_profile&google_connect=error");
        } else {
            header("Location: index.php");
        }
    } else {
        header("Location: index.php");
    }
    exit;
}
?>