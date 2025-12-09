<?php
// booking_status.php - returns sessions with is_owner and is_admin flags
// Added: POST action 'start_edit' which frees an entire session and returns its slots
// Note: this file assumes mysqli connection provided by db_connect.php in $conn

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$__booking_status_log = __DIR__ . '/booking_status.log';
function dbg($m) {
    global $__booking_status_log;
    $time = date('Y-m-d H:i:s');
    if (is_array($m) || is_object($m)) $m = print_r($m, true);
    @file_put_contents($__booking_status_log, "[$time] $m\n", FILE_APPEND);
}

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
            dbg("GET prepare failed: " . $conn->error);
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
        dbg("POST RAW: " . substr($raw,0,2000));
        $data = json_decode($raw, true);
        if (!is_array($data)) throw new Exception('Invalid JSON input');
        $action = $data['action'] ?? '';
        dbg("action={$action} user={$me_id}");

        // ---- START_EDIT: free entire session and return its slots ----
        if ($action === 'start_edit') {
            $session_id = trim($data['session_id'] ?? '');
            if (!$me_id) throw new Exception('Not logged in');
            if ($session_id === '') throw new Exception('Missing session_id');

            // handle single_<id> case: return that slot and cancel it
            if (strpos($session_id, 'single_') === 0) {
                $id = intval(substr($session_id, 7));
                if (!$id) throw new Exception('Invalid single id');

                // check permission
                $stmt = $conn->prepare("SELECT id, user_id, room_id, slot_date, time_start, time_end, status, created_at FROM bookings WHERE id=? LIMIT 1");
                if ($stmt === false) throw new Exception('DB prepare error: '.$conn->error);
                $stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result();
                if (!$res || $res->num_rows === 0) { $stmt->close(); throw new Exception('Booking not found'); }
                $row = $res->fetch_assoc(); $stmt->close();

                if (!$isAdmin && intval($row['user_id']) !== $me_id) throw new Exception('Not allowed to edit this booking');

                // build slot payload
                $slot = [
                    'booking_id' => (int)$row['id'],
                    'date' => $row['slot_date'],
                    'time_start' => substr($row['time_start'],0,5),
                    'time_end' => substr($row['time_end'],0,5),
                    'status' => $row['status']
                ];

                // cancel it (mark cancelled but keep audit reason)
                $now = date('Y-m-d H:i:s');
                $reason = 'freed_for_edit:'.$me_id.':'.$now;
                $u = $conn->prepare("UPDATE bookings SET status='cancelled', cancelled_by=?, cancelled_at=?, cancel_reason=? WHERE id = ?");
                if ($u === false) throw new Exception('DB prepare error: '.$conn->error);
                $u->bind_param('issi', $me_id, $now, $reason, $id);
                $ok = $u->execute(); $u->close();

                if (!$ok) throw new Exception('Failed to free booking for edit: '.$conn->error);

                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'room_id' => $row['room_id'],
                    'created_at' => $row['created_at'],
                    'slots' => [$slot]
                ]);
                exit;
            }

            // Normal session_id: find all bookings in this session
            $chk = $conn->prepare("SELECT id, user_id FROM bookings WHERE session_id = ?");
            if ($chk === false) throw new Exception('DB prepare error: '.$conn->error);
            $chk->bind_param('s', $session_id); $chk->execute(); $r = $chk->get_result();
            $owners = [];
            $bookingIds = [];
            while ($ro = $r->fetch_assoc()) {
                $owners[] = intval($ro['user_id']);
                $bookingIds[] = intval($ro['id']);
            }
            $chk->close();
            if (count($bookingIds) === 0) throw new Exception('Session not found');

            // check permission: admin OR all owners same = me
            if (!$isAdmin) {
                $unique = array_unique($owners);
                if (!(count($unique) === 1 && intval($unique[0]) === $me_id)) throw new Exception('Not allowed to edit this session');
            }

            // fetch the detailed bookings for the session (date/time)
            $in = implode(',', array_map('intval', $bookingIds));
            $q = "SELECT id, room_id, slot_date, time_start, time_end, status, created_at FROM bookings WHERE id IN ($in) ORDER BY slot_date, time_start";
            $rs = $conn->query($q);
            if ($rs === false) throw new Exception('DB query error: '.$conn->error);

            $slots = [];
            $room_id = null;
            $created_at = null;
            while ($row = $rs->fetch_assoc()) {
                $room_id = $row['room_id'];
                if ($created_at === null) $created_at = $row['created_at'];
                $slots[] = [
                    'booking_id' => (int)$row['id'],
                    'date' => $row['slot_date'],
                    'time_start' => substr($row['time_start'],0,5),
                    'time_end' => substr($row['time_end'],0,5),
                    'status' => $row['status']
                ];
            }

            // perform transactional update: mark these bookings as cancelled (freed for edit)
            $conn->begin_transaction();
            try {
                $now = date('Y-m-d H:i:s');
                $reason = 'freed_for_edit:'.$me_id.':'.$now;
                // update using session_id (safe) rather than IN list to cover the session
                $u = $conn->prepare("UPDATE bookings SET status='cancelled', cancelled_by=?, cancelled_at=?, cancel_reason=? WHERE session_id = ? AND status IN ('pending','booked')");
                if ($u === false) {
                    // fallback to updating by ids
                    $ok = true;
                    foreach ($bookingIds as $bid) {
                        $fb = $conn->prepare("UPDATE bookings SET status='cancelled', cancelled_by=?, cancelled_at=?, cancel_reason=? WHERE id = ?");
                        if ($fb === false) throw new Exception('DB prepare error: '.$conn->error);
                        $fb->bind_param('issi', $me_id, $now, $reason, $bid);
                        $r = $fb->execute();
                        $fb->close();
                        if (!$r) { $ok = false; break; }
                    }
                    if (!$ok) throw new Exception('Failed to free some bookings for edit');
                } else {
                    $u->bind_param('isss', $me_id, $now, $reason, $session_id);
                    $ok = $u->execute();
                    $u->close();
                    if ($ok === false) throw new Exception('Failed to free bookings for edit: '.$conn->error);
                }
                $conn->commit();
            } catch (Exception $ex) {
                $conn->rollback();
                throw $ex;
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'room_id' => $room_id,
                'created_at' => $created_at,
                'slots' => $slots
            ]);
            exit;
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

        // cancel_slot (legacy) - cancel one slot belonging to logged user
        if ($action === 'cancel_slot') {
            $room_id = $data['room'] ?? null;
            $slot_date = $data['slot_date'] ?? null;
            $time_start = $data['time_start'] ?? null;
            $reason = trim($data['reason'] ?? '');
            if (!$me_id) throw new Exception('Not logged in');
            if (!$room_id || !$slot_date || !$time_start) throw new Exception('Missing identifiers');

            $q = "SELECT id,status FROM bookings WHERE user_id=? AND room_id=? AND slot_date=? AND time_start=? AND status IN ('pending','booked') ORDER BY id DESC LIMIT 1";
            $s = $conn->prepare($q);
            if ($s === false) throw new Exception('DB prepare error: '.$conn->error);
            $s->bind_param('isss', $me_id, $room_id, $slot_date, $time_start);
            $s->execute();
            $r = $s->get_result();
            if (!$r || $r->num_rows === 0) { $s->close(); throw new Exception('No active booking found for this slot'); }
            $row = $r->fetch_assoc(); $s->close();
            $target = intval($row['id']);

            $now = date('Y-m-d H:i:s');
            $u = $conn->prepare("UPDATE bookings SET status='cancelled', cancelled_by=?, cancelled_at=?, cancel_reason=? WHERE id = ?");
            if ($u === false) throw new Exception('DB prepare error: '.$conn->error);
            $u->bind_param('issi', $me_id, $now, $reason, $target);
            $ok = $u->execute();
            $u->close();
            if ($ok) { ob_end_clean(); echo json_encode(['success'=>true,'booking_id'=>$target,'status'=>'cancelled']); exit; }
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
                $ok = $u->execute(); $u->close();
                if ($ok) { ob_end_clean(); echo json_encode(['success'=>true,'session_id'=>$session_id,'status'=>'cancelled']); exit; }
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
            $upd = $conn->prepare("UPDATE bookings SET status='cancelled', cancelled_by=?, cancelled_at=?, cancel_reason=? WHERE session_id = ? AND status IN ('pending','booked')");
            if ($upd === false) {
                // fallback: try without audit columns
                $fb = $conn->prepare("UPDATE bookings SET status='cancelled' WHERE session_id = ? AND status IN ('pending','booked')");
                if ($fb === false) throw new Exception('DB prepare error: '.$conn->error);
                $fb->bind_param('s', $session_id);
                $ok = $fb->execute();
                $fb->close();
            } else {
                $upd->bind_param('isss', $me_id, $now, $reason, $session_id);
                $ok = $upd->execute();
                $upd->close();
            }

            if ($ok) { ob_end_clean(); echo json_encode(['success'=>true,'session_id'=>$session_id,'status'=>'cancelled']); exit; }
            throw new Exception('DB update failed: '.$conn->error);
        }

        throw new Exception('Unknown action: '.$action);
    }

    ob_end_clean();
    echo json_encode(['success'=>false,'msg'=>'Unsupported method']);
    exit;
} catch (Exception $e) {
    $buf = '';
    if (ob_get_length() !== false) $buf = ob_get_clean();
    if ($buf !== '') dbg("STRAY_OUTPUT: " . preg_replace("/\s+/", ' ', trim($buf)));
    dbg("Exception: " . $e->getMessage());
    http_response_code(200);
    echo json_encode(['success'=>false,'msg'=>$e->getMessage()]);
    exit;
} catch (Error $e) {
    $buf = '';
    if (ob_get_length() !== false) $buf = ob_get_clean();
    if ($buf !== '') dbg("STRAY_OUTPUT: " . preg_replace("/\s+/", ' ', trim($buf)));
    dbg("Fatal: " . $e->getMessage());
    http_response_code(200);
    echo json_encode(['success'=>false,'msg'=>'Server error: '.$e->getMessage()]);
    exit;
}
