<?php
// get_rooms.php
// Returns JSON list of all active rooms from the database
// Used to populate the room dropdown on page load

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
session_start();

function json_err($msg){
    echo json_encode(['success'=>false, 'msg'=>$msg]);
    exit;
}

// require db_connect.php which must set $conn (mysqli)
$dbfile = __DIR__ . '/../includes/db_connect.php';
if (!file_exists($dbfile)) json_err('Server configuration missing');
require_once $dbfile;

if (!isset($conn) || !($conn instanceof mysqli)) json_err('Database connection not available');

// Fetch all active rooms ordered by name
$sql = "
SELECT room_id, name, capacity, floor
FROM rooms
WHERE active = 1
ORDER BY name ASC
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    json_err('DB prepare failed: ' . $conn->error);
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

echo json_encode(['success' => true, 'rooms' => $rooms]);
exit;