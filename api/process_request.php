<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
// 1. INCLUDE EMAIL HELPER
require_once __DIR__ . '/../api/email_helper.php';

// Set JSON header FIRST
header('Content-Type: application/json');

//--------------------------------------------------
// ACCESS CONTROL: Only Admin can use this page
//--------------------------------------------------
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true ||
    strcasecmp(trim($_SESSION["User_Type"]), 'Admin') != 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

//--------------------------------------------------
// ENSURE POST REQUEST
//--------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

//--------------------------------------------------
// ADMIN ID (from users.id)
//--------------------------------------------------
$admin_id = $_SESSION['id'];
if (!$admin_id) {
    echo json_encode(['success' => false, 'message' => 'Admin ID not found in session']);
    exit;
}

//--------------------------------------------------
// INPUTS
//--------------------------------------------------
$action     = $_POST['action'] ?? '';
$session_id = $_POST['session_id'] ?? '';
$reason     = trim($_POST['reason'] ?? '');

if (empty($session_id)) {
    echo json_encode(['success' => false, 'message' => 'Session ID is required']);
    exit;
}

//--------------------------------------------------
// LOGGING FUNCTION
//--------------------------------------------------
function insert_admin_log($conn, $admin_id, $booking_id, $action, $note = null)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $sql = "INSERT INTO admin_logs (admin_id, booking_id, action, note, ip_address) 
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $admin_id, $booking_id, $action, $note, $ip);
    $stmt->execute();
    $stmt->close();
}

//--------------------------------------------------
// BEGIN TRANSACTION
//--------------------------------------------------
$conn->begin_transaction();

try {
    //--------------------------------------------------
    // GET ALL BOOKINGS IN THE SESSION (AND USER/ROOM INFO)
    //--------------------------------------------------
    $stmt = $conn->prepare("
        SELECT 
            b.id, b.status, b.ticket, b.user_id, b.slot_date, b.time_start, b.time_end, b.purpose,
            u.Fullname AS user_name, u.Email AS user_email,
            r.room_id AS room_no, r.name AS room_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN rooms r ON b.room_id = r.room_id
        WHERE b.session_id = ? 
        FOR UPDATE
    ");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $session_bookings = [];
    while ($row = $res->fetch_assoc()) {
        $session_bookings[] = $row;
    }
    $stmt->close();

    if (count($session_bookings) === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'No bookings found for this session']);
        exit;
    }

    // Since all bookings in a session are by the same user and for the same date/room,
    // we can extract the common data from the first row.
    $first_booking = $session_bookings[0];
    $user_email = $first_booking['user_email'];
    $user_name  = $first_booking['user_name'];
    $room_info  = htmlspecialchars($first_booking['room_name'] . " (" . $first_booking['room_no'] . ")");
    $slot_date  = htmlspecialchars($first_booking['slot_date']);
    $purpose    = htmlspecialchars($first_booking['purpose']);

    // Build Time Slots string for email
    $time_slots = [];
    foreach ($session_bookings as $b) {
        // Only include slots that are currently pending for approval/rejection logic
        if ($b['status'] === 'pending' || $action === 'delete') {
            $time_slots[] = htmlspecialchars($b['time_start'] . " - " . $b['time_end']);
        }
    }
    $time_slots_string = implode(' | ', $time_slots);

    // Build the booking details HTML for the email
    $booking_details_html = "
        <p><strong>Room:</strong> $room_info</p>
        <p><strong>Date:</strong> $slot_date</p>
        <p><strong>Time Slots:</strong> $time_slots_string</p>
        <p><strong>Purpose:</strong> $purpose</p>
    ";

    //--------------------------------------------------
    // ACTION: APPROVE ALL BOOKINGS
    //--------------------------------------------------
    if ($action === "approve") {

        $approved_count = 0;
        foreach ($session_bookings as $b) {

            if ($b['status'] !== 'pending') continue;

            // Generate ticket if none exists
            $ticket = $b['ticket'];
            if (empty($ticket)) {
                $ticket = "B" . str_pad($b['id'], 4, '0', STR_PAD_LEFT);

                $stmt = $conn->prepare("UPDATE bookings SET ticket = ? WHERE id = ?");
                $stmt->bind_param("si", $ticket, $b['id']);
                $stmt->execute();
                $stmt->close();
                
                // If this is the first booking being processed, update the ticket in the email content
                if ($approved_count == 0) {
                    $booking_details_html .= "<p><strong>Ticket ID:</strong> $ticket</p>";
                }
            }

            // Approve booking
            $stmt = $conn->prepare("
                UPDATE bookings 
                SET status='booked', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $b['id']);
            $stmt->execute();
            $stmt->close();

            insert_admin_log($conn, $admin_id, $b['id'], "approve", "Approved | Ticket: $ticket");
            $approved_count++;
        }

        if ($approved_count > 0) {
            // 2. SEND EMAIL ON SUCCESSFUL APPROVAL
            sendStatusEmail($user_email, $user_name, 'Approved', $booking_details_html);
        }

        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Successfully approved $approved_count booking(s)",
            'count' => $approved_count
        ]);
        exit;
    }

    //--------------------------------------------------
    // ACTION: REJECT ALL BOOKINGS
    //--------------------------------------------------
    if ($action === "reject") {

        if (empty($reason)) {
            $reason = "Rejected by admin (no reason provided)";
        }
        $reason_html = htmlspecialchars($reason);
        $booking_details_html .= "<p style='color:#dc2626; margin-top: 10px;'><strong>Rejection Reason:</strong> $reason_html</p>";

        $rejected_count = 0;
        foreach ($session_bookings as $b) {
            if ($b['status'] !== 'pending') continue;

            $stmt = $conn->prepare("
                UPDATE bookings 
                SET status='rejected', 
                    cancel_reason=?, 
                    cancelled_by=?, 
                    cancelled_at=NOW(), 
                    updated_at=NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $reason, $admin_id, $b['id']);
            $stmt->execute();
            $stmt->close();

            insert_admin_log($conn, $admin_id, $b['id'], "reject", $reason);
            $rejected_count++;
        }

        if ($rejected_count > 0) {
            // 2. SEND EMAIL ON SUCCESSFUL REJECTION
            sendStatusEmail($user_email, $user_name, 'Rejected', $booking_details_html);
        }

        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Successfully rejected $rejected_count booking(s)",
            'count' => $rejected_count
        ]);
        exit;
    }

    //--------------------------------------------------
    // ACTION: DELETE BOOKINGS (NO EMAIL SENT)
    //--------------------------------------------------
    if ($action === "delete") {

        $deleted_count = 0;
        foreach ($session_bookings as $b) {

            // Delete booking
            $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->bind_param("i", $b['id']);
            $stmt->execute();
            $stmt->close();

            insert_admin_log($conn, $admin_id, $b['id'], "delete", "Booking deleted.");
            $deleted_count++;
        }

        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Successfully deleted $deleted_count booking(s)",
            'count' => $deleted_count
        ]);
        exit;
    }

    //--------------------------------------------------
    // UNKNOWN ACTION → ROLLBACK
    //--------------------------------------------------
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Database error in process_request.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>