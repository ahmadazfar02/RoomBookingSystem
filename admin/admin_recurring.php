<?php
// admin_recurring_timetable.php - FIXED VERSION for time_start/time_end columns
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

function json_ok($data = []) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(array_merge(['success'=>true], $data)); exit; }
function json_err($msg = 'Error', $extra = []) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(array_merge(['success'=>false, 'msg'=>$msg], $extra)); exit; }
function log_error($m) { error_log($m); @file_put_contents(__DIR__.'/admin_recurring_error.log',"[".date('Y-m-d H:i:s')."] ".$m.PHP_EOL, FILE_APPEND); }

$admin_id = $_SESSION['User_ID'] ?? $_SESSION['id'] ?? null;
$user_type = $_SESSION['User_Type'] ?? null;
$is_json_request = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) || ($_SERVER['REQUEST_METHOD'] === 'POST');

$admin_name = $_SESSION['Fullname'] ?? 'Admin'; 
$admin_email = $_SESSION['Email'] ?? 'Admin';
$username = $_SESSION['username'] ?? 'superadmin';

if (!$admin_id || strcasecmp(trim($user_type ?? ''), 'Admin') !== 0) {
    if ($is_json_request) json_err('Not authorized (admin only)');
    header('Location: ../loginterface.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['endpoint'])) {
    $endpoint = $_GET['endpoint'];

    if ($endpoint === 'rooms') {
        $rooms = [];
        $res = $conn->query("SELECT room_id, name, capacity FROM rooms ORDER BY name");
        if ($res) while ($r = $res->fetch_assoc()) $rooms[] = $r;
        json_ok(['rooms'=>$rooms]);
    }

    if ($endpoint === 'list') {
        $room = $_GET['room'] ?? '';
        if ($room) {
            // FIXED: Use time_start and time_end columns
            $stmt = $conn->prepare("SELECT id, room_id, day_of_week, time_start, time_end, purpose, description, tel, status FROM recurring_bookings WHERE room_id = ? ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), time_start");
            if (!$stmt) {
                log_error("list prepare failed: " . $conn->error);
                json_err('DB prepare failed', ['db_error'=>$conn->error]);
            }
            $stmt->bind_param("s", $room);
            if (!$stmt->execute()) {
                log_error("list execute failed: " . $stmt->error);
                $stmt->close();
                json_err('DB execute failed', ['db_error'=>$stmt->error]);
            }
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) {
                // Combine time_start and time_end into time_slot for frontend
                $r['time_slot'] = substr($r['time_start'], 0, 5) . '-' . substr($r['time_end'], 0, 5);
                $rows[] = $r;
            }
            $stmt->close();
        } else {
            $res = $conn->query("SELECT id, room_id, day_of_week, time_start, time_end, purpose, description, tel, status FROM recurring_bookings ORDER BY room_id, FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), time_start");
            if (!$res) {
                log_error("list query failed: " . $conn->error);
                json_err('DB query failed', ['db_error'=>$conn->error]);
            }
            $rows = [];
            while ($r = $res->fetch_assoc()) {
                $r['time_slot'] = substr($r['time_start'], 0, 5) . '-' . substr($r['time_end'], 0, 5);
                $rows[] = $r;
            }
        }
        json_ok(['recurring'=>$rows]);
    }

    json_err('Unknown endpoint');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) json_err('Invalid JSON');

    $action = $data['action'] ?? '';
    if (!$action) json_err('Missing action');

    if ($action === 'create_bulk') {
        $bookings = $data['bookings'] ?? [];
        if (!is_array($bookings) || count($bookings) === 0) json_err('No bookings provided');

        $conn->begin_transaction();
        $created = 0; $skipped = 0; $errors = [];
        
        try {
            foreach ($bookings as $i => $b) {
                $room_id = trim($b['room_id'] ?? '');
                $day = trim($b['day_of_week'] ?? '');
                $slot = trim($b['time_slot'] ?? ''); // e.g. "08:00-08:50"
                $purpose = trim($b['purpose'] ?? '');
                $description = isset($b['description']) ? trim($b['description']) : null;
                $tel = isset($b['tel']) ? trim($b['tel']) : null;

                if (!$room_id || !$day || !$slot || !$purpose) {
                    $errors[] = "Missing fields for item #$i";
                    $skipped++;
                    continue;
                }

                // FIXED: Split time_slot into time_start and time_end
                $parts = explode('-', $slot);
                if (count($parts) !== 2) {
                    $errors[] = "Invalid time_slot format for item #$i";
                    $skipped++;
                    continue;
                }
                $time_start = $parts[0] . ':00'; // e.g. "08:00:00"
                $time_end = $parts[1] . ':00';   // e.g. "08:50:00"

                // FIXED: Check conflict using time_start
                $chk = $conn->prepare("SELECT id FROM recurring_bookings WHERE room_id=? AND day_of_week=? AND time_start=? AND status='active' LIMIT 1");
                if (!$chk) { 
                    $errors[] = "DB prepare failed"; 
                    $skipped++; 
                    continue; 
                }
                $chk->bind_param("sss", $room_id, $day, $time_start);
                $chk->execute();
                $res = $chk->get_result();
                $exists = ($res && $res->num_rows > 0);
                $chk->close();
                
                if ($exists) { 
                    $skipped++; 
                    continue; 
                }

                // FIXED: Insert with time_start and time_end
                $ins = $conn->prepare("INSERT INTO recurring_bookings (room_id, day_of_week, time_start, time_end, purpose, description, tel, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)");
                if (!$ins) { 
                    $errors[] = "DB prepare insert failed: " . $conn->error; 
                    $skipped++; 
                    continue; 
                }
                $ins->bind_param("sssssssi", $room_id, $day, $time_start, $time_end, $purpose, $description, $tel, $admin_id);
                
                if ($ins->execute()) { 
                    $created++; 
                } else { 
                    $errors[] = "Insert failed for item #$i: ".$ins->error; 
                    $skipped++; 
                }
                $ins->close();
            }
            
            $conn->commit();
            
        } catch (Exception $e) {
            $conn->rollback();
            log_error("create_bulk error: " . $e->getMessage());
            json_err('Transaction failed', ['error'=>$e->getMessage()]);
        }
        
        json_ok(['created'=>$created,'skipped'=>$skipped,'errors'=>$errors]);
    }

    if ($action === 'update') {
        $id = intval($data['id'] ?? 0);
        $purpose = trim($data['purpose'] ?? '');
        $description = isset($data['description']) ? trim($data['description']) : null;
        $tel = trim($data['tel'] ?? '');
        $status = $data['status'] ?? 'active';
        if (!$id || !$purpose) json_err('Missing fields');

        $stmt = $conn->prepare("UPDATE recurring_bookings SET purpose=?, description=?, tel=?, status=? WHERE id=?");
        if (!$stmt) json_err('DB prepare failed', ['db_error'=>$conn->error]);
        $stmt->bind_param("ssssi", $purpose, $description, $tel, $status, $id);
        if (!$stmt->execute()) json_err('Update failed', ['db_error'=>$stmt->error]);
        $stmt->close();
        json_ok(['msg'=>'Updated']);
    }

    if ($action === 'delete') {
        $id = intval($data['id'] ?? 0);
        if (!$id) json_err('Invalid id');
        $stmt = $conn->prepare("DELETE FROM recurring_bookings WHERE id=?");
        if (!$stmt) json_err('DB prepare failed', ['db_error'=>$conn->error]);
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) json_err('Delete failed', ['db_error'=>$stmt->error]);
        $stmt->close();
        json_ok(['msg'=>'Deleted']);
    }

    json_err('Unknown action');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recurring Timetable Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --primary-light: #dbeafe;
    --success: #059669;
    --success-light: #d1fae5;
    --warning: #f59e0b;
    --info: #0891b2;
    --info-light: #cffafe;
    --danger: #dc2626;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  }
  
  * { box-sizing: border-box; margin: 0; padding: 0; }
  
  body { 
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    min-height: 100vh;
    padding: 0;
    margin: 0;
    /* Removed display: flex from body to allow proper scrolling wrappers */
  }
  
  /* Navigation Bar */
  .nav-bar {
    background: white;
    padding: 16px 24px;
    box-shadow: var(--shadow-md);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 80px;
  }
  
  .nav-bar img {
    height: 50px; /* Adjusted slightly to fit better */
    width: auto;
  }

  /* --- NEW LAYOUT CONTAINER --- */
  /* This fixes the sidebar issue by creating a flexible container */
  .layout-container {
    width: 100%;
    max-width: 2300px;   /* allow up to 2000px */
    padding: 24px;
    gap: 24px;
    margin: 100px auto 0; /* keep centered and below fixed navbar */
    display: flex;
    align-items: flex-start;/* Important for sticky sidebar */
  }
  
  /* Sidebar - Modified to be Sticky */
  .sidebar {
    width: 260px;
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: var(--shadow-lg);
    z-index: 100;
    flex-shrink: 0; /* Prevent sidebar from shrinking */
    
    /* Sticky Magic */
    position: sticky;
    top: 100px; /* Sticks 100px from top of viewport */
  }
  
  .sidebar-title {
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gray-600);
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--gray-200);
  }
  
  .sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
  }
  
  .sidebar-menu li {
    margin-bottom: 8px;
  }
  
  .sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 8px;
    text-decoration: none;
    color: var(--gray-700);
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
  }
  
  .sidebar-menu a:hover {
    background: var(--gray-100);
    color: var(--primary);
  }
  
  .sidebar-menu a.active {
    background: var(--primary-light);
    color: var(--primary);
    font-weight: 600;
  }

    /* Sidebar Profile Styles */
  .sidebar-profile {
    margin-top: auto; /* Pushes to bottom */
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 12px;
  }

    .profile-icon {
     width: 36px; 
     height: 36px; 
     background: var(--primary-light); 
     border-radius: 50%; 
     display: flex; 
     align-items: center; 
     justify-content: center; 
     color: var(--primary); 
     font-weight: 700;
  }
  
  .profile-info { font-size: 13px; }
  .profile-name { font-weight: 600; color: var(--gray-800); margin-bottom: 2px; }
  .profile-email { font-size: 11px; color: var(--gray-600); }
  
  /* Main Content Area - Modified to fill space */
  .main-wrapper {
    flex: 1; /* Takes remaining width */
    min-width: 0; /* Prevents overflow issues */
    /* Removed margins as flex gap handles spacing */
  }
  
  /* Header Card */
  .header-card {
    background: white;
    border-radius: 12px;
    padding: 24px 32px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-md);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .header-title {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  
  .header-title h1 {
    font-size: 24px;
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
  }
  
  .header-badge {
    background: var(--primary-light);
    color: var(--primary);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  
  .header-subtitle {
    font-size: 14px;
    color: var(--gray-600);
    margin-top: 4px;
  }
  
  /* Main Container */
  .main-container {
    background: white;
    border-radius: 12px;
    padding: 32px;
    box-shadow: var(--shadow-lg);
  }
  
  /* Control Panel - Structure Improved */
  .control-panel {
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 24px;
    margin-bottom: 24px;
  }
  
  .control-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--gray-700);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
    display: block;
  }
  
  .room-selector {
    width: 100%;
    padding: 10px 16px; /* Adjusted height slightly */
    border: 2px solid var(--gray-300);
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: var(--gray-800);
    background: white;
    transition: all 0.2s;
    cursor: pointer;
  }
  
  .room-selector:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
  }
  
  /* Action Bar */
  .action-bar {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 16px 20px;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
  }
  
  .action-instruction {
    flex: 1;
    min-width: 250px;
    font-size: 13px;
    color: var(--gray-600);
    line-height: 1.4;
  }
  
  /* Buttons */
  .btn-custom {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center; /* Centered content */
    gap: 8px;
    text-decoration: none;
    box-shadow: var(--shadow-sm);
    white-space: nowrap;
    height: 46px; /* Uniform height for alignment */
  }
  
  .btn-primary-custom {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
  }
  
  .btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
  }
  
  .btn-success-custom {
    background: linear-gradient(135deg, var(--success) 0%, #047857 100%);
    color: white;
  }
  
  .btn-success-custom:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
  }
  
  .btn-danger-custom {
    background: linear-gradient(135deg, var(--danger) 0%, #b91c1c 100%);
    color: white;
  }
  
  .btn-danger-custom:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
  }
  
  .btn-outline-custom {
    background: white;
    color: var(--gray-700);
    border: 2px solid var(--gray-300);
  }
  
  .btn-outline-custom:hover {
    background: var(--gray-50);
    border-color: var(--gray-400);
  }
  
  /* Grid Container */
  .grid-container {
    overflow: auto; /* Enables both horizontal and vertical scrolling */
    max-height: 75vh; /* Restricts height so sticky headers work perfectly */
    border-radius: 10px;
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow);
    position: relative; /* Context for sticky elements */
  }
  
  table.grid { 
    border-collapse: separate; /* Switch to separate to fix sticky border glitches */
    border-spacing: 0; /* Keeps it looking like collapse */
    width: 100%;
    background: white;
  }
  
  table.grid th, 
  table.grid td { 
    border-right: 1px solid var(--gray-200);
    border-bottom: 1px solid var(--gray-200);
    padding: 12px 8px;
    text-align: center;
    vertical-align: middle;
  }

  /* Fix borders for top and left edges */
  table.grid th { border-top: 1px solid var(--gray-200); }
  table.grid tr td:first-child, table.grid tr th:first-child { border-left: 1px solid var(--gray-200); }
  
  table.grid th { 
    background: linear-gradient(180deg, var(--gray-100) 0%, var(--gray-50) 100%);
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gray-700);
    position: sticky;
    top: 0;
    z-index: 20; /* Level 2: Headers sit above slots */
  }

  /* THE CRITICAL FIX: Top-Left Corner Cell */
  /* This ensures the corner stays fixed while both columns and rows scroll under it */
  table.grid thead tr th:first-child {
    position: sticky;
    left: 0;
    top: 0;
    z-index: 30; /* Level 3: Corner sits above EVERYTHING */
    background: #f3f4f6; /* Solid background to prevent transparency issues */
    border-right: 1px solid var(--gray-300); /* Stronger border for separation */
  }
  
  td.day-col { 
    background: linear-gradient(180deg, #f0f9ff 0%, #e0f2fe 100%);
    font-weight: 700;
    font-size: 13px;
    width: 140px;
    color: var(--primary-dark);
    position: sticky;
    left: 0;
    z-index: 15; /* Level 1.5: Day Col sits above slots but below Headers */
    border-right: 2px solid var(--primary-light); /* Visual separation */
  }
  
  /* Slot Cells */
  td.slot { 
    cursor: pointer;
    min-width: 110px;
    height: 65px;
    position: relative;
    transition: all 0.2s;
    z-index: 1; /* Level 1: Lowest level */
  }
  
  table.grid { 
    border-collapse: collapse; 
    width: 100%;
    background: white;
  }
  
  table.grid th, 
  table.grid td { 
    border: 1px solid var(--gray-200);
    padding: 12px 8px;
    text-align: center;
    vertical-align: middle;
  }
  
  table.grid th { 
    background: linear-gradient(180deg, var(--gray-100) 0%, var(--gray-50) 100%);
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gray-700);
    position: sticky;
    top: 0;
    z-index: 10;
  }
  
  td.day-col { 
    background: linear-gradient(180deg, #f0f9ff 0%, #e0f2fe 100%);
    font-weight: 700;
    font-size: 13px;
    width: 140px;
    color: var(--primary-dark);
    position: sticky;
    left: 0;
    z-index: 5;
  }
  
  /* Slot Cells */
  td.slot { 
    cursor: pointer;
    min-width: 110px;
    height: 65px;
    position: relative;
    transition: all 0.2s;
  }
  
  td.slot:hover:not(.recurring) { 
    transform: scale(1.02);
    box-shadow: inset 0 0 0 2px var(--primary);
    z-index: 2;
  }
  
  td.available { 
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
  }
  
  td.selected { 
    background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
    border: 2px solid var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
  }
  
  td.recurring { 
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    color: #3730a3;
    font-weight: 700;
    border-left: 4px solid #4f46e5;
    cursor: pointer;
  }
  
  td.recurring:hover {
    background: linear-gradient(135deg, #c7d2fe 0%, #a5b4fc 100%);
    transform: scale(1.02);
    box-shadow: 0 0 0 2px #4f46e5, 0 4px 6px rgba(79, 70, 229, 0.2);
    z-index: 2;
  }
  
  .recurring-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 4px;
  }
  
  .recurring-title {
    font-weight: 700;
    font-size: 12px;
    color: #3730a3;
  }
  
  .recurring-time {
    font-size: 10px;
    color: #4f46e5;
    opacity: 0.8;
  }
  
  .edit-hint {
    position: absolute;
    bottom: 2px;
    right: 4px;
    font-size: 9px;
    color: #4f46e5;
    opacity: 0;
    transition: opacity 0.2s;
  }
  
  td.recurring:hover .edit-hint {
    opacity: 1;
  }
  
  /* Modal Styles */
  .modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: var(--shadow-lg);
  }
  
  .modal-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border-radius: 12px 12px 0 0;
    padding: 20px 24px;
  }
  
  .modal-title {
    font-weight: 700;
    font-size: 18px;
  }
  
  .modal-body {
    padding: 24px;
  }
  
  .modal-footer {
    padding: 16px 24px;
    background: var(--gray-50);
    border-radius: 0 0 12px 12px;
  }
  
  .form-label {
    font-weight: 600;
    font-size: 13px;
    color: var(--gray-700);
    margin-bottom: 6px;
  }
  
  .form-control, .form-select {
    border: 2px solid var(--gray-300);
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 14px;
    transition: all 0.2s;
  }
  
  .form-control:focus, .form-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
  }
  
  .form-control[readonly] {
    background: var(--gray-100);
    cursor: not-allowed;
  }
  
  /* Responsive */
  @media (max-width: 1200px) {
    .sidebar {
      display: none;
    }
    
    .layout-container {
      display: block; /* Stack on mobile */
    }
  }
  
  @media (max-width: 768px) {
    .header-card { 
      flex-direction: column; 
      gap: 16px;
      text-align: center;
    }
    .main-container { padding: 20px; }
    .control-panel { padding: 16px; }
    .action-bar { 
      flex-direction: column;
      align-items: stretch;
    }
    .btn-custom { 
      width: 100%;
    }
    .action-instruction {
      text-align: center;
    }
  }
