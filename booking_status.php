<?php
// booking_status.php - returns sessions with is_owner and is_admin flags
// Added: POST action 'start_edit' which frees an entire session and returns its slots
// Note: this file assumes mysqli connection provided by db_connect.php in $conn

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ob_start();
try {
    header('Content-Type: application/json; charset=utf-8');
    session_start();

    if (!file_exists(__DIR__ . '/includes/db_connect.php')) throw new Exception('db_connect.php missing');
    require_once __DIR__ . '/includes/db_connect.php';
    if (!isset($conn) || !($conn instanceof mysqli)) throw new Exception('$conn not available');

    // current user (tolerant)
    $me_id = 0;
    if (isset($_SESSION['id'])) $me_id = intval($_SESSION['id']);
    elseif (isset($_SESSION['user_id'])) $me_id = intval($_SESSION['user_id']);

    $me_type = '';
    if (isset($_SESSION['user_type'])) $me_type = strtolower(trim($_SESSION['user_type']));
    elseif (isset($_SESSION['User_Type'])) $me_type = strtolower(trim($_SESSION['User_Type']));
    elseif (isset($_SESSION['role'])) $me_type = strtolower(trim($_SESSION['role']));

    $isAdmin = ($me_type === 'admin');

    /* ---------- GET: sessions listing ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $view = $_GET['view'] ?? '';
        if ($view !== 'status') {
            ob_end_clean();
            echo json_encode([]);
            exit;
        }

        $filter = $_GET['filter'] ?? 'all';
        $allowed = ['pending','booked','cancelled','all','rejected'];
        if (!in_array($filter, $allowed)) $filter = 'all';

        /* Group bookings by session and build slots list */
        $sql = "
            SELECT
                COALESCE(b.session_id, CONCAT('single_', CAST(b.id AS CHAR))) AS session_key,
                MIN(b.id) AS repr_booking_id,
                b.room_id,
                r.name AS room_name,
                MIN(b.created_at) AS created_at,
                GROUP_CONCAT(CONCAT(b.slot_date,'::',TIME_FORMAT(b.time_start,'%H:%i'),'-',TIME_FORMAT(b.time_end,'%H:%i'),'::',b.id,'::',b.status,'::',b.user_id) 
                             ORDER BY b.slot_date, b.time_start SEPARATOR '||') AS slots,
                COUNT(*) AS slot_count,
                MAX(CASE WHEN LOWER(b.status) = 'pending' THEN 1 ELSE 0 END) AS has_pending,
                MAX(CASE WHEN LOWER(b.status) = 'booked' THEN 1 ELSE 0 END) AS has_booked,
                MAX(CASE WHEN LOWER(b.status) = 'cancelled' THEN 1 ELSE 0 END) AS has_cancelled
            FROM bookings b
            LEFT JOIN rooms r ON b.room_id = r.room_id
            WHERE 1=1
        ";

        $params = []; $types = '';
        if (!$isAdmin) { $sql .= " AND b.user_id = ?"; $types .= 'i'; $params[] = $me_id; }
        if ($filter !== 'all') { $sql .= " AND LOWER(b.status) = ?"; $types .= 's'; $params[] = strtolower($filter); }

        $sql .= " GROUP BY session_key, b.room_id, r.name ORDER BY created_at DESC";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            ob_end_clean();
            echo json_encode(['success'=>false,'msg'=>'DB prepare failed']);
            exit;
        }
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $sessions = [];
        while ($row = $res->fetch_assoc()) {
            $slotsRaw = $row['slots'] ?? '';
            $slotItems = $slotsRaw === '' ? [] : explode('||', $slotsRaw);
            $slots = [];
            $owners = [];
            $dates = [];
            $times = [];

            foreach ($slotItems as $si) {
                // format: slot_date::timeRange::booking_id::status::user_id
                $parts = explode('::', $si);
                $slot_date = $parts[0] ?? null;
                $timeRange = $parts[1] ?? null;
                $booking_id = isset($parts[2]) ? intval($parts[2]) : null;
                $status = $parts[3] ?? null;
                $user_id = isset($parts[4]) ? intval($parts[4]) : 0;

                $slots[] = [
                    'booking_id' => $booking_id,
                    'date' => $slot_date,
                    'time' => $timeRange,
                    'status' => $status,
                    'user_id' => $user_id
                ];
                $owners[$user_id] = true;
                if ($slot_date) $dates[$slot_date] = true;
                if ($timeRange) $times[$timeRange] = true;
            }

            // Session aggregate status
            $status = 'cancelled';
            if ((int)$row['has_pending'] > 0) $status = 'pending';
            elseif ((int)$row['has_booked'] > 0) $status = 'booked';
            elseif ((int)$row['has_cancelled'] > 0) $status = 'cancelled';

            $ownerIds = array_map('intval', array_keys($owners));
            sort($ownerIds);

            // Determine if current user owns this session
            // Session is "owned" if all slots belong to the same user and that user is me
            $is_owner = (count($ownerIds) === 1 && $ownerIds[0] === $me_id);

            // Can cancel: admin always can, or owner can
            $can_cancel = $isAdmin || $is_owner;

            $sessions[] = [
                'session_id' => $row['session_key'],
                'id' => (int)$row['repr_booking_id'],
                'repr_booking_id' => (int)$row['repr_booking_id'],
                'room_id' => $row['room_id'],
                'room' => $row['room_id'],
                'room_name' => $row['room_name'],
                'created_at' => $row['created_at'],
                'slot_count' => (int)$row['slot_count'],
                'slots' => $slots,
                'owners' => $ownerIds,
                'dates' => array_values(array_keys($dates)),
                'times' => array_values(array_keys($times)),
                'status' => $status,
                'can_cancel' => $can_cancel,
                'is_owner' => $is_owner ? 1 : 0,
                'is_admin' => $isAdmin ? 1 : 0
            ];
        }
        $stmt->close();

        ob_end_clean();
        echo json_encode(['success'=>true,'sessions'=>$sessions]);
        exit;
    }

    /* ---------- POST: actions (start_edit, cancel, cancel_slot, cancel_session) ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) throw new Exception('Invalid JSON input');
        $action = $data['action'] ?? '';

        
        // START EDIT - prepare edit data
// START EDIT - prepare edit data
        if ($action === 'start_edit') {
            $session_id = trim($data['session_id'] ?? '');
            
            if (!$session_id) {
                echo json_encode(['success'=>false,'msg'=>'Missing session_id']);
                exit;
            }
            if (!$me_id) {
                echo json_encode(['success'=>false,'msg'=>'Not logged in']);
                exit;
            }
            
            // 1. Fetch bookings to verify ownership & get details
            $stmt = $conn->prepare("SELECT id, user_id, room_id, slot_date, time_start, time_end, purpose, description, tel, status 
                                    FROM bookings 
                                    WHERE (session_id = ? OR id = ?) AND status IN ('pending', 'booked')");
            if (!$stmt) {
                echo json_encode(['success'=>false,'msg'=>'DB error']);
                exit;
            }
            
            $stmt->bind_param('ss', $session_id, $session_id);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if (!$res || $res->num_rows === 0) {
                echo json_encode(['success'=>false,'msg'=>'No active bookings found for this session']);
                exit;
            }
            
            $slots = [];
            $booking_ids = []; // Array to store IDs to clean up logs
            $room_id = '';
            $first_row = null;
            $authorized = false;
            
            while ($row = $res->fetch_assoc()) {
                if (!$first_row) $first_row = $row;
                $room_id = $row['room_id'];
                $booking_ids[] = $row['id']; // Collect ID
                
                // Permission Check
                $isOwner = ($row['user_id'] == $me_id);
                if ($isAdmin || $isOwner) {
                    $authorized = true;
                } else {
                    if (!$isAdmin) {
                        $stmt->close();
                        echo json_encode(['success'=>false,'msg'=>'Not authorized to edit this booking']);
                        exit;
                    }
                }
                
                // Format time
                $time_slot = substr($row['time_start'],0,5) . '-' . substr($row['time_end'],0,5);
                
                $slots[] = [
                    'id' => (int)$row['id'],
                    'date' => $row['slot_date'],
                    'time' => $time_slot
                ];
            }
            $stmt->close();

            if (!$authorized) {
                 echo json_encode(['success'=>false,'msg'=>'Authorization failed.']);
                 exit;
            }
            
            // 2. CLEANUP & DELETE (Handle Foreign Keys)
            $conn->begin_transaction();
            try {
                if (!empty($booking_ids)) {
                    // Create a comma-separated string of IDs for the query: "1, 2, 3"
                    $ids_string = implode(',', array_map('intval', $booking_ids));

                    // A. Delete dependent Email Logs
                    $conn->query("DELETE FROM email_logs WHERE booking_id IN ($ids_string)");

                    // B. Delete dependent Admin Logs (prevent future errors)
                    $conn->query("DELETE FROM admin_logs WHERE booking_id IN ($ids_string)");
                    
                    // C. Also set room_problems booking_id to NULL if you have that link
                    // $conn->query("UPDATE room_problems SET booking_id = NULL WHERE booking_id IN ($ids_string)");
                }

                // D. Now safe to delete the bookings
                $stmtDel = $conn->prepare("DELETE FROM bookings WHERE (session_id = ? OR id = ?) AND status IN ('pending', 'booked')");
                $stmtDel->bind_param('ss', $session_id, $session_id);
                $stmtDel->execute();
                $stmtDel->close();

                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'session_id' => $session_id,
                    'room_id' => $room_id,
                    'room' => $room_id,
                    'slots' => $slots,
                    'purpose' => $first_row['purpose'],
                    'description' => $first_row['description'],
                    'tel' => $first_row['tel'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success'=>false, 'msg'=>'Database error during delete: ' . $e->getMessage()]);
                exit;
            }
        }

        // cancel single booking by id
        if ($action === 'cancel') {
            $booking_id = isset($data['booking_id']) ? intval($data['booking_id']) : 0;
            $reason = trim($data['reason'] ?? '');
            if (!$me_id) throw new Exception('Not logged in');
            if (!$booking_id) throw new Exception('Missing booking_id');

            $stmt = $conn->prepare("SELECT id,user_id,status FROM bookings WHERE id = ? LIMIT 1");
            if ($stmt === false) throw new Exception('DB prepare error: '.$conn->error);
            $stmt->bind_param('i', $booking_id);
            $stmt->execute();
            $r = $stmt->get_result();
            if (!$r || $r->num_rows === 0) { $stmt->close(); throw new Exception('Booking not found'); }
            $row = $r->fetch_assoc(); $stmt->close();

            if (!$isAdmin && intval($row['user_id']) !== $me_id) throw new Exception('Not allowed');

            $now = date('Y-m-d H:i:s');
            $u = $conn->prepare("UPDATE bookings SET status='cancelled', cancelled_by=?, cancelled_at=?, cancel_reason=? WHERE id = ?");
            if ($u === false) throw new Exception('DB prepare error: '.$conn->error);
            $u->bind_param('issi', $me_id, $now, $reason, $booking_id);
            $ok = $u->execute();
            $u->close();
            if ($ok) { ob_end_clean(); echo json_encode(['success'=>true,'booking_id'=>$booking_id,'status'=>'cancelled']); exit; }
            throw new Exception('DB update failed: '.$conn->error);
        }

        // cancel_session: session may be 'single_<id>' or real session_id
        if ($action === 'cancel_session') {
            $session_id = trim($data['session_id'] ?? '');
            $reason = trim($data['reason'] ?? '');
            if (!$me_id) throw new Exception('Not logged in');
            if ($session_id === '') throw new Exception('Missing session_id');

            // if it's a single_<id> synthetic id -> cancel that single booking
            if (strpos($session_id, 'single_') === 0) {
                $id = intval(substr($session_id, 7));
                if (!$id) throw new Exception('Invalid single id');

                $stmt = $conn->prepare("SELECT id,user_id,status FROM bookings WHERE id=? LIMIT 1");
                if ($stmt === false) throw new Exception('DB prepare error: '.$conn->error);
                $stmt->bind_param('i', $id); $stmt->execute();
                $res = $stmt->get_result();
                if (!$res || $res->num_rows === 0) { $stmt->close(); throw new Exception('Booking not found'); }
                $row = $res->fetch_assoc(); $stmt->close();

                if (!$isAdmin && intval($row['user_id']) !== $me_id) throw new Exception('Not allowed to cancel this booking');

                $now = date('Y-m-d H:i:s');
                $u = $conn->prepare("UPDATE bookings SET status='cancelled', cancelled_by=?, cancelled_at=?, cancel_reason=? WHERE id = ?");
                if ($u === false) throw new Exception('DB prepare error: '.$conn->error);
                $u->bind_param('issi', $me_id, $now, $reason, $id);
                $ok = $u->execute(); 
                $cancelled_count = $u->affected_rows;
                $u->close();
                if ($ok) { 
                    ob_end_clean(); 
                    echo json_encode([
                        'success'=>true,
                        'session_id'=>$session_id,
                        'status'=>'cancelled',
                        'cancelled_count'=>$cancelled_count,
                        'msg'=>"Successfully cancelled {$cancelled_count} booking(s)"
                    ]); 
                    exit; 
                }
                throw new Exception('DB update failed: '.$conn->error);
            }

            // normal session_id: ensure permission (admin OR all owners same = me)
            $chk = $conn->prepare("SELECT DISTINCT user_id FROM bookings WHERE session_id = ?");
            if ($chk === false) throw new Exception('DB prepare error: '.$conn->error);
            $chk->bind_param('s', $session_id); $chk->execute(); $r = $chk->get_result();
            $owners = [];
            while ($ro = $r->fetch_assoc()) $owners[] = intval($ro['user_id']);
            $chk->close();
            if (count($owners) === 0) throw new Exception('Session not found');

            if (!$isAdmin) {
                $unique = array_unique($owners);
                if (!(count($unique) === 1 && $unique[0] === $me_id)) throw new Exception('Not allowed to cancel this session');
            }

            $now = date('Y-m-d H:i:s');
            $cancelled_count = 0;
            $upd = $conn->prepare("UPDATE bookings SET status='cancelled', cancelled_by=?, cancelled_at=?, cancel_reason=? WHERE session_id = ? AND status IN ('pending','booked')");
            if ($upd === false) {
                // fallback: try without audit columns
                $fb = $conn->prepare("UPDATE bookings SET status='cancelled' WHERE session_id = ? AND status IN ('pending','booked')");
                if ($fb === false) throw new Exception('DB prepare error: '.$conn->error);
                $fb->bind_param('s', $session_id);
                $ok = $fb->execute();
                $cancelled_count = $fb->affected_rows;
                $fb->close();
            } else {
                $upd->bind_param('isss', $me_id, $now, $reason, $session_id);
                $ok = $upd->execute();
                $cancelled_count = $upd->affected_rows;
                $upd->close();
            }

            if ($ok) { 
                ob_end_clean(); 
                echo json_encode([
                    'success'=>true,
                    'session_id'=>$session_id,
                    'status'=>'cancelled',
                    'cancelled_count'=>$cancelled_count,
                    'msg'=>"Successfully cancelled {$cancelled_count} booking(s)"
                ]); 
                exit; 
            }
            throw new Exception('DB update failed: '.$conn->error);
        }
    }

    ob_end_clean();
    echo json_encode(['success'=>false,'msg'=>'Unsupported method']);
    exit;
    
} catch (Exception $e) {
    $buf = '';
    if (ob_get_length() !== false) $buf = ob_get_clean();
    http_response_code(200);
    echo json_encode(['success'=>false,'msg'=>$e->getMessage()]);
    exit;
} catch (Error $e) {
    $buf = '';
    if (ob_get_length() !== false) $buf = ob_get_clean();
    http_response_code(200);
    echo json_encode(['success'=>false,'msg'=>'Server error: '.$e->getMessage()]);
    exit;
}