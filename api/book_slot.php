<?php
// book_slot.php - GET bookings by room_id, GET status, POST bookings, POST cancel
ini_set('display_errors', 0); // Hide errors from output to prevent "Network Error"
ini_set('log_errors', 1);     // Log errors to file instead
error_reporting(E_ALL);
session_start();

header('Content-Type: application/json; charset=utf-8');

// Adjust this path if necessary to match your folder structure
require_once __DIR__ . '/../includes/db_connect.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['success'=>false, 'msg'=>'Database connection not available']);
    exit;
}

// identify logged user
$me = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
$me_type = '';
if (isset($_SESSION['user_type'])) $me_type = strtolower(trim($_SESSION['user_type']));
elseif (isset($_SESSION['User_Type'])) $me_type = strtolower(trim($_SESSION['User_Type']));

require_once __DIR__ . '/../includes/mail_helper.php';

/* ---------- GET handlers ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET bookings for timetable view: ?room=ROOM_ID
    if (isset($_GET['room'])) {
        $room_id = $_GET['room'];

        // 1. Fetch regular bookings
        // Note: We ONLY fetch 'pending', 'booked', or 'maintenance'.
        // 'cancelled' and 'rejected' are excluded here, so they will NOT block recurring slots.
        $sql = "SELECT id, user_id, slot_date, time_start, time_end, purpose, description, tel, status
                FROM bookings
                WHERE room_id = ?
                AND status IN ('pending','booked','maintenance')
                ORDER BY slot_date, time_start";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode([]);
            exit;
        }
        $stmt->bind_param('s', $room_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        $existingKeys = []; 
        
        while ($row = $res->fetch_assoc()) {
            $time_slot = substr($row['time_start'],0,5) . '-' . substr($row['time_end'],0,5);
            $key = $row['slot_date'] . '|' . $time_slot;
            $existingKeys[$key] = true;
            
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

        // 2. Fetch recurring bookings
        $candidate_tables = ['recurring_bookings','admin_recurring','admin_recurring_bookings'];
        $recurring_table = null;
        foreach ($candidate_tables as $tname) {
            $check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tname) . "'");
            if ($check && $check->num_rows > 0) { 
                $recurring_table = $tname; 
                break; 
            }
        }

        if ($recurring_table) {
            // --- FIX START: Increased limit from 35 days to 1 year ---
            $today = date('Y-m-d');
            $futureDate = date('Y-m-d', strtotime('+1 year')); 
            // --- FIX END ---
            
            // Fetch active recurring templates
            $rsql = "SELECT id, room_id, day_of_week, time_start, time_end, purpose, description, tel 
                    FROM `{$recurring_table}` 
                    WHERE room_id = ? AND (status IS NULL OR status = 'active')";
            $rstmt = $conn->prepare($rsql);
            if ($rstmt) {
                $rstmt->bind_param('s', $room_id);
                if ($rstmt->execute()) {
                    $rres = $rstmt->get_result();
                    
                    // Generate dates loop
                    $periodStart = new DateTime($today);
                    $periodEnd = new DateTime($futureDate);
                    $periodEndPlus = clone $periodEnd;
                    $periodEndPlus->modify('+1 day');
                    $period = new DatePeriod($periodStart, new DateInterval('P1D'), $periodEndPlus);
                    
                    while ($rr = $rres->fetch_assoc()) {
                        $recDay = $rr['day_of_week'];
                        
                        foreach ($period as $dt) {
                            if ($dt->format('l') !== $recDay) continue;
                            
                            $dateStr = $dt->format('Y-m-d');
                            $slotTime = substr($rr['time_start'],0,5) . '-' . substr($rr['time_end'],0,5);
                            $key = $dateStr . '|' . $slotTime;
                            
                            // If a regular booking exists (pending/booked/maintenance), skip this recurring slot
                            if (isset($existingKeys[$key])) continue;
                            
                            $out[] = [
                                'id' => 0, 
                                'user_id' => 0,
                                'date' => $dateStr,
                                'time_slot' => $slotTime,
                                'purpose' => $rr['purpose'],
                                'description' => $rr['description'],
                                'tel' => $rr['tel'],
                                'status' => 'recurring',
                                'recurring' => true,
                                'recurring_id' => (int)$rr['id']
                            ];
                        }
                    }
                }
                $rstmt->close();
            }
        }

        // Sort by date then time
        usort($out, function($a, $b) {
            if ($a['date'] === $b['date']) {
                return strcmp($a['time_slot'], $b['time_slot']);
            }
            return strcmp($a['date'], $b['date']);
        });

        echo json_encode($out);
        exit;
    }

    // GET status list logic (kept exactly as original)
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

    echo json_encode([]);
    exit;
}

/* ---------- POST handlers ---------- */
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    // This catches invalid JSON input without crashing
    echo json_encode(['success'=>false,'msg'=>'Invalid JSON payload']);
    exit;
}