</style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="nav-bar">
  <img src="../assets/images/utmlogo.png" alt="UTM Logo" />
</nav>

<!-- Layout Container (Flexbox Wrapper) -->
<div class="layout-container">

  <!-- Sidebar (Sticky) -->
  <aside class="sidebar">
    <div class="sidebar-title">Main Menu</div>
    <ul class="sidebar-menu">
      <li><a href="index-admin.php">Dashboard</a></li>
      <li><a href="reservation_request.php">Reservation Request</a></li>
      <li><a href="admin_timetable.php">Regular Timetable</a></li>
      <li><a href="admin_recurring.php" class="active">Recurring Templates</a></li>
      <?php if ($username === 'superadmin'): ?>
          <li><a href="manage_users.php">Manage Users</a></li>
      <?php endif; ?>
      <li><a href="admin_logbook.php">Logbook</a></li>
      <li><a href="generate_reports.php">Generate Reports</a></li>
      <li><a href="admin_problems.php">Room Problems</a></li>
    </ul>

    <div class="sidebar-profile">
      <div class="profile-icon"><?php echo strtoupper(substr($admin_name,0,1)); ?></div>
      <div class="profile-info">
        <div class="profile-name"> <?php echo htmlspecialchars($admin_name); ?></div>
        <div class="profile-email"><?php echo htmlspecialchars($admin_email); ?></div>
      </div>
    </div>
  </aside>

  <!-- Main Content Wrapper -->
  <div class="main-wrapper">
    
    <!-- Header -->
    <div class="header-card">
      <div class="header-title">
        <div>
          <h1>Recurring Timetable Templates</h1>
          <div class="header-subtitle">Create weekly repeating schedules</div>
        </div>
        <span class="header-badge">Admin</span>
      </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
      
      <!-- Control Panel (Structured with Bootstrap Grid) -->
      <div class="control-panel">
        <div class="row g-3 align-items-end">
          
          <!-- Room Selection (Wider) -->
          <div class="col-md-5">
            <label class="control-label">Select Room</label>
            <select id="roomSelect" class="room-selector" size="1"></select>
          </div>
          
          <!-- Load Button -->
          <div class="col-md-2">
            <button id="loadBtn" class="btn-custom btn-primary-custom w-100">
              Load
            </button>
          </div>
          
          <!-- Spacer for visual separation -->
          <div class="col-md-1"></div>

          <!-- Action Buttons -->
          <div class="col-md-2">
            <button id="clearSelBtn" class="btn-custom btn-outline-custom w-100">
              Clear
            </button>
          </div>
          
          <div class="col-md-2">
            <button id="addSelectedBtn" class="btn-custom btn-success-custom w-100">
              + Add
            </button>
          </div>
          
        </div>
      </div>

      <!-- Action Bar with Instructions -->
      <div class="action-bar">
        <div class="action-instruction">
          <strong>Quick Guide:</strong> Click on empty (light blue) cells to select multiple time slots. Click on recurring (indigo) cells to edit or delete them.
        </div>
      </div>

      <!-- Timetable Grid -->
      <div id="gridArea" class="grid-container"></div>

    </div>
  </div>

