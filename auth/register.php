<?php

include __DIR__ . '/../includes/db_connect.php';

// Variable to trigger modal from PHP if server-side validation fails
$server_error_title = "";
$server_error_msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $fullname = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone_number = trim($_POST["phone_number"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $role = $_POST["role"];

    // ----------------------------------------------------
    // SECURITY CHECK 0: Email Domain Validation (UTM ONLY)
    // ----------------------------------------------------
    if (!preg_match("/utm\.my$/i", $email)) {
        $server_error_title = "Restricted Access";
        $server_error_msg = "Registration is restricted to Universiti Teknologi Malaysia members. Please use an official <b>.utm.my</b> email address.";
    } 
    else {
        // ----------------------------------------------------
        // SECURITY CHECK 1: Reserved Username Check
        // ----------------------------------------------------
        $normalized_username = preg_replace('/[^a-z]/', '', strtolower($username));
        $forbidden_keywords = ['admin', 'superadmin', 'root', 'system', 'moderator', 'support'];

        $is_forbidden = false;
        foreach ($forbidden_keywords as $keyword) {
            if (strpos($normalized_username, $keyword) !== false) {
                $is_forbidden = true;
                break;
            }
        }

        if ($is_forbidden) {
            echo "<script>alert('This username contains reserved words and cannot be used.'); window.history.back();</script>";
            exit();
        }

        // ----------------------------------------------------
        // SECURITY CHECK 2: Role Validation
        // ----------------------------------------------------
        $allowed_roles = ['Student', 'Lecturer', 'Staff'];
        if (!in_array($role, $allowed_roles)) {
            echo "<script>alert('Invalid role specified.'); window.history.back();</script>";
            exit();
        }

        if ($password !== $confirm_password) {
            echo "<script>alert('Passwords do not match!'); window.history.back();</script>";
            exit();
        }

        // Check for duplicates
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR Email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo "<script>alert('Username or Email already exists!'); window.history.back();</script>";
            exit();
        }
        $check_stmt->close();

        // Insert User
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, Fullname, Email, password_hash, User_Type, Phone_Number, Created_At, Updated_At)
                                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("ssssss", $username, $fullname, $email, $password_hash, $role, $phone_number);

        if ($stmt->execute()) {
            echo "<script>alert('Registration successful! You can now log in.'); window.location.href='../loginterface.html';</script>";
        } else {
            echo "<script>alert('Error: " . addslashes($stmt->error) . "');</script>";
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UTM Reservation - Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
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

        .register-wrapper {
            width: 100%;
            max-width: 700px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .register-header {
            padding: 40px 30px 30px;
            text-align: center;
        }

        .register-header img {
            height: 100px;
            margin-bottom: 20px;
            width: auto;
            object-fit: contain;
        }

        .register-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .register-header p {
            font-size: 15px;
            color: var(--text-light);
        }

        .register-body {
            padding: 0 40px 40px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-size: 14px;
            font-weight: 500;
        }

        /* ICON + INPUT LAYOUT FIX (minimal changes to structure) */
        /* Input: keep in normal flow but allow stacking via z-index */
        .input-group input,
        .input-group select {
            width: 100%;
            padding: 14px 50px 14px 50px; /* left + right space for icons */
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
            color: var(--text-dark);
            background-color: #ffffff;
            transition: all 0.3s ease;
            position: relative; /* allows left/right icons to layer above via z-index */
            z-index: 1;         /* base layer for input */
        }

        .input-group input:focus,
        .input-group select:focus {
            outline: none;
            border-color: var(--utm-maroon);
            box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.06);
        }

        /* LEFT ICONS (visible but non-interactive) */
        .input-group i:not(.toggle-password) {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
            pointer-events: none; /* allow clicks to go to input */
            z-index: 3;            /* above input */
        }

        /* adjust when label is used (you used has-label earlier) */
        .input-group.has-label i:not(.toggle-password) {
            top: calc(50% + 14px);
        }

        /* RIGHT EYE ICON - must be clickable */
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 16px;
            padding: 6px;          /* larger tap area */
            border-radius: 6px;
            pointer-events: auto;  /* allow clicks */
            z-index: 4;            /* highest so it receives clicks */
            background: transparent;
        }

        .input-group.has-label .toggle-password {
            top: calc(50% + 14px);
        }

        .match-indicator {
            font-size: 13px;
            margin-top: 8px;
            font-weight: 500;
        }
        
        .match-indicator.good { 
            color: #16a34a;
        }
        
        .match-indicator.bad { 
            color: #dc2626;
        }

        .register-btn {
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

        .register-btn:hover {
            background-color: #600000;
            transform: translateY(-1px);
        }

        .register-btn:active {
            transform: translateY(0);
        }

        .login-section {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: var(--text-light);
            font-size: 14px;
        }

        .login-section a {
            color: var(--utm-maroon);
            text-decoration: none;
            font-weight: 700;
            margin-left: 5px;
        }

        .login-section a:hover {
            text-decoration: underline;
        }

        /* --- POPUP CARD STYLES --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            z-index: 1000;
            display: none;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.show {
            display: flex;
            opacity: 1;
        }

        .modal-card {
            background: white;
            padding: 40px 30px;
            border-radius: 16px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .modal-overlay.show .modal-card {
            transform: scale(1);
        }

        .modal-icon {
            width: 70px;
            height: 70px;
            background: #fee2e2;
            color: #dc2626;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
        }

        .modal-title {
            color: var(--text-dark);
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .modal-message {
            color: var(--text-light);
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .modal-btn {
            background-color: var(--utm-maroon);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: background 0.2s;
            width: 100%;
        }

        .modal-btn:hover {
            background-color: #600000;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .register-wrapper {
                max-width: 90%;
            }

            .register-header {
                padding: 30px 20px 25px;
            }

            .register-body {
                padding: 0 25px 30px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            body {
                background-attachment: scroll;
            }
        }
    </style>
</head>
<body>

    <div id="errorModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-icon">
                <i class="fa-solid fa-ban"></i>
            </div>
            <h3 class="modal-title" id="modalTitle">Registration Failed</h3>
            <p class="modal-message" id="modalMessage">
                This account type is not authorized.
            </p>
            <button class="modal-btn" onclick="closeModal()">Understood</button>
        </div>
    </div>

    <div class="register-wrapper">
        <div class="register-header">
            <img src="../assets/images/utm_logo.png" alt="UTM Logo">
            <h1>Create Account</h1>
            <p>Join UTM Room Reservation System</p>
        </div>

        <form method="POST" class="register-body" onsubmit="return validateForm()">
            <div class="form-row">
                <div class="input-group has-label">
                    <label for="username">Username</label>
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="username" name="username" placeholder="Enter username" required>
                </div>

                <div class="input-group has-label">
                    <label for="name">Full Name</label>
                    <i class="fa-solid fa-id-card"></i>
                    <input type="text" id="name" name="name" placeholder="Enter full name" required>
                </div>
            </div>

            <div class="form-row">
                <div class="input-group has-label">
                    <label for="email">Email Address</label>
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="Enter UTM email" required>
                </div>

                <div class="input-group has-label">
                    <label for="phone_number">Phone Number</label>
                    <i class="fa-solid fa-phone"></i>
                    <input type="text" id="phone_number" name="phone_number" placeholder="Enter phone number" required>
                </div>
            </div>

            <div class="form-row">
                <div class="input-group has-label">
                    <label for="password">Password</label>
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Create password" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
                </div>

                <div class="input-group has-label">
                    <label for="confirm_password">Confirm Password</label>
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                </div>
            </div>

            <div id="passwordMatch" class="match-indicator"></div>

            <div class="input-group has-label">
                <label for="role">Role</label>
                <i class="fa-solid fa-user-tag"></i>
                <select id="role" name="role" required>
                    <option value="">-- Select Role --</option>
                    <option value="Student">Student</option>
                    <option value="Lecturer">Lecturer</option>
                    <option value="Staff">Staff</option>
                </select>
            </div>

            <button type="submit" class="register-btn">CREATE ACCOUNT</button>

            <div class="login-section">
                Already have an account?<a href="../loginterface.html">Login here</a>
            </div>
        </form>
    </div>

    <script>
        // Modal Control Functions
        const modal = document.getElementById('errorModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');

        function showModal(title, message) {
            modalTitle.innerText = title;
            modalMessage.innerHTML = message;
            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        // Close modal if clicked outside
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });

        // Trigger PHP Server-Side Errors
        <?php if (!empty($server_error_msg)) : ?>
            showModal("<?php echo $server_error_title; ?>", "<?php echo $server_error_msg; ?>");
        <?php endif; ?>

        // Password Toggle
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

        // Main Form Validation
        function validateForm() {
            // 1. Check Passwords
            if (password.value !== confirm.value) {
                alert('Passwords do not match!');
                return false;
            }

            // 2. Check UTM Email
            const emailField = document.getElementById('email').value;
            // Regex: String must end with 'utm.my' (case insensitive)
            // It allows student@graduate.utm.my, staff@utm.my, etc.
            const utmEmailRegex = /@.*utm\.my$/i;

            if (!utmEmailRegex.test(emailField)) {
                showModal(
                    "Restricted Access", 
                    "You must use an official <b>.utm.my</b> email address to register (e.g., ali@graduate.utm.my or staff@utm.my)."
                );
                return false; // Stop form submission
            }

            return true; // Allow submission
        }

        // Fallback: attach click handler to .toggle-password if it doesn't have inline onclick
        // (this prevents double-calls when inline onclick exists)
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.toggle-password').forEach(function(icon){
                if (!icon.getAttribute('onclick')) {
                    icon.addEventListener('click', function(e){
                        // find target input id (data-target attribute or nearest input in group)
                        let target = icon.getAttribute('data-target');
                        if (!target) {
                            const wrapper = icon.closest('.input-group');
                            const input = wrapper ? wrapper.querySelector('input') : null;
                            if (input && input.id) target = input.id;
                        }
                        if (target) {
                            togglePassword(target, icon);
                        }
                    }, {passive: true});
                }
            });
        });
    </script>
</body>
</html>
