<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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

        input[type="email"] {
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
    </style>
</head>
<body>
    <div class="utm-header">
        <img src="utm_logo.png" alt="UTM Logo"><br>
    </div>

    <div class="container">
        <h2>Forgot Password</h2>
        <p>Enter your email address and we'll send you instructions to reset your password.</p>

        <?php
        session_start();
        if (isset($_SESSION['message'])) {
            $message_type = $_SESSION['message_type'];
            echo '<div class="message ' . $message_type . '">' . $_SESSION['message'] . '</div>';
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        }
        ?>

        <form action="password_reset.php" method="POST">
            <input type="hidden" name="action" value="request">
            <input type="email" name="email" placeholder="Email Address" required><br>
            <button type="submit" class="submit-btn">Reset Password</button>
        </form>

        <div class="back-link">
            Remember your password? <a href="loginterface.html">Back to Login</a>
        </div>
    </div>
</body>
</html>
