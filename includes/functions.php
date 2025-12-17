<?php
// Reusable helper functions for password reset flow

if (!function_exists('start_session_once')) {
    function start_session_once() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('generate_reset_token')) {
    function generate_reset_token() {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('store_reset_token')) {
    function store_reset_token($conn, $user_id, $token) {
        date_default_timezone_set('Asia/Kuala_Lumpur');
        $expiry_time = date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRY);
        $sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('ssi', $token, $expiry_time, $user_id);
            $res = $stmt->execute();
            $stmt->close();
            return $res;
        }
        return false;
    }
}

if (!function_exists('send_reset_email')) {
    function send_reset_email($to_email, $to_name, $reset_link) {
        require_once __DIR__ . '/../includes/config.php';
        require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // SMTP config
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            if (strtolower(MAIL_ENCRYPTION) === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port = MAIL_PORT;

            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to_email, $to_name);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - ' . MAIL_FROM_NAME;

            // HTML Body with styled button
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                        margin: 0;
                        padding: 0;
                        background-color: #f4f4f4;
                    }
                    .email-container {
                        max-width: 600px;
                        margin: 0 auto;
                        background-color: #ffffff;
                        padding: 0;
                    }
                    .email-header {
                        background-color: #3f51b5;
                        color: #ffffff;
                        padding: 20px;
                        text-align: center;
                    }
                    .email-content {
                        padding: 30px;
                    }
                    .button-container {
                        text-align: center;
                        margin: 30px 0;
                    }
                    .reset-button {
                        display: inline-block;
                        padding: 15px 40px;
                        background-color: #3f51b5;
                        color: #ffffff !important;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: bold;
                        font-size: 16px;
                    }
                    .reset-button:hover {
                        background-color: #1c296fff;
                    }
                    .footer {
                        padding: 20px;
                        text-align: center;
                        color: #666;
                        font-size: 12px;
                        background-color: #f9f9f9;
                        border-top: 1px solid #ddd;
                    }
                    .warning {
                        background-color: #fff3cd;
                        border-left: 4px solid #ffc107;
                        padding: 15px;
                        margin: 20px 0;
                    }
                </style>
            </head>
            <body>
                <div class="email-container">
                    <div class="email-header">
                        <h1>' . MAIL_FROM_NAME . '</h1>
                    </div>
                    <div class="email-content">
                        <p>Hello <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
                        
                        <p>We received a request to reset your password. Click the button below to create a new password:</p>
                        
                        <div class="button-container">
                            <a href="' . htmlspecialchars($reset_link) . '" class="reset-button">Reset Password</a>
                        </div>
                        
                        <div class="warning">
                            <strong>⏰ Important:</strong> This link will expire in 1 hour for security reasons.
                        </div>
                        
                        <p>If the button above doesn\'t work, you can copy and paste this link into your browser:</p>
                        <p style="word-break: break-all; color: #666; font-size: 14px;">' . htmlspecialchars($reset_link) . '</p>
                        
                        <p>If you didn\'t request a password reset, please ignore this email. Your password will remain unchanged.</p>
                        
                        <p>Best regards,<br>
                        <strong>' . MAIL_FROM_NAME . '</strong></p>
                    </div>
                    <div class="footer">
                        <p>This is an automated message, please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>';

            // Plain text alternative
            $mail->AltBody = "Hello " . $to_name . "\n\n"
                . "We received a request to reset your password.\n\n"
                . "Click the link below to reset your password:\n"
                . $reset_link . "\n\n"
                . "This link will expire in 1 hour.\n\n"
                . "If you didn't request this, please ignore this email. Your password will remain unchanged.\n\n"
                . "Best regards,\n"
                . MAIL_FROM_NAME;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("send_reset_email error: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('find_user_by_email')) {
    function find_user_by_email($conn, $email) {
        $sql = "SELECT id, username, Email FROM users WHERE Email = ? LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $username, $user_email);
                $stmt->fetch();
                $stmt->close();
                return ['id' => $id, 'username' => $username, 'email' => $user_email];
            }
            $stmt->close();
        }
        return false;
    }
}

if (!function_exists('validate_token')) {
    function validate_token($conn, $token) {
        $sql = "SELECT id, User_Type, reset_token_expiry FROM users WHERE reset_token = ? LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $user_type, $expiry);
                $stmt->fetch();
                $stmt->close();
                if (new DateTime() < new DateTime($expiry)) {
                    return ['id' => $id, 'user_type' => $user_type];
                }
            }
            $stmt->close();
        }
        return false;
    }
}

if (!function_exists('update_password')) {
    function update_password($conn, $user_id, $new_password) {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('si', $hash, $user_id);
            $res = $stmt->execute();
            $stmt->close();
            return $res;
        }
        return false;
    }
}

if (!function_exists('send_activation_email')) {
    function send_activation_email($to_email, $to_name, $username, $role, $activation_link) {
        // Reuse the same includes/logic as send_reset_email
        require_once __DIR__ . '/../includes/config.php';
        require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // SMTP Settings (Same as before)
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = (strtolower(MAIL_ENCRYPTION) === 'ssl') ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = MAIL_PORT;

            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to_email, $to_name);
            $mail->isHTML(true);
            $mail->Subject = 'Activate Your Account - ' . MAIL_FROM_NAME;

            // HTML Body for Welcome Email
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; padding: 20px;">
                <div style="max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px;">
                    <h2 style="color: #3f51b5; text-align: center;">Welcome to ' . MAIL_FROM_NAME . '</h2>
                    <p>Hello <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
                    <p>An account has been created for you by the administrator.</p>
                    <p style="background: #f9f9f9; padding: 15px; border-left: 4px solid #3f51b5;">
                        <strong>Username:</strong> ' . htmlspecialchars($username) . '<br>
                        <strong>Role:</strong> ' . htmlspecialchars($role) . '
                    </p>
                    <p>Please click the button below to set your password and activate your account:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . htmlspecialchars($activation_link) . '" style="background-color: #3f51b5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;">Activate Account</a>
                    </div>
                    <p style="font-size: 12px; color: #666;">Link expires in 24 hours.<br>If the button does not work, paste this link: ' . htmlspecialchars($activation_link) . '</p>
                </div>
            </body>
            </html>';

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Activation email error: " . $mail->ErrorInfo);
            return false;
        }
    }
}

?>
