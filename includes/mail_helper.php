<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_mail(string $to, string $toName, string $subject, string $html, string $alt = ''): bool {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $alt ?: strip_tags($html);
        // headers
        $mail->addCustomHeader('List-Unsubscribe', '<' . rtrim(BASE_URL, '/') . '/unsubscribe.php>');
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

function send_mail_return_id(string $to, string $toName, string $subject, string $html, string $alt = '', ?string $inReplyTo = null): ?string {
    try {
        $mail = new PHPMailer(true);
        // same config as above...
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        if ($inReplyTo) {
            $mail->addCustomHeader('In-Reply-To', $inReplyTo);
            $mail->addCustomHeader('References', $inReplyTo);
        }
        $mail->addCustomHeader('List-Unsubscribe', '<' . rtrim(BASE_URL, '/') . '/unsubscribe.php>');
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $alt ?: strip_tags($html);
        $mail->send();
        // PHPMailer stores Message-ID:
        return $mail->getLastMessageID() ?: null;
    } catch (Exception $e) {
        error_log('Mail error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
        return null;
    }
}

function insert_email_log(mysqli $conn,
                         ?int $booking_id,
                         string $recipient_email,
                         string $recipient_role, // 'user'|'admin'
                         string $subject,
                         ?string $message_id,
                         ?string $in_reply_to,
                         string $status, // 'sent'|'failed'
                         ?string $error_message = null,
                         ?int $admin_log_id = null) : bool
{
    $sql = "INSERT INTO email_logs
        (booking_id, admin_log_id, recipient_email, recipient_role, subject, message_id, in_reply_to, status, error_message, sent_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("insert_email_log prepare failed: " . $conn->error);
        return false;
    }
    // binding: booking_id (i), admin_log_id (i), recipient_email (s), recipient_role (s),
    // subject (s), message_id (s), in_reply_to (s), status (s), error_message (s)
    $stmt->bind_param(
        'iisssssss',
        $booking_id,
        $admin_log_id,
        $recipient_email,
        $recipient_role,
        $subject,
        $message_id,
        $in_reply_to,
        $status,
        $error_message
    );
    $ok = $stmt->execute();
    if (!$ok) error_log("insert_email_log execute failed: " . $stmt->error);
    $stmt->close();
    return $ok;
}
?>