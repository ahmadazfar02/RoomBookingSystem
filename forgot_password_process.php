<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Set timezone to GMT+8
    date_default_timezone_set('Asia/Kuala_Lumpur');
    
    $email = trim($_POST["email"]);
    
    // Check if email exists
    $sql = "SELECT id, username, Email FROM users WHERE Email = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        
        if ($stmt->execute()) {
            $stmt->store_result();
            
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($user_id, $username, $user_email);
                $stmt->fetch();
                
                // Generate a unique reset token
                $reset_token = bin2hex(random_bytes(32));
                
                // Set token expiry (1 hour from now)
                $expiry_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $update_sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?";
                
                if ($update_stmt = $conn->prepare($update_sql)) {
                    $update_stmt->bind_param("ssi", $reset_token, $expiry_time, $user_id);
                    
                    if ($update_stmt->execute()) {
                        // Create reset link
                        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $reset_token;
                        
                        // In a production environment, you would send this via email
                        // For now, we'll display it on the page (for development/testing)
                        $_SESSION['message'] = "Password reset instructions have been generated. Use the link below to reset your password:<br><br><a href='" . $reset_link . "' style='color: #155724; text-decoration: underline;'>" . $reset_link . "</a><br><br><small>(This link expires in 1 hour)</small>";
                        $_SESSION['message_type'] = "success";
                        
                        // NOTE: In production, uncomment this code and configure email settings
                        /*
                        $to = $user_email;
                        $subject = "Password Reset Request - Room Reservation System";
                        $message = "Hello " . $username . ",\n\n";
                        $message .= "You requested to reset your password. Click the link below to reset it:\n\n";
                        $message .= $reset_link . "\n\n";
                        $message .= "This link will expire in 1 hour.\n\n";
                        $message .= "If you didn't request this, please ignore this email.\n\n";
                        $message .= "Best regards,\nRoom Reservation System";
                        
                        $headers = "From: noreply@roomreservation.utm.my";
                        
                        if (mail($to, $subject, $message, $headers)) {
                            $_SESSION['message'] = "Password reset instructions have been sent to your email address.";
                            $_SESSION['message_type'] = "success";
                        } else {
                            $_SESSION['message'] = "Failed to send email. Please try again later.";
                            $_SESSION['message_type'] = "error";
                        }
                        */
                    } else {
                        $_SESSION['message'] = "Something went wrong. Please try again later.";
                        $_SESSION['message_type'] = "error";
                    }
                    
                    $update_stmt->close();
                } else {
                    $_SESSION['message'] = "Something went wrong. Please try again later.";
                    $_SESSION['message_type'] = "error";
                }
            } else {
                // Email not found - but don't reveal this for security
                $_SESSION['message'] = "If an account exists with this email, you will receive password reset instructions.";
                $_SESSION['message_type'] = "success";
            }
        }
        
        $stmt->close();
    }
    
    $conn->close();
    header("location: forgot_password.php");
    exit;
}
?>
