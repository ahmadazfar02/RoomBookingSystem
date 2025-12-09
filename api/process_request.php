<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

//--------------------------------------------------
// ACCESS CONTROL: Only Admin can use this page
//--------------------------------------------------
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true ||
    strcasecmp(trim($_SESSION["User_Type"]), 'Admin') != 0) {
    header("location: ../loginterface.html");
    exit;
}

//--------------------------------------------------
// ENSURE POST REQUEST
//--------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin/reservation_request.php");
    exit;
}

//--------------------------------------------------
// ADMIN ID (from users.id)
//--------------------------------------------------
$admin_id = $_SESSION['id'];   // FIXED - correct source for user ID
if (!$admin_id) {
    die("ERROR: Admin ID not found in session.");
}

//--------------------------------------------------
// INPUTS
//--------------------------------------------------
$action     = $_POST['action'] ?? '';
$session_id = $_POST['session_id'] ?? '';
$reason     = trim($_POST['reason'] ?? '');

if (empty($session_id)) {
    header("Location: reservation_request.php?err=nosession");
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

//--------------------------------------------------
// GET ALL BOOKINGS IN THE SESSION
//--------------------------------------------------
$stmt = $conn->prepare("
    SELECT id, status, ticket 
    FROM bookings 
    WHERE session_id = ? 
    FOR UPDATE
");
$stmt->bind_param("s", $session_id);
$stmt->execute();
$res = $stmt->get_result();

$booking_ids = [];
while ($row = $res->fetch_assoc()) {
    $booking_ids[] = $row;
}
$stmt->close();

if (count($booking_ids) === 0) {
    $conn->rollback();
    header("Location: reservation_request.php?err=nobookings");
    exit;
}

//--------------------------------------------------
// ACTION: APPROVE ALL BOOKINGS
//--------------------------------------------------
if ($action === "approve") {

    foreach ($booking_ids as $b) {

        if ($b['status'] !== 'pending') continue;

        // Generate ticket if none exists
        $ticket = $b['ticket'];
        if (empty($ticket)) {
            $ticket = "B" . str_pad($b['id'], 4, '0', STR_PAD_LEFT);

            $stmt = $conn->prepare("UPDATE bookings SET ticket = ? WHERE id = ?");
            $stmt->bind_param("si", $ticket, $b['id']);
            $stmt->execute();
            $stmt->close();
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
    }

    $conn->commit();
    header("Location: reservation_request.php?msg=approved");
    exit;
}

//--------------------------------------------------
// ACTION: REJECT ALL BOOKINGS
//--------------------------------------------------
if ($action === "reject") {

    if (empty($reason)) {
        $reason = "Rejected by admin (no reason provided)";
    }

    foreach ($booking_ids as $b) {
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
    }

    $conn->commit();
    header("Location: reservation_request.php?msg=rejected");
    exit;
}

//--------------------------------------------------
// ACTION: DELETE BOOKINGS
//--------------------------------------------------
if ($action === "delete") {

    foreach ($booking_ids as $b) {

        // Delete booking
        $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
        $stmt->bind_param("i", $b['id']);
        $stmt->execute();
        $stmt->close();

        insert_admin_log($conn, $admin_id, $b['id'], "delete", "Booking deleted.");
    }

    $conn->commit();
    header("Location: reservation_request.php?msg=deleted");
    exit;
}

//--------------------------------------------------
// UNKNOWN ACTION → ROLLBACK
//--------------------------------------------------
$conn->rollback();
header("Location: ../admin/reservation_request.php");
exit;

?>
