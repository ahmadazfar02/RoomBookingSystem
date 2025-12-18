<?php
session_start();

// Check if user came from successful password reset
if (!isset($_SESSION['password_reset_success'])) {
    header("location: ../loginterface.html");
    exit;
}

// Clear the session variable
unset($_SESSION['password_reset_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UTM Reservation - Password Reset Successful</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --utm-maroon: #800000; 
            --text-dark: #1e293b;
            --text-light: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../assets/images/image_utm_background.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        .success-wrapper {
            width: 100%;
            max-width: 500px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .success-header {
            padding: 40px 30px 30px;
            text-align: center;
        }

        .success-header img {
            height: 100px;
            margin-bottom: 20px;
            width: auto;
            object-fit: contain;
        }

        .success-body {
            padding: 0 40px 40px;
            text-align: center;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: successPulse 0.6s ease-out;
        }

        .success-icon i {
            font-size: 40px;
            color: white;
        }

        @keyframes successPulse {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 16px;
        }

        .success-message {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 28px;
            color: #166534;
            font-size: 14px;
            line-height: 1.6;
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background-color: var(--utm-maroon);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px rgba(128, 0, 0, 0.2);
            text-decoration: none;
            display: inline-block;
        }

        .login-btn:hover {
            background-color: #600000;
            transform: translateY(-1px);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn i {
            margin-right: 8px;
        }

        .info-text {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: var(--text-light);
            font-size: 13px;
            line-height: 1.6;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .success-wrapper {
                max-width: 90%;
            }

            .success-header {
                padding: 30px 20px 25px;
            }

            .success-body {
                padding: 0 25px 30px;
            }

            body {
                background-attachment: scroll;
            }
        }
    </style>
</head>
<body>
    <div class="success-wrapper">
        <div class="success-header">
            <img src="../assets/images/utm_logo.png" alt="UTM Logo">
        </div>

        <div class="success-body">
            <div class="success-icon">
                <i class="fa-solid fa-check"></i>
            </div>

            <h1>Password Reset Successful!</h1>
            
            <div class="success-message">
                <i class="fa-solid fa-circle-check"></i>
                Your password has been successfully reset. You can now log in with your new password.
            </div>

            <a href="../loginterface.html" class="login-btn">
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
                GO TO LOGIN
            </a>

            <p class="info-text">
                Make sure to keep your new password secure and don't share it with anyone.
            </p>
        </div>
    </div>
</body>
</html>