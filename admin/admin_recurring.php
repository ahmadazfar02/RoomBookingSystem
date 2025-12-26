<?php
// admin_recurring.php - Recurring Timetable Templates
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

function json_ok($data = []) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(array_merge(['success'=>true], $data)); exit; }
function json_err($msg = 'Error', $extra = []) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(array_merge(['success'=>false, 'msg'=>$msg], $extra)); exit; }
function log_error($m) { error_log($m); @file_put_contents(__DIR__.'/admin_recurring_error.log',"[".date('Y-m-d H:i:s')."] ".$m.PHP_EOL, FILE_APPEND); }

// --- 1. ACCESS CONTROL ---
$admin_id = $_SESSION['User_ID'] ?? $_SESSION['id'] ?? null;
$uType = trim($_SESSION['User_Type'] ?? '');
$username = $_SESSION['username'] ?? '';

// Roles
$isTechAdmin  = (strcasecmp($uType, 'Technical Admin') === 0);
$isSuperAdmin = (strcasecmp($uType, 'SuperAdmin') === 0 || strtolower($username) === 'superadmin');
$isAdmin      = (strcasecmp($uType, 'Admin') === 0);

// Strict Access: Only Admin and SuperAdmin (Technical Admin blocked)
if (!$admin_id || ($isTechAdmin || (!$isAdmin && !$isSuperAdmin))) {
    $is_json_request = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) || ($_SERVER['REQUEST_METHOD'] === 'POST');
    if ($is_json_request) json_err('Not authorized');
    header('Location: loginterface.html');
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin'; 
$admin_email = $_SESSION['Email'] ?? ($_SESSION['User_Type'] ?? 'Admin');

// --- NOTIFICATION COUNTERS ---
$tech_pending = 0;
$pending_approvals = 0;
$active_problems = 0;

if ($isTechAdmin) {
    // Tech Admin: Count Pending Repair SESSIONS (Not Slots)
    $sql = "SELECT COUNT(DISTINCT session_id) FROM bookings WHERE tech_token IS NOT NULL AND tech_status != 'Work Done'";
    $result = $conn->query($sql);
    if($result) { $row = $result->fetch_row(); $tech_pending = intval($row[0]); }
} else {
    // Admin: Count Pending Request Sessions
    $sql = "SELECT COUNT(DISTINCT session_id) FROM bookings WHERE status = 'pending'";
    $result = $conn->query($sql);
    if($result) { $row = $result->fetch_row(); $pending_approvals = intval($row[0]); }

    // Admin: Count Active Problems
    $sql = "SELECT COUNT(*) FROM room_problems WHERE status != 'Resolved'";
    $result = $conn->query($sql);
    if($result) { $row = $result->fetch_row(); $active_problems = intval($row[0]); }
}

// --- 2. API ENDPOINTS ---
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
            $stmt = $conn->prepare("SELECT id, room_id, day_of_week, time_start, time_end, purpose, description, tel, status FROM recurring_bookings WHERE room_id = ? ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), time_start");
            $stmt->bind_param("s", $room);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) {
                $r['time_slot'] = substr($r['time_start'], 0, 5) . '-' . substr($r['time_end'], 0, 5);
                $rows[] = $r;
            }
            $stmt->close();
        } else {
            // Empty list if no room selected
            $rows = [];
        }
        json_ok(['recurring'=>$rows]);
    }
    json_err('Unknown endpoint');
}

