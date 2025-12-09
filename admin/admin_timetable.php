<?php
// admin_timetable.php - COMPLETE FIXED VERSION
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

function log_error($msg) {
    error_log($msg);
    @file_put_contents(__DIR__ . '/admin_timetable_error.log', "[".date('Y-m-d H:i:s')."] ".$msg.PHP_EOL, FILE_APPEND);
}

$admin_id = $_SESSION['User_ID'] ?? $_SESSION['id'] ?? null;
$user_type = $_SESSION['User_Type'] ?? null;
$is_ajax = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) || (isset($_GET['endpoint'])) || ($_SERVER['REQUEST_METHOD'] === 'POST' && (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false));

$admin_name = $_SESSION['Fullname'] ?? 'Admin'; 
$admin_email = $_SESSION['Email'] ?? 'Admin';
$username = $_SESSION['username'] ?? 'superadmin';

if (!$admin_id || strcasecmp(trim($user_type ?? ''), 'Admin') !== 0) {
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false, 'msg'=>'Not authorized']);
        exit;
    } else {
        header('Location: ../loginterface.html');
        exit;
    }
}

function json_ok($data = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success'=>true], $data));
    exit;
}
function json_err($msg = 'Error', $extra = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success'=>false, 'msg'=>$msg], $extra));
    exit;
}

