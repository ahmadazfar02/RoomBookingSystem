<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UTM Reservation - Forgot Password</title>
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
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../assets/images/Kampus-UTMKL.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        .forgot-wrapper {
            width: 100%;
            max-width: 500px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .forgot-header {
            padding: 40px 30px 30px;
            text-align: center;
        }

        .forgot-header img {
            height: 100px;
            margin-bottom: 20px;
            width: auto;
            object-fit: contain;
        }

        .forgot-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .forgot-header p {
            font-size: 14px;
            color: var(--text-light);
            line-height: 1.6;
        }

        .forgot-body {
            padding: 0 40px 40px;
        }

        .message {
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message i {
            font-size: 18px;
        }

        .success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: start;
            gap: 12px;
        }

        .info-box i {
            color: #0284c7;
            font-size: 20px;
            margin-top: 2px;
        }

        .info-box p {
            color: #075985;
            font-size: 13px;
            line-height: 1.6;
            margin: 0;
        }

        .input-group {
            position: relative;
            margin-bottom: 24px;
        }

        .input-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
        }

        .input-group input {
            width: 100%;
            padding: 14px 15px 14px 50px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
            color: var(--text-dark);
            background-color: #ffffff;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--utm-maroon);
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.1);
        }

        .submit-btn {
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
        }

        .submit-btn:hover {
            background-color: #600000;
            transform: translateY(-1px);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .back-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: var(--text-light);
            font-size: 14px;
        }

        .back-link a {
            color: var(--utm-maroon);
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .forgot-wrapper {
                max-width: 90%;
            }

            .forgot-header {
                padding: 30px 20px 25px;
            }

            .forgot-body {
                padding: 0 25px 30px;
            }

            body {
                background-attachment: scroll;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-wrapper">
        <div class="forgot-header">
            <img src="../assets/images/utm_logo.png" alt="UTM Logo">
            <h1>Forgot Password?</h1>
            <p>No worries! Enter your email and we'll send you reset instructions.</p>
        </div>

        <div class="forgot-body">
            <?php
            if (isset($_SESSION['message'])) {
                $message_type = $_SESSION['message_type'];
                $icon = $message_type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
                echo '<div class="message ' . $message_type . '">';
                echo '<i class="fa-solid ' . $icon . '"></i>';
                echo '<span>' . $_SESSION['message'] . '</span>';
                echo '</div>';
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            }
            ?>

            <div class="info-box">
                <i class="fa-solid fa-circle-info"></i>
                <p>We'll send a password reset link to your registered email address. Please check your inbox and spam folder.</p>
            </div>

            <form action="password_reset.php" method="POST">
                <input type="hidden" name="action" value="request">
                
                <div class="input-group">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" placeholder="Enter your email address" required autocomplete="email">
                </div>

                <button type="submit" class="submit-btn">SEND RESET LINK</button>
            </form>

            <div class="back-link">
                Remember your password?<a href="../loginterface.html"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>