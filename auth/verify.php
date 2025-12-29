<?php
include __DIR__ . '/../includes/db_connect.php';

$title = "Verifying...";
$message = "Please wait while we verify your account.";
$type = "pending"; // pending, success, error

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Find the user with this token
    $stmt = $conn->prepare("SELECT id, Fullname FROM users WHERE verification_token = ? AND is_verified = 0 LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $name = $user['Fullname'];

        // Update user to verified
        $update = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
        $update->bind_param("i", $user_id);
        
        if ($update->execute()) {
            $title = "Account Verified!";
            $message = "Hello $name, your account has been successfully activated. You can now log in.";
            $type = "success";
        } else {
            $title = "Verification Failed";
            $message = "System error during activation. Please try again later.";
            $type = "error";
        }
        $update->close();
    } else {
        $title = "Invalid Link";
        $message = "This verification link is invalid or has already been used.";
        $type = "error";
    }
    $stmt->close();
} else {
    $title = "Missing Token";
    $message = "No verification token provided.";
    $type = "error";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UTM Room Booking - Email Verification</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../assets/images/Kampus-UTMKL.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 450px; }
        h1 { color: #800000; margin-bottom: 10px; }
        p { color: #64748b; line-height: 1.6; }
        .btn { display: inline-block; background: #800000; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin-top: 25px; font-weight: 600; }
        .icon { font-size: 48px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <?php 
                if($type == "success") echo "✅";
                elseif($type == "error") echo "❌";
                else echo "⏳";
            ?>
        </div>
        <h1><?php echo $title; ?></h1>
        <p><?php echo $message; ?></p>
        <a href="../loginterface.html" class="btn">Go to Login Page</a>
    </div>
</body>
</html>