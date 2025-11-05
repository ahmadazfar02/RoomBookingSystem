<?php
// book_slot.php - combined: GET bookings, POST booking(s), POST cancel
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
session_start();

$logfile = __DIR__ . '/booking_debug.log';
function dbg($msg) {
    global $logfile;
    $time = date('Y-m-d H:i:s');
    @file_put_contents($logfile, "[$time] $msg\n", FILE_APPEND);
}

// include DB connect - must provide $conn (mysqli)
require 'db_connect.php';
if (!isset($conn)) {
    dbg("No \$conn found");
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'msg'=>'DB connection not initialized']);
    exit;
}

// identify logged user (expect session)
$me = $_SESSION['id'] ?? null;
$me_type = $_SESSION['user_type'] ?? null;

// ------------------ GET handlers ------------------ //
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // endpoint: GET ?room=...  -> returns array of bookings for that room
    if (isset($_GET['room'])) {
        $room = $_GET['room'];
        $sql = "SELECT id, user_id, slot_date, time_start, time_end, purpose, description, tel, status
                FROM timetable
                WHERE room = ?
                AND status IN ('pending','booked')
                ORDER BY slot_date, time_start";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            dbg("GET prepare failed: ".$conn->error);
            echo json_encode([]);
            exit;
        }
        $stmt->bind_param('s', $room);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $time_slot = substr($row['time_start'],0,5) . '-' . substr($row['time_end'],0,5);
            $out[] = [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'date' => $row['slot_date'],
                'time_slot' => $time_slot,
                'purpose' => $row['purpose'],
                'description' => $row['description'],
                'tel' => $row['tel'],
                'status' => $row['status']
            ];
        }
        $stmt->close();
        header('Content-Type: application/json');
        echo json_encode($out);
        exit;
    }

    // optional: GET status listing for booking_status page
    if (isset($_GET['view']) && $_GET['view'] === 'status') {
        $filter = $_GET['filter'] ?? 'all';
        $allowedFilters = ['pending','booked','cancelled','all','rejected'];
        if (!in_array($filter, $allowedFilters)) $filter = 'all';
        $isAdmin = (strtolower($me_type) === 'admin');

        $sql = "SELECT id, user_id, room, slot_date, time_start, time_end, purpose, description, tel, status
                FROM timetable
                WHERE 1=1";
        $params = [];
        $types = '';
        if (!$isAdmin) {
            $sql .= " AND user_id = ?";
            $types .= 'i';
            $params[] = $me;
        }
        if ($filter !== 'all') {
            $sql .= " AND status = ?";
            $types .= 's';
            $params[] = $filter;
        }
        $sql .= " ORDER BY slot_date DESC, time_start DESC";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            dbg("status prepare failed: ".$conn->error);
            echo json_encode([]);
            exit;
        }
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'room' => $row['room'],
                'slot_date' => $row['slot_date'],
                'time_start' => $row['time_start'],
                'time_end' => $row['time_end'],
                'purpose' => $row['purpose'],
                'description' => $row['description'],
                'tel' => $row['tel'],
                'status' => $row['status'],
                'is_owner' => ($row['user_id'] == $me) ? 1 : 0,
                'is_admin' => $isAdmin ? 1 : 0
            ];
        }
        $stmt->close();
        header('Content-Type: application/json');
        echo json_encode($out);
        exit;
    }

    // default GET fallback
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// ------------------ POST handler ------------------ //
$raw = file_get_contents('php://input');
dbg("Raw POST: " . substr($raw,0,2000));
$data = json_decode($raw, true);
if (!is_array($data)) {
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'msg'=>'Invalid JSON: '.json_last_error_msg()]);
    exit;
}

// CANCEL action (if action == 'cancel')
$action = $data['action'] ?? null;
if ($action === 'cancel') {
    $booking_id = isset($data['booking_id']) ? intval($data['booking_id']) : 0;
    $reason = trim($data['reason'] ?? '');

    if (!$booking_id) {
        echo json_encode(['success'=>false,'msg'=>'Missing booking_id']);
        exit;
    }
    if (!$me) {
        echo json_encode(['success'=>false,'msg'=>'Not logged in']);
        exit;
    }

    // fetch booking
    $stmt = $conn->prepare("SELECT id, user_id, status FROM timetable WHERE id = ? LIMIT 1");
    if (!$stmt) {
        dbg("Cancel fetch prepare failed: ".$conn->error);
        echo json_encode(['success'=>false,'msg'=>'DB error']);
        exit;
    }
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        echo json_encode(['success'=>false,'msg'=>'Booking not found']);
        exit;
    }
    $row = $res->fetch_assoc();
    $stmt->close();

    // policy: owner can cancel their pending booking; admin can cancel anything
    $isAdmin = (strtolower($me_type) === 'admin');
    $isOwner = ($row['user_id'] == $me);
    if (!($isAdmin || ($isOwner && $row['status'] === 'pending'))) {
        echo json_encode(['success'=>false,'msg'=>'Not allowed to cancel this booking']);
        exit;
    }

    // update status to cancelled (soft cancel)
    $now = date('Y-m-d H:i:s');
    // try to update with audit columns if exist, else update status only
    $upd = $conn->prepare("UPDATE timetable SET status='cancelled' WHERE id = ?");
    if (!$upd) {
        dbg("Cancel update prepare failed: ".$conn->error);
        echo json_encode(['success'=>false,'msg'=>'DB error']);
        exit;
    }
    $upd->bind_param('i', $booking_id);
    $ok = $upd->execute();
    $upd->close();

    if ($ok) {
        dbg("Booking {$booking_id} cancelled by user {$me}");
        echo json_encode(['success'=>true,'booking_id'=>$booking_id,'status'=>'cancelled']);
    } else {
        dbg("Cancel failed: " . $conn->error);
        echo json_encode(['success'=>false,'msg'=>'DB update failed']);
    }
    exit;
}

