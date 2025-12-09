<?php
// book_slot.php - GET bookings by room_id, GET status, POST bookings, POST cancel (bookings table; session_id added)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
session_start();

$logfile = __DIR__ . '/booking_debug.log';
function dbg($m) {
    global $logfile;
    $time = date('Y-m-d H:i:s');
    if (is_array($m) || is_object($m)) $m = print_r($m, true);
    @file_put_contents($logfile, "[$time] $m\n", FILE_APPEND);
}

header('Content-Type: application/json; charset=utf-8');

// include DB connect - must set $conn (mysqli)
require_once __DIR__ . '/db_connect.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    dbg("db_connect missing or \$conn not mysqli");
    echo json_encode(['success'=>false, 'msg'=>'Database connection not available']);
    exit;
}

// identify logged user
$me = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
$me_type = '';
if (isset($_SESSION['user_type'])) $me_type = strtolower(trim($_SESSION['user_type']));
elseif (isset($_SESSION['User_Type'])) $me_type = strtolower(trim($_SESSION['User_Type']));

/* ---------- GET handlers ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET bookings for timetable view: ?room=ROOM_ID
    if (isset($_GET['room'])) {
        $room_id = $_GET['room'];

        // NEW CODE - Add 'maintenance' to the status list:
        $sql = "SELECT id, user_id, slot_date, time_start, time_end, purpose, description, tel, status
                FROM bookings
                WHERE room_id = ?
                AND status IN ('pending','booked','maintenance')
                ORDER BY slot_date, time_start";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            dbg("GET prepare failed: " . $conn->error);
            echo json_encode([]);
            exit;
        }
        $stmt->bind_param('s', $room_id);
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
                'status' => $row['status']  // This will now include 'maintenance'
            ];
        }
        $stmt->close();
        echo json_encode($out);
        exit;
    }

    // GET status list for booking_status page: ?view=status
    if (isset($_GET['view']) && $_GET['view'] === 'status') {
        $filter = $_GET['filter'] ?? 'all';
        $allowedFilters = ['pending','booked','cancelled','all','rejected'];
        if (!in_array($filter, $allowedFilters)) $filter = 'all';
        $isAdmin = ($me_type === 'admin');

        $sql = "SELECT id, user_id, room_id, slot_date, time_start, time_end, purpose, description, tel, status
                FROM bookings WHERE 1=1";
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
            dbg("status prepare failed: " . $conn->error);
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
                'room' => $row['room_id'],
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
        echo json_encode($out);
        exit;
    }

    // fallback
    echo json_encode([]);
    exit;
}

/* ---------- POST handlers ---------- */

// read JSON payload
$raw = file_get_contents('php://input');
dbg("Raw POST (book_slot): " . substr($raw,0,2000));
$data = json_decode($raw, true);
if (!is_array($data)) {
    $err = json_last_error_msg();
    dbg("JSON decode error: " . $err . " raw: " . substr($raw,0,2000));
    echo json_encode(['success'=>false,'msg'=>'Invalid JSON payload: '.$err]);
    exit;
}

// CANCEL by booking id
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
    $stmt = $conn->prepare("SELECT id, user_id, room_id, slot_date, time_start, status FROM bookings WHERE id = ? LIMIT 1");
    if (!$stmt) {
        dbg("Cancel fetch prepare failed: " . $conn->error);
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

    $isAdmin = ($me_type === 'admin');
    $isOwner = ($row['user_id'] == $me);

    // permission: admin can cancel anything; owner can cancel their pending or booked (you may tighten to pending-only)
    if (!($isAdmin || ($isOwner && in_array(strtolower($row['status']), ['pending','booked'])))) {
        echo json_encode(['success'=>false,'msg'=>'Not allowed to cancel this booking']);
        exit;
    }

    $update_ok = false;
    $now = date('Y-m-d H:i:s');

    // try to update with audit columns if they exist
    $try = $conn->prepare("UPDATE bookings SET status = 'cancelled', cancelled_by = ?, cancelled_at = ?, cancel_reason = ? WHERE id = ?");
    if ($try !== false) {
        $try->bind_param('issi', $me, $now, $reason, $booking_id);
        $update_ok = $try->execute();
        $try->close();
    } else {
        $fallback = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        if ($fallback !== false) {
            $fallback->bind_param('i', $booking_id);
            $update_ok = $fallback->execute();
            $fallback->close();
        } else {
            dbg("Cancel update prepare failed: " . $conn->error);
            echo json_encode(['success'=>false,'msg'=>'DB update error']);
            exit;
        }
    }

    if ($update_ok) {
        dbg("Booking {$booking_id} set to cancelled by user {$me}");
        echo json_encode(['success'=>true,'booking_id'=>$booking_id,'status'=>'cancelled']);
        exit;
    } else {
        $errno = $conn->errno;
        $err = $conn->error;
        dbg("Cancel update failed id={$booking_id} errno={$errno} err={$err}");
        echo json_encode(['success'=>false,'msg'=>'DB update failed: '.$err]);
        exit;
    }
}