// --- 3. POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) json_err('Invalid JSON');

    $action = $data['action'] ?? '';

    if ($action === 'create_bulk') {
        $bookings = $data['bookings'] ?? [];
        if (empty($bookings)) json_err('No bookings provided');

        $conn->begin_transaction();
        $created = 0; $skipped = 0; $errors = [];
        
        try {
            foreach ($bookings as $i => $b) {
                $room_id = trim($b['room_id'] ?? '');
                $day = trim($b['day_of_week'] ?? '');
                $slot = trim($b['time_slot'] ?? ''); 
                $purpose = trim($b['purpose'] ?? '');
                $description = trim($b['description'] ?? '');
                $tel = trim($b['tel'] ?? '');

                if (!$room_id || !$day || !$slot || !$purpose) { $skipped++; continue; }

                $parts = explode('-', $slot);
                if (count($parts) !== 2) { $skipped++; continue; }
                $time_start = $parts[0] . ':00';
                $time_end = $parts[1] . ':00';

                // Check conflict
                $chk = $conn->prepare("SELECT id FROM recurring_bookings WHERE room_id=? AND day_of_week=? AND time_start=? AND status='active' LIMIT 1");
                $chk->bind_param("sss", $room_id, $day, $time_start);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) { $skipped++; $chk->close(); continue; }
                $chk->close();

                // Insert
                $ins = $conn->prepare("INSERT INTO recurring_bookings (room_id, day_of_week, time_start, time_end, purpose, description, tel, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)");
                $ins->bind_param("sssssssi", $room_id, $day, $time_start, $time_end, $purpose, $description, $tel, $admin_id);
                if ($ins->execute()) $created++; else $errors[] = $ins->error;
                $ins->close();
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            json_err('Transaction failed', ['error'=>$e->getMessage()]);
        }
        json_ok(['created'=>$created,'skipped'=>$skipped,'errors'=>$errors]);
    }

    if ($action === 'update') {
        $id = intval($data['id'] ?? 0);
        $purpose = trim($data['purpose'] ?? '');
        $description = trim($data['description'] ?? '');
        $tel = trim($data['tel'] ?? '');
        $status = $data['status'] ?? 'active';
        
        $stmt = $conn->prepare("UPDATE recurring_bookings SET purpose=?, description=?, tel=?, status=? WHERE id=?");
        $stmt->bind_param("ssssi", $purpose, $description, $tel, $status, $id);
        if ($stmt->execute()) json_ok(['msg'=>'Updated']);
        else json_err('Update failed');
    }

    if ($action === 'delete') {
        $id = intval($data['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM recurring_bookings WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) json_ok(['msg'=>'Deleted']);
        else json_err('Delete failed');
    }
    json_err('Unknown action');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recurring Templates - Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root { 
    --utm-maroon: #800000;
    --utm-maroon-light: #a31313;
    --utm-maroon-dark: #600000;
    --bg-light: #f9fafb;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --border: #e2e8f0;
    --nav-height: 70px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: var(--bg-light); min-height: 100vh; color: var(--text-primary); }

/* NAVBAR */
.nav-bar { position: fixed; top: 0; left: 0; right: 0; height: var(--nav-height); background: white; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); z-index: 1000; border-bottom: 1px solid var(--border); }
.nav-left { display: flex; align-items: center; gap: 16px; }
.nav-logo { height: 50px; }
.nav-title h1 { font-size: 16px; font-weight: 700; color: var(--utm-maroon); margin: 0; }
.nav-title p { font-size: 11px; color: var(--text-secondary); margin: 0; }
.btn-logout { text-decoration: none; color: var(--text-secondary); font-size: 13px; font-weight: 500; padding: 8px 12px; border-radius: 6px; transition: 0.2s; }
.btn-logout:hover { background: #fef2f2; color: var(--utm-maroon); }

/* LAYOUT */
.layout { display: flex; margin-top: var(--nav-height); min-height: calc(100vh - var(--nav-height)); }

/* SIDEBAR */
.sidebar { width: 260px; background: white; border-right: 1px solid var(--border); padding: 24px; flex-shrink: 0; position: sticky; top: var(--nav-height); height: calc(100vh - var(--nav-height)); display: flex; flex-direction: column; }
.sidebar-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px; margin-bottom: 16px; }
.sidebar-menu { list-style: none; flex: 1; padding: 0; }
.sidebar-menu li { margin-bottom: 4px; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 6px; text-decoration: none; color: var(--text-primary); font-size: 14px; font-weight: 500; transition: all 0.2s; }
.sidebar-menu a:hover { background: var(--bg-light); color: var(--utm-maroon); }
.sidebar-menu a.active { background: #fef2f2; color: var(--utm-maroon); font-weight: 600; }
.sidebar-menu a i { width: 20px; text-align: center; }

/* NOTIFICATION BADGE */
.nav-badge {
    background-color: #dc2626; /* Red */
    color: white; 
    font-size: 10px; 
    font-weight: 700;
    padding: 2px 8px; 
    border-radius: 99px; 
    margin-left: auto; /* Pushes badge to the right */
}

.sidebar-profile { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 12px; }
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
.main-content { flex: 1; padding: 32px; min-width: 0; }
.page-header { margin-bottom: 24px; }
.page-title h2 { font-size: 24px; font-weight: 700; color: var(--utm-maroon); margin: 0; }
.page-title p { color: var(--text-secondary); font-size: 14px; margin: 4px 0 0 0; }

/* CARD & CONTROLS */
.card { background: white; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid var(--border); padding: 24px; margin-bottom: 24px; }

.control-panel { display: flex; gap: 16px; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; }
.form-group { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 200px; }
.form-label { font-size: 12px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; }
.form-control { padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; width: 100%; }
.form-control:focus { border-color: var(--utm-maroon); }

.btn { padding: 10px 16px; border-radius: 8px; border: 1px solid var(--border); background: white; color: var(--text-primary); font-weight: 600; cursor: pointer; font-size: 13px; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }

.btn-primary { background: var(--utm-maroon); color: white; border-color: var(--utm-maroon); }
.btn-primary:hover { background: var(--utm-maroon-light); color: white; border-color: var(--utm-maroon-light); }
.btn-success { background: #059669; color: white; border-color: #059669; }
.btn-danger { background: #dc2626; color: white; border-color: #dc2626; }

/* GRID STYLES */
.grid-container { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; }
table.grid { width: 100%; border-collapse: collapse; min-width: 1200px; }
table.grid th, table.grid td { border: 1px solid var(--border); padding: 8px; text-align: center; vertical-align: middle; }
table.grid th { background: #f8fafc; font-weight: 600; font-size: 11px; text-transform: uppercase; color: var(--text-secondary); position: sticky; top: 0; z-index: 10; }
td.day-col { background: #fdfdfd; position: sticky; left: 0; z-index: 5; font-weight: 600; color: var(--utm-maroon); min-width: 120px; }

td.slot { height: 60px; min-width: 100px; cursor: pointer; transition: 0.2s; font-size: 12px; position: relative; }
td.slot:hover { background: #f8fafc; }
td.selected { background: #dbeafe !important; border: 2px solid #2563eb; }
td.recurring { background: #e0e7ff; color: #3730a3; border-left: 3px solid #4338ca; font-weight: 600; }
td.recurring:hover { opacity: 0.9; }

/* MODAL */
.modal-backdrop { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.modal { background: white; width: 95%; max-width: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; animation: popIn 0.2s ease-out; }
.modal-header { padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
.modal-header h3 { margin: 0; font-size: 16px; color: var(--utm-maroon); font-weight: 700; }
.modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-secondary); }
.modal-body { padding: 24px; font-size: 14px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; background: #f8fafc; }

@keyframes popIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
@media (max-width: 1024px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
</style>
</head>
<body>

<nav class="nav-bar">
    <div class="nav-left">
        <img class="nav-logo" src="../assets/images/utmlogo.png" alt="UTM Logo">
        <div class="nav-title">
            <h1>Room Booking System</h1>
            <p>Admin Dashboard</p>
        </div>
    </div>
    <a href="../auth/logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</nav>

<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-title">Main Menu</div>
    <ul class="sidebar-menu">
        <li>
            <a href="index-admin.php">
                <i class="fa-solid fa-gauge-high"></i> Dashboard
                <?php if ($isTechAdmin && $tech_pending > 0): ?>
                    <span class="nav-badge"><?php echo $tech_pending; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <?php if (!$isTechAdmin): ?>
        <li>
            <a href="reservation_request.php" <?php echo basename($_SERVER['PHP_SELF']) == 'reservation_request.php' ? 'class="active"' : ''; ?>>
                <i class="fa-solid fa-inbox"></i> Requests
                <?php if ($pending_approvals > 0): ?>
                    <span class="nav-badge"><?php echo $pending_approvals; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endif; ?>

        <li><a href="admin_timetable.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_timetable.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
        
        <?php if (!$isTechAdmin): ?>
        <li><a href="admin_recurring.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_recurring.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-rotate"></i> Recurring</a></li>
        <li><a href="admin_logbook.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_logbook.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-book"></i> Logbook</a></li>
        <?php endif; ?>

        <li><a href="generate_reports.php" <?php echo basename($_SERVER['PHP_SELF']) == 'generate_reports.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-chart-pie"></i> Reports</a></li>
        
        <li>
            <a href="admin_problems.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_problems.php' ? 'class="active"' : ''; ?>>
                <i class="fa-solid fa-triangle-exclamation"></i> Problems
                <?php if ($isTechAdmin && $tech_pending > 0): ?>
                    <span class="nav-badge"><?php echo $tech_pending; ?></span>
                <?php elseif (!$isTechAdmin && $active_problems > 0): ?>
                    <span class="nav-badge"><?php echo $active_problems; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <?php if ($isSuperAdmin || $isTechAdmin): ?>
            <li><a href="manage_users.php" <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-users-gear"></i> Users</a></li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-profile">
      <div class="profile-icon"><?php echo strtoupper(substr($admin_name,0,1)); ?></div>
      <div class="profile-info">
        <div class="profile-name"><?php echo htmlspecialchars($admin_name); ?></div>
        <div class="profile-email"><?php echo htmlspecialchars($admin_email); ?></div>
      </div>
    </div>
  </aside>

  <main class="main-content">
    <div class="page-header">
        <div class="page-title">
            <h2>Recurring Templates</h2>
            <p>Define weekly class schedules or repeating maintenance blocks.</p>
        </div>
    </div>

    <div class="card">
        <div class="control-panel">
            <div class="form-group" style="flex:2;">
                <label class="form-label">Select Room</label>
                <select id="roomSelect" class="form-control">
                    <option value="">-- Loading Rooms --</option>
                </select>
            </div>
            <div class="form-group">
                <button id="loadBtn" class="btn btn-primary"><i class="fa-solid fa-rotate"></i> Load</button>
            </div>
            <div class="form-group" style="margin-left:auto; flex:0;">
                <button id="clearSelBtn" class="btn"><i class="fa-regular fa-square"></i> Clear</button>
            </div>
            <div class="form-group" style="flex:0;">
                <button id="addSelectedBtn" class="btn btn-success"><i class="fa-solid fa-plus"></i> Add Selected</button>
            </div>
        </div>

        <div class="grid-container" id="gridArea">
            <div style="padding:40px; text-align:center; color:var(--text-secondary);">Select a room to view the template grid.</div>
        </div>
    </div>
  </main>
</div>

<div id="slotModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modalTitle">Edit Template</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editId">
      
      <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:16px; font-size:13px;">
          <strong>Target:</strong> <span id="modalTargetDisplay"></span>
      </div>

      <div class="form-group" style="margin-bottom:12px;">
          <label class="form-label">Subject Code / Title *</label>
          <input id="modalPurpose" class="form-control" placeholder="e.g. SECJ1013">
      </div>
      <div class="form-group" style="margin-bottom:12px;">
          <label class="form-label">Description / Lecturer</label>
          <textarea id="modalDesc" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-group" style="margin-bottom:12px;">
          <label class="form-label">Contact Tel</label>
          <input id="modalTel" class="form-control" placeholder="+60...">
      </div>
      <div class="form-group" id="modalStatusGroup">
          <label class="form-label">Status</label>
          <select id="modalStatus" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
          </select>
      </div>
    </div>
    <div class="modal-footer">
        <button id="modalDeleteBtn" class="btn btn-danger" style="margin-right:auto; display:none;">Delete</button>
        <button class="btn" onclick="closeModal()">Cancel</button>
        <button id="modalSaveBtn" class="btn btn-primary">Save Template</button>
    </div>
  </div>
</div>

<div id="page-loader" class="loader-overlay">
    <div class="spinner"></div>
</div>

<style>
/* 1. Full Screen Overlay */
.loader-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.8); /* White see-through background */
    z-index: 99999; /* On top of everything */
    display: flex;
    justify-content: center;
    align-items: center;
    opacity: 0;
    pointer-events: none; /* Let clicks pass through when hidden */
    transition: opacity 0.3s ease;
}

/* 2. The Round Bullet Spinner */
.spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #e5e7eb; /* Light grey ring */
    border-top: 5px solid #800000; /* UTM Maroon spinning part */
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* 3. Animation Keyframes */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 4. Active State (Show Overlay) */
.loader-overlay.active {
    opacity: 1;
    pointer-events: all; /* Block clicks while loading */
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const loader = document.getElementById('page-loader');

    // 1. Hide loader when the new page finishes loading
    // We use a small timeout to make sure the transition is smooth
    setTimeout(() => {
        loader.classList.remove('active');
    }, 300); 

    // 2. Show loader when user clicks a valid link
    document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            const target = this.getAttribute('target');

            // Ignore local anchors (#), javascript, or new tab links
            if (!href || href.startsWith('#') || href.startsWith('javascript') || target === '_blank') return;

            // Show the spinner immediately
            loader.classList.add('active');
        });
    });
    
    // 3. Also show on form submissions (like filtering)
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            loader.classList.add('active');
        });
    });
});
</script>

