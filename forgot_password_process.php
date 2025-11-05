<?php
// Start output buffering to prevent header issues
ob_start();
session_start();
require_once 'db_connect.php';
require 'vendor/PHPMailer/src/Exception.php';
require 'vendor/PHPMailer/src/PHPMailer.php';
require 'vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

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
                
                // Store token in the users table
                $update_sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?";
                
                if ($update_stmt = $conn->prepare($update_sql)) {
                    $update_stmt->bind_param("ssi", $reset_token, $expiry_time, $user_id);
                    
                    if ($update_stmt->execute()) {
                        // Create reset link
                        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . str_replace(' ', '%20', dirname($_SERVER['PHP_SELF'])) . "/reset_password.php?token=" . $reset_token;
                        
                        try {
                            // Create a new PHPMailer instance
                            $mail = new PHPMailer(true);
                            
                            // Server settings
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'reserveroom446@gmail.com';
                            $mail->Password = 'kaudwtzzuytnfjwi';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;
                            
                            // Recipients
                            $mail->setFrom('reserveroom446@gmail.com', 'Room Reservation System');
                            $mail->addAddress($user_email, $username);
                            
                            // Content
                            $mail->isHTML(true);
                            $mail->Subject = 'Password Reset Request - Room Reservation System';
                            $mail->Body = "
                                <html>
                                <body>
                                    <p>Hello {$username},</p>
                                    <p>You requested to reset your password. Click the link below to reset it:</p>
                                    <p><a href='{$reset_link}'>{$reset_link}</a></p>
                                    <p>This link will expire in 1 hour.</p>
                                    <p>If you didn't request this, please ignore this email.</p>
                                    <br>
                                    <p>Best regards,<br>Room Reservation System</p>
                                </body>
                                </html>
                            ";
                            $mail->AltBody = "Hello {$username},\n\n"
                                . "You requested to reset your password. Click the link below to reset it:\n\n"
                                . "{$reset_link}\n\n"
                                . "This link will expire in 1 hour.\n\n"
                                . "If you didn't request this, please ignore this email.\n\n"
                                . "Best regards,\nRoom Reservation System";
                            
                            // Send the email
                            $mail->send();
                            $_SESSION['message'] = "If the email exists in our system, a password reset link has been sent.";
                            $_SESSION['message_type'] = "success";
                        } catch (Exception $e) {
                            // Log the error but don't show it to the user
                            error_log("Failed to send password reset email: " . $mail->ErrorInfo);
                            $_SESSION['message'] = "If the email exists in our system, a password reset link has been sent.";
                            $_SESSION['message_type'] = "success";
                        }
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
    
    // Clean output buffer and redirect
    ob_end_clean();
    header("location: forgot_password.php");
    exit();
}
?>
