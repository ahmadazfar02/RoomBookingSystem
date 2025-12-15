<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Successful</title>
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

        .success-icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 20px;
        }

        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-size: 14px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .login-btn {
            background-color: #6e0b0b;
            color: white;
            border: none;
            padding: 10px 45px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 15px;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .login-btn:hover {
            background-color: #a31313;
        }
    </style>
</head>
<body>
    <?php
    session_start();
    
    // Check if user came from successful password reset
    if (!isset($_SESSION['password_reset_success'])) {
        header("location: loginterface.html");
        exit;
    }
    
    // Clear the session variable
    unset($_SESSION['password_reset_success']);
    ?>

    <div class="utm-header">
        <img src="utm_logo.png" alt="UTM Logo"><br>
    </div>

    <div class="container">
        <div class="success-icon">✓</div>
        <h2>Password Reset Successful!</h2>
        
        <div class="message">
            Your password has been successfully reset. You can now log in with your new password.
        </div>

        <a href="../loginterface.html" class="login-btn">Go to Login</a>
    </div>
</body>
</html>
