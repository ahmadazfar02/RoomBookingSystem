<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(to right, #1b2ea6, #c03a3a); 
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: #000;
        }

        .utm-header {
            position: absolute;
            top: 0;
            width: 100%;
            background: linear-gradient(to right, #3a4fd4, #e25c5c); 
            text-align: center;
            padding: 15px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .utm-header img {
            height: 80px; 
            margin-bottom: 5px;
        }

        .container {
            text-align: center;
            background: rgba(255, 255, 255, 0.15); 
            backdrop-filter: blur(10px);
            padding: 35px 60px;
            border-radius: 12px;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.3);
            margin-top: 120px;
            min-width: 350px;
        }

        .container h2 {
            font-family: 'Segoe UI', sans-serif;
            font-size: 22px;
            margin-bottom: 15px;
            color: #040000;
        }

        .container p {
            color: #333;
            margin-bottom: 20px;
            font-size: 14px;
        }

        input[type="password"] {
            width: 90%;
            padding: 10px;
            margin: 8px 0;
            border: none;
            border-radius: 5px;
            background: rgba(255,255,255,0.9);
            outline: none;
            text-align: left;
        }

        .submit-btn {
            background-color: #6e0b0b;
            color: white;
            border: none;
            padding: 10px 45px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
            transition: background 0.3s;
        }

        .submit-btn:hover {
            background-color: #a31313;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
            color: #fff;
            font-size: 14px;
        }

        .back-link a {
            color: #fff;
            text-decoration: underline;
        }

        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-size: 14px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .password-requirements {
            background: rgba(255, 255, 255, 0.8);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 12px;
            text-align: left;
            color: #333;
        }

        .password-requirements ul {
            margin: 5px 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="utm-header">
        <img src="utm_logo.png" alt="UTM Logo"><br>
    </div>

    <?php
    session_start();
    require_once 'db_connect.php';

    // Set timezone to ensure consistent time comparison
    date_default_timezone_set('Asia/Kuala_Lumpur');

    $token = isset($_GET['token']) ? $_GET['token'] : '';
    $valid_token = false;
    $error_message = '';

    if (empty($token)) {
        $error_message = "Invalid password reset link.";
    } else {
        // Verify token
        $sql = "SELECT id, username, reset_token_expiry FROM users WHERE reset_token = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $token);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($user_id, $username, $token_expiry);
                    $stmt->fetch();
                    
                    $current_time = new DateTime();
                    $expiry_time = new DateTime($token_expiry);

                    if ($current_time < $expiry_time) {
                        $valid_token = true;
                    } else {
                        $error_message = "This password reset link is invalid or has expired.";
                    }
                } else {
                    $error_message = "This password reset link is invalid or has expired.";
                }
            }
            
            $stmt->close();
        }
    }

    $conn->close();
    ?>

    <div class="container">
        <h2>Reset Password</h2>

        <?php
        if (isset($_SESSION['message'])) {
            $message_type = $_SESSION['message_type'];
            echo '<div class="message ' . $message_type . '">' . $_SESSION['message'] . '</div>';
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        }

        if (!empty($error_message)) {
            echo '<div class="message error">' . $error_message . '</div>';
            echo '<div class="back-link"><a href="loginterface.html">Back to Login</a></div>';
        } elseif ($valid_token) {
        ?>
            <p>Enter your new password below.</p>

            <div class="password-requirements">
                <strong>Password Requirements:</strong>
                <ul>
                    <li>At least 8 characters long</li>
                    <li>Contains letters and numbers</li>
                </ul>
            </div>

            <form action="reset_password_process.php" method="POST" onsubmit="return validatePassword()">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="password" id="new_password" name="new_password" placeholder="New Password" required><br>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required><br>
                <button type="submit" class="submit-btn">Reset Password</button>
            </form>

            <div class="back-link">
                <a href="loginterface.html">Back to Login</a>
            </div>

            <script>
                function validatePassword() {
                    var password = document.getElementById('new_password').value;
                    var confirm = document.getElementById('confirm_password').value;

                    if (password.length < 8) {
                        alert('Password must be at least 8 characters long.');
                        return false;
                    }

                    if (password !== confirm) {
                        alert('Passwords do not match.');
                        return false;
                    }

                    return true;
                }
            </script>
        <?php
        }
        ?>
    </div>
</body>
</html>