</div>

<!-- Modal (Unchanged IDs for script compatibility) -->
<div class="modal fade" id="slotModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="modalTitle" class="modal-title">Add Recurring Slot(s)</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editId" value="">
        
        <div class="mb-3">
          <label class="form-label">Room</label>
          <input id="modalRoom" class="form-control" readonly>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Day(s)</label>
          <input id="modalDays" class="form-control" readonly>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Time Slot(s)</label>
          <input id="modalTimes" class="form-control" readonly>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Subject Code *</label>
          <input id="modalPurpose" class="form-control" placeholder="e.g., SECJ1234">
        </div>
        
        <div class="mb-3">
          <label class="form-label">Subject Name *</label>
          <textarea id="modalDesc" class="form-control" rows="3" placeholder="Subject Name"></textarea>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Contact Tel</label>
          <input id="modalTel" class="form-control" placeholder="e.g., +60123456789">
        </div>
        
        <div class="mb-3" id="modalStatusRow" style="display:none;">
          <label class="form-label">Status</label>
          <select id="modalStatus" class="form-select">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      
      <div class="modal-footer">
        <button id="modalDeleteBtn" class="btn-custom btn-danger-custom me-auto" style="display:none;">
          Delete
        </button>
        <button class="btn-custom btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
        <button id="modalSaveBtn" class="btn-custom btn-success-custom">
          Save
        </button>
      </div>
    </div>
  </div>
