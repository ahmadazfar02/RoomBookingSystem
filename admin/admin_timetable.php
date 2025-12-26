<?php
// admin_timetable.php - REDESIGNED & FIXED VERSION
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/mail_helper.php';

// --- Fetch Technicians for Dropdown ---
$technicians = [];
$techQuery = $conn->query("SELECT id, Fullname, Email FROM users WHERE User_Type = 'Technician' ORDER BY Fullname ASC");
if ($techQuery) {
    while ($tech = $techQuery->fetch_assoc()) {
        $technicians[] = $tech;
    }
}


$admin_id = $_SESSION['User_ID'] ?? $_SESSION['id'] ?? null;
$user_type = $_SESSION['User_Type'] ?? null;
$is_ajax = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) || (isset($_GET['endpoint'])) || ($_SERVER['REQUEST_METHOD'] === 'POST' && (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false));

$admin_name = $_SESSION['Fullname'] ?? 'Admin'; 
$admin_email = $_SESSION['Email'] ?? ($_SESSION['User_Type'] ?? 'Admin');
$username = $_SESSION['username'] ?? 'superadmin';

// --- FIXED ACCESS CONTROL ---
$uType = trim($user_type ?? '');

// 1. DEFINE ROLES (Required for Sidebar Logic)
$isTechAdmin  = (strcasecmp($uType, 'Technical Admin') === 0);
$isSuperAdmin = (strcasecmp($uType, 'SuperAdmin') === 0 || strtolower($username) === 'superadmin');

// 2. CHECK ACCESS
$allowed = (
    strcasecmp($uType, 'Admin') === 0 || 
    $isTechAdmin || 
    $isSuperAdmin
);

