<?php
// booking_status.php - fixed version (adds missing dbg() + small robustness tweaks)
// NOTE: keep db_connect.php silent (no output). This file always returns JSON.

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// simple logger used by the script (guarantees dbg() exists)
$__booking_status_log = __DIR__ . '/booking_status.log';
function dbg($msg) {
    global $__booking_status_log;
    $time = date('Y-m-d H:i:s');
    // try to safely stringify arrays/objects
    if (is_array($msg) || is_object($msg)) $msg = print_r($msg, true);
    @file_put_contents($__booking_status_log, "[$time] $msg\n", FILE_APPEND);
}

// Buffer output to capture stray output and ensure we only send JSON
ob_start();

try {
    header('Content-Type: application/json; charset=utf-8');
    session_start();

    // include db_connect (must set $conn to mysqli). If it prints anything, it'll be captured.
    if (!file_exists(__DIR__ . '/db_connect.php')) {
        throw new Exception('Database configuration file not found');
    }
    require_once __DIR__ . '/db_connect.php';

    // verify $conn
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection failed or $conn not set by db_connect.php');
    }

    // tolerant session keys (some of your pages use 'User_Type' capitalized)
    $me_id = 0;
    if (isset($_SESSION['id'])) $me_id = intval($_SESSION['id']);
    elseif (isset($_SESSION['user_id'])) $me_id = intval($_SESSION['user_id']);

    $me_type = '';
    if (isset($_SESSION['user_type'])) $me_type = strtolower(trim($_SESSION['user_type']));
    elseif (isset($_SESSION['User_Type'])) $me_type = strtolower(trim($_SESSION['User_Type']));
    elseif (isset($_SESSION['role'])) $me_type = strtolower(trim($_SESSION['role']));

    // ----------- GET: return booking list ----------- //
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $view = $_GET['view'] ?? '';
        if ($view !== 'status') {
            ob_end_clean();
            echo json_encode([]);
            exit;
        }

        $filter = $_GET['filter'] ?? 'all';
        $allowedFilters = ['pending','booked','cancelled','all','rejected'];
        if (!in_array($filter, $allowedFilters)) $filter = 'all';

        $isAdmin = ($me_type === 'admin');

        $sql = "SELECT id, user_id, room, slot_date, time_start, time_end, purpose, description, tel, status
                FROM timetable
                WHERE 1=1";

        $params = [];
        $types = '';

        if (!$isAdmin) {
            $sql .= " AND user_id = ?";
            $types .= 'i';
            $params[] = $me_id;
        }

        if ($filter !== 'all') {
            $sql .= " AND status = ?";
            $types .= 's';
            $params[] = $filter;
        }

        $sql .= " ORDER BY slot_date DESC, time_start DESC";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            dbg("GET prepare failed: " . $conn->error);
            throw new Exception('Database prepare failed');
        }
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $is_owner = ($row['user_id'] == $me_id) ? 1 : 0;
            $is_admin = $isAdmin ? 1 : 0;
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
                'is_owner' => $is_owner,
                'is_admin' => $is_admin
            ];
        }
        $stmt->close();
        ob_end_clean();
        echo json_encode($out);
        exit;
    }

    // ----------- POST: dispatch actions (cancel) ----------- //
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // read JSON body
        $raw = file_get_contents('php://input');
        dbg("POST RAW: " . substr($raw,0,2000));
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new Exception('Invalid JSON input');
        }
        $action = $data['action'] ?? '';

        dbg("POST action: {$action}, user_id: {$me_id}");

        // CANCEL action by booking_id
        if ($action === 'cancel') {
            $booking_id = isset($data['booking_id']) ? intval($data['booking_id']) : 0;
            $reason = trim($data['reason'] ?? '');

            if (!$me_id) throw new Exception('Not logged in');
            if (!$booking_id) throw new Exception('Missing booking_id');

            $isAdmin = ($me_type === 'admin');

            // Get the booking
            $stmt = $conn->prepare("SELECT id, user_id, status FROM timetable WHERE id = ?");
            if ($stmt === false) throw new Exception('DB prepare error: '.$conn->error);
            $stmt->bind_param('i', $booking_id);
            $stmt->execute();
            $res = $stmt->get_result();

            if (!$res || $res->num_rows === 0) {
                $stmt->close();
                throw new Exception('Booking not found');
            }

            $booking = $res->fetch_assoc();
            $stmt->close();

            if (!$isAdmin && $booking['user_id'] != $me_id) {
                throw new Exception('Not allowed to cancel this booking');
            }

            if (strtolower($booking['status']) === 'cancelled') {
                throw new Exception('Already cancelled');
            }

            $current_status = strtolower($booking['status']);
            if ($current_status !== 'pending' && $current_status !== 'booked') {
                throw new Exception('Cannot cancel booking with status: '.$booking['status']);
            }

            // UPDATE the booking to cancelled (keeps history)
            // If you have audit columns cancelled_by/cancelled_at/cancel_reason they will be set; if not, the query still works for status only.
            // Use the extended query if audit columns exist, otherwise fallback to status-only update.
            $update_sql = "UPDATE timetable SET status = 'cancelled'";
            $update_params = [];
            $update_types = '';

            // try to detect if audit columns exist: (cheaper to attempt and ignore)
            // We'll attempt to prepare an update with audit columns; if it fails, we'll fallback to status-only update.
            $try_update_with_audit = $conn->prepare("UPDATE timetable SET status = 'cancelled', cancelled_by = ?, cancelled_at = ?, cancel_reason = ? WHERE id = ?");

            if ($try_update_with_audit !== false) {
                $now = date('Y-m-d H:i:s');
                $try_update_with_audit->bind_param('issi', $me_id, $now, $reason, $booking_id);
                $ok = $try_update_with_audit->execute();
                $try_update_with_audit->close();
                if (!$ok) {
                    // fallback to status-only update below
                    dbg("Attempt to update with audit columns failed: " . $conn->error);
                    $fallback = $conn->prepare("UPDATE timetable SET status = 'cancelled' WHERE id = ?");
                    if ($fallback === false) throw new Exception('DB prepare error (fallback): '.$conn->error);
                    $fallback->bind_param('i', $booking_id);
                    $ok = $fallback->execute();
                    $fallback->close();
                }
            } else {
                // fallback to simple update
                $fallback = $conn->prepare("UPDATE timetable SET status = 'cancelled' WHERE id = ?");
                if ($fallback === false) throw new Exception('DB prepare error: '.$conn->error);
                $fallback->bind_param('i', $booking_id);
                $ok = $fallback->execute();
                $fallback->close();
            }

            if ($ok) {
                dbg("cancel success (updated to cancelled) id={$booking_id} by user={$me_id}");
                ob_end_clean();
                echo json_encode(['success'=>true,'booking_id'=>$booking_id,'status'=>'cancelled']);
                exit;
            } else {
                throw new Exception('DB update failed: '.$conn->error);
            }
        }

        // CANCEL_SLOT action (by slot identifiers)
        if ($action === 'cancel_slot') {
            $room = $data['room'] ?? null;
            $slot_date = $data['slot_date'] ?? null;
            $time_start = $data['time_start'] ?? null;
            $reason = trim($data['reason'] ?? '');

            if (!$me_id) throw new Exception('Not logged in');
            if (!$room || !$slot_date || !$time_start) throw new Exception('Missing slot identifiers (room/slot_date/time_start)');

            $sql = "SELECT id, status FROM timetable
                    WHERE user_id = ? AND room = ? AND slot_date = ? AND time_start = ? AND status IN ('pending','booked')
                    ORDER BY id DESC LIMIT 1";

            $stmt = $conn->prepare($sql);
            if ($stmt === false) throw new Exception('DB prepare error: '.$conn->error);
            $stmt->bind_param('isss', $me_id, $room, $slot_date, $time_start);
            $stmt->execute();
            $res = $stmt->get_result();

            if (!$res || $res->num_rows === 0) {
                $stmt->close();
                dbg("cancel_slot: no active booking found for user {$me_id} on {$room} {$slot_date} {$time_start}");
                throw new Exception('No active booking found for this slot');
            }

            $candidate = $res->fetch_assoc();
            $stmt->close();
            $target_id = (int)$candidate['id'];

            // Update to cancelled (with audit if available)
            $try_update_with_audit = $conn->prepare("UPDATE timetable SET status = 'cancelled', cancelled_by = ?, cancelled_at = ?, cancel_reason = ? WHERE id = ?");
            if ($try_update_with_audit !== false) {
                $now = date('Y-m-d H:i:s');
                $try_update_with_audit->bind_param('issi', $me_id, $now, $reason, $target_id);
                $ok = $try_update_with_audit->execute();
                $try_update_with_audit->close();
                if (!$ok) {
                    dbg("Attempt to update with audit columns failed (cancel_slot): " . $conn->error);
                    $fallback = $conn->prepare("UPDATE timetable SET status = 'cancelled' WHERE id = ?");
                    if ($fallback === false) throw new Exception('DB prepare error (fallback): '.$conn->error);
                    $fallback->bind_param('i', $target_id);
                    $ok = $fallback->execute();
                    $fallback->close();
                }
            } else {
                $fallback = $conn->prepare("UPDATE timetable SET status = 'cancelled' WHERE id = ?");
                if ($fallback === false) throw new Exception('DB prepare error: '.$conn->error);
                $fallback->bind_param('i', $target_id);
                $ok = $fallback->execute();
                $fallback->close();
            }

            if ($ok) {
                dbg("cancel_slot success (updated to cancelled) id={$target_id} by user={$me_id}");
                ob_end_clean();
                echo json_encode(['success'=>true,'booking_id'=>$target_id,'status'=>'cancelled']);
                exit;
            } else {
                throw new Exception('DB update failed: '.$conn->error);
            }
        }

        // unknown action
        throw new Exception('Unknown action: '.$action);
    }


    // fallback for other methods
    ob_end_clean();
    echo json_encode([]);
    exit;

} catch (Exception $e) {
    // capture any buffered output, log it
    $buf = '';
    if (ob_get_length() !== false) $buf = ob_get_clean();
    if ($buf !== '') dbg("STRAY_OUTPUT: " . preg_replace("/\s+/", ' ', trim($buf)));

    dbg("Exception: " . $e->getMessage());
    // return JSON error
    http_response_code(200);
    echo json_encode(['success'=>false, 'msg'=>$e->getMessage()]);
    exit;
} catch (Error $e) {
    // capture any buffered output, log it
    $buf = '';
    if (ob_get_length() !== false) $buf = ob_get_clean();
    if ($buf !== '') dbg("STRAY_OUTPUT: " . preg_replace("/\s+/", ' ', trim($buf)));

    dbg("Fatal error: " . $e->getMessage());
    http_response_code(200);
    echo json_encode(['success'=>false, 'msg'=>'Server error: '.$e->getMessage()]);
    exit;
}