</div> 




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
const TIME_SLOTS = [
  "08:00-08:50","09:00-09:50","10:00-10:50","11:00-11:50",
  "12:00-12:50","13:00-13:50","14:00-14:50","15:00-15:50",
  "16:00-16:50","17:00-17:50","18:00-18:50","19:00-19:50",
  "20:00-20:50","21:00-21:50","22:00-22:50","23:00-23:50",
];
const DAYS = ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];

const roomSelect = document.getElementById('roomSelect');
const loadBtn = document.getElementById('loadBtn');
const gridArea = document.getElementById('gridArea');
const clearSelBtn = document.getElementById('clearSelBtn');
const addSelectedBtn = document.getElementById('addSelectedBtn');

const slotModal = new bootstrap.Modal(document.getElementById('slotModal'));
const modalTitle = document.getElementById('modalTitle');
const editId = document.getElementById('editId');
const modalRoom = document.getElementById('modalRoom');
const modalDays = document.getElementById('modalDays');
const modalTimes = document.getElementById('modalTimes');
const modalPurpose = document.getElementById('modalPurpose');
const modalDesc = document.getElementById('modalDesc');
const modalTel = document.getElementById('modalTel');
const modalStatusRow = document.getElementById('modalStatusRow');
const modalStatus = document.getElementById('modalStatus');
const modalDeleteBtn = document.getElementById('modalDeleteBtn');
const modalSaveBtn = document.getElementById('modalSaveBtn');

