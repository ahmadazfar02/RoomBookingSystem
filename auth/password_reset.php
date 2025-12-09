<?php
// Consolidated password reset handler (request + reset)
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

start_session_once();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: loginterface.html');
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'request') {
    // Password reset request (send email)
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    if (empty($email)) {
        $_SESSION['message'] = 'Please provide an email address.';
        $_SESSION['message_type'] = 'error';
        header('Location: forgot_password.php');
        exit;
    }

    $user = find_user_by_email($conn, $email);

    if ($user) {
        $token = generate_reset_token();
        if (store_reset_token($conn, $user['id'], $token)) {
            $reset_link = SITE_BASE_URL . '/reset_password.php?token=' . $token;
            // Send email (don't rely on result for security message)
            send_reset_email($user['email'], $user['username'], $reset_link);
        }
    }

    // Always show same message for privacy
    $_SESSION['message'] = 'If the email exists in our system, a password reset link has been sent.';
    $_SESSION['message_type'] = 'success';
    $conn->close();
    header('Location: forgot_password.php');
    exit;

} elseif ($action === 'reset') {
    // Perform password update
    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    if ($new_password !== $confirm_password) {
        $_SESSION['message'] = 'Passwords do not match.';
        $_SESSION['message_type'] = 'error';
        header('Location: reset_password.php?token=' . urlencode($token));
        exit;
    }

    if (strlen($new_password) < 8) {
        $_SESSION['message'] = 'Password must be at least 8 characters long.';
        $_SESSION['message_type'] = 'error';
        header('Location: reset_password.php?token=' . urlencode($token));
        exit;
    }

    $valid = validate_token($conn, $token);
    if (!$valid) {
        $_SESSION['message'] = 'This password reset link is invalid or has expired.';
        $_SESSION['message_type'] = 'error';
        $conn->close();
        header('Location: reset_password.php?token=' . urlencode($token));
        exit;
    }

    $user_id = $valid['id'];
    $user_type = isset($valid['user_type']) ? $valid['user_type'] : '';

    if (update_password($conn, $user_id, $new_password)) {
        $_SESSION['password_reset_success'] = true;
        $conn->close();
        // Redirect all users to the single success page
        header('Location: password_reset_success.php');
        exit;
    } else {
        $_SESSION['message'] = 'Failed to update password. Please try again.';
        $_SESSION['message_type'] = 'error';
        $conn->close();
        header('Location: reset_password.php?token=' . urlencode($token));
        exit;
    }

} else {
    // Unknown action
    header('Location: loginterface.html');
    exit;
}

?>
