<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Set timezone to ensure consistent time comparison
date_default_timezone_set('Asia/Kuala_Lumpur');

$token = isset($_GET['token']) ? $_GET['token'] : '';
$valid_token = false;
$error_message = '';

if (empty($token)) {
    $error_message = "Invalid password reset link.";
} else {
    // Verify token
    $sql = "SELECT id, reset_token_expiry FROM users WHERE reset_token = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $token);
        
        if ($stmt->execute()) {
            $stmt->store_result();
            
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($user_id, $token_expiry);
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UTM Reservation - Reset Password</title>
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

        .reset-wrapper {
            width: 100%;
            max-width: 500px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .reset-header {
            padding: 40px 30px 30px;
            text-align: center;
        }

        .reset-header img {
            height: 100px;
            margin-bottom: 20px;
            width: auto;
            object-fit: contain;
        }

        .reset-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .reset-header p {
            font-size: 15px;
            color: var(--text-light);
        }

        .reset-body {
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

        .password-requirements {
            background: #f8fafc;
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        .password-requirements strong {
            display: block;
            margin-bottom: 10px;
            color: var(--text-dark);
            font-size: 14px;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 24px;
            color: var(--text-light);
            font-size: 13px;
        }

        .password-requirements li {
            margin-bottom: 6px;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group i.icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
        }

        .input-group input {
            width: 100%;
            padding: 14px 50px 14px 50px;
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

        .toggle-password {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .toggle-password:hover {
            color: #64748b;
        }

        .match-indicator {
            font-size: 13px;
            margin-top: 8px;
            margin-bottom: 12px;
            font-weight: 500;
        }
        
        .match-indicator.good { 
            color: #16a34a;
        }
        
        .match-indicator.bad { 
            color: #dc2626;
        }

        .reset-btn {
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
            margin-top: 10px;
        }

        .reset-btn:hover {
            background-color: #600000;
            transform: translateY(-1px);
        }

        .reset-btn:active {
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
            .reset-wrapper {
                max-width: 90%;
            }

            .reset-header {
                padding: 30px 20px 25px;
            }

            .reset-body {
                padding: 0 25px 30px;
            }

            body {
                background-attachment: scroll;
            }
        }
    </style>
</head>
<body>
    <div class="reset-wrapper">
        <div class="reset-header">
            <img src="../assets/images/utm_logo.png" alt="UTM Logo">
            <h1>Reset Password</h1>
            <p>Create a new secure password</p>
        </div>

        <div class="reset-body">
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

            if (!empty($error_message)) {
                echo '<div class="message error">';
                echo '<i class="fa-solid fa-circle-exclamation"></i>';
                echo '<span>' . $error_message . '</span>';
                echo '</div>';
                echo '<div class="back-link"><a href="../loginterface.html"><i class="fa-solid fa-arrow-left"></i> Back to Login</a></div>';
            } elseif ($valid_token) {
            ?>
                <div class="password-requirements">
                    <strong><i class="fa-solid fa-shield-halved"></i> Password Requirements:</strong>
                    <ul>
                        <li>At least 8 characters long</li>
                        <li>Contains letters and numbers</li>
                    </ul>
                </div>

                <form action="password_reset.php" method="POST" onsubmit="return validatePassword()">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="input-group">
                        <i class="fa-solid fa-lock icon"></i>
                        <input type="password" id="new_password" name="new_password" placeholder="New Password" required>
                        <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('new_password', this)"></i>
                    </div>

                    <div class="input-group">
                        <i class="fa-solid fa-lock icon"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                        <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                    </div>

                    <div id="passwordMatch" class="match-indicator"></div>

                    <button type="submit" class="reset-btn">RESET PASSWORD</button>
                </form>

                <div class="back-link">
                    <a href="../loginterface.html"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
                </div>
            <?php
            }
            ?>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, icon) {
            const field = document.getElementById(fieldId);
            if (field.type === "password") {
                field.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                field.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        const password = document.getElementById('new_password');
        const confirm = document.getElementById('confirm_password');
        const indicator = document.getElementById('passwordMatch');

        if (confirm && password) {
            confirm.addEventListener('input', () => {
                if (confirm.value === "") {
                    indicator.textContent = "";
                } else if (confirm.value === password.value) {
                    indicator.textContent = "✅ Passwords match";
                    indicator.className = "match-indicator good";
                } else {
                    indicator.textContent = "❌ Passwords do not match";
                    indicator.className = "match-indicator bad";
                }
            });
        }

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
</body>
</html>