if (!$admin_id || !$allowed) {
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

    if ($endpoint === 'bookings') {
        $room = $_GET['room'] ?? '';
        $start = $_GET['start'] ?? '';
        $end = $_GET['end'] ?? '';
        if (!$room || !$start || !$end) json_err('Missing parameters');

        $sql = "SELECT id, ticket, user_id, purpose, description, tel, technician, slot_date, time_start, time_end, status, tech_status, created_at
                FROM bookings
                WHERE room_id = ? AND slot_date BETWEEN ? AND ? AND status NOT IN ('deleted')
                ORDER BY slot_date, time_start";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $room, $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        $existingKeys = [];

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
                'technician' => $r['technician'],
                'slot_date' => $r['slot_date'],
                'time_start' => substr($r['time_start'],0,5),
                'time_end' => substr($r['time_end'],0,5),
                'status' => $r['status'],
                'tech_status' => $r['tech_status'],
                'created_at' => $r['created_at'],
            ];
        }
        $stmt->close();

        // Recurring bookings logic
        $candidate_tables = ['recurring_bookings','admin_recurring','admin_recurring_bookings'];
        $recurring_table = null;
        foreach ($candidate_tables as $tname) {
            $check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tname) . "'");
            if ($check && $check->num_rows > 0) { $recurring_table = $tname; break; }
        }

        if ($recurring_table) {
            $q = "SELECT id, room_id, day_of_week, time_start, time_end, purpose, description, tel, status FROM `{$recurring_table}` WHERE room_id = ? AND (status IS NULL OR status = 'active')";
            $rstmt = $conn->prepare($q);
            if ($rstmt) {
                $rstmt->bind_param("s", $room);
                if ($rstmt->execute()) {
                    $rres = $rstmt->get_result();
                    $periodStart = new DateTime($start);
                    $periodEnd = new DateTime($end);
                    $periodEndPlus = clone $periodEnd;
                    $periodEndPlus->modify('+1 day');
                    $period = new DatePeriod($periodStart, new DateInterval('P1D'), $periodEndPlus);

                    while ($rr = $rres->fetch_assoc()) {
                        $recDay = $rr['day_of_week'];
                        foreach ($period as $dt) {
                            if ($dt->format('l') !== $recDay) continue;
                            $dateStr = $dt->format('Y-m-d');
                            $slotTime = substr($rr['time_start'],0,5) . '-' . substr($rr['time_end'],0,5);
                            $key = $dateStr . '|' . $slotTime;
                            if (isset($existingKeys[$key])) continue;
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
                }
                $rstmt->close();
            }
        }

        usort($out, function($a, $b){
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

            // 1. Get Session ID and Linked Problem info
            $chk = $conn->query("SELECT session_id, linked_problem_id, tech_status FROM bookings WHERE id = $booking_id");
            $row = $chk->fetch_assoc();

            if (!$row) throw new Exception('Booking not found');

            $session_id = $row['session_id']; 
            $pid = !empty($row['linked_problem_id']) ? intval($row['linked_problem_id']) : 0;

            // 2. Sync with Room Problems
            if ($pid > 0) {
                if ($row['tech_status'] === 'Work Done') {
                    // Technician finished -> Mark problem Resolved
                    $conn->query("UPDATE room_problems SET status = 'Resolved', resolved_at = NOW(), admin_notice = 0 WHERE id = $pid");
                } else {
                    // Manual delete/cancel -> Reset problem to Pending
                    $conn->query("UPDATE room_problems SET status = 'Pending', admin_notice = 0 WHERE id = $pid");
                }
            }

            // 3. BATCH DELETE
            if (!empty($session_id)) {
                $stmt = $conn->prepare("DELETE FROM bookings WHERE session_id = ?");
                $stmt->bind_param("s", $session_id);
            } else {
                $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
                $stmt->bind_param("i", $booking_id);
            }

            $stmt->execute();
            $stmt->close();

            admin_log($conn, $admin_id, $booking_id, 'delete', "Deleted session via grid");
            $conn->commit();
            json_ok(['msg'=>'Deleted']);
        }
        
        if (in_array($action, ['save','create','update'])) {
            $room_id = $data['room_id'] ?? ($data['room'] ?? '');
            $slots = $data['slots'] ?? [];
            $purpose = trim($data['purpose'] ?? '');
            $description = trim($data['description'] ?? '');
            $tel = trim($data['tel'] ?? '');
            $technician = trim($data['technician'] ?? '');
            $status = $data['status'] ?? 'booked';
            $overwrite = !empty($data['overwrite']);
            $provided_booking_id = intval($data['booking_id'] ?? 0);
            $linked_problem_id = intval($data['problem_id'] ?? 0);

            if (!$room_id) throw new Exception('Missing room_id');
            if (!is_array($slots) || count($slots) === 0) throw new Exception('Missing slots');
            if (!$purpose) throw new Exception('Purpose required');

            $created = 0; $updated = 0; $skipped = 0; $errors = [];
            $slots_for_email = [];

            foreach ($slots as $slot) {
                $slot_date = $slot['date'] ?? ($slot[0] ?? null);
                $slot_range = $slot['slot'] ?? ($slot[1] ?? null);
                if (!$slot_date || !$slot_range) { $errors[] = "Bad slot"; continue; }

                $parts = explode('-', $slot_range);
                if (count($parts) < 2) { $errors[] = "Bad time"; continue; }
                $time_start = date('H:i:s', strtotime(trim($parts[0])));
                $time_end = date('H:i:s', strtotime(trim($parts[1])));

                $success = false;
                $current_booking_id = null;

                // Generate token ONLY if maintenance + technician assigned
                $tech_token = null;
                if ($status === 'maintenance' && !empty($technician)) {
                    $tech_token = bin2hex(random_bytes(32));
                }

                // UPDATE existing
                if ($provided_booking_id) {
                    if ($tech_token) {
                        $stmtUpd = $conn->prepare("UPDATE bookings SET purpose=?, description=?, tel=?, technician=?, status=?, tech_token=?, tech_status='Pending', linked_problem_id=?, updated_at=NOW() WHERE id=?");
                        $stmtUpd->bind_param("ssssssii", $purpose, $description, $tel, $technician, $status, $tech_token, $linked_problem_id, $provided_booking_id);
                    } else {
                        $stmtUpd = $conn->prepare("UPDATE bookings SET purpose=?, description=?, tel=?, technician=?, status=?, linked_problem_id=?, updated_at=NOW() WHERE id=?");
                        $stmtUpd->bind_param("sssssii", $purpose, $description, $tel, $technician, $status, $linked_problem_id, $provided_booking_id);
                    }
                    if ($stmtUpd->execute()) {
                        admin_log($conn, $admin_id, $provided_booking_id, 'update', 'Updated maintenance');
                        $updated++;
                        $success = true;
                        $current_booking_id = $provided_booking_id;
                    }
                    $stmtUpd->close();
                } 
                // INSERT new
                else {
                    $chk = $conn->prepare("SELECT id FROM bookings WHERE room_id=? AND slot_date=? AND time_start=? AND status NOT IN ('cancelled','rejected') LIMIT 1");
                    $chk->bind_param("sss", $room_id, $slot_date, $time_start);
                    $chk->execute();
                    $resChk = $chk->get_result();
                    $exists = $resChk->fetch_assoc();
                    $chk->close();

                    if ($exists && $overwrite) {
                        $eid = intval($exists['id']);
                        if ($tech_token) {
                            $ust = $conn->prepare("UPDATE bookings SET purpose=?, description=?, tel=?, technician=?, status=?, tech_token=?, tech_status='Pending', linked_problem_id=?, updated_at=NOW() WHERE id=?");
                            $ust->bind_param("ssssssii", $purpose, $description, $tel, $technician, $status, $tech_token, $linked_problem_id, $eid);
                        } else {
                            $ust = $conn->prepare("UPDATE bookings SET purpose=?, description=?, tel=?, technician=?, status=?, linked_problem_id=?, updated_at=NOW() WHERE id=?");
                            $ust->bind_param("sssssii", $purpose, $description, $tel, $technician, $status, $linked_problem_id, $eid);
                        }
                        if ($ust->execute()) {
                            $updated++;
                            $success = true;
                            $current_booking_id = $eid;
                        }
                        $ust->close();
                    } elseif (!$exists) {
                        $ticket = 'ADM-' . strtoupper(substr(md5(uniqid((string)microtime(true), true)),0,10));
                        $active_key = 'ADM' . str_pad(mt_rand(0,99999999), 8, '0', STR_PAD_LEFT);
                        $session_id = $ticket;

                        if ($tech_token) {
                            $stmtIns = $conn->prepare("INSERT INTO bookings (ticket, user_id, room_id, purpose, description, tel, technician, slot_date, time_start, time_end, status, tech_token, tech_status, linked_problem_id, created_at, updated_at, active_key, session_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, NOW(), NOW(), ?, ?)");
                            $stmtIns->bind_param("sisssssssssssss", $ticket, $admin_id, $room_id, $purpose, $description, $tel, $technician, $slot_date, $time_start, $time_end, $status, $tech_token, $linked_problem_id, $active_key, $session_id);
                        } else {
                            $stmtIns = $conn->prepare("INSERT INTO bookings (ticket, user_id, room_id, purpose, description, tel, technician, slot_date, time_start, time_end, status, linked_problem_id, created_at, updated_at, active_key, session_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)");
                            $stmtIns->bind_param("sisssssssssiss", $ticket, $admin_id, $room_id, $purpose, $description, $tel, $technician, $slot_date, $time_start, $time_end, $status, $linked_problem_id, $active_key, $session_id);
                        }

                        if ($stmtIns->execute()) {
                            $newid = $stmtIns->insert_id;
                            admin_log($conn, $admin_id, $newid, 'create', "Created ($ticket)");
                            $created++;
                            $success = true;
                            $current_booking_id = $newid;
                        }
                        $stmtIns->close();
                    } else {
                        $skipped++;
                    }
                }

                if ($success && $status === 'maintenance' && !empty($technician) && $tech_token) {
                    $slots_for_email[] = [
                        'date' => $slot_date,
                        'time' => $parts[0] . ' - ' . $parts[1],
                        'token' => $tech_token,
                        'booking_id' => $current_booking_id
                    ];
                }
            }

            // EMAIL TECHNICIAN
            if (!empty($slots_for_email)) {
                $stmtTech = $conn->prepare("SELECT Email, Fullname FROM users WHERE Fullname = ? AND User_Type = 'Technician' LIMIT 1");
                $stmtTech->bind_param("s", $technician);
                $stmtTech->execute();
                $resTech = $stmtTech->get_result();
                
                if ($techRow = $resTech->fetch_assoc()) {
                    $toEmail = $techRow['Email'];
                    $toName = $techRow['Fullname'];
                    
                    $first_slot = $slots_for_email[0];
                    $token = $first_slot['token'];
                    
                    $priority = 'Normal'; $priority_color = '#3b82f6';
                    if ($linked_problem_id > 0) {
                        $p_query = $conn->query("SELECT priority FROM room_problems WHERE id = $linked_problem_id");
                        if ($p_row = $p_query->fetch_assoc()) $priority = $p_row['priority'];
                    }

                    switch ($priority) {
                        case 'Critical': $priority_color = '#dc2626'; break;
                        case 'High':     $priority_color = '#ea580c'; break;
                        case 'Low':      $priority_color = '#6b7280'; break;
                        default:         $priority_color = '#2563eb'; break;
                    }

                    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    $script_dir = dirname($_SERVER['PHP_SELF']);
                    $completion_link = $protocol . "://" . $host . $script_dir . "/technician_task.php?token=" . $token;
                    
                    $subject = "[" . strtoupper($priority) . "] Maintenance: " . $room_id;
                    
                    $message = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;'>";
                    $message .= "<div style='background: {$priority_color}; padding: 20px; text-align: center;'>";
                    $message .= "<h2 style='color: white; margin: 0;'>Maintenance Assignment</h2>";
                    $message .= "<div style='color: white; font-weight: bold; margin-top: 5px; text-transform: uppercase; font-size: 14px; letter-spacing: 1px;'>Priority: {$priority}</div>";
                    $message .= "</div>";
                    $message .= "<div style='padding: 24px;'>";
                    $message .= "<p>Hello <strong>" . htmlspecialchars($toName) . "</strong>,</p>";
                    $message .= "<p>You have been assigned a new task. Please review the details below:</p>";
                    $message .= "<div style='background: #f9fafb; padding: 16px; border-radius: 8px; margin: 20px 0; border-left: 4px solid {$priority_color};'>";
                    $message .= "<p style='margin: 5px 0;'><strong>Room:</strong> " . htmlspecialchars($room_id) . "</p>";
                    $message .= "<p style='margin: 5px 0;'><strong>Task:</strong> " . htmlspecialchars($purpose) . "</p>";
                    if ($description) $message .= "<p style='margin: 5px 0;'><strong>Notes:</strong> " . nl2br(htmlspecialchars($description)) . "</p>";
                    $message .= "</div>";
                    $message .= "<table style='width: 100%; border-collapse: collapse; margin-bottom: 24px;'>";
                    $message .= "<tr style='background: #f3f4f6;'><th style='padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb;'>Date</th><th style='padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb;'>Time Slot</th></tr>";
                    foreach ($slots_for_email as $s) {
                        $message .= "<tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>" . $s['date'] . "</td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>" . $s['time'] . "</td></tr>";
                    }
                    $message .= "</table>";
                    $message .= "<div style='text-align: center; margin-top: 30px;'>";
                    $message .= "<a href='" . $completion_link . "' style='background-color: #059669; color: white; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;'>Mark as Completed</a>";
                    $message .= "<p style='margin-top: 15px; font-size: 12px; color: #6b7280;'>Click this button only when the work is finished.</p>";
                    $message .= "</div></div></div>";

                    $email_sent = send_mail($toEmail, $toName, $subject, $message);
                    if ($email_sent) insert_email_log($conn, $first_slot['booking_id'], $toEmail, 'technician', $subject, null, null, 'sent');
                    else insert_email_log($conn, $first_slot['booking_id'], $toEmail, 'technician', $subject, null, null, 'failed', 'Mail send failed');
                }
                $stmtTech->close();
            }

            if ($linked_problem_id > 0) {
                $conn->query("UPDATE room_problems SET status = 'In Progress' WHERE id = {$linked_problem_id}");
            }

            $conn->commit();
            json_ok(['created'=>$created, 'updated'=>$updated, 'skipped'=>$skipped, 'errors'=>$errors]);
        }
        throw new Exception('Unknown action');

    } catch (Exception $ex) {
        $conn->rollback();
        log_error("Error: " . $ex->getMessage());
        json_err('Error: ' . $ex->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Regular Timetable - UTM Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root { 
    --utm-maroon: #800000;
    --utm-maroon-light: #a31313;
    --utm-maroon-dark: #600000;
    --bg-light: #f9fafb;
    --card-bg: #ffffff;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --border: #e2e8f0;
    
    --sidebar-width: 260px;
    --sidebar-bg: #800000;
    --sidebar-text: #e2e8f0;
    --sidebar-hover: #991b1b;
    --sidebar-active: #ffffff;
    --sidebar-active-text: #800000;

    --nav-height: 70px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body { 
    font-family: 'Inter', sans-serif;
    background: var(--bg-light);
    min-height: 100vh;
    color: var(--text-primary);
}

/* NAVBAR */
.nav-bar {
  position: fixed; top: 0; left: 0; right: 0;
  height: var(--nav-height);
  background: white;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 24px;
  box-shadow: var(--shadow-sm);
  z-index: 1000;
  border-bottom: 1px solid var(--border);
}

.nav-left { display: flex; align-items: center; gap: 16px; }
.nav-logo { height: 50px; }
.nav-title h1 { font-size: 16px; font-weight: 700; color: var(--utm-maroon); margin: 0; }
.nav-title p { font-size: 11px; color: var(--text-secondary); margin: 0; }

.btn-logout { 
    text-decoration: none; color: var(--text-secondary); font-size: 13px; font-weight: 500;
    padding: 8px 12px; border-radius: 6px; transition: 0.2s;
}
.btn-logout:hover { background: #fef2f2; color: var(--utm-maroon); }

/* LAYOUT */
.layout {
    display: flex;
    margin-top: var(--nav-height);
    min-height: calc(100vh - var(--nav-height));
}

/* SIDEBAR */
.sidebar {
  width: 260px;
  background: white;
  border-right: 1px solid var(--border);
  padding: 24px;
  flex-shrink: 0;
  position: sticky;
  top: var(--nav-height);
  height: calc(100vh - var(--nav-height));
  display: flex; flex-direction: column;
}

.sidebar-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  color: var(--text-secondary); letter-spacing: 0.5px;
  margin-bottom: 16px;
}

.sidebar-menu { list-style: none; flex: 1; padding: 0; }
.sidebar-menu li { margin-bottom: 4px; }
.sidebar-menu a {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 12px;
  border-radius: 6px;
  text-decoration: none;
  color: var(--text-primary);
  font-size: 14px; font-weight: 500;
  transition: all 0.2s;
}
.sidebar-menu a:hover { background: var(--bg-light); color: var(--utm-maroon); }
.sidebar-menu a.active { background: #fef2f2; color: var(--utm-maroon); font-weight: 600; }
.sidebar-menu a i { width: 20px; text-align: center; }

.sidebar-profile {
  margin-top: auto; padding-top: 16px;
  border-top: 1px solid var(--border);
  display: flex; align-items: center; gap: 12px;
}
.profile-icon { 
  width: 40px; height: 40px; 
  background: linear-gradient(135deg, var(--utm-maroon) 0%, var(--utm-maroon-light) 100%); 
  color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; 
  font-weight: 700; font-size: 15px; box-shadow: 0 2px 8px rgba(128,0,0,0.2);
}
.profile-info { font-size: 13px; overflow: hidden; }
.profile-name { font-weight: 600; white-space: nowrap; text-overflow: ellipsis; }
.profile-email { font-size: 11px; color: var(--text-secondary); white-space: nowrap; text-overflow: ellipsis; }

/* MAIN CONTENT */
.main-content {
    flex: 1; padding: 32px; min-width: 0;
}
.page-header { margin-bottom: 24px; }
.page-title h2 { font-size: 24px; font-weight: 700; color: var(--utm-maroon); margin: 0; }
.page-title p { color: var(--text-secondary); font-size: 14px; margin: 4px 0 0 0; }

/* CARDS & CONTROLS */
.card { background: white; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid var(--border); padding: 24px; margin-bottom: 24px; }

.control-group { margin-bottom: 20px; }
.control-label { display: block; font-size: 12px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 8px; }

.room-selector { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; }
.room-selector:focus { border-color: var(--utm-maroon); }

.week-nav { display: flex; gap: 12px; align-items: center; }
.week-display { flex: 1; background: var(--utm-maroon); color: white; padding: 10px; border-radius: 8px; text-align: center; font-weight: 600; font-size: 14px; }
.btn { padding: 10px 16px; border-radius: 8px; border: 1px solid var(--border); background: white; color: var(--text-primary); font-weight: 600; cursor: pointer; font-size: 13px; transition: 0.2s; }
.btn:hover { border-color: var(--utm-maroon); color: var(--utm-maroon); }
.btn-primary { background: var(--utm-maroon); color: white; border-color: var(--utm-maroon); }
.btn-primary:hover { background: var(--utm-maroon-light); color: white; border-color: var(--utm-maroon-light); }

.action-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; margin-bottom: 20px; }
.toggle-group { display: flex; align-items: center; gap: 8px; font-size: 13px; }

/* LEGEND */
.legend { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid var(--border); }
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; }
.dot { width: 12px; height: 12px; border-radius: 3px; }

/* GRID */
.grid-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; }
table.grid { width: 100%; border-collapse: collapse; min-width: 1200px; }
table.grid th, table.grid td { border: 1px solid var(--border); padding: 8px; text-align: center; font-size: 12px; vertical-align: middle; }
table.grid th { background: #f8fafc; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; font-size: 11px; position: sticky; top: 0; z-index: 10; }
table.grid td.time-col { background: #fdfdfd; position: sticky; left: 0; z-index: 5; font-weight: 600; color: var(--utm-maroon); min-width: 100px; text-align: left; padding-left: 12px; }

/* SLOT STYLES */
.slot { height: 60px; min-width: 120px; cursor: pointer; transition: 0.2s; position: relative; }
.slot:hover:not(.past) { transform: scale(1.02); z-index: 2; box-shadow: 0 0 0 2px var(--utm-maroon); }
.slot.available { background: #fff; }
.slot.selected { background: #dbeafe; border: 2px solid #2563eb; }
.slot.booked { background: #fee2e2; color: #991b1b; font-weight: 600; }
.slot.pending { background: #fef3c7; color: #92400e; font-weight: 600; }
.slot.maintenance { background: #ffedd5; color: #9a3412; font-weight: 700; border: 1px solid #fdba74; }
.slot.maintenance-done { background: #dcfce7 !important; color: #166534 !important; border: 2px solid #16a34a !important; }
.slot.recurring { background: #e0e7ff; color: #4338ca; border-left: 3px solid #4338ca; }
.slot.past { background: #f3f4f6; color: #9ca3af; cursor: not-allowed; }

/* REPORTED SLOT HIGHLIGHT */
@keyframes pulse-red { 0% { box-shadow: inset 0 0 0 2px rgba(220, 38, 38, 0.8); } 50% { box-shadow: inset 0 0 0 6px rgba(220, 38, 38, 0.2); } 100% { box-shadow: inset 0 0 0 2px rgba(220, 38, 38, 0.8); } }
.reported-slot { animation: pulse-red 1.5s infinite; border: 2px dashed #dc2626 !important; }
.reported-label { position: absolute; top: -8px; left: 50%; transform: translateX(-50%); background: #dc2626; color: white; font-size: 9px; padding: 2px 6px; border-radius: 4px; font-weight: bold; z-index: 10; white-space: nowrap; }

.cell-content { font-size: 11px; line-height: 1.3; }
.cell-meta { font-size: 10px; opacity: 0.8; margin-top: 2px; }
.delete-btn { position: absolute; top: 2px; right: 2px; background: #dc2626; color: white; width: 20px; height: 20px; border-radius: 50%; font-size: 10px; border: none; cursor: pointer; display: none; align-items: center; justify-content: center; }
.slot:hover .delete-btn { display: flex; }

/* MODAL */
.modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(2px); }
.modal.show { display: flex; }
.modal-content { background: white; width: 95%; max-width: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); padding: 24px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 16px; }
.modal-header h3 { margin: 0; font-size: 18px; color: var(--utm-maroon); font-weight: 700; }
.btn-close { background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary); }
.form-control, .form-select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; }
.form-control:focus { border-color: var(--utm-maroon); }
.tech-box { background: #fff7ed; padding: 12px; border: 1px solid #fdba74; border-radius: 8px; margin-bottom: 16px; display: none; }
.modal-footer { display: flex; justify-content: flex-end; gap: 8px; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border); }

/* --- TOAST NOTIFICATIONS (Pop-ups) --- */
#toast-container {
    position: fixed; top: 90px; right: 24px; z-index: 9999; /* Below nav */
    display: flex; flex-direction: column; gap: 10px; pointer-events: none;
}
.toast-msg {
    min-width: 300px; background: white; padding: 16px; border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px;
    border-left: 4px solid #3b82f6; animation: slideIn 0.3s ease-out; pointer-events: auto;
}
.toast-msg.success { border-left-color: #10b981; }
.toast-msg.error { border-left-color: #ef4444; }
.toast-msg.info { border-left-color: #3b82f6; }

.toast-icon { font-size: 18px; display: flex; align-items: center; }
.toast-content { font-size: 14px; font-weight: 500; color: #1f2937; flex: 1; }

@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }

@media (max-width: 1024px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
</style>
</head>
<body>

<nav class="nav-bar">
    <div class="nav-left">
        <img class="nav-logo" src="../assets/images/utmlogo.png" alt="UTM Logo">
        <div class="nav-title">
            <h1>Room Booking System</h1>
            <p>Admin Timetable</p>
        </div>
    </div>
    <a href="../auth/logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</nav>

<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-title">Main Menu</div>
        <ul class="sidebar-menu">
            <li><a href="index-admin.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
            
            <?php if (!$isTechAdmin): ?>
            <li><a href="reservation_request.php"><i class="fa-solid fa-inbox"></i> Requests</a></li>
            <?php endif; ?>

            <li><a href="admin_timetable.php" class="active"><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
            
            <?php if (!$isTechAdmin): ?>
            <li><a href="admin_recurring.php"><i class="fa-solid fa-rotate"></i> Recurring</a></li>
            <?php endif; ?>

            <li><a href="admin_logbook.php"><i class="fa-solid fa-book"></i> Logbook</a></li>
            <li><a href="generate_reports.php"><i class="fa-solid fa-chart-pie"></i> Reports</a></li>
            <li><a href="admin_problems.php"><i class="fa-solid fa-triangle-exclamation"></i> Problems</a></li>
            
            <?php if ($isSuperAdmin || $isTechAdmin): ?>
                <li><a href="manage_users.php"><i class="fa-solid fa-users-gear"></i> Users</a></li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-profile">
            <div class="profile-icon"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
            <div class="profile-info">
                <div class="profile-name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="profile-email"><?php echo htmlspecialchars($admin_email); ?></div>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h2>Regular Timetable</h2>
                <p>Manage weekly room schedules, bookings, and maintenance slots.</p>
            </div>
        </div>

        <div class="card">
            <div class="control-group">
                <label class="control-label">Select Room</label>
                <select id="roomSelect" class="room-selector">
                    <option value="">-- Choose a Room --</option>
                    <?php
                        $rs = $conn->query("SELECT room_id, name, capacity FROM rooms ORDER BY name");
                        while($r = $rs->fetch_assoc()) {
                            echo '<option value="'.htmlspecialchars($r['room_id']).'">'.htmlspecialchars($r['name']).' ('.$r['capacity'].' pax)</option>';
                        }
                    ?>
                </select>
            </div>

            <div class="control-group">
                <label class="control-label">Week Navigation</label>
                <div class="week-nav">
                    <button id="prevWeekBtn" class="btn"><i class="fa-solid fa-chevron-left"></i> Prev</button>
                    <div id="weekDisplay" class="week-display">Select a Room</div>
                    <button id="nextWeekBtn" class="btn">Next <i class="fa-solid fa-chevron-right"></i></button>
                    <input type="date" id="weekStart" class="room-selector" style="width: auto;">
                    <button id="renderBtn" class="btn btn-primary">Go</button>
                </div>
            </div>

            <div class="legend">
                <div class="legend-item"><div class="dot" style="background:#fff; border:1px solid #ccc"></div> Available</div>
                <div class="legend-item"><div class="dot" style="background:#dbeafe; border:1px solid #2563eb"></div> Selected</div>
                <div class="legend-item"><div class="dot" style="background:#fef3c7"></div> Pending</div>
                <div class="legend-item"><div class="dot" style="background:#fee2e2"></div> Booked</div>
                <div class="legend-item"><div class="dot" style="background:#ffedd5"></div> Maintenance</div>
                <div class="legend-item"><div class="dot" style="background:#e0e7ff"></div> Recurring</div>
            </div>

            <div class="action-bar">
                <div class="toggle-group">
                    <button id="clearSelectionBtn" class="btn">Clear Selection</button>
                    <input id="overwriteChk" type="checkbox"> <label for="overwriteChk">Overwrite bookings</label>
                </div>
                <button id="openCreateModalBtn" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Book Selected Slots</button>
            </div>

            <div id="gridArea" class="grid-wrap"></div>
        </div>
    </main>
</div>

<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Create Booking</h3>
            <button class="btn-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Purpose *</label>
                <input id="modalPurpose" class="form-control" placeholder="e.g. Weekly Meeting" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea id="modalDesc" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Contact Tel</label>
                <input type="tel" id="modalTel" class="form-control" placeholder="+60...">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select id="modalStatus" class="form-select">
                    <option value="booked">Booked</option>
                    <option value="pending">Pending</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            
            <div id="technicianField" class="tech-box">
                <label class="form-label" style="color:#c2410c;">👷 Assign Technician</label>
                <select id="modalTech" class="form-select">
                    <option value="">-- Select Technician --</option>
                    <?php foreach ($technicians as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['Fullname']); ?>"><?php echo htmlspecialchars($t['Fullname']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="font-size:12px; color:var(--text-secondary);">
                Selected slots: <span id="selectedCount" style="font-weight:700; color:var(--utm-maroon);">0</span>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="closeModal()">Cancel</button>
            <button id="modalSaveBtn" class="btn btn-primary">Save Booking</button>
        </div>
    </div>
</div>

<script>
  
// --- TOAST HELPER FUNCTION ---
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast-msg ${type}`;
    
    // Icons
    const icons = { 
        success: '<i class="fa-solid fa-circle-check" style="color:#10b981"></i>', 
        error: '<i class="fa-solid fa-circle-exclamation" style="color:#ef4444"></i>', 
        info: '<i class="fa-solid fa-circle-info" style="color:#3b82f6"></i>' 
    };
    const icon = icons[type] || icons.info;

    toast.innerHTML = `
        <span class="toast-icon">${icon}</span>
        <span class="toast-content">${message}</span>
    `;

    container.appendChild(toast);

    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

const TIME_SLOTS = [
  "08:00-08:50","09:00-09:50","10:00-10:50","11:00-11:50",
  "12:00-12:50","13:00-13:50","14:00-14:50","15:00-15:50",
  "16:00-16:50","17:00-17:50","18:00-18:50","19:00-19:50",
  "20:00-20:50","21:00-21:50","22:00-22:50","23:00-23:50",
];

let selectedCells = new Map();
let bookingsIndex = {};
let currentWeekStart = null;

// Elements
const roomSelect = document.getElementById('roomSelect');
const weekStart = document.getElementById('weekStart');
const weekDisplay = document.getElementById('weekDisplay');
const renderBtn = document.getElementById('renderBtn');
const prevWeekBtn = document.getElementById('prevWeekBtn');
const nextWeekBtn = document.getElementById('nextWeekBtn');
const gridArea = document.getElementById('gridArea');
const modalSaveBtn = document.getElementById('modalSaveBtn');
const modalStatus = document.getElementById('modalStatus');
const technicianField = document.getElementById('technicianField');
const selectedCount = document.getElementById('selectedCount');

// Helper Functions
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
    if (currentWeekStart) weekDisplay.textContent = formatDateRange(currentWeekStart);
}

function closeModal() {
    document.getElementById('createModal').classList.remove('show');
}

// Logic
modalStatus.addEventListener('change', function() {
    if (this.value === 'maintenance') technicianField.style.display = 'block';
    else { technicianField.style.display = 'none'; document.getElementById('modalTech').value = ''; }
});

weekStart.addEventListener('change', () => {
    if(weekStart.value) {
        currentWeekStart = getMonday(weekStart.value);
        renderGrid();
    }
});

prevWeekBtn.addEventListener('click', () => {
    if (!currentWeekStart) currentWeekStart = getMonday(new Date().toISOString().slice(0,10));
    currentWeekStart = isoAddDays(currentWeekStart, -7);
    weekStart.value = currentWeekStart;
    renderGrid();
});

nextWeekBtn.addEventListener('click', () => {
    if (!currentWeekStart) currentWeekStart = getMonday(new Date().toISOString().slice(0,10));
    currentWeekStart = isoAddDays(currentWeekStart, 7);
    weekStart.value = currentWeekStart;
    renderGrid();
});

renderBtn.addEventListener('click', renderGrid);
roomSelect.addEventListener('change', renderGrid);

document.getElementById('clearSelectionBtn').addEventListener('click', () => {
    selectedCells.forEach(o => o.td.classList.remove('selected'));
    selectedCells.clear();
    selectedCount.textContent = 0;
});

document.getElementById('openCreateModalBtn').addEventListener('click', () => {
    if (selectedCells.size === 0) return showToast('Please select at least one slot first.', 'error'); // New
    
    const urlParams = new URLSearchParams(window.location.search);
    const maintenanceId = urlParams.get('maintenance');

    if (maintenanceId) {
        document.getElementById('modalPurpose').value = `Fixing Reported Issue #${maintenanceId}`;
        modalStatus.value = 'maintenance';
        technicianField.style.display = 'block';
    } else {
        document.getElementById('modalPurpose').value = '';
        modalStatus.value = 'booked';
        technicianField.style.display = 'none';
    }
    
    document.getElementById('modalDesc').value = '';
    document.getElementById('modalTel').value = '';
    document.getElementById('modalTitle').textContent = 'Create Booking';
    modalSaveBtn.textContent = 'Save Booking';
    modalSaveBtn.dataset.actionType = 'save';
    
    document.getElementById('createModal').classList.add('show');
});

function renderGrid() {
    const room = roomSelect.value;
    
    // 1. UPDATED: Show Toast if no room selected
    if (!room) {
        showToast('Please select a room first.', 'error');
        return;
    }

    if (!currentWeekStart) currentWeekStart = getMonday(new Date().toISOString().slice(0,10));
    
    const monday = currentWeekStart;
    weekStart.value = monday;
    updateWeekDisplay();

    gridArea.innerHTML = '';
    selectedCells.clear();
    selectedCount.textContent = 0;
    bookingsIndex = {};

    const table = document.createElement('table');
    table.className = 'grid';

    // Header
    const thead = document.createElement('thead');
    const trHead = document.createElement('tr');
    trHead.innerHTML = '<th style="background:#f3f4f6; position:sticky; left:0; z-index:20;">Date / Time</th>';
    TIME_SLOTS.forEach(ts => trHead.innerHTML += `<th>${ts}</th>`);
    thead.appendChild(trHead);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    for (let i = 0; i < 7; i++) {
        const iso = isoAddDays(monday, i);
        const tr = document.createElement('tr');
        
        // Time Col
        const tdTime = document.createElement('td');
        tdTime.className = 'time-col';
        tdTime.innerHTML = `${iso}<br><span style="font-weight:400;color:#64748b;font-size:10px;">${getDayName(iso)}</span>`;
        tr.appendChild(tdTime);

        TIME_SLOTS.forEach(ts => {
            const td = document.createElement('td');
            td.className = 'slot available';
            td.dataset.date = iso;
            td.dataset.slot = ts;

            if (iso < new Date().toISOString().slice(0,10)) td.classList.add('past');

            td.addEventListener('click', () => {
                if (td.classList.contains('past')) return;
                const key = iso + '|' + ts;
                if (td.classList.contains('selected')) {
                    td.classList.remove('selected', 'reported-slot');
                    td.innerHTML = ''; // Remove report label
                    selectedCells.delete(key);
                } else if (td.classList.contains('available')) {
                    td.classList.add('selected');
                    selectedCells.set(key, {date: iso, slot: ts, td});
                } else if (!td.classList.contains('available')) {
                    openEditForCell(iso, ts);
                }
                selectedCount.textContent = selectedCells.size;
            });
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    }
    table.appendChild(tbody);
    gridArea.appendChild(table);

    // Fetch Bookings
    const endDate = isoAddDays(monday, 6);
    fetch(`admin_timetable.php?endpoint=bookings&room=${encodeURIComponent(room)}&start=${monday}&end=${endDate}`)
    .then(r => r.json())
    .then(json => {
        // 2. UPDATED: Use Toast for API errors
        if(!json.success) return showToast(json.msg, 'error');
        
        json.bookings.forEach(b => {
            const dbStart = b.time_start.slice(0,5);
            const targetSlot = TIME_SLOTS.find(ts => ts.startsWith(dbStart));
            if(!targetSlot) return;

            const selector = `td.slot[data-date="${b.slot_date}"][data-slot="${targetSlot}"]`;
            const td = document.querySelector(selector);
            if(!td) return;

            const key = b.slot_date + '|' + targetSlot;
            bookingsIndex[key] = b;

            td.className = 'slot'; // Reset
            td.innerHTML = '';

            // Status Styling
            if(b.recurring) {
                td.classList.add('recurring');
                td.innerHTML = `<div class="cell-content"><strong>${b.purpose}</strong></div>`;
                // Optional: Add Double click info for recurring
                td.addEventListener('dblclick', () => showToast('This is a recurring template.', 'info'));
            } else {
                const s = (b.status||'').toLowerCase();
                let cls = 'booked';
                if(s === 'maintenance') cls = (b.tech_status === 'Work Done') ? 'maintenance-done' : 'maintenance';
                else if(s === 'pending') cls = 'pending';
                td.classList.add(cls);

                let content = `<div class="cell-content"><strong>${b.purpose}</strong>`;
                if(s === 'maintenance') {
                     if(b.tech_status==='Work Done') content += `<br>✅ DONE`;
                     if(b.technician) content += `<div class="cell-meta">👷 ${b.technician}</div>`;
                } else if(b.tel) {
                    content += `<div class="cell-meta">📞 ${b.tel}</div>`;
                }
                content += `</div>`;
                
                // Delete Button
                const btn = document.createElement('button');
                btn.className = 'delete-btn';
                btn.innerHTML = '<i class="fa-solid fa-times"></i>';
                btn.onclick = (e) => { e.stopPropagation(); deleteBooking(b.id); };
                td.appendChild(btn);
                
                td.insertAdjacentHTML('beforeend', content);
            }
        });
        checkAndHighlightReport();
    });
}

function checkAndHighlightReport() {
    const urlParams = new URLSearchParams(window.location.search);
    const pDate = urlParams.get('date');
    const pStart = urlParams.get('time_start');
    
    if (pDate && pStart) {
        const targetSlot = TIME_SLOTS.find(ts => ts.startsWith(pStart));
        if (targetSlot) {
            const cell = document.querySelector(`td.slot[data-date="${pDate}"][data-slot="${targetSlot}"]`);
            if (cell && cell.classList.contains('available')) {
                cell.classList.add('reported-slot');
                cell.innerHTML += '<div class="reported-label">REPORTED</div>';
                if (!cell.classList.contains('selected')) {
                    cell.classList.add('selected');
                    selectedCells.set(pDate + '|' + targetSlot, { date: pDate, slot: targetSlot, td: cell });
                    selectedCount.textContent = selectedCells.size;
                }
                cell.scrollIntoView({behavior: "smooth", block: "center"});
            }
        }
    }
}

function openEditForCell(date, slot) {
    const key = date+'|'+slot;
    const b = bookingsIndex[key];
    if(!b) return;

    document.getElementById('modalPurpose').value = b.purpose || '';
    document.getElementById('modalDesc').value = b.description || '';
    document.getElementById('modalTel').value = b.tel || '';
    modalStatus.value = b.status || 'booked';
    
    modalSaveBtn.textContent = "Update Booking";
    modalSaveBtn.dataset.actionType = 'save';
    modalSaveBtn.dataset.bookingId = b.id;

    if(b.status === 'maintenance') {
        technicianField.style.display = 'block';
        document.getElementById('modalTech').value = b.technician || '';
        if(b.tech_status === 'Work Done') {
            document.getElementById('modalTitle').textContent = "✅ Verify Work";
            modalSaveBtn.textContent = "Verify & Clear Slot";
            modalSaveBtn.dataset.actionType = 'verify_clear';
        } else {
            document.getElementById('modalTitle').textContent = "Update Maintenance";
        }
    } else {
        technicianField.style.display = 'none';
        document.getElementById('modalTitle').textContent = "Update Booking";
    }

    selectedCells.clear();
    selectedCells.set(key, {date, slot, bookingId: b.id});
    selectedCount.textContent = '1 (Editing)';
    document.getElementById('createModal').classList.add('show');
}

modalSaveBtn.addEventListener('click', () => {
    if(modalSaveBtn.dataset.actionType === 'verify_clear') {
        if(confirm("Mark problem resolved and clear slot?")) {
            deleteBooking(modalSaveBtn.dataset.bookingId);
            closeModal();
        }
        return;
    }

    const room = roomSelect.value;
    if (!room || selectedCells.size === 0) return showToast('Missing room or slot selection.', 'error'); // New

    const urlParams = new URLSearchParams(window.location.search);
    const maintenanceId = urlParams.get('maintenance');

    const payload = {
        action: 'save',
        room_id: room,
        purpose: document.getElementById('modalPurpose').value,
        description: document.getElementById('modalDesc').value,
        tel: document.getElementById('modalTel').value,
        status: modalStatus.value,
        technician: document.getElementById('modalTech').value,
        overwrite: document.getElementById('overwriteChk').checked ? 1 : 0,
        problem_id: maintenanceId ? parseInt(maintenanceId) : 0,
        slots: Array.from(selectedCells.values()).map(o => ({date: o.date, slot: o.slot})),
        booking_id: modalSaveBtn.dataset.bookingId || 0
    };

    modalSaveBtn.disabled = true;
    modalSaveBtn.innerText = 'Processing...';

    fetch('admin_timetable.php', { method:'POST', body:JSON.stringify(payload) })
    .then(r => r.json())
    .then(res => {
        modalSaveBtn.disabled = false;
        modalSaveBtn.innerText = 'Save Booking';
        if(res.success) {
            closeModal();
            renderGrid();
            window.history.replaceState({}, document.title, window.location.pathname);
            
            const msg = payload.status === 'maintenance' ? 'Maintenance Scheduled Successfully!' : 'Booking Saved Successfully!';
            showToast(msg, 'success');
        } else {
            showToast('Error: ' + res.msg, 'error');
        }
    });
});

function deleteBooking(id) {
    if(!confirm('Delete this booking?')) return;
    fetch('admin_timetable.php', { method:'POST', body:JSON.stringify({action:'delete', booking_id:id}) })
    .then(r=>r.json()).then(d => {
        if(d.success) renderGrid();
        else showToast(d.msg || 'Failed to delete booking', 'error'); // New
    });
}

// Init
(function() {
    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.get('date')) currentWeekStart = getMonday(urlParams.get('date'));
    if(urlParams.get('room')) {
        roomSelect.value = urlParams.get('room');
        renderGrid();
    }
})();
</script>
</body>
</html>