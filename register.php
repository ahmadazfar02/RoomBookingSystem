<?php

include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $fullname = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone_number = trim($_POST["phone_number"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $role = $_POST["role"];

    // ----------------------------------------------------
    // SECURITY CHECK 1: Advanced Reserved Username Check changes start here
    // ----------------------------------------------------
    
    // 1. Normalize the username: Remove numbers and special chars to check the "core" word
    // e.g., "Admin123!" becomes "admin"
    $normalized_username = preg_replace('/[^a-z]/', '', strtolower($username));
    
    // 2. Define forbidden keywords
    $forbidden_keywords = ['admin', 'superadmin', 'root', 'system', 'moderator', 'support'];

    // 3. Check if the normalized username contains any forbidden keyword
    // This blocks "Admin1", "TheAdmin", "Super-Admin", etc.
    foreach ($forbidden_keywords as $keyword) {
        if (strpos($normalized_username, $keyword) !== false) {
            echo "<script>alert('This username contains reserved words (like \"$keyword\") and cannot be used.'); window.history.back();</script>";
            exit();
        }
    }

    // ----------------------------------------------------
    // SECURITY CHECK 2: Role Validation
    // ----------------------------------------------------
    if (strcasecmp($role, 'Admin') == 0) {
        echo "<script>alert('Invalid role selection. Please choose Student, Lecturer, or Staff.'); window.history.back();</script>";
        exit();
    }
    
    $allowed_roles = ['Student', 'Lecturer', 'Staff'];
    if (!in_array($role, $allowed_roles)) {
        echo "<script>alert('Invalid role specified.'); window.history.back();</script>";
        exit();
    }

    // ----------------------------------------------------
    // end here
    // ----------------------------------------------------

    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!'); window.history.back();</script>";
        exit();
    }

    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR Email = ?");
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo "<script>alert('Username or Email already exists!'); window.history.back();</script>";
        exit();
    }
    $check_stmt->close();

    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO users (username, Fullname, Email, password_hash, User_Type, Phone_Number, Created_At, Updated_At)
                            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("ssssss", $username, $fullname, $email, $password_hash, $role, $phone_number);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful! You can now log in.'); window.location.href='loginterface.html';</script>";
    } else {
        echo "<script>alert('Error: " . addslashes($stmt->error) . "');</script>";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>

    <!-- Use Font Awesome for better icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

        <style>
            body {
                margin: 0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(to right, #1b2ea6, #c03a3a);
                height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                flex-direction: column;
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
            }

            .register-container {
                background: rgba(255, 255, 255, 0.2);
                backdrop-filter: blur(12px);
                padding: 30px 50px;
                border-radius: 15px;
                box-shadow: 0 0 25px rgba(0, 0, 0, 0.3);
                margin-top: 120px;
                width: 380px;
                color: #fff;
            }

            .register-container h2 {
                text-align: center;
                margin-bottom: 25px;
                color: #fff;
                letter-spacing: 1px;
            }

            .form-group {
                margin-bottom: 18px;
                text-align: left;
                position: relative;
            }

            label {
                display: block;
                font-size: 14px;
                font-weight: 600;
                color: #f9f9f9;
                margin-bottom: 6px;
            }

            input[type="text"],
            input[type="email"],
            input[type="password"],
            select {
                width: 85%;
                padding: 10px 40px 10px 10px;
                border-radius: 6px;
                border: none;
                background: rgba(255, 255, 255, 0.95);
                font-size: 14px;
                outline: none;
                color: #333;
            }

            input:focus, select:focus {
                box-shadow: 0 0 6px rgba(255, 255, 255, 0.6);
            }

            .toggle-password {
                position: absolute;
                right: 10px;
                top: 68%;
                transform: translateY(-50%);
                cursor: pointer;
                color: #666;
                font-size: 16px;
            }

            .toggle-password:hover {
                color: #333;
            }

            .match-indicator {
                font-size: 13px;
                margin-top: 4px;
            }
            .match-indicator.good { color: #a0ffb4; }
            .match-indicator.bad { color: #ffb4b4; }

            .register-btn {
                width: 100%;
                background-color: #6e0b0b;
                color: white;
                border: none;
                padding: 12px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: bold;
                font-size: 15px;
                margin-top: 10px;
                transition: background 0.3s ease;
            }

            .register-btn:hover {
                background-color: #a31313;
            }

            .login-link {
                text-align: center;
                margin-top: 20px;
                color: #fff;
                font-size: 14px;
            }

            .login-link a {
                color: #fff;
                text-decoration: underline;
            }
        </style>
    </head>

    <body>

        <div class="utm-header">
            <img src="img/utmlogo.png" alt="UTM Logo">
        </div>

        <form method="POST" onsubmit="return validatePassword()">
            <div class="register-container">
                <h2>Create Your Account</h2>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="phone_number">Phone Number</label>
                    <input type="text" id="phone_number" name="phone_number" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                    
                </div>

                <div id="passwordMatch" class="match-indicator"></div>
                <br>

                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="">-- Select Role --</option>
                        <option value="Student">Student</option>
                        <option value="Lecturer">Lecturer</option>
                        <option value="Staff">Staff</option>
                        <!--- I remove the admin option-->
                    </select>
                </div>

                <button type="submit" class="register-btn">Register</button>

                <div class="login-link">
                    Already have an account? <a href="loginterface.html">Login here</a>
                </div>
            </div>
        </form>

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

        const password = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        const indicator = document.getElementById('passwordMatch');

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
        </script>

    </body>
</html>