function admin_log($conn, $admin_id, $booking_id, $action, $note = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $sql = "INSERT INTO admin_logs (admin_id, booking_id, action, note, ip_address) VALUES (?, ?, ?, ?, ?)";
    $stmt = @$conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iisss", $admin_id, $booking_id, $action, $note, $ip);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}

// GET endpoints
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['endpoint'])) {
    $endpoint = $_GET['endpoint'];

    if ($endpoint === 'rooms') {
        $rooms = [];
        $res = $conn->query("SELECT room_id, name, capacity FROM rooms ORDER BY name");
        if ($res) {
            while ($r = $res->fetch_assoc()) $rooms[] = $r;
        }
        json_ok(['rooms'=>$rooms]);
    }

    // ---------- bookings endpoint (REPLACE existing bookings block with this) ----------
    if ($endpoint === 'bookings') {
        $room = $_GET['room'] ?? '';
        $start = $_GET['start'] ?? '';
        $end = $_GET['end'] ?? '';

        if (!$room || !$start || !$end) json_err('Missing parameters: room/start/end');

        // 1) fetch one-time bookings in range
        $sql = "SELECT id, ticket, user_id, purpose, description, tel, slot_date, time_start, time_end, status, created_at
                FROM bookings
                WHERE room_id = ? AND slot_date BETWEEN ? AND ?
                AND status NOT IN ('deleted')
                ORDER BY slot_date, time_start";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            log_error("bookings prepare failed: " . $conn->error);
            json_err('DB prepare failed', ['db_error'=>$conn->error]);
        }
        $stmt->bind_param("sss", $room, $start, $end);
        if (!$stmt->execute()) {
            log_error("bookings execute failed: " . $stmt->error);
            $stmt->close();
            json_err('DB execute failed', ['db_error'=>$stmt->error]);
        }
        $res = $stmt->get_result();
        $out = [];
        $existingKeys = []; // to prevent recurring duplicates where one-time exists

        while ($r = $res->fetch_assoc()) {
            $slotTime = substr($r['time_start'],0,5) . '-' . substr($r['time_end'],0,5);
            $key = $r['slot_date'] . '|' . $slotTime;
            $existingKeys[$key] = true;

            $out[] = [
                'id' => (int)$r['id'],
                'ticket' => $r['ticket'],
                'user_id' => (int)$r['user_id'],
                'purpose' => $r['purpose'],
                'description' => $r['description'],
                'tel' => $r['tel'],
                'slot_date' => $r['slot_date'],
                'time_start' => substr($r['time_start'],0,5),
                'time_end' => substr($r['time_end'],0,5),
                'status' => $r['status'],
                'created_at' => $r['created_at'],
            ];
        }
        $stmt->close();

        // 2) find which recurring table actually exists (your DB uses admin_recurring or recurring_bookings etc.)
        $candidate_tables = ['recurring_bookings','admin_recurring','admin_recurring_bookings'];
        $recurring_table = null;
        foreach ($candidate_tables as $tname) {
            $check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tname) . "'");
            if ($check && $check->num_rows > 0) { $recurring_table = $tname; break; }
        }

        if ($recurring_table) {
            // 3) fetch recurring entries for this room that are active (if column 'status' exists)
            $q = "SELECT id, room_id, day_of_week, time_start, time_end, purpose, description, tel, status FROM `{$recurring_table}` WHERE room_id = ? AND (status IS NULL OR status = 'active')";
            $rstmt = $conn->prepare($q);
            if ($rstmt) {
                $rstmt->bind_param("s", $room);
                if ($rstmt->execute()) {
                    $rres = $rstmt->get_result();

                    // iterate days between start and end
                    $periodStart = new DateTime($start);
                    $periodEnd = new DateTime($end);
                    // include end date (DatePeriod is exclusive of end)
                    $periodEndPlus = clone $periodEnd;
                    $periodEndPlus->modify('+1 day');
                    $period = new DatePeriod($periodStart, new DateInterval('P1D'), $periodEndPlus);

                    while ($rr = $rres->fetch_assoc()) {
                        // normalize day string (ensure format like "Monday")
                        $recDay = $rr['day_of_week'];
                        foreach ($period as $dt) {
                            if ($dt->format('l') !== $recDay) continue;
                            $dateStr = $dt->format('Y-m-d');
                            $slotTime = substr($rr['time_start'],0,5) . '-' . substr($rr['time_end'],0,5);
                            $key = $dateStr . '|' . $slotTime;
                            if (isset($existingKeys[$key])) continue; // one-time exists -> skip recurring for that date/slot
                            $out[] = [
                                'id' => 0,
                                'ticket' => 'REC-' . $rr['id'],
                                'user_id' => 0,
                                'purpose' => $rr['purpose'],
                                'description' => $rr['description'],
                                'tel' => $rr['tel'],
                                'slot_date' => $dateStr,
                                'time_start' => substr($rr['time_start'],0,5),
                                'time_end' => substr($rr['time_end'],0,5),
                                'status' => 'recurring',
                                'created_at' => null,
                                'recurring_id' => (int)$rr['id'],
                                'recurring' => true
                            ];
                        }
                    }
                } else {
                    log_error("recurring execute failed: " . $rstmt->error);
                }
                $rstmt->close();
            } else {
                log_error("recurring prepare failed: " . $conn->error . " (tried table {$recurring_table})");
            }
        } else {
            // no recurring table found — log for debugging (not fatal)
            log_error("No recurring table found among candidates: " . implode(',', $candidate_tables));
        }

        // 4) sort combined results by slot_date then time_start for predictable output
        usort($out, function($a, $b){
            // null-safe compare
            $da = $a['slot_date'] ?? '';
            $db = $b['slot_date'] ?? '';
            if ($da === $db) {
                return strcmp($a['time_start'] ?? '', $b['time_start'] ?? '');
            }
            return strcmp($da, $db);
        });

        json_ok(['bookings'=>$out]);
    }

    json_err('Unknown endpoint');
}

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if (empty($raw)) json_err('Empty request');

    $data = json_decode($raw, true);
    if (!is_array($data)) json_err('Invalid JSON');

    $action = $data['action'] ?? '';
    if (!$action) json_err('Missing action');

    $conn->begin_transaction();
    try {
        if ($action === 'delete') {
            $booking_id = intval($data['booking_id'] ?? 0);
            if (!$booking_id) throw new Exception('Invalid booking_id');

            $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
            if (!$stmt) throw new Exception('DB prepare failed');
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            $stmt->close();

            admin_log($conn, $admin_id, $booking_id, 'delete', 'Deleted via admin_timetable');
            $conn->commit();
            json_ok(['msg'=>'Deleted']);
        }

        if (in_array($action, ['save','create','update'])) {
            $room_id = $data['room_id'] ?? ($data['room'] ?? '');
            $slots = $data['slots'] ?? [];
            $purpose = trim($data['purpose'] ?? '');
            $description = trim($data['description'] ?? '');
            $tel = trim($data['tel'] ?? '');
            $status = $data['status'] ?? 'booked';
            $overwrite = !empty($data['overwrite']);
            $provided_booking_id = intval($data['booking_id'] ?? 0);

            if (!$room_id) throw new Exception('Missing room_id');
            if (!is_array($slots) || count($slots) === 0) throw new Exception('Missing slots');
            if (!$purpose) throw new Exception('Purpose required');

            $created = 0; $updated = 0; $skipped = 0; $errors = [];

            foreach ($slots as $slot) {
                $slot_date = $slot['date'] ?? ($slot[0] ?? null);
                $slot_range = $slot['slot'] ?? ($slot[1] ?? null);
                if (!$slot_date || !$slot_range) { $errors[] = "Bad slot format"; continue; }

                $parts = explode('-', $slot_range);
                if (count($parts) < 2) { $errors[] = "Bad time range"; continue; }
                $time_start = date('H:i:s', strtotime(trim($parts[0])));
                $time_end = date('H:i:s', strtotime(trim($parts[1])));

                if ($provided_booking_id) {
                    $stmtUpd = $conn->prepare("UPDATE bookings SET purpose=?, description=?, tel=?, status=?, updated_at=NOW() WHERE id=?");
                    $stmtUpd->bind_param("ssssi", $purpose, $description, $tel, $status, $provided_booking_id);
                    if (!$stmtUpd->execute()) { $errors[] = "Update failed"; $stmtUpd->close(); continue; }
                    $stmtUpd->close();
                    admin_log($conn, $admin_id, $provided_booking_id, 'update', 'Admin updated');
                    $updated++;
                    continue;
                }

                $chk = $conn->prepare("SELECT id FROM bookings WHERE room_id=? AND slot_date=? AND time_start=? AND status NOT IN ('cancelled','rejected') LIMIT 1 FOR UPDATE");
                $chk->bind_param("sss", $room_id, $slot_date, $time_start);
                $chk->execute();
                $resChk = $chk->get_result();
                $exists = $resChk->fetch_assoc() ?? null;
                $chk->close();

                if ($exists) {
                    if ($overwrite) {
                        $eid = intval($exists['id']);
                        $ust = $conn->prepare("UPDATE bookings SET purpose=?, description=?, tel=?, status=?, updated_at=NOW() WHERE id=?");
                        $ust->bind_param("ssssi", $purpose, $description, $tel, $status, $eid);
                        if (!$ust->execute()) { $errors[] = "Overwrite failed"; $ust->close(); continue; }
                        $ust->close();
                        admin_log($conn, $admin_id, $eid, 'update', 'Admin overwrote');
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                $ticket = 'ADM-' . strtoupper(substr(md5(uniqid((string)microtime(true), true)),0,10));
                $stmtIns = $conn->prepare("INSERT INTO bookings (ticket, user_id, room_id, purpose, description, tel, slot_date, time_start, time_end, status, created_at, updated_at, active_key, session_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)");
                $active_key = 'ADM' . str_pad(mt_rand(0,99999999), 8, '0', STR_PAD_LEFT);
                $session_id = $ticket;
                $stmtIns->bind_param("sissssssssss", $ticket, $admin_id, $room_id, $purpose, $description, $tel, $slot_date, $time_start, $time_end, $status, $active_key, $session_id);

                if (!$stmtIns->execute()) { $errors[] = "Insert failed"; $stmtIns->close(); continue; }
                $newid = $stmtIns->insert_id;
                $stmtIns->close();
                admin_log($conn, $admin_id, $newid, 'create', "Created ($ticket)");
                $created++;
            }

            $conn->commit();
            json_ok(['created'=>$created,'updated'=>$updated,'skipped'=>$skipped,'errors'=>$errors]);
        }

        throw new Exception('Unknown action');

    } catch (Exception $ex) {
        $conn->rollback();
        log_error("Error: ".$ex->getMessage());
        json_err('Error: '.$ex->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Timetable Management</title>
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
    --warning-light: #fef3c7;
    --danger: #dc2626;
    --danger-light: #fee2e2;
    --info: #0891b2;
    --info-light: #cffafe;
    --purple: #7c3aed;
    --purple-light: #ede9fe;
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
    justify-content: flex-start;
    height: 80px;
  }
  
  .nav-bar img {
    height: 50px; 
    width: auto;
  }

  /* --- LAYOUT CONTAINER (Margin to clear fixed nav) --- */
  .layout-container {
    width: 100%;
    max-width: 2300px;   /* allow up to 2000px */
    padding: 24px;
    gap: 24px;
    margin: 100px auto 0; /* keep centered and below fixed navbar */
    display: flex;
    align-items: flex-start;
  }

  
  /* Sidebar - Modified to be Sticky */
  .sidebar {
    width: 260px;
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: var(--shadow-lg);
    z-index: 100;
    flex-shrink: 0;
    
    /* Sticky Magic (Adjusted for tighter layout) */
    position: sticky;
    top: 100px; /* Sticks slightly below the main layout padding (80px nav + 24px padding = 104px) */

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
  
  /* Margin for secondary titles */
  .sidebar-title.mt-4 { margin-top: 24px; }
  
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
  
  /* Main Content Area */
  .main-wrapper {
    flex: 1; /* Takes remaining width */
    min-width: 0; /* Prevents overflow issues */
  }

  /* --- END SIDEBAR / LAYOUT STYLES --- */
  
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
  
  /* Control Panel */
  .control-panel {
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 24px;
    margin-bottom: 24px;
  }
  
  .control-section {
    margin-bottom: 20px;
  }
  
  .control-section:last-child {
    margin-bottom: 0;
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
  
  /* Room Selector */
  .room-selector {
    width: 100%;
    padding: 12px 16px;
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
  
  .room-selector option {
    padding: 8px;
  }
  
  /* Week Navigation */
  .week-nav-container {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
  }
  
  .week-display-box {
    flex: 1;
    min-width: 250px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 14px 20px;
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
    font-size: 15px;
    box-shadow: var(--shadow);
  }
  
  .date-picker-group {
    display: flex;
    gap: 8px;
    align-items: center;
  }
  
  .date-picker {
    padding: 10px 14px;
    border: 2px solid var(--gray-300);
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
  }
  
  .date-picker:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
  }
  
  /* Action Bar */
  .action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    margin-bottom: 20px;
  }
  
  .action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }
  
  .action-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: white;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: var(--gray-700);
  }
  
  .action-toggle input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
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
    gap: 8px;
    text-decoration: none;
    box-shadow: var(--shadow-sm);
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
  
  .btn-outline-custom {
    background: white;
    color: var(--gray-700);
    border: 2px solid var(--gray-300);
  }
  
  .btn-outline-custom:hover {
    background: var(--gray-50);
    border-color: var(--gray-400);
  }
  
  .btn-secondary-custom {
    background: var(--gray-200);
    color: var(--gray-700);
  }
  
  .btn-secondary-custom:hover {
    background: var(--gray-300);
  }
  
  .btn-icon {
    width: 40px;
    height: 40px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  
  /* Grid Table */
  .grid-container {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid var(--gray-200);
    box-shadow: var(--shadow);
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
  
  table.grid td.time-col { 
    background: linear-gradient(180deg, #f0f9ff 0%, #e0f2fe 100%);
    font-weight: 700;
    font-size: 13px;
    min-width: 120px;
    color: var(--primary-dark);
    position: sticky;
    left: 0;
    z-index: 5;
  }

  /* Sticky Top-Left Corner Cell */
  table.grid thead tr th:first-child {
    position: sticky;
    left: 0;
    top: 0;
    z-index: 15; /* Higher than time-col and standard headers */
    background: #f3f4f6;
  }
  
  /* Slot Cells */
  td.slot { 
    cursor: pointer;
    user-select: none;
    position: relative;
    height: 75px;
    min-width: 140px;
    transition: all 0.2s;
  }
  
  td.slot:hover:not(.past) { 
    transform: scale(1.02);
    box-shadow: inset 0 0 0 2px var(--primary);
    z-index: 2;
  }
  
  td.available { 
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
  }
  
  td.selected { 
    background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%);
    border: 2px solid var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
  }
  
  td.booked { 
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: var(--danger);
    font-weight: 600;
  }
  
  td.pending { 
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    font-weight: 600;
  }
  
  td.maintenance { 
    background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
    color: #9a3412;
    font-weight: 600;
  }
  
  td.recurring { 
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    color: var(--purple);
    font-weight: 700;
    border-left: 4px solid var(--purple);
  }
  
  td.past { 
    background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
    color: var(--gray-600);
    cursor: not-allowed;
    opacity: 0.6;
  }
  
  /* Cell Content */
  .day-head { 
    font-weight: 700;
    font-size: 14px;
    color: var(--gray-800);
  }
  
  .day-name { 
    font-size: 12px;
    color: var(--gray-600);
    margin-top: 2px;
  }
  
  .cell-content { 
    font-size: 11px;
    word-wrap: break-word;
    line-height: 1.3;
  }
  
  .cell-title {
    font-weight: 700;
    font-size: 12px;
    margin-bottom: 4px;
  }
  
  .cell-meta {
    font-size: 10px;
    opacity: 0.8;
  }
  
  /* Delete Button */
  .delete-btn { 
    position: absolute;
    right: 4px;
    top: 4px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--danger);
    color: white;
    border: 2px solid white;
    font-size: 12px;
    font-weight: 700;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: var(--shadow);
    transition: all 0.2s;
  }
  
  .delete-btn:hover {
    background: #b91c1c;
    transform: scale(1.1);
  }
  
  td.slot:hover .delete-btn { 
    display: flex;
  }
  
  /* Legend */
  .legend {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    padding: 16px 20px;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    margin-bottom: 20px;
  }
  
  .legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 500;
    color: var(--gray-700);
  }
  
  .legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid var(--gray-300);
  }
  
  .legend-available { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); }
  .legend-selected { background: linear-gradient(135deg, #dbeafe 0%, #93c5fd 100%); }
  .legend-pending { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); }
  .legend-booked { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); }
  .legend-maintenance { background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%); }
  .legend-recurring { background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); }
  
  /* Modal Improvements */
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
  
  /* Responsive */
  @media (max-width: 1200px) {
    .sidebar {
      display: none; /* Hide sidebar on smaller screens */
    }
    .layout-container {
      display: block; /* Make the layout container linear */
    }
    .main-wrapper {
      padding: 0 24px; /* Add horizontal padding back to main content */
    }
  }

  @media (max-width: 768px) {
    .header-card { flex-direction: column; gap: 16px; }
    .header-actions { justify-content: center; width: 100%; }
    .main-container { padding: 20px; }
    .control-panel { padding: 16px; }
    .week-nav-container { flex-direction: column; }
    .action-bar { flex-direction: column; align-items: stretch; }
    .action-buttons { justify-content: stretch; }
    .btn-custom { width: 100%; justify-content: center; }
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
      <li><a href="admin_timetable.php" class="active">Regular Timetable</a></li>
      <li><a href="admin_recurring.php">Recurring Timetable</a></li>
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

    <!-- Header (Modified from container-fluid content) -->
    <div class="header-card">
      <div class="header-title">
        <div>
          <h1>Timetable Management</h1>
          <div class="header-subtitle">Managing Weekly Timetable</div>
        </div>
        <span class="header-badge">Admin</span>
      </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
      
      <!-- Control Panel -->
      <div class="control-panel">
        <div class="row g-4">
          
          <!-- Room Selection -->
          <div class="col-md-4">
            <div class="control-section">
              <label class="control-label">🏢 Select Room</label>
              <select id="roomSelect" class="room-selector" size="5">
                  <!-- PHP code for rooms -->
                  <?php
                      $rs = $conn->query("SELECT room_id, name, capacity FROM rooms ORDER BY name");
                      while($r = $rs->fetch_assoc()) {
                          echo '<option value="'.htmlspecialchars($r['room_id']).'">'.htmlspecialchars($r['name']).' ('.htmlspecialchars($r['room_id']).') - '.$r['capacity'].' pax</option>';
                      }
                  ?> 
                  <!-- End PHP code -->
              </select>
            </div>
          </div>
          
          <!-- Week Navigation -->
          <div class="col-md-8">
            <div class="control-section">
              <label class="control-label">📆 Week Navigation</label>
              <div class="week-nav-container">
                <button id="prevWeekBtn" class="btn-custom btn-outline-custom">
                  ← Previous
                </button>
                <div id="weekDisplay" class="week-display-box">
                  Select a room to start
                </div>
                <button id="nextWeekBtn" class="btn-custom btn-outline-custom">
                  Next →
                </button>
              </div>
            </div>
            
            <div class="control-section" style="margin-top: 16px;">
              <label class="control-label">🗓️ Quick Date Jump</label>
              <div class="date-picker-group">
                <input type="date" id="weekStart" class="date-picker" />
                <button id="renderBtn" class="btn-custom btn-secondary-custom">
                  Go to Week
                </button>
              </div>
            </div>
          </div>
          
        </div>
      </div>

      <!-- Legend -->
      <div class="legend">
        <div class="legend-item">
          <span class="legend-color legend-available"></span>
          Available
        </div>
        <div class="legend-item">
          <span class="legend-color legend-selected"></span>
          Selected
        </div>
        <div class="legend-item">
          <span class="legend-color legend-pending"></span>
          Pending
        </div>
        <div class="legend-item">
          <span class="legend-color legend-booked"></span>
          Booked
        </div>
        <div class="legend-item">
          <span class="legend-color legend-maintenance"></span>
          Maintenance
        </div>
        <div class="legend-item">
          <span class="legend-color legend-recurring"></span>
          Recurring
        </div>
      </div>
      
      <!-- Action Bar -->
      <div class="action-bar">
        <div class="action-buttons">
          <button id="clearSelectionBtn" class="btn-custom btn-outline-custom">
            ✕ Clear Selection
          </button>
          <button id="openCreateModalBtn" class="btn-custom btn-success-custom">
            ✓ Save Selected Slots
          </button>
        </div>
        <div class="action-toggle">
          <input id="overwriteChk" type="checkbox">
          <label for="overwriteChk" style="margin: 0; cursor: pointer;">Overwrite existing bookings</label>
        </div>
      </div>

      <!-- Timetable Grid -->
      <div id="gridArea" class="grid-container"></div>

    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create / Update Booking</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Purpose *</label>
          <input id="modalPurpose" class="form-control" placeholder="e.g., Weekly Meeting">
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea id="modalDesc" class="form-control" rows="3" placeholder="Additional details (optional)"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Contact Tel</label>
          <input id="modalTel" class="form-control" placeholder="e.g., +60123456789">
        </div>
        <div class="mb-3">
          <label class="form-label">Status</label>
          <select id="modalStatus" class="form-select">
            <option value="booked">Booked</option>
            <option value="pending">Pending</option>
            <option value="maintenance">Maintenance</option>
          </select>
        </div>
        <div style="padding: 12px; background: var(--gray-50); border-radius: 8px; font-size: 13px; color: var(--gray-700);">
          <strong>Selected slots:</strong> <span id="selectedCount">0</span>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-custom btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
        <button id="modalSaveBtn" class="btn-custom btn-success-custom">Save Booking</button>
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

let selectedCells = new Map();
let bookingsIndex = {};
let currentWeekStart = null;

const roomSelect = document.getElementById('roomSelect');
const weekStart = document.getElementById('weekStart');
const weekDisplay = document.getElementById('weekDisplay');
const renderBtn = document.getElementById('renderBtn');
const prevWeekBtn = document.getElementById('prevWeekBtn');
const nextWeekBtn = document.getElementById('nextWeekBtn');
const gridArea = document.getElementById('gridArea');
const openCreateModalBtn = document.getElementById('openCreateModalBtn');
const clearSelectionBtn = document.getElementById('clearSelectionBtn');
const overwriteChk = document.getElementById('overwriteChk');

const createModal = new bootstrap.Modal(document.getElementById('createModal'));
const modalPurpose = document.getElementById('modalPurpose');
const modalDesc = document.getElementById('modalDesc');
const modalTel = document.getElementById('modalTel');
const modalStatus = document.getElementById('modalStatus');
const selectedCount = document.getElementById('selectedCount');
const modalSaveBtn = document.getElementById('modalSaveBtn');

function getMonday(dateString) {
    const d = new Date(dateString + 'T12:00:00');
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    d.setDate(diff);
    return d.toISOString().slice(0,10);
}

function isoAddDays(iso, n) {
    const d = new Date(iso + 'T12:00:00');
    d.setDate(d.getDate() + n);
    return d.toISOString().slice(0,10);
}

function getDayName(iso) {
    const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    const d = new Date(iso + 'T12:00:00');
    return days[d.getDay()];
}

function formatDateRange(monday) {
    const sunday = isoAddDays(monday, 6);
    return `Week: ${monday} to ${sunday}`;
}

function updateWeekDisplay() {
    if (currentWeekStart) {
        weekDisplay.textContent = formatDateRange(currentWeekStart);
    }
}

weekStart.addEventListener('change', () => {
  const selected = weekStart.value;
  if (!selected) return;
  currentWeekStart = getMonday(selected);
  weekStart.value = currentWeekStart;
  updateWeekDisplay();
  renderGrid();
});

roomSelect.addEventListener('change', () => {
  if (roomSelect.value && currentWeekStart) {
    renderGrid();
  }
});

function renderGrid() {
    const room = roomSelect.value;
    if (!room) {
        alert('Please select a room first.');
        return;
    }

    if (!currentWeekStart) {
        const today = new Date();
        currentWeekStart = getMonday(today.toISOString().slice(0,10));
    }
    
    const monday = currentWeekStart;
    weekStart.value = monday;
    updateWeekDisplay();

    gridArea.innerHTML = '';
    selectedCells.clear();
    bookingsIndex = {};

    const table = document.createElement('table');
    table.className = 'grid';

    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    const cornerTh = document.createElement('th');
    cornerTh.textContent = 'Date / Time';
    cornerTh.style.minWidth = '120px';
    headRow.appendChild(cornerTh);
    TIME_SLOTS.forEach(ts => {
        const th = document.createElement('th');
        th.textContent = ts;
        th.style.minWidth = '100px';
        headRow.appendChild(th);
    });
    thead.appendChild(headRow);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    for (let i = 0; i < 7; i++) {
        const iso = isoAddDays(monday, i);
        const tr = document.createElement('tr');

        const tdDate = document.createElement('td');
        tdDate.className = 'time-col';
        tdDate.innerHTML = `<div class="day-head">${iso}</div><div class="day-name">${getDayName(iso)}</div>`;
        tr.appendChild(tdDate);

        TIME_SLOTS.forEach(ts => {
            const td = document.createElement('td');
            td.className = 'slot available';
            td.dataset.date = iso;
            td.dataset.slot = ts;

            const [start] = ts.split('-');
            const slotDateTime = new Date(iso + 'T' + start + ':00');
            if (slotDateTime < new Date()) {
                td.classList.add('past');
            }

            td.addEventListener('click', () => {
                if (td.classList.contains('past')) return;
                
                const key = iso + '|' + ts;
                if (td.classList.contains('selected')) {
                    td.classList.remove('selected');
                    selectedCells.delete(key);
                } else if (td.classList.contains('available')) {
                    td.classList.add('selected');
                    selectedCells.set(key, {date: iso, slot: ts, td});
                } else {
                    openEditForCell(iso, ts, td);
                }
                updateSelectedCount();
            });

            tr.appendChild(td);
        });

        tbody.appendChild(tr);
    }
    table.appendChild(tbody);
    gridArea.appendChild(table);

    const endDate = isoAddDays(monday, 6);

    fetch(`admin_timetable.php?endpoint=bookings&room=${encodeURIComponent(room)}&start=${monday}&end=${endDate}`, { cache: 'no-store' })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(json => {
        if (!json.success) {
        console.error('Load bookings error', json);
        alert('Failed to load bookings: ' + (json.msg || 'unknown'));
        return;
        }

        json.bookings.forEach(b => {
        if (!b.slot_date || !b.time_start || !b.time_end) return;
        const slotTime = b.time_start.slice(0,5) + '-' + b.time_end.slice(0,5);
        const key = b.slot_date + '|' + slotTime;
        bookingsIndex[key] = b;

        const td = document.querySelector(`td.slot[data-date="${b.slot_date}"][data-slot="${slotTime}"]`);
        if (!td) return;

        td.classList.remove('available','selected');

        // recurring entries (created from recurring table) are read-only in this view
        if (b.recurring) {
            td.classList.add('recurring');
            td.innerHTML = `<div class="cell-content"><strong>${escapeHtml(b.purpose || 'Recurring')}</strong><br>${escapeHtml(b.tel || '')}</div>`;
            td.dataset.recurringId = b.recurring_id || '';
            td.title = 'Recurring slot — edit via Recurring manager';
            return;
        }

        // normal one-time booking
        td.classList.add(b.status || 'booked');
        td.innerHTML = `<div class="cell-content"><strong>${escapeHtml(b.purpose || 'Booked')}</strong><br>${escapeHtml(b.tel || '')}</div>`;

        // admin edit/delete for one-time bookings
        td.dataset.bookingId = b.id;
        if (!td.querySelector('.delete-btn')) {
            const del = document.createElement('button');
            del.className = 'btn btn-danger btn-sm delete-btn';
            del.innerHTML = '✕';
            del.addEventListener('click', (ev) => {
            ev.stopPropagation();
            if (!confirm('Delete this booking?')) return;
            deleteBooking(b.id);
            });
            td.appendChild(del);
        }
        td.addEventListener('dblclick', () => openEditForCell(b.slot_date, slotTime, td));
        });
    })
    .catch(err => {
        console.error('Failed loading bookings', err);
        alert('Could not load bookings: ' + (err.message || 'network error'));
    });


}

function openEditForCell(date, slot, td) {
    const key = date+'|'+slot;
    const b = bookingsIndex[key];
    if (!b) return;
    modalPurpose.value = b.purpose || '';
    modalDesc.value = b.description || '';
    modalTel.value = b.tel || '';
    modalStatus.value = b.status || 'booked';
    selectedCount.textContent = '1 (editing)';
    selectedCells.clear();
    selectedCells.set(key, {date, slot, td, bookingId: b.id});
    createModal.show();
}

function updateSelectedCount() {
    selectedCount.textContent = selectedCells.size;
}

function escapeHtml(s){ 
    if(!s) return ''; 
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); 
}

