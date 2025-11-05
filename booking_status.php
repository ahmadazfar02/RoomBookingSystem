<?php
// booking_status.php
// Wrap everything in try-catch to ensure JSON is always returned
ob_start(); // Buffer output to catch any stray output

try {
    // CRITICAL: Prevent any output before JSON
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    header('Content-Type: application/json; charset=utf-8');
    session_start();

    // Try to include db_connect
    if (!file_exists(__DIR__ . '/db_connect.php')) {
        throw new Exception('Database configuration file not found');
    }
    
    require 'db_connect.php';

    // check DB
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }

    // get logged-in user
    $me_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
    $me_type = isset($_SESSION['user_type']) ? strtolower(trim($_SESSION['user_type'])) : '';

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

        // basic query: owners see their bookings; admins see all
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
            dbg("prepare failed: ".$conn->error);
            throw new Exception('Database prepare failed: '.$conn->error);
        }
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
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

            if (!$me_id) {
                throw new Exception('Not logged in');
            }
            if (!$booking_id) {
                throw new Exception('Missing booking_id');
            }

            // Check if user owns this booking or is admin
            $isAdmin = ($me_type === 'admin');
            
            // Get the booking
            $stmt = $conn->prepare("SELECT id, user_id, status FROM timetable WHERE id = ?");
            if ($stmt === false) {
                throw new Exception('DB prepare error: '.$conn->error);
            }
            $stmt->bind_param('i', $booking_id);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if (!$res || $res->num_rows === 0) {
                $stmt->close();
                throw new Exception('Booking not found');
            }
            
            $booking = $res->fetch_assoc();
            $stmt->close();
            
            // Check permissions
            if (!$isAdmin && $booking['user_id'] != $me_id) {
                throw new Exception('Not allowed to cancel this booking');
            }
            
            // Check if already cancelled
            if (strtolower($booking['status']) === 'cancelled') {
                throw new Exception('Already cancelled');
            }
            
            // Check if status is pending or booked
            $current_status = strtolower($booking['status']);
            if ($current_status !== 'pending' && $current_status !== 'booked') {
                throw new Exception('Cannot cancel booking with status: '.$booking['status']);
            }

            // DELETE the booking instead of updating to avoid unique constraint issues
            $del = $conn->prepare("DELETE FROM timetable WHERE id = ?");
            if ($del === false) {
                throw new Exception('DB prepare error: '.$conn->error);
            }
            $del->bind_param('i', $booking_id);
            $ok = $del->execute();
            $del->close();

            if ($ok) {
                dbg("cancel success (deleted) id={$booking_id} by user={$me_id}");
                ob_end_clean();
                echo json_encode(['success'=>true,'booking_id'=>$booking_id,'status'=>'cancelled']);
                exit;
            } else {
                throw new Exception('DB delete failed: '.$conn->error);
            }
        }

        // CANCEL_SLOT action
        if ($action === 'cancel_slot') {
            $room = $data['room'] ?? null;
            $slot_date = $data['slot_date'] ?? null;
            $time_start = $data['time_start'] ?? null;
            $reason = trim($data['reason'] ?? '');

            if (!$me_id) {
                throw new Exception('Not logged in');
            }
            if (!$room || !$slot_date || !$time_start) {
                throw new Exception('Missing slot identifiers (room/slot_date/time_start)');
            }

            // Find booking - use simple ORDER BY id if created_at doesn't exist
            $sql = "SELECT id, status FROM timetable
                    WHERE user_id = ? AND room = ? AND slot_date = ? AND time_start = ? AND status IN ('pending','booked')
                    ORDER BY id DESC LIMIT 1";
            
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception('DB prepare error: '.$conn->error);
            }
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

            // DELETE the booking instead of updating to avoid unique constraint issues
            $del = $conn->prepare("DELETE FROM timetable WHERE id = ?");
            if ($del === false) {
                throw new Exception('DB prepare error: '.$conn->error);
            }
            $del->bind_param('i', $target_id);
            $ok = $del->execute();
            $del->close();

            if ($ok) {
                dbg("cancel_slot success (deleted) id={$target_id} by user={$me_id}");
                ob_end_clean();
                echo json_encode(['success'=>true,'booking_id'=>$target_id,'status'=>'cancelled']);
                exit;
            } else {
                throw new Exception('DB delete failed: '.$conn->error);
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
    // Clean any output buffer and return error as JSON
    ob_end_clean();
    dbg("Exception: " . $e->getMessage());
    http_response_code(200); // Keep 200 so JavaScript can parse JSON
    echo json_encode(['success'=>false, 'msg'=>$e->getMessage()]);
    exit;
} catch (Error $e) {
    // Catch fatal errors
    ob_end_clean();
    dbg("Fatal error: " . $e->getMessage());
    http_response_code(200);
    echo json_encode(['success'=>false, 'msg'=>'Server error: '.$e->getMessage()]);
    exit;
}
?>