<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Update this path if your vendor folder is elsewhere
require_once __DIR__ . '/../vendor/autoload.php'; 

function sendStatusEmail($userEmail, $userName, $status, $bookingDetails) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'reserveroom446@gmail.com'; 
        $mail->Password   = 'kaudwtzzuytnfjwi';    
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('reserveroom446@gmail.com', 'MJIIT Room Booking');
        $mail->addAddress($userEmail, $userName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Booking Update: ' . ucfirst($status);
        
        // Define color based on status
        $color = ($status == 'Approved') ? '#059669' : '#dc2626';

        $mail->Body    = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
            <h2 style='color: $color;'>Booking $status</h2>
            <p>Dear $userName,</p>
            <p>Your booking request has been <b>$status</b>.</p>
            <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid $color; margin: 20px 0;'>
                <strong>Booking Details:</strong><br>
                $bookingDetails
            </div>
            <p>Regards,<br>MJIIT Admin Team</p>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false; 
    }
}
?>