// ---------- Otherwise: assume booking POST (payload with room/purpose/tel/slots) ---------- //
$room = $data['room'] ?? '';
$purpose = trim($data['purpose'] ?? '');
$description = trim($data['description'] ?? '');
$tel = trim($data['tel'] ?? '');
$slots = $data['slots'] ?? [];

dbg("Book payload: room={$room}, purpose=" . substr($purpose,0,80) . ", tel={$tel}, slots_count=" . (is_array($slots)?count($slots):0));

if (!$room || !$purpose || !$tel || !is_array($slots) || count($slots)===0) {
    echo json_encode(['success'=>false,'msg'=>'Missing required fields (room/purpose/tel/slots)']);
    exit;
}

// prepare statements
$insertSql = "INSERT INTO timetable (user_id, room, purpose, description, tel, slot_date, time_start, time_end, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
$insertStmt = $conn->prepare($insertSql);
if (!$insertStmt) {
    dbg("Insert prepare failed: " . $conn->error);
    echo json_encode(['success'=>false,'msg'=>'Prepare failed: '.$conn->error]);
    exit;
}

$checkSql = "SELECT id, status FROM timetable WHERE room = ? AND slot_date = ? AND time_start = ? AND status IN ('booked','pending') LIMIT 1";
$checkStmt = $conn->prepare($checkSql);
if (!$checkStmt) {
    dbg("Check prepare failed: " . $conn->error);
    echo json_encode(['success'=>false,'msg'=>'Prepare failed: '.$conn->error]);
    exit;
}

$results = [];
foreach ($slots as $s) {
    $date = $s['date'] ?? '';
    $slotVal = $s['slot'] ?? '';
    if (!$date || !$slotVal) {
        $results[] = ['date'=>$date,'slot'=>$slotVal,'success'=>false,'msg'=>'Invalid slot format'];
        continue;
    }
    $parts = explode('-', $slotVal);
    if (count($parts) != 2) {
        $results[] = ['date'=>$date,'slot'=>$slotVal,'success'=>false,'msg'=>'Invalid slot times'];
        continue;
    }
    $start = date('H:i:s', strtotime($parts[0]));
    $end   = date('H:i:s', strtotime($parts[1]));

    // check conflict (only against pending/booked)
    $checkStmt->bind_param('sss', $room, $date, $start);
    if (!$checkStmt->execute()) {
        dbg("Check execute failed for {$room},{$date},{$start}: " . $checkStmt->error);
        $results[] = ['date'=>$date,'slot'=>$slotVal,'success'=>false,'msg'=>'DB check failed'];
        continue;
    }
    $checkRes = $checkStmt->get_result();
    if ($checkRes && $checkRes->num_rows > 0) {
        $existing = $checkRes->fetch_assoc();
        $results[] = ['date'=>$date,'slot'=>$slotVal,'success'=>false,'msg'=>'Slot already reserved ('.$existing['status'].')'];
        continue;
    }

    // insert pending booking
    $insertStmt->bind_param('isssssss', $me, $room, $purpose, $description, $tel, $date, $start, $end);
    if ($insertStmt->execute()) {
        // capture the inserted ID and return it to client
        $newId = $insertStmt->insert_id;
        $results[] = ['id' => (int)$newId, 'date'=>$date,'slot'=>$slotVal,'success'=>true,'status'=>'pending'];
        dbg("Inserted pending booking id={$newId}: user={$me}, room={$room}, date={$date}, slot={$slotVal}");
    } else {
        $errno = $insertStmt->errno;
        $errstr = $insertStmt->error;
        dbg("Insert failed for {$date},{$slotVal}: errno={$errno} err={$errstr}");
        if ($errno == 1062) {
            $results[] = ['date'=>$date,'slot'=>$slotVal,'success'=>false,'msg'=>'SQL duplicate (unique index)'];
        } else {
            $results[] = ['date'=>$date,'slot'=>$slotVal,'success'=>false,'msg'=>"SQL Error {$errno}: {$errstr}"];
        }
    }

}

$insertStmt->close();
$checkStmt->close();
$conn->close();

dbg("Returning results: " . json_encode($results));
header('Content-Type: application/json');
echo json_encode(['success'=>true,'results'=>$results]);
exit;
?>
