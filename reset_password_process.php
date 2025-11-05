<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Set timezone to ensure consistent time comparison
    date_default_timezone_set('Asia/Kuala_Lumpur');

    $token = trim($_POST["token"]);
    $new_password = trim($_POST["new_password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    
    // Validate passwords match
    if ($new_password !== $confirm_password) {
        $_SESSION['message'] = "Passwords do not match.";
        $_SESSION['message_type'] = "error";
        header("location: reset_password.php?token=" . urlencode($token));
        exit;
    }
    
    // Validate password length
    if (strlen($new_password) < 8) {
        $_SESSION['message'] = "Password must be at least 8 characters long.";
        $_SESSION['message_type'] = "error";
        header("location: reset_password.php?token=" . urlencode($token));
        exit;
    }
    
    // Verify token is still valid
    $sql = "SELECT id, User_Type FROM users WHERE reset_token = ? AND reset_token_expiry > ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $current_time = date('Y-m-d H:i:s');
        $stmt->bind_param("ss", $token, $current_time);
        
        if ($stmt->execute()) {
            $stmt->store_result();
            
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($user_id, $user_type);
                $stmt->fetch();
                $stmt->close();
                
                // Hash the new password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password and clear reset token
                $update_sql = "UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?";
                
                if ($update_stmt = $conn->prepare($update_sql)) {
                    $update_stmt->bind_param("si", $new_password_hash, $user_id);
                    
                    if ($update_stmt->execute()) {
                        $_SESSION['password_reset_success'] = true;
                        $conn->close();
                        if ($user_type === 'admin') {
                            header("location: password_reset_success_admin.php");
                        } else {
                            header("location: password_reset_success.php");
                        }
                        exit;
                    } else {
                        $_SESSION['message'] = "Failed to update password. Please try again.";
                        $_SESSION['message_type'] = "error";
                    }
                    
                    $update_stmt->close();
                } else {
                    $_SESSION['message'] = "Something went wrong. Please try again.";
                    $_SESSION['message_type'] = "error";
                }
            } else {
                $_SESSION['message'] = "This password reset link is invalid or has expired.";
                $_SESSION['message_type'] = "error";
            }
        }
    }
    
    $conn->close();
    header("location: reset_password.php?token=" . urlencode($token));
    exit;
}
?>