<script>
const TIME_SLOTS = [
  "08:00-08:50","09:00-09:50","10:00-10:50","11:00-11:50",
  "12:00-12:50","13:00-13:50","14:00-14:50","15:00-15:50",
  "16:00-16:50","17:00-17:50","18:00-18:50","19:00-19:50",
  "20:00-20:50","21:00-21:50","22:00-22:50","23:00-23:50",
];
const DAYS = ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];

let recurringIndex = {};
let selectedCells = new Map();
let currentRoom = null;

// UI Elements
const roomSelect = document.getElementById('roomSelect');
const gridArea = document.getElementById('gridArea');
const modal = document.getElementById('slotModal');

// Init
loadRooms();

// Functions
function loadRooms(){
  fetch('admin_recurring.php?endpoint=rooms')
    .then(r=>r.json())
    .then(j=>{
      if(!j.success) return alert(j.msg);
      roomSelect.innerHTML = '<option value="">-- Select Room --</option>';
      j.rooms.forEach(r=>{
          const opt = document.createElement('option');
          opt.value = r.room_id;
          opt.textContent = r.name;
          roomSelect.appendChild(opt);
      });
    });
}

function renderGrid(){
  const room = roomSelect.value;
  if (!room) return;
  currentRoom = room;
  recurringIndex = {};
  selectedCells.clear();
  
  gridArea.innerHTML = '<div style="padding:20px; text-align:center;"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';

  fetch(`admin_recurring.php?endpoint=list&room=${encodeURIComponent(room)}`)
    .then(r=>r.json())
    .then(j=>{
        if(!j.success) return alert(j.msg);
        buildTable(j.recurring);
    });
}

