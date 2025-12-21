<?php
// api/get_week_schedule.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_connect.php';

$room_id = $_GET['room_id'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d'); // The date of the request

if (!$room_id) {
    echo json_encode(['error' => 'Missing room_id']);
    exit;
}

// Calculate Start (Monday) and End (Sunday) of that week
$ts = strtotime($date);
$start_of_week = date('Y-m-d', strtotime('monday this week', $ts));
$end_of_week   = date('Y-m-d', strtotime('sunday this week', $ts));

// Fetch confirmed bookings for this room in this date range
$sql = "SELECT slot_date, time_start, time_end, status, purpose 
        FROM bookings 
        WHERE room_id = ? 
        AND slot_date BETWEEN ? AND ? 
        AND status IN ('booked', 'approved', 'recurring')"; // Only show occupied slots

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $room_id, $start_of_week, $end_of_week);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    // Simplify time to HH:MM
    $row['time_start'] = substr($row['time_start'], 0, 5);
    $row['time_end'] = substr($row['time_end'], 0, 5);
    $bookings[] = $row;
}

echo json_encode([
    'start_date' => $start_of_week,
    'end_date' => $end_of_week,
    'bookings' => $bookings
]);
?>