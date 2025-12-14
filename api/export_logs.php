<?php
// export_logs.php - Export admin logs to CSV
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Access Control
if (!isset($_SESSION['loggedin']) || strcasecmp(trim($_SESSION['User_Type']), 'Admin') != 0) {
    exit("Access Denied");
}

// Reuse the filtering logic
$where = ["1=1"];
$params = [];
$types = "";

if (!empty($_GET['search'])) {
    $where[] = "u.username LIKE ?";
    $params[] = "%" . $_GET['search'] . "%";
    $types .= "s";
}
if (!empty($_GET['action']) && $_GET['action'] != 'all') {
    $where[] = "l.action = ?";
    $params[] = $_GET['action'];
    $types .= "s";
}
if (!empty($_GET['start'])) {
    $where[] = "DATE(l.created_at) >= ?";
    $params[] = $_GET['start'];
    $types .= "s";
}
if (!empty($_GET['end'])) {
    $where[] = "DATE(l.created_at) <= ?";
    $params[] = $_GET['end'];
    $types .= "s";
}

$sql = "SELECT l.id, u.username as admin_name, l.action, l.booking_id, l.note, l.ip_address, l.created_at 
        FROM admin_logs l 
        LEFT JOIN users u ON l.admin_id = u.id 
        WHERE " . implode(" AND ", $where) . " 
        ORDER BY l.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Set Headers for Download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="admin_logs_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Log ID', 'Admin Name', 'Action', 'Booking Ref', 'Details', 'IP Address', 'Date']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}
fclose($output);
exit;
?>