function buildTable(data){
    gridArea.innerHTML = '';
    const table = document.createElement('table'); table.className = 'grid';
    
    // Header
    const thead = document.createElement('thead');
    const tr = document.createElement('tr');
    tr.innerHTML = '<th style="background:#f3f4f6; position:sticky; left:0; z-index:20;">Day</th>';
    TIME_SLOTS.forEach(ts => tr.innerHTML += `<th>${ts}</th>`);
    thead.appendChild(tr);
    table.appendChild(thead);

    // Body
    const tbody = document.createElement('tbody');
    
    // Map data
    data.forEach(r => { recurringIndex[r.day_of_week + '|' + r.time_slot] = r; });

    DAYS.forEach(day => {
        const tr = document.createElement('tr');
        const tdDay = document.createElement('td'); 
        tdDay.className = 'day-col'; 
        tdDay.textContent = day; 
        tr.appendChild(tdDay);

        TIME_SLOTS.forEach(slot => {
            const key = day + '|' + slot;
            const td = document.createElement('td');
            td.className = 'slot';
            td.dataset.key = key;
            
            if(recurringIndex[key]) {
                const rec = recurringIndex[key];
                td.className += ' recurring';
                td.innerHTML = `<div>${rec.purpose}</div>`;
                td.onclick = () => openEdit(rec);
            } else {
                td.onclick = () => toggleSelect(td, day, slot);
            }
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
    
    table.appendChild(tbody);
    gridArea.appendChild(table);
}

function toggleSelect(td, day, slot){
    const key = day + '|' + slot;
    if(selectedCells.has(key)) {
        selectedCells.delete(key);
        td.classList.remove('selected');
    } else {
        selectedCells.set(key, {day, slot, td});
        td.classList.add('selected');
    }
}

// Modal Logic
function openModal() { modal.style.display = 'flex'; }
function closeModal() { modal.style.display = 'none'; }

function openEdit(rec){
    document.getElementById('modalTitle').textContent = 'Edit Template';
    document.getElementById('editId').value = rec.id;
    document.getElementById('modalTargetDisplay').textContent = `${rec.day_of_week} @ ${rec.time_slot}`;
    document.getElementById('modalPurpose').value = rec.purpose;
    document.getElementById('modalDesc').value = rec.description || '';
    document.getElementById('modalTel').value = rec.tel || '';
    document.getElementById('modalStatus').value = rec.status;
    
    document.getElementById('modalStatusGroup').style.display = 'flex';
    document.getElementById('modalDeleteBtn').style.display = 'block';
    
    openModal();
}

document.getElementById('addSelectedBtn').onclick = () => {
    if(selectedCells.size === 0) return alert("Please select empty slots first.");
    
    document.getElementById('modalTitle').textContent = 'Create Templates';
    document.getElementById('editId').value = '';
    document.getElementById('modalTargetDisplay').textContent = `${selectedCells.size} slot(s) selected`;
    document.getElementById('modalPurpose').value = '';
    document.getElementById('modalDesc').value = '';
    document.getElementById('modalTel').value = '';
    
    document.getElementById('modalStatusGroup').style.display = 'none';
    document.getElementById('modalDeleteBtn').style.display = 'none';
    
    openModal();
};

document.getElementById('modalSaveBtn').onclick = () => {
    const id = document.getElementById('editId').value;
    const purpose = document.getElementById('modalPurpose').value;
    if(!purpose) return alert("Subject Code required");
    
    const payload = {
        purpose,
        description: document.getElementById('modalDesc').value,
        tel: document.getElementById('modalTel').value,
        status: document.getElementById('modalStatus').value
    };

    if(id) {
        // Update
        payload.action = 'update';
        payload.id = id;
        sendPost(payload);
    } else {
        // Create Bulk
        payload.action = 'create_bulk';
        payload.bookings = Array.from(selectedCells.values()).map(s => ({
            room_id: currentRoom,
            day_of_week: s.day,
            time_slot: s.slot,
            purpose: payload.purpose,
            description: payload.description,
            tel: payload.tel
        }));
        sendPost(payload);
    }
};

document.getElementById('modalDeleteBtn').onclick = () => {
    if(!confirm("Delete this recurring template?")) return;
    sendPost({ action: 'delete', id: document.getElementById('editId').value });
};

function sendPost(data){
    fetch('admin_recurring.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r=>r.json())
    .then(j=>{
        if(j.success) {
            closeModal();
            renderGrid();
        } else {
            alert("Error: " + j.msg);
        }
    });
}

document.getElementById('loadBtn').onclick = renderGrid;
document.getElementById('roomSelect').onchange = renderGrid;
document.getElementById('clearSelBtn').onclick = () => {
    selectedCells.forEach(v => v.td.classList.remove('selected'));
    selectedCells.clear();
};

// Close on outside click
window.onclick = function(e) {
    if(e.target == modal) closeModal();
}
</script>
</body>
</html>