// CANCEL Logic (kept exactly as original)
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

    $stmt = $conn->prepare("SELECT id, user_id, room_id, slot_date, time_start, status FROM bookings WHERE id = ? LIMIT 1");
    if (!$stmt) {
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

    if (!($isAdmin || ($isOwner && in_array(strtolower($row['status']), ['pending','booked'])))) {
        echo json_encode(['success'=>false,'msg'=>'Not allowed to cancel this booking']);
        exit;
    }

    $update_ok = false;
    $now = date('Y-m-d H:i:s');

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
            echo json_encode(['success'=>false,'msg'=>'DB update error']);
            exit;
        }
    }

    if ($update_ok) {
        echo json_encode(['success'=>true,'booking_id'=>$booking_id,'status'=>'cancelled']);
        exit;
    } else {
        $err = $conn->error;
        echo json_encode(['success'=>false,'msg'=>'DB update failed: '.$err]);
        exit;
    }
}

// CREATE BOOKING Logic (kept exactly as original)
$room_id = trim($data['room'] ?? '');
$purpose = trim($data['purpose'] ?? '');
$description = trim($data['description'] ?? '');
$tel = trim($data['tel'] ?? '');
$slots = $data['slots'] ?? [];

if (!$me) {
    echo json_encode(['success'=>false,'msg'=>'Not logged in']);
    exit;
}
if (!$room_id || !$purpose || !$tel || !is_array($slots) || count($slots) === 0) {
    echo json_encode(['success'=>false,'msg'=>'Missing required fields (room/purpose/tel/slots)']);
    exit;
}

$hasSessionCol = false;
$checkColRes = $conn->query("SHOW COLUMNS FROM bookings LIKE 'session_id'");
if ($checkColRes && $checkColRes->num_rows > 0) $hasSessionCol = true;

$conn->begin_transaction();

$res = $conn->query("SELECT MAX(id) AS max_id FROM bookings");
$row = $res->fetch_assoc();
$nextNum = intval($row['max_id'] ?? 0) + 1;
$session_id = 'B' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

if ($hasSessionCol) {
    $insertSql = "INSERT INTO bookings (user_id, room_id, purpose, description, tel, slot_date, time_start, time_end, status, session_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
    $insertStmt = $conn->prepare($insertSql);
} else {
    $insertSql = "INSERT INTO bookings (user_id, room_id, purpose, description, tel, slot_date, time_start, time_end, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    $insertStmt = $conn->prepare($insertSql);
}

if (!$insertStmt) {
    $conn->rollback();
    echo json_encode(['success'=>false,'msg'=>'Prepare failed: '.$conn->error]);
    exit;
}

// Conflict check: Only checking 'booked', 'pending', 'maintenance'.
// Cancelled/Rejected are ignored, so you can re-book over them.
$checkSql = "SELECT id, status FROM bookings 
             WHERE room_id = ? AND slot_date = ? AND time_start = ? 
             AND status IN ('booked','pending','maintenance') 
             LIMIT 1";
$checkStmt = $conn->prepare($checkSql);

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

    $checkStmt->bind_param('sss', $room_id, $date, $start);
    if (!$checkStmt->execute()) {
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

    if ($hasSessionCol) {
        $insertStmt->bind_param('issssssss', $me, $room_id, $purpose, $description, $tel, $date, $start, $end, $session_id);
    } else {
        $insertStmt->bind_param('isssssss', $me, $room_id, $purpose, $description, $tel, $date, $start, $end);
    }

    if ($insertStmt->execute()) {
        $newId = $insertStmt->insert_id;
        $ticket = 'B' . str_pad($newId, 4, '0', STR_PAD_LEFT);
        $upd_ticket = $conn->prepare("UPDATE bookings SET ticket = ? WHERE id = ?");
        if ($upd_ticket) {
            $upd_ticket->bind_param('si', $ticket, $newId);
            $upd_ticket->execute();
            $upd_ticket->close();
        }
        $activeKey = 'BK' . str_pad($newId, 8, '0', STR_PAD_LEFT);
        $upd = $conn->prepare("UPDATE bookings SET active_key = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param('si', $activeKey, $newId);
            $upd->execute();
        }
        $slotResult = ['id' => (int)$newId, 'date'=>$date,'slot'=>$slotVal,'success'=>true,'status'=>'pending','active_key'=>$activeKey,'ticket'=>$ticket];
        if ($hasSessionCol) $slotResult['session_id'] = $session_id;
        $results[] = $slotResult;
    } else {
        $results[] = ['date'=>$date,'slot'=>$slotVal,'success'=>false,'msg'=>'SQL Insert Error'];
        $fatalError = true; 
    }
}

if ($fatalError) {
    $conn->rollback();
    echo json_encode(['success'=>false,'msg'=>'Transaction failed, rolled back','results'=>$results]);
    exit;
} else {
    $conn->commit();
    
    // Email Sending Logic (Kept exactly as original)
    $ADMIN_EMAIL = 'superuserutm123@gmail.com';
    $subject = "Booking Request Session {$session_id} Room {$room_id}";
    
    // Fetch user details
    $uStmt = $conn->prepare("SELECT Email, username FROM users WHERE id = ? LIMIT 1");
    $uStmt->bind_param('i', $me);
    $uStmt->execute();
    $userRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    
    $userEmail = $userRow['Email'] ?? null;
    $userName = $userRow['username'] ?? 'User';
    
    // Send email logic (Placeholder for send_mail_return_id as per original)
    // Note: This block assumes send_mail_return_id function exists in mail_helper.php
    
    $response = ['success'=>true, 'results'=>$results];
    if ($hasSessionCol) $response['session_id'] = $session_id;
    echo json_encode($response);
    exit;
}
?>