// Otherwise treat as booking creation
$room_id = trim($data['room'] ?? '');
$purpose = trim($data['purpose'] ?? '');
$description = trim($data['description'] ?? '');
$tel = trim($data['tel'] ?? '');
$slots = $data['slots'] ?? [];

dbg("Book request: user={$me} room_id={$room_id} slots_count=" . (is_array($slots)?count($slots):0));

if (!$me) {
    echo json_encode(['success'=>false,'msg'=>'Not logged in']);
    exit;
}
if (!$room_id || !$purpose || !$tel || !is_array($slots) || count($slots) === 0) {
    echo json_encode(['success'=>false,'msg'=>'Missing required fields (room/purpose/tel/slots)']);
    exit;
}

// detect whether bookings table has session_id column
$hasSessionCol = false;
$checkColRes = $conn->query("SHOW COLUMNS FROM bookings LIKE 'session_id'");
if ($checkColRes && $checkColRes->num_rows > 0) $hasSessionCol = true;
dbg("session_id column present: " . ($hasSessionCol ? 'yes' : 'no'));

// start transaction
// get the current max id in bookings table
$res = $conn->query("SELECT MAX(id) AS max_id FROM bookings");
$row = $res->fetch_assoc();
$nextNum = intval($row['max_id'] ?? 0) + 1;

// friendly session ID for display
$session_id = 'B' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
$slotResult = [
    'id' => (int)$newId,
    'date' => $date,
    'slot' => $slotVal,
    'success' => true,
    'status' => 'pending',
    'active_key' => $activeKey,
    'ticket' => $ticket,
    'session_id' => $session_id  // <- here!
];

$response['session_id'] = $session_id; // useful for front-end grouping



// prepare insert statement (include session_id if available)
if ($hasSessionCol) {
    $insertSql = "INSERT INTO bookings (user_id, room_id, purpose, description, tel, slot_date, time_start, time_end, status, session_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        dbg("Insert prepare failed: " . $conn->error);
        $conn->rollback();
        echo json_encode(['success'=>false,'msg'=>'Prepare failed: '.$conn->error]);
        exit;
    }
} else {
    // fallback to older schema (no session_id)
    $insertSql = "INSERT INTO bookings (user_id, room_id, purpose, description, tel, slot_date, time_start, time_end, status)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        dbg("Insert prepare failed: " . $conn->error);
        $conn->rollback();
        echo json_encode(['success'=>false,'msg'=>'Prepare failed: '.$conn->error]);
        exit;
    }
}

// prepare conflict-check (only consider pending/booked)
$checkSql = "SELECT id, status FROM bookings 
             WHERE room_id = ? AND slot_date = ? AND time_start = ? 
             AND status IN ('booked','pending','maintenance') 
             LIMIT 1";
$checkStmt = $conn->prepare($checkSql);
if (!$checkStmt) {
    dbg("Check prepare failed: " . $conn->error);
    $insertStmt->close();
    $conn->rollback();
    echo json_encode(['success'=>false,'msg'=>'Prepare failed: '.$conn->error]);
    exit;
}

