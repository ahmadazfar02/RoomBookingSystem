<?php
// find_rooms.php
// Returns JSON list of available rooms matching capacity and not booked in the requested time window.
// Client expects GET: ?capacity=40&date=2025-06-02&start=08:00&end=09:00

// Start output buffering to prevent any accidental output
ob_start();

// Disable error display (log only)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();

// Set header after session_start
header('Content-Type: application/json; charset=utf-8');

function json_err($msg){
    echo json_encode(['success'=>false, 'msg'=>$msg]);
    exit;
}

// require db_connect.php which must set $conn (mysqli) and produce NO output
$dbfile = __DIR__ . '/db_connect.php';
if (!file_exists($dbfile)) json_err('Server configuration missing');
require_once $dbfile;

if (!isset($conn) || !($conn instanceof mysqli)) json_err('Database connection not available');

// read parameters (GET)
$capacity = isset($_GET['capacity']) ? intval($_GET['capacity']) : 0;
$date     = isset($_GET['date']) ? trim($_GET['date']) : '';
$start    = isset($_GET['start']) ? trim($_GET['start']) : '';
$end      = isset($_GET['end']) ? trim($_GET['end']) : '';

// basic validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_err('Invalid date (expected YYYY-MM-DD)');
if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) json_err('Invalid time format (HH:MM)');
if ($start >= $end) json_err('Start time must be before end time');

// SQL: pick rooms that meet capacity and are not blocked by a booking (pending/booked) that overlaps
// Overlap condition: NOT (existing_end <= requested_start OR existing_start >= requested_end)

$sql = "
SELECT r.room_id, r.name, r.capacity, r.floor
FROM rooms r
WHERE r.active = 1
  AND ( ? <= 0 OR r.capacity >= ? )
  AND r.room_id NOT IN (
      SELECT t.room_id FROM timetable t
      WHERE t.slot_date = ?
        AND t.status IN ('pending','booked')
        AND NOT ( t.time_end <= ? OR t.time_start >= ? )
  )
ORDER BY r.capacity ASC, r.name ASC
LIMIT 500
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    json_err('DB prepare failed: ' . $conn->error);
}

// bind parameters: capacity twice, date, start, end
$cap_for_bind = (int)$capacity;
if (!$stmt->bind_param('iisss', $cap_for_bind, $cap_for_bind, $date, $start, $end)) {
    json_err('DB bind failed: ' . $stmt->error);
}

if (!$stmt->execute()) {
    json_err('DB execute failed: ' . $stmt->error);
}

$res = $stmt->get_result();
$rooms = [];
while ($row = $res->fetch_assoc()) {
    $rooms[] = [
        'room_id'  => (string)$row['room_id'],
        'name'     => (string)$row['name'],
        'capacity' => (int)$row['capacity'],
        'floor'    => isset($row['floor']) ? $row['floor'] : null
    ];
}
$stmt->close();
$conn->close();

if (empty($rooms)) {
    echo json_encode(['success' => false, 'msg' => 'No available rooms found for the selected criteria', 'rooms' => []]);
} else {
    echo json_encode(['success' => true, 'rooms' => $rooms]);
}
exit;