function clearSelection() {
    selectedCells.forEach(o=> o.td.classList.remove('selected'));
    selectedCells.clear();
    updateSelectedCount();
}

function deleteBooking(id) {
    fetch('admin_timetable.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'delete', booking_id: id})
    })
    .then(r=>r.json()).then(js=>{
        if (js.success) {
            alert('Deleted successfully');
            renderGrid();
        } else alert('Delete failed: '+(js.msg||''));
    })
    .catch(err=> { console.error(err); alert('Delete failed'); });
}

modalSaveBtn.addEventListener('click', ()=>{
    const room = roomSelect.value;
    if (!room) return alert('Choose a room');
    if (selectedCells.size === 0) return alert('Select at least one slot');
    const purpose = modalPurpose.value.trim();
    if (!purpose) return alert('Purpose required');

    const slots = [];
    selectedCells.forEach(o => {
        slots.push({ date: o.date, slot: o.slot });
    });

    const payload = {
        action: 'save',
        room_id: room,
        purpose: purpose,
        description: modalDesc.value.trim(),
        tel: modalTel.value.trim(),
        status: modalStatus.value,
        overwrite: overwriteChk.checked ? 1 : 0,
        slots: slots
    };

    modalSaveBtn.disabled = true;
    fetch('admin_timetable.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r=>r.json())
    .then(js=>{
        modalSaveBtn.disabled = false;
        if (js.success) {
            alert(`Success! Created: ${js.created||0}, Updated: ${js.updated||0}, Skipped: ${js.skipped||0}`);
            createModal.hide();
            renderGrid();
            clearSelection();
        } else {
            alert('Save failed: ' + (js.msg || ''));
        }
    })
    .catch(err=>{ 
        modalSaveBtn.disabled = false; 
        console.error(err); 
        alert('Network error'); 
    });
});

renderBtn.addEventListener('click', renderGrid);
clearSelectionBtn.addEventListener('click', clearSelection);

prevWeekBtn.addEventListener('click', () => {
    if (!currentWeekStart) {
        currentWeekStart = getMonday(new Date().toISOString().slice(0,10));
    }
    currentWeekStart = isoAddDays(currentWeekStart, -7);
    weekStart.value = currentWeekStart;
    updateWeekDisplay();
    renderGrid();
});

nextWeekBtn.addEventListener('click', () => {
    if (!currentWeekStart) {
        currentWeekStart = getMonday(new Date().toISOString().slice(0,10));
    }
    currentWeekStart = isoAddDays(currentWeekStart, 7);
    weekStart.value = currentWeekStart;
    updateWeekDisplay();
    renderGrid();
});

openCreateModalBtn.addEventListener('click', ()=>{
    if (selectedCells.size === 0) return alert('Select slots first');
    modalPurpose.value = '';
    modalDesc.value = '';
    modalTel.value = '';
    modalStatus.value = 'booked';
    createModal.show();
});

// Initialize
(function init(){
    const today = new Date();
    const monday = getMonday(today.toISOString().slice(0,10));
    currentWeekStart = monday;
    weekStart.value = monday;
    updateWeekDisplay();
})();
</script>
</body>
</html>