$results = [];
$fatalError = false;
foreach ($slots as $s) {
    if ($fatalError) break;

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

    // conflict check
    $checkStmt->bind_param('sss', $room_id, $date, $start);
    if (!$checkStmt->execute()) {
        dbg("Check execute failed for {$room_id},{$date},{$start}: " . $checkStmt->error);
        $results[] = ['date'=>$date,'slot'=>$slotVal,'success'=>false,'msg'=>'DB check failed'];
        $fatalError = true;
        break;
    }
    $checkRes = $checkStmt->get_result();
    if ($checkRes && $checkRes->num_rows > 0) {
        $existing = $checkRes->fetch_assoc();
        $results[] = ['date'=>$date,'slot'=>$slotVal,'success'=>false,'msg'=>'Slot already reserved ('.$existing['status'].')'];
        continue;
    }

    // attempt insert (bind appropriate params)
    if ($hasSessionCol) {
        $insertStmt->bind_param('issssssss', $me, $room_id, $purpose, $description, $tel, $date, $start, $end, $session_id);
    } else {
        $insertStmt->bind_param('isssssss', $me, $room_id, $purpose, $description, $tel, $date, $start, $end);
    }

    if ($insertStmt->execute()) {
        $newId = $insertStmt->insert_id;

        // generate ticket (e.g., B + 4-digit booking id)
        $ticket = 'B' . str_pad($newId, 4, '0', STR_PAD_LEFT);

        // update booking row with ticket
        $upd_ticket = $conn->prepare("UPDATE bookings SET ticket = ? WHERE id = ?");
        if ($upd_ticket) {
            $upd_ticket->bind_param('si', $ticket, $newId);
            $upd_ticket->execute();
            $upd_ticket->close();
        } else {
            dbg("Ticket update prepare failed: " . $conn->error);
        }

        // optionally return it in response
        $slotResult['ticket'] = $ticket;


        // build active key (BK + 8-digit zero-padded id)
        $activeKey = 'BK' . str_pad($newId, 8, '0', STR_PAD_LEFT);

        // update the row to set active_key (use prepared statement)
        $upd = $conn->prepare("UPDATE bookings SET active_key = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param('si', $activeKey, $newId);
            $upd->execute();
            $upd->close();
        } else {
            dbg("active_key update prepare failed: " . $conn->error);
        }

        // success for this slot
        $slotResult = ['id' => (int)$newId, 'date'=>$date,'slot'=>$slotVal,'success'=>true,'status'=>'pending','active_key'=>$activeKey,'ticket'=>$ticket];
        if ($hasSessionCol) $slotResult['session_id'] = $session_id;
        $results[] = $slotResult;

        dbg("Inserted pending booking id={$newId}: user={$me}, room={$room_id}, date={$date}, slot={$slotVal}, session={$session_id}, ticket={$ticket}");
    } else {
        $errno = $insertStmt->errno;
        $errstr = $insertStmt->error;
        dbg("Insert failed for {$date},{$slotVal}: errno={$errno} err={$errstr}");
        if ($errno == 1062) {
            $results[] = ['date'=>$date,'slot'=>$slotVal,'success'=>false,'msg'=>'SQL duplicate (unique index)'];
            // not fatal: other slots may still insert - depending on your desired behavior you might rollback all
        } else {
            // fatal error: rollback and stop processing further slots
            $results[] = ['date'=>$date,'slot'=>$slotVal,'success'=>false,'msg'=>"SQL Error {$errno}: {$errstr}"];
            $fatalError = true;
            break;
        }
    }
}

// commit or rollback depending on fatalError
if ($fatalError) {
    $conn->rollback();
    dbg("Transaction rolled back due to fatal error. Results: " . json_encode($results));
    $insertStmt->close();
    $checkStmt->close();
    $conn->close();
    echo json_encode(['success'=>false,'msg'=>'Transaction failed, rolled back','results'=>$results]);
    exit;
} else {
    $conn->commit();
    $insertStmt->close();
    $checkStmt->close();
    $conn->close();

    dbg("Returning results: " . json_encode($results));
    $response = ['success'=>true, 'results'=>$results];
    if ($hasSessionCol) $response['session_id'] = $session_id; // useful for front-end grouping
    echo json_encode($response);
    exit;
}
?>
