<?php
declare(strict_types=1);

// Prevent any output before we start
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Start output buffering
ob_start();

// Start session
session_start();

// Function to send JSON and exit cleanly
function send_json_response(array $data): void {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Function to log errors properly
function log_error(string $message): void {
    error_log('[process_request.php] ' . $message);
}

// Try-catch wrapper for the entire script
try {
    // Set JSON header early
    header('Content-Type: application/json; charset=utf-8');

    // Check if includes exist before requiring
    $db_file = __DIR__ . '/../includes/db_connect.php';
    $mail_file = __DIR__ . '/../includes/mail_helper.php';
    
    if (!file_exists($db_file)) {
        log_error('db_connect.php not found at: ' . $db_file);
        send_json_response(['success' => false, 'message' => 'Configuration error']);
    }
    
    if (!file_exists($mail_file)) {
        log_error('mail_helper.php not found at: ' . $mail_file);
        send_json_response(['success' => false, 'message' => 'Configuration error']);
    }

    // Require files
    require_once $db_file;
    require_once $mail_file;

    // ---------------- BASIC CHECKS ----------------
    if (!isset($conn) || !($conn instanceof mysqli)) {
        log_error('Database connection not available');
        send_json_response(['success' => false, 'message' => 'Database connection error']);
    }

    // Check for database connection errors
    if ($conn->connect_error) {
        log_error('MySQL connection error: ' . $conn->connect_error);
        send_json_response(['success' => false, 'message' => 'Database connection error']);
    }

    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        log_error('User not logged in');
        send_json_response(['success' => false, 'message' => 'Unauthorized access']);
    }

    if (!isset($_SESSION['User_Type']) || strcasecmp(trim($_SESSION['User_Type']), 'Admin') !== 0) {
        log_error('User is not admin. User type: ' . ($_SESSION['User_Type'] ?? 'not set'));
        send_json_response(['success' => false, 'message' => 'Unauthorized access']);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        log_error('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
        send_json_response(['success' => false, 'message' => 'Invalid request method']);
    }

    // ---------------- INPUTS ----------------
    $action     = trim((string)($_POST['action'] ?? ''));
    $session_id = trim((string)($_POST['session_id'] ?? ''));
    $reason     = trim((string)($_POST['reason'] ?? ''));

    if ($action === '' || $session_id === '') {
        log_error('Missing parameters. Action: ' . $action . ', Session ID: ' . $session_id);
        send_json_response(['success' => false, 'message' => 'Missing parameters']);
    }

    $admin_id = intval($_SESSION['id'] ?? 0);
    if ($admin_id <= 0) {
        log_error('Admin ID missing or invalid: ' . $admin_id);
        send_json_response(['success' => false, 'message' => 'Admin ID missing']);
    }

    // ---------------- HELPERS ----------------
    function insert_admin_log_and_get_id(mysqli $conn, int $admin_id, int $booking_id, string $action, ?string $note = null): int {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, booking_id, action, note, ip_address) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                log_error('Failed to prepare admin_logs insert: ' . $conn->error);
                return 0;
            }
            $stmt->bind_param('iisss', $admin_id, $booking_id, $action, $note, $ip);
            if (!$stmt->execute()) {
                log_error('Failed to execute admin_logs insert: ' . $stmt->error);
                $stmt->close();
                return 0;
            }
            $newId = $conn->insert_id;
            $stmt->close();
            return (int)$newId;
        } catch (Exception $e) {
            log_error('Exception in insert_admin_log_and_get_id: ' . $e->getMessage());
            return 0;
        }
    }

    // Note: insert_email_log is now defined in mail_helper.php
    // No need to redefine it here

    // ---------------- FETCH USER ----------------
    $userStmt = $conn->prepare("
        SELECT u.Email AS email, u.username AS username
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.session_id = ?
        LIMIT 1
    ");
    
    if (!$userStmt) {
        log_error('Failed to prepare user fetch query: ' . $conn->error);
        send_json_response(['success' => false, 'message' => 'Database error']);
    }

    $userStmt->bind_param('s', $session_id);
    
    if (!$userStmt->execute()) {
        log_error('Failed to execute user fetch query: ' . $userStmt->error);
        $userStmt->close();
        send_json_response(['success' => false, 'message' => 'Database error']);
    }

    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        log_error('User not found for session: ' . $session_id);
        send_json_response(['success' => false, 'message' => 'User not found for session']);
    }

    // Validate BASE_URL is defined
    if (!defined('BASE_URL')) {
        log_error('BASE_URL constant not defined');
        send_json_response(['success' => false, 'message' => 'Configuration error']);
    }

    // ---------------- BEGIN TRANSACTION ----------------
    if (!$conn->begin_transaction()) {
        log_error('Failed to begin transaction: ' . $conn->error);
        send_json_response(['success' => false, 'message' => 'Database error']);
    }

    try {
        // Lock bookings for this session
        $stmt = $conn->prepare("SELECT id, status, ticket FROM bookings WHERE session_id = ? FOR UPDATE");
        if (!$stmt) {
            throw new Exception('Failed to prepare bookings query: ' . $conn->error);
        }
        
        $stmt->bind_param('s', $session_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute bookings query: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception('Failed to get result: ' . $stmt->error);
        }
        
        $bookings = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($bookings)) {
            throw new Exception('No bookings found for this session');
        }

        $updated_count = 0;
        $subject_base = "Booking Request — Session {$session_id}";
        $link = rtrim(BASE_URL, '/') . "/booking_status.php?session_id=" . urlencode($session_id);

        // ---------------- APPROVE ----------------
        if ($action === 'approve') {
            $tickets = [];
            $all_admin_log_ids = [];
            
            foreach ($bookings as $b) {
                if (($b['status'] ?? '') !== 'pending') continue;

                // Generate ticket if missing
                $ticket = $b['ticket'] ?? '';
                if (!$ticket) {
                    $ticket = 'B' . str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT);
                    $t = $conn->prepare("UPDATE bookings SET ticket = ? WHERE id = ?");
                    if (!$t) {
                        throw new Exception('Failed to prepare ticket update: ' . $conn->error);
                    }
                    $t->bind_param('si', $ticket, $b['id']);
                    if (!$t->execute()) {
                        throw new Exception('Failed to execute ticket update: ' . $t->error);
                    }
                    $t->close();
                }

                // Update booking status
                $u = $conn->prepare("UPDATE bookings SET status = 'booked', updated_at = NOW() WHERE id = ?");
                if (!$u) {
                    throw new Exception('Failed to prepare booking update: ' . $conn->error);
                }
                $u->bind_param('i', $b['id']);
                if (!$u->execute()) {
                    throw new Exception('Failed to execute booking update: ' . $u->error);
                }
                $u->close();

                // Insert admin log
                $admin_log_id = insert_admin_log_and_get_id($conn, $admin_id, (int)$b['id'], 'approve', "Ticket: $ticket");
                $all_admin_log_ids[] = $admin_log_id;
                
                $tickets[] = $ticket;
                $updated_count++;
            }

            // Send ONE email for the entire session with all tickets
            if ($updated_count > 0) {
                $tickets_display = count($tickets) === 1 
                    ? $tickets[0] 
                    : implode(', ', $tickets);
                
                $booking_word = count($tickets) === 1 ? 'booking' : 'bookings';
                
                $html = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f5f5f5;'>
    <table role='presentation' style='width: 100%; border-collapse: collapse;'>
        <tr>
            <td style='padding: 40px 20px;'>
                <table role='presentation' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                    <!-- Header -->
                    <tr>
                        <td style='padding: 40px 40px 30px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px 8px 0 0;'>
                            <div style='width: 60px; height: 60px; background-color: #ffffff; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;'>
                                <span style='font-size: 32px;'>✓</span>
                            </div>
                            <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;'>Booking Approved</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style='padding: 40px;'>
                            <p style='margin: 0 0 24px; color: #333333; font-size: 16px; line-height: 1.6;'>
                                Dear {$user['username']},
                            </p>
                            <p style='margin: 0 0 24px; color: #333333; font-size: 16px; line-height: 1.6;'>
                                Great news! Your {$booking_word} " . (count($tickets) === 1 ? 'has' : 'have') . " been approved and confirmed.
                            </p>
                            
                            <!-- Booking Details Box -->
                            <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 0 0 30px; background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;'>
                                <tr>
                                    <td style='padding: 20px;'>
                                        <table role='presentation' style='width: 100%;'>
                                            <tr>
                                                <td style='padding: 8px 0; color: #6c757d; font-size: 14px; font-weight: 500;'>Booking Session:</td>
                                                <td style='padding: 8px 0; color: #333333; font-size: 14px; font-weight: 600; text-align: right;'>{$session_id}</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #6c757d; font-size: 14px; font-weight: 500;'>Ticket " . (count($tickets) === 1 ? 'Number' : 'Numbers') . ":</td>
                                                <td style='padding: 8px 0; color: #333333; font-size: 14px; font-weight: 600; text-align: right;'>{$tickets_display}</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #6c757d; font-size: 14px; font-weight: 500;'>Total Bookings:</td>
                                                <td style='padding: 8px 0; color: #333333; font-size: 14px; font-weight: 600; text-align: right;'>" . count($tickets) . "</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #6c757d; font-size: 14px; font-weight: 500;'>Status:</td>
                                                <td style='padding: 8px 0; text-align: right;'>
                                                    <span style='display: inline-block; padding: 4px 12px; background-color: #d4edda; color: #155724; font-size: 13px; font-weight: 600; border-radius: 12px;'>Confirmed</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- CTA Button -->
                            <table role='presentation' style='width: 100%; margin: 0 0 24px;'>
                                <tr>
                                    <td style='text-align: center;'>
                                        <a href='{$link}' style='display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);'>View Booking Details</a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style='margin: 0; color: #6c757d; font-size: 14px; line-height: 1.6;'>
                                If you have any questions or need to make changes, please contact our support team.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style='padding: 24px 40px; background-color: #f8f9fa; border-radius: 0 0 8px 8px; text-align: center;'>
                            <p style='margin: 0 0 8px; color: #6c757d; font-size: 13px;'>
                                This is an automated confirmation email.
                            </p>
                            <p style='margin: 0; color: #adb5bd; font-size: 12px;'>
                                © " . date('Y') . " All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
                $alt  = "Your {$booking_word} (Session: {$session_id}, Tickets: {$tickets_display}) " . (count($tickets) === 1 ? 'has' : 'have') . " been approved and confirmed. View your booking details at: {$link}";

                $user_msgid = null;
                $email_status = 'failed';
                $email_error = null;

                try {
                    if (function_exists('send_mail_return_id')) {
                        $user_msgid = send_mail_return_id($user['email'], $user['username'], "Re: $subject_base", $html, $alt);
                        $email_status = $user_msgid ? 'sent' : 'failed';
                    } elseif (function_exists('send_mail')) {
                        $ok = send_mail($user['email'], $user['username'], "Re: $subject_base", $html, $alt);
                        $user_msgid = $ok ? 'sent-no-msgid' : null;
                        $email_status = $ok ? 'sent' : 'failed';
                    } else {
                        $email_error = 'Mail function not available';
                        log_error('Mail function not available');
                    }
                } catch (Exception $ex) {
                    $email_error = $ex->getMessage();
                    log_error('Email error (approve): ' . $email_error);
                }

                // Log email for first booking with all admin_log_ids combined
                insert_email_log(
                    $conn,
                    (int)$bookings[0]['id'],  // Use first booking ID
                    $user['email'],
                    'user',
                    "Re: $subject_base",
                    $user_msgid,
                    null,
                    $email_status,
                    $email_error,
                    $all_admin_log_ids[0] ?? null
                );
            }

            $conn->commit();
            send_json_response([
                'success' => true,
                'action' => 'approve',
                'updated' => $updated_count
            ]);
        }

        // ---------------- REJECT ----------------
        if ($action === 'reject') {
            if ($reason === '') $reason = 'Rejected by admin';

            $all_admin_log_ids = [];
            
            foreach ($bookings as $b) {
                if (($b['status'] ?? '') !== 'pending') continue;

                // Update booking status
                $u = $conn->prepare("
                    UPDATE bookings
                    SET status = 'rejected',
                        cancel_reason = ?,
                        cancelled_by = ?,
                        cancelled_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                if (!$u) {
                    throw new Exception('Failed to prepare rejection update: ' . $conn->error);
                }
                $u->bind_param('sii', $reason, $admin_id, $b['id']);
                if (!$u->execute()) {
                    throw new Exception('Failed to execute rejection update: ' . $u->error);
                }
                $u->close();

                // Insert admin log
                $admin_log_id = insert_admin_log_and_get_id($conn, $admin_id, (int)$b['id'], 'reject', $reason);
                $all_admin_log_ids[] = $admin_log_id;
                
                $updated_count++;
            }

            // Send ONE email for the entire session
            if ($updated_count > 0) {
                $booking_word = $updated_count === 1 ? 'booking' : 'bookings';
                
                $html = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f5f5f5;'>
    <table role='presentation' style='width: 100%; border-collapse: collapse;'>
        <tr>
            <td style='padding: 40px 20px;'>
                <table role='presentation' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                    <!-- Header -->
                    <tr>
                        <td style='padding: 40px 40px 30px; text-align: center; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border-radius: 8px 8px 0 0;'>
                            <div style='width: 60px; height: 60px; background-color: #ffffff; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;'>
                                <span style='font-size: 32px;'>✕</span>
                            </div>
                            <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;'>Booking Declined</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style='padding: 40px;'>
                            <p style='margin: 0 0 24px; color: #333333; font-size: 16px; line-height: 1.6;'>
                                Dear {$user['username']},
                            </p>
                            <p style='margin: 0 0 24px; color: #333333; font-size: 16px; line-height: 1.6;'>
                                We regret to inform you that your {$booking_word} " . ($updated_count === 1 ? 'has' : 'have') . " been declined.
                            </p>
                            
                            <!-- Booking Details Box -->
                            <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 0 0 30px; background-color: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;'>
                                <tr>
                                    <td style='padding: 20px;'>
                                        <table role='presentation' style='width: 100%;'>
                                            <tr>
                                                <td style='padding: 8px 0; color: #6c757d; font-size: 14px; font-weight: 500;'>Booking Session:</td>
                                                <td style='padding: 8px 0; color: #333333; font-size: 14px; font-weight: 600; text-align: right;'>{$session_id}</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #6c757d; font-size: 14px; font-weight: 500;'>Total Bookings:</td>
                                                <td style='padding: 8px 0; color: #333333; font-size: 14px; font-weight: 600; text-align: right;'>{$updated_count}</td>
                                            </tr>
                                            <tr>
                                                <td style='padding: 8px 0; color: #6c757d; font-size: 14px; font-weight: 500;'>Status:</td>
                                                <td style='padding: 8px 0; text-align: right;'>
                                                    <span style='display: inline-block; padding: 4px 12px; background-color: #f8d7da; color: #721c24; font-size: 13px; font-weight: 600; border-radius: 12px;'>Rejected</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Reason Box -->
                            <table role='presentation' style='width: 100%; border-collapse: collapse; margin: 0 0 30px; background-color: #fff3cd; border-radius: 6px; border-left: 4px solid #ffc107;'>
                                <tr>
                                    <td style='padding: 20px;'>
                                        <p style='margin: 0 0 8px; color: #856404; font-size: 14px; font-weight: 600;'>Reason for Rejection:</p>
                                        <p style='margin: 0; color: #856404; font-size: 14px; line-height: 1.5;'>" . htmlspecialchars($reason) . "</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- CTA Button -->
                            <table role='presentation' style='width: 100%; margin: 0 0 24px;'>
                                <tr>
                                    <td style='text-align: center;'>
                                        <a href='{$link}' style='display: inline-block; padding: 14px 32px; background-color: #6c757d; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: 600;'>View Booking Details</a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style='margin: 0; color: #6c757d; font-size: 14px; line-height: 1.6;'>
                                If you have any questions or would like to submit a new booking request, please contact our support team.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style='padding: 24px 40px; background-color: #f8f9fa; border-radius: 0 0 8px 8px; text-align: center;'>
                            <p style='margin: 0 0 8px; color: #6c757d; font-size: 13px;'>
                                This is an automated notification email.
                            </p>
                            <p style='margin: 0; color: #adb5bd; font-size: 12px;'>
                                © " . date('Y') . " All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
                $alt  = "Your {$booking_word} (Session: {$session_id}) " . ($updated_count === 1 ? 'has' : 'have') . " been rejected. Reason: {$reason}. View your booking details at: {$link}";

                $user_msgid = null;
                $email_status = 'failed';
                $email_error = null;

                try {
                    if (function_exists('send_mail_return_id')) {
                        $user_msgid = send_mail_return_id($user['email'], $user['username'], "Re: $subject_base", $html, $alt);
                        $email_status = $user_msgid ? 'sent' : 'failed';
                    } elseif (function_exists('send_mail')) {
                        $ok = send_mail($user['email'], $user['username'], "Re: $subject_base", $html, $alt);
                        $user_msgid = $ok ? 'sent-no-msgid' : null;
                        $email_status = $ok ? 'sent' : 'failed';
                    } else {
                        $email_error = 'Mail function not available';
                        log_error('Mail function not available');
                    }
                } catch (Exception $ex) {
                    $email_error = $ex->getMessage();
                    log_error('Email error (reject): ' . $email_error);
                }

                // Log email for first booking
                insert_email_log(
                    $conn,
                    (int)$bookings[0]['id'],  // Use first booking ID
                    $user['email'],
                    'user',
                    "Re: $subject_base",
                    $user_msgid,
                    null,
                    $email_status,
                    $email_error,
                    $all_admin_log_ids[0] ?? null
                );
            }

            $conn->commit();
            send_json_response([
                'success' => true,
                'action' => 'reject',
                'updated' => $updated_count
            ]);
        }

        throw new Exception('Invalid action specified: ' . $action);

    } catch (Exception $e) {
        $conn->rollback();
        log_error('Transaction error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
        send_json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} catch (Throwable $e) {
    // Catch any fatal errors
    log_error('Fatal error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    exit;
}
?>