let recurringIndex = {};
let selectedCells = new Map();
let currentRoom = null;

function escapeHtml(s){ if(!s) return ''; return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function loadRooms(){
  // FIXED: Changed to admin_recurring.php
  fetch('admin_recurring.php?endpoint=rooms', {cache:'no-store'})
    .then(r=>{
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(j=>{
      if (!j.success) {
        console.error('Load rooms error:', j);
        alert('Failed: ' + (j.msg || 'Unknown'));
        return;
      }
      roomSelect.innerHTML = '';
      j.rooms.forEach(rm=>{
        const opt = document.createElement('option');
        opt.value = rm.room_id;
        opt.textContent = `${rm.name} (${rm.room_id}) - ${rm.capacity || ''}`;
        roomSelect.appendChild(opt);
      });
      currentRoom = roomSelect.value;
    })
    .catch(e=>{ console.error(e); alert('Network error: ' + e.message); });
}

function renderGrid(){
  const room = roomSelect.value;
  if (!room) { gridArea.innerHTML = '<div class="alert alert-warning">Select a room</div>'; return; }
  currentRoom = room;
  recurringIndex = {};
  selectedCells.clear();
  gridArea.innerHTML = '';

  const table = document.createElement('table'); table.className = 'grid';
  const thead = document.createElement('thead');
  const headRow = document.createElement('tr');
  const corner = document.createElement('th'); corner.textContent = 'Day'; corner.style.minWidth='140px'; headRow.appendChild(corner);
  TIME_SLOTS.forEach(ts => { const th = document.createElement('th'); th.textContent = ts; th.style.minWidth='110px'; headRow.appendChild(th); });
  thead.appendChild(headRow); table.appendChild(thead);

  const tbody = document.createElement('tbody');
  DAYS.forEach(day => {
    const tr = document.createElement('tr');
    const tdDay = document.createElement('td'); tdDay.className = 'day-col'; tdDay.textContent = day; tr.appendChild(tdDay);

    TIME_SLOTS.forEach(slot => {
      const key = day + '|' + slot;
      const td = document.createElement('td');
      td.className = 'slot available';
      td.dataset.day = day;
      td.dataset.slot = slot;
      td.dataset.key = key;

      td.addEventListener('click', (ev) => {
        if (td.classList.contains('recurring')) {
          openEditModalForCell(td);
          return;
        }
        if (td.classList.contains('selected')) {
          td.classList.remove('selected');
          selectedCells.delete(key);
        } else {
          td.classList.add('selected');
          selectedCells.set(key, {day, slot, td});
        }
      });

      tr.appendChild(td);
    });

    tbody.appendChild(tr);
  });

  table.appendChild(tbody);
  gridArea.appendChild(table);

  // FIXED: Changed to admin_recurring.php
  fetch(`admin_recurring.php?endpoint=list&room=${encodeURIComponent(room)}`, {cache:'no-store'})
    .then(r=>{
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(j=>{
      if (!j.success) {
        console.error('Load recurring error:', j);
        alert('Failed: ' + (j.msg || 'Unknown'));
        return;
      }
      j.recurring.forEach(r => {
        const key = r.day_of_week + '|' + r.time_slot;
        recurringIndex[key] = r;
        const td = document.querySelector(`td[data-key="${key}"]`);
        if (!td) return;
        td.classList.remove('available','selected');
        td.classList.add('recurring');
        td.innerHTML = `<div style="font-weight:700">${escapeHtml(r.purpose)}</div><div style="font-size:0.85rem">${r.time_slot}</div>`;
        td.dataset.recurringId = r.id;
      });
    })
    .catch(e=>{ console.error(e); alert('Network error: ' + e.message); });
}

function openEditModalForCell(td){
  const id = td.dataset.recurringId;
  if (!id) return;
  const key = td.dataset.key;
  const r = recurringIndex[key];
  if (!r) return alert('Data not found');
  fillModalForEdit(r);
}

function fillModalForEdit(r){
  editId.value = r.id;
  modalRoom.value = r.room_id;
  modalDays.value = r.day_of_week;
  modalTimes.value = r.time_slot;
  modalPurpose.value = r.purpose || '';
  modalDesc.value = r.description || '';
  modalTel.value = r.tel || '';
  modalStatus.value = r.status || 'active';
  modalStatusRow.style.display = 'block';
  modalDeleteBtn.style.display = 'inline-block';
  modalTitle.textContent = 'Edit Recurring Slot';
  slotModal.show();
}

function openAddSelectedModal(){
  if (selectedCells.size === 0) return alert('Select at least one slot');
  editId.value = '';
  modalRoom.value = currentRoom;
  modalStatusRow.style.display = 'none';
  modalDeleteBtn.style.display = 'none';
  const days = Array.from(new Set(Array.from(selectedCells.values()).map(x=>x.day)));
  const times = Array.from(new Set(Array.from(selectedCells.values()).map(x=>x.slot)));
  modalDays.value = days.join(', ');
  modalTimes.value = times.join(', ');
  modalPurpose.value = '';
  modalDesc.value = '';
  modalTel.value = '';
  modalTitle.textContent = 'Add Recurring Slot(s)';
  slotModal.show();
}

modalSaveBtn.addEventListener('click', ()=>{
  const id = editId.value;
  const purpose = modalPurpose.value.trim();
  if (!purpose) return alert('Purpose required');
  modalSaveBtn.disabled = true;

  if (id) {
    const payload = { action:'update', id: parseInt(id,10), purpose, description: modalDesc.value.trim(), tel: modalTel.value.trim(), status: modalStatus.value };
    // FIXED: Changed to admin_recurring.php
    fetch('admin_recurring.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
      .then(r=>r.json()).then(j=>{
        modalSaveBtn.disabled = false;
        if (!j.success) return alert('Update failed: ' + (j.msg||''));
        slotModal.hide();
        renderGrid();
      }).catch(e=>{ modalSaveBtn.disabled=false; console.error(e); alert('Error: ' + e.message); });
    return;
  }

  const bookings = [];
  selectedCells.forEach(v=>{
    bookings.push({
      room_id: currentRoom,
      day_of_week: v.day,
      time_slot: v.slot,
      purpose,
      description: modalDesc.value.trim(),
      tel: modalTel.value.trim()
    });
  });

  const payload = { action:'create_bulk', bookings };
  // FIXED: Changed to admin_recurring.php
  fetch('admin_recurring.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
    .then(r=>r.json()).then(j=>{
      modalSaveBtn.disabled = false;
      if (!j.success) return alert('Failed: ' + (j.msg|| (j.errors && j.errors.join('\n')) || ''));
      selectedCells.forEach(v=> v.td.classList.remove('selected'));
      selectedCells.clear();
      slotModal.hide();
      renderGrid();
    }).catch(e=>{ modalSaveBtn.disabled=false; console.error(e); alert('Error: ' + e.message); });
});

modalDeleteBtn.addEventListener('click', ()=>{
  const id = parseInt(editId.value||0,10);
  if (!id) return;
  if (!confirm('Delete?')) return;
  modalDeleteBtn.disabled = true;
  // FIXED: Changed to admin_recurring.php
  fetch('admin_recurring.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete', id }) })
    .then(r=>r.json()).then(j=>{
      modalDeleteBtn.disabled = false;
      if (!j.success) return alert('Delete failed: ' + (j.msg||''));
      slotModal.hide();
      renderGrid();
    }).catch(e=>{ modalDeleteBtn.disabled=false; console.error(e); alert('Error: ' + e.message); });
});

clearSelBtn.addEventListener('click', ()=>{
  selectedCells.forEach(v=> v.td.classList.remove('selected'));
  selectedCells.clear();
});

addSelectedBtn.addEventListener('click', openAddSelectedModal);
loadBtn.addEventListener('click', renderGrid);
roomSelect.addEventListener('change', renderGrid);

loadRooms();
setTimeout(()=>{ if (roomSelect.value) renderGrid(); }, 300);
</script>
</body>
</html>