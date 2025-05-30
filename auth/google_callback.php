<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once 'config/google_config.php';

function write_log_callback($message)
{ // Changed function name to avoid conflict
    $log_dir = 'logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/google_auth_' . date('Y-m-d') . '.log';
    $log_message = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $user_type = isset($_SESSION['google_auth_type']) ? $_SESSION['google_auth_type'] : '';

    write_log_callback("Received code from Google, user_type: $user_type");

    try {
        $google_config = new GoogleConfig();
        $token_data = $google_config->getAccessToken($code);

        if (!$token_data || !isset($token_data['access_token'])) {
            write_log_callback("Failed to get access token: " . json_encode($token_data));
            throw new Exception("ไม่สามารถรับ access token ได้");
        }
        write_log_callback("Received access token successfully");

        $user_info = $google_config->getUserInfo($token_data['access_token']);
        if (!$user_info || !isset($user_info['email'])) {
            write_log_callback("Failed to get user info: " . json_encode($user_info));
            throw new Exception("ไม่สามารถรับข้อมูลผู้ใช้ได้");
        }

        $email = $user_info['email'];
        $google_id = $user_info['sub'];
        write_log_callback("User info received: email=$email, google_id=$google_id, user_type: $user_type");

        if ($user_type === 'student') {
            $query = "SELECT * FROM students WHERE email = :email LIMIT 0,1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (empty($row['google_id'])) {
                    $update_query = "UPDATE students SET google_id = :google_id WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':google_id', $google_id);
                    $update_stmt->bindParam(':id', $row['id']);
                    $update_stmt->execute();
                    write_log_callback("Updated student google_id: student_id={$row['student_id']}");
                }

                $_SESSION['student_id'] = $row['id'];
                $_SESSION['student_code'] = $row['student_id'];
                $_SESSION['student_name'] = $row['firstname'] . ' ' . $row['lastname'];
                $_SESSION['student_email'] = $row['email'];
                $_SESSION['google_access_token'] = $token_data['access_token'];
                if (isset($token_data['refresh_token'])) {
                    $_SESSION['google_refresh_token'] = $token_data['refresh_token'];
                    write_log_callback("Stored refresh token for student: {$row['student_id']}");
                }

                // Store tokens in DB
                try {
                    $check_columns = $db->query("SHOW COLUMNS FROM students LIKE 'access_token'");
                    if ($check_columns->rowCount() > 0) {
                        $token_query = "UPDATE students SET access_token = :access_token, refresh_token = :refresh_token, token_expires_at = :expires_at WHERE id = :id";
                        $token_stmt = $db->prepare($token_query);
                        $expires_at = isset($token_data['expires_in']) ? date('Y-m-d H:i:s', time() + $token_data['expires_in']) : null;
                        $token_stmt->bindValue(':access_token', $token_data['access_token']);
                        $token_stmt->bindValue(':refresh_token', $token_data['refresh_token'] ?? null);
                        $token_stmt->bindValue(':expires_at', $expires_at);
                        $token_stmt->bindValue(':id', $row['id']);
                        if ($token_stmt->execute()) {
                            write_log_callback("Stored tokens in database for student: {$row['student_id']}");
                        } else {
                            write_log_callback("Failed to store tokens for student: " . print_r($token_stmt->errorInfo(), true));
                        }
                    }
                } catch (Exception $e) {
                    write_log_callback("Warning: Could not store tokens in database for student: " . $e->getMessage());
                }

                write_log_callback("Student login successful: id={$row['id']}, name={$row['firstname']} {$row['lastname']}");

                if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
                    $_SESSION['google_connect_success'] = "เชื่อมต่อบัญชี Google และ Gmail สำเร็จ";
                    unset($_SESSION['connecting_from_profile']);
                    header("Location: index.php?page=student_profile&google_connect=success");
                } else {
                    // Redirect ไปยังหน้า dashboard ของนักศึกษา
                    header("Location: index.php?page=student_dashboard"); // <--- แก้ไขตรงนี้
                }
                exit;
            } else {
                write_log_callback("Student email not found: $email");
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
            // Admin logic remains the same, redirecting to admin_dashboard or admin_profile
            $query = "SELECT * FROM admins WHERE email = :email LIMIT 0,1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            write_log_callback("Admin email check: $email, rows found: " . $stmt->rowCount());

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                write_log_callback("Admin found: id={$row['id']}, name={$row['name']}");
                if (empty($row['google_id'])) {
                    $update_query = "UPDATE admins SET google_id = :google_id WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':google_id', $google_id);
                    $update_stmt->bindParam(':id', $row['id']);
                    $update_stmt->execute();
                    write_log_callback("Updated admin google_id: id={$row['id']}");
                }
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin_name'] = $row['name'];
                $_SESSION['admin_email'] = $row['email'];
                $_SESSION['google_access_token'] = $token_data['access_token'];
                if (isset($token_data['refresh_token'])) {
                    $_SESSION['google_refresh_token'] = $token_data['refresh_token'];
                    write_log_callback("Stored refresh token for admin: {$row['id']}");
                }
                // Store tokens in DB for admin
                try {
                    $check_columns_admin = $db->query("SHOW COLUMNS FROM admins LIKE 'access_token'");
                    if ($check_columns_admin->rowCount() > 0) {
                        $token_query_admin = "UPDATE admins SET access_token = :access_token, refresh_token = :refresh_token, token_expires_at = :expires_at WHERE id = :id";
                        $token_stmt_admin = $db->prepare($token_query_admin);
                        $expires_at_admin = isset($token_data['expires_in']) ? date('Y-m-d H:i:s', time() + $token_data['expires_in']) : null;
                        $token_stmt_admin->bindValue(':access_token', $token_data['access_token']);
                        $token_stmt_admin->bindValue(':refresh_token', $token_data['refresh_token'] ?? null);
                        $token_stmt_admin->bindValue(':expires_at', $expires_at_admin);
                        $token_stmt_admin->bindValue(':id', $row['id']);
                        if ($token_stmt_admin->execute()) {
                            write_log_callback("Stored tokens in database for admin: {$row['id']}");
                        } else {
                            write_log_callback("Failed to store tokens for admin: " . print_r($token_stmt_admin->errorInfo(), true));
                        }
                    }
                } catch (Exception $e) {
                    write_log_callback("Warning: Could not store tokens in database for admin: " . $e->getMessage());
                }

                try {
                    $update_login = "UPDATE admins SET last_login = NOW() WHERE id = :id";
                    $update_login_stmt = $db->prepare($update_login);
                    $update_login_stmt->bindParam(':id', $row['id']);
                    $update_login_stmt->execute();
                } catch (Exception $e) {
                    write_log_callback("Warning: Could not update last_login for admin: " . $e->getMessage());
                }
                write_log_callback("Admin login successful, checking if connecting from profile");

                if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
                    $_SESSION['google_connect_success'] = "เชื่อมต่อบัญชี Google และ Gmail สำเร็จ";
                    unset($_SESSION['connecting_from_profile']);
                    header("Location: index.php?page=admin_profile&google_connect=success");
                } else {
                    header("Location: index.php?page=admin_dashboard");
                }
                exit;
            } else {
                write_log_callback("Admin email not found: $email");
                if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
                    $_SESSION['google_connect_error'] = "ไม่พบอีเมลนี้ในระบบผู้ดูแล กรุณาตรวจสอบอีเมลในโปรไฟล์ให้ตรงกับบัญชี Google";
                    unset($_SESSION['connecting_from_profile']);
                    header("Location: index.php?page=admin_profile&google_connect=error");
                } else {
                    $_SESSION['auth_error'] = "ไม่พบอีเมลนี้ ($email) ในระบบผู้ดูแล";
                    header("Location: index.php?page=admin_login");
                }
                exit;
            }
        } elseif ($user_type === 'teacher') {
            $query = "SELECT * FROM teachers WHERE email = :email LIMIT 0,1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            write_log_callback("Teacher email check: $email, rows found: " . $stmt->rowCount());

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                write_log_callback("Teacher found: id={$row['id']}, name={$row['firstname']} {$row['lastname']}");
                if (empty($row['google_id'])) {
                    $update_query = "UPDATE teachers SET google_id = :google_id WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':google_id', $google_id);
                    $update_stmt->bindParam(':id', $row['id']);
                    $update_stmt->execute();
                    write_log_callback("Updated teacher google_id: id={$row['id']}");
                }
                $_SESSION['teacher_user_id'] = $row['id'];
                $_SESSION['teacher_code'] = $row['teacher_id'];
                $_SESSION['teacher_name'] = $row['firstname'] . ' ' . $row['lastname'];
                $_SESSION['teacher_email'] = $row['email'];
                $_SESSION['google_access_token'] = $token_data['access_token'];
                if (isset($token_data['refresh_token'])) {
                    $_SESSION['google_refresh_token'] = $token_data['refresh_token'];
                    write_log_callback("Stored refresh token for teacher: {$row['id']}");
                }

                // Store tokens in DB for teacher
                try {
                    $check_columns_teacher = $db->query("SHOW COLUMNS FROM teachers LIKE 'access_token'");
                    if ($check_columns_teacher->rowCount() > 0) {
                        $token_query_teacher = "UPDATE teachers SET access_token = :access_token, refresh_token = :refresh_token, token_expires_at = :expires_at WHERE id = :id";
                        $token_stmt_teacher = $db->prepare($token_query_teacher);
                        $expires_at_teacher = isset($token_data['expires_in']) ? date('Y-m-d H:i:s', time() + $token_data['expires_in']) : null;
                        $token_stmt_teacher->bindValue(':access_token', $token_data['access_token']);
                        $token_stmt_teacher->bindValue(':refresh_token', $token_data['refresh_token'] ?? null);
                        $token_stmt_teacher->bindValue(':expires_at', $expires_at_teacher);
                        $token_stmt_teacher->bindValue(':id', $row['id']);
                        if ($token_stmt_teacher->execute()) {
                            write_log_callback("Stored tokens in database for teacher: {$row['id']}");
                        } else {
                            write_log_callback("Failed to store tokens for teacher: " . print_r($token_stmt_teacher->errorInfo(), true));
                        }
                    }
                } catch (Exception $e) {
                    write_log_callback("Warning: Could not store tokens in database for teacher: " . $e->getMessage());
                }

                try { // Update first_login if applicable
                    if ($row['first_login'] == 1) {
                        $update_first_login_query = "UPDATE teachers SET first_login = 0 WHERE id = :id";
                        $update_first_login_stmt = $db->prepare($update_first_login_query);
                        $update_first_login_stmt->bindParam(':id', $row['id']);
                        $update_first_login_stmt->execute();
                    }
                } catch (Exception $e) {
                    write_log_callback("Warning: Could not update first_login for teacher: " . $e->getMessage());
                }

                write_log_callback("Teacher login successful, checking if connecting from profile");

                if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
                    $_SESSION['google_connect_success'] = "เชื่อมต่อบัญชี Google สำเร็จ";
                    unset($_SESSION['connecting_from_profile']);
                    header("Location: index.php?page=teacher_profile&google_connect=success");
                } else {
                    header("Location: index.php?page=teacher_dashboard");
                }
                exit;
            } else {
                write_log_callback("Teacher email not found: $email");
                if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
                    $_SESSION['google_connect_error'] = "ไม่พบอีเมลนี้ในระบบอาจารย์ กรุณาตรวจสอบอีเมลในโปรไฟล์ให้ตรงกับบัญชี Google";
                    unset($_SESSION['connecting_from_profile']);
                    header("Location: index.php?page=teacher_profile&google_connect=error");
                } else {
                    $_SESSION['auth_error'] = "ไม่พบอีเมลนี้ ($email) ในระบบอาจารย์";
                    header("Location: index.php?page=teacher_login");
                }
                exit;
            }
        } else {
            write_log_callback("Invalid user type in callback: $user_type");
            throw new Exception("ประเภทผู้ใช้ไม่ถูกต้อง ($user_type)");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        write_log_callback("Error in google_callback: $error_message");
        $_SESSION['auth_error'] = "เกิดข้อผิดพลาดระหว่างการยืนยันตัวตนด้วย Google: " . $error_message;

        if (isset($_SESSION['connecting_from_profile']) && $_SESSION['connecting_from_profile'] === true) {
            $_SESSION['google_connect_error'] = $_SESSION['auth_error'];
            unset($_SESSION['auth_error']);
            unset($_SESSION['connecting_from_profile']);
            $target_page = ($user_type === 'student') ? 'student_profile' : (($user_type === 'admin') ? 'admin_profile' : 'teacher_profile');
            header("Location: index.php?page={$target_page}&google_connect=error");
        } else {
            $target_page = ($user_type === 'student') ? 'student_login' : (($user_type === 'admin') ? 'admin_login' : (($user_type === 'teacher') ? 'teacher_login' : 'home'));
            header("Location: index.php?page={$target_page}");
        }
        exit;
    }
} else {
    write_log_callback("No authorization code received from Google.");
    $_SESSION['auth_error'] = "ไม่ได้รับรหัสยืนยันจาก Google";
    header("Location: index.php"); // Redirect to a generic page if no code
    exit;
}
