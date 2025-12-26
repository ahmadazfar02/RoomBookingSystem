<?php
// reservation_request.php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Access control
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true ||
   (strcasecmp(trim($_SESSION["User_Type"]), 'Admin') != 0 && strcasecmp(trim($_SESSION["User_Type"]), 'SuperAdmin') != 0)) {
    header("location: ../loginterface.html");
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin'; 
$admin_email = $_SESSION['Email'] ?? ($_SESSION['User_Type'] ?? 'Admin');
$username = $_SESSION['username'] ?? 'superadmin';
$uType = trim($_SESSION['User_Type']);

// Define Roles
$isTechAdmin  = (strcasecmp($uType, 'Technical Admin') === 0);
$isSuperAdmin = (strcasecmp($uType, 'SuperAdmin') === 0 || strtolower($username) === 'superadmin');

// --- 1. NOTIFICATION COUNTERS (Copy this block to all files) ---
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

// Determine current tab/filter
$filter = $_GET['filter'] ?? 'pending';
$allowed_filters = ['pending', 'booked', 'rejected', 'cancelled', 'all'];
if (!in_array($filter, $allowed_filters)) $filter = 'pending';

// Get search query
$search_room = trim($_GET['search_room'] ?? '');

// Build WHERE clause
$where = [];
if ($filter !== 'all') $where[] = "b.status='" . $conn->real_escape_string($filter) . "'";
if ($search_room !== '') {
    $search_room_safe = $conn->real_escape_string($search_room);
    $where[] = "(r.name LIKE '%$search_room_safe%' OR r.room_id LIKE '%$search_room_safe%')";
}
$where_sql = '';
if (count($where) > 0) $where_sql = 'WHERE ' . implode(' AND ', $where);

// Fetch bookings grouped by session
$sql = "
SELECT 
    b.session_id,
    GROUP_CONCAT(b.id ORDER BY b.time_start ASC) AS booking_ids,
    GROUP_CONCAT(CONCAT(SUBSTRING(b.time_start, 1, 5),'-',SUBSTRING(b.time_end, 1, 5)) ORDER BY b.time_start ASC SEPARATOR ', ') AS time_slots,
    b.slot_date,
    r.room_id AS room_no,
    r.name AS room_name,
    u.username AS requested_by,
    u.fullname AS user_fullname,
    u.email AS user_email,
    b.purpose,
    b.description,
    b.created_at,
    MAX(b.ticket) AS ticket,
    b.status
FROM bookings b
JOIN rooms r ON b.room_id = r.room_id
JOIN users u ON b.user_id = u.id
$where_sql
GROUP BY b.session_id, r.room_id, b.slot_date, r.name, u.username, u.fullname, u.email, b.purpose, b.description, b.status, b.created_at
ORDER BY b.created_at DESC, b.slot_date ASC
";

$result = $conn->query($sql);

// Fetch all rooms for dropdown
$rooms_result = $conn->query("SELECT room_id, name FROM rooms ORDER BY name");
$rooms = [];
while($r = $rooms_result->fetch_assoc()){
    $rooms[] = $r;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reservation Requests - UTM Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root {
  --utm-maroon: #800000;
  --utm-maroon-light: #a31313;
  --bg-light: #f9fafb;
  --text-primary: #1f2937;
  --text-secondary: #6b7280;
  --border: #e5e7eb;
  --success: #16a34a;
  --danger: #dc2626;
  --warning: #f59e0b;
  --purple: #7c3aed;
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --nav-height: 70px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Inter', sans-serif;
  background: var(--bg-light);
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

/* NOTIFICATION BADGE */
.nav-badge {
    background-color: var(--danger); color: white; font-size: 10px; font-weight: 700;
    padding: 2px 8px; border-radius: 99px; margin-left: auto;
}

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
.main-content { flex: 1; padding: 32px; min-width: 0; }

/* HEADER CARD */
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 24px;
}
.page-title h2 { font-size: 24px; font-weight: 700; color: var(--utm-maroon); margin:0; }
.page-title p { color: var(--text-secondary); font-size: 14px; margin: 4px 0 0 0; }

/* CONTENT CARD */
.card {
  background: white; border-radius: 12px;
  box-shadow: var(--shadow-sm); border: 1px solid var(--border);
  padding: 24px;
}

/* FILTERS & CONTROLS */
.top-controls {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 24px; gap: 16px; flex-wrap: wrap;
}

.tabs {
  display: flex; background: var(--bg-light);
  padding: 4px; border-radius: 8px; gap: 4px;
}
.tab {
  padding: 8px 16px; border-radius: 6px;
  text-decoration: none; color: var(--text-secondary);
  font-size: 13px; font-weight: 600;
  transition: all 0.2s;
}
.tab:hover { color: var(--utm-maroon); background: white; }
.tab.active { background: white; color: var(--utm-maroon); box-shadow: var(--shadow-sm); }

.search-form { display: flex; gap: 8px; }
.room-select {
  padding: 8px 12px; border: 1px solid var(--border);
  border-radius: 6px; font-size: 13px; min-width: 180px;
}
.room-select:focus { outline: none; border-color: var(--utm-maroon); }

/* BUTTONS */
.btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 8px 16px; border-radius: 6px;
  font-size: 13px; font-weight: 600; cursor: pointer; border: none;
  transition: all 0.2s; text-decoration: none;
}
.btn-primary { background: var(--utm-maroon); color: white; }
.btn-primary:hover { background: var(--utm-maroon-light); }
.btn-outline { background: white; border: 1px solid var(--border); color: var(--text-primary); }
.btn-outline:hover { border-color: var(--utm-maroon); color: var(--utm-maroon); }

/* TABLE */
.table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; }
.list-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
.list-table th {
  background: var(--bg-light); text-align: left;
  padding: 12px 16px; font-size: 12px; font-weight: 600;
  color: var(--text-secondary); text-transform: uppercase;
}
.list-table td {
  padding: 14px 16px; border-top: 1px solid var(--border);
  font-size: 13px; vertical-align: middle;
}
.list-table tr:hover { background: #fafafa; }
.user-subtext { font-size: 11px; color: var(--text-secondary); display: block; margin-top: 2px; }

/* STATUS BADGES */
.status {
  display: inline-flex; padding: 4px 10px; border-radius: 99px;
  font-size: 11px; font-weight: 600; text-transform: uppercase;
}
.status.pending { background: #fef3c7; color: #92400e; }
.status.booked, .status.approved { background: #dcfce7; color: #166534; }
.status.rejected, .status.cancelled { background: #fee2e2; color: #991b1b; }

/* ACTIONS */
.actions { display: flex; gap: 6px; }
.btn-icon {
  width: 32px; height: 32px; border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--border); background: white;
  color: var(--text-secondary); cursor: pointer;
  transition: all 0.2s;
}
.btn-icon:hover { border-color: var(--utm-maroon); color: var(--utm-maroon); }
.btn-icon.view:hover { background: #eff6ff; border-color: #3b82f6; color: #3b82f6; }
.btn-icon.approve:hover { background: var(--success); border-color: var(--success); color: white; }
.btn-icon.reject:hover { background: var(--danger); border-color: var(--danger); color: white; }

/* MODALS */
.modal {
  position: fixed; inset: 0; background: rgba(0,0,0,0.5);
  display: none; align-items: center; justify-content: center;
  z-index: 2000; backdrop-filter: blur(2px);
}
.modal.show { display: flex; }

.modal-card {
  background: white; border-radius: 12px;
  width: 100%; max-width: 500px; padding: 24px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  display: flex; flex-direction: column;
}
.modal-card.large { max-width: 1200px; max-height: 90vh; }

.modal-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 16px;
}

.modal-header h3 { 
  font-size: 18px; 
  font-weight: 700; color: var(--utm-maroon); 
  margin:0; }

.btn-close {
  background: none; 
  border: none; 
  font-size: 20px;
  color: var(--text-secondary); 
  cursor: pointer;
}

.modal-body { 
  overflow-y: auto; 
  flex: 1; 
  padding-bottom: 20px; 
}

.modal-footer {
  margin-top: auto; 
  padding-top: 16px; 
  border-top: 1px solid var(--border);
  display: flex; 
  justify-content: flex-end; 
  gap: 8px;
}

.modal-textarea {
  width: 100%; 
  height: 100px; 
  padding: 12px;
  border: 1px solid var(--border); 
  border-radius: 6px;
  font-family: inherit; 
  font-size: 14px; 
  margin-top: 8px;
  resize: vertical;
}


.slot-list { 
    display: flex; 
    flex-wrap: wrap; 
    gap: 6px; 
    margin-top: 4px;
}

.slot-pill { 
    background: #f3f4f6; 
    border: 1px solid #e5e7eb; 
    padding: 4px 8px; 
    border-radius: 6px; 
    font-size: 11px; 
    font-weight: 500; 
    color: #374151; 
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.slot-pill i { color: #6b7280; font-size: 10px; }

/* TIMETABLE GRID */
.grid { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
.grid th, .grid td { border: 1px solid var(--border); padding: 8px; text-align: center; }
.grid th { background: var(--bg-light); color: var(--text-secondary); position: sticky; top:0; z-index: 10; }
.grid td.date-col { background: var(--bg-light); font-weight: 600; position: sticky; left:0; z-index: 5; text-align: left;}

.slot { height: 50px; position: relative; transition: all 0.2s; }
.slot.available { background: #f9fafb; }
.slot.booked { background: #fee2e2; color: #991b1b; font-weight: 600; font-size: 10px; }
.slot.maintenance { background: #ffedd5; color: #9a3412; font-weight: 600; }
.slot.recurring { background: #e0e7ff; border-left: 3px solid #6366f1; color: #4338ca; }
.slot.highlight-request { 
    background: #dbeafe; 
    border: 2.5px dashed #2563eb; 
    color: #1e40af; 
    font-weight: 700; 
    animation: pulse 2s infinite; 
    z-index: 2;
}
@keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }

/* DETAILS PANEL */
.booking-details-panel {
    background: var(--bg-light);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    margin-top: 20px;
}
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 12px; }
.detail-item strong { display: block; font-size: 11px; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 4px; }
.detail-item span { font-size: 14px; color: var(--text-primary); font-weight: 500; }
.detail-desc { grid-column: 1 / -1; margin-top: 8px; }

/* --- PAGINATION STYLES --- */
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 24px;
    padding-bottom: 10px;
}
.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: white;
    color: var(--text-primary);
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.page-btn:hover:not(:disabled) {
    border-color: var(--utm-maroon);
    color: var(--utm-maroon);
    background: #fff5f5;
}
.page-btn.active {
    background: var(--utm-maroon);
    color: white;
    border-color: var(--utm-maroon);
}
.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: var(--bg-light);
}

/* --- TOAST NOTIFICATIONS --- */
#toast-container {
    position: fixed; top: 24px; right: 24px; z-index: 9999;
    display: flex; flex-direction: column; gap: 10px;
}
.toast-msg {
    min-width: 300px; background: white; padding: 16px; border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px;
    border-left: 4px solid #3b82f6; animation: slideIn 0.3s ease-out;
}
.toast-msg.success { border-left-color: #10b981; }
.toast-msg.error { border-left-color: #ef4444; }
.toast-icon { font-size: 18px; font-weight: bold; }
.toast-content { font-size: 14px; font-weight: 500; color: #1f2937; flex: 1; }

@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }

@media (max-width: 1024px) {
  .sidebar { display: none; }
  .layout { margin-left: 0; }
}
</style>
</head>
<body>

<nav class="nav-bar">
    <div class="nav-left">
        <img class="nav-logo" src="../assets/images/utmlogo.png" alt="UTM Logo">
        <div class="nav-title">
            <h1>Room Booking System</h1>
            <p>Admin Control Panel</p>
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
                    <?php if ($isTechAdmin && isset($tech_pending) && $tech_pending > 0): ?>
                        <span class="nav-badge"><?php echo $tech_pending; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <?php if (!$isTechAdmin): ?>
            <li>
                <a href="reservation_request.php" class="active">
                    <i class="fa-solid fa-inbox"></i> Requests
                    <?php if (isset($pending_approvals) && $pending_approvals > 0): ?>
                        <span class="nav-badge"><?php echo $pending_approvals; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

            <li><a href="admin_timetable.php"><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
            
            <?php if (!$isTechAdmin): ?>
            <li><a href="admin_recurring.php"><i class="fa-solid fa-rotate"></i> Recurring</a></li>
            <li><a href="admin_logbook.php"><i class="fa-solid fa-book"></i> Logbook</a></li>
            <?php endif; ?>

            <li><a href="generate_reports.php"><i class="fa-solid fa-chart-pie"></i> Reports</a></li>
            
            <li>
                <a href="admin_problems.php">
                    <i class="fa-solid fa-triangle-exclamation"></i> Problems
                    <?php if ($isTechAdmin && isset($tech_pending) && $tech_pending > 0): ?>
                        <span class="nav-badge"><?php echo $tech_pending; ?></span>
                    <?php elseif (!$isTechAdmin && isset($active_problems) && $active_problems > 0): ?>
                        <span class="nav-badge"><?php echo $active_problems; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
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
                <h2>Reservation Requests</h2>
                <p>Manage incoming booking approvals and schedule conflicts.</p>
            </div>
        </div>

        <div class="card">
            <div class="top-controls">
                <div class="tabs">
                    <?php foreach ($allowed_filters as $f): ?>
                    <a href="?filter=<?php echo $f; ?>&search_room=<?php echo urlencode($search_room); ?>" 
                       class="tab <?php echo ($filter==$f)?'active':''; ?>">
                       <?php echo ucfirst($f); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <form method="GET" class="search-form">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <select name="search_room" class="room-select">
                        <option value="">All Rooms</option>
                        <?php foreach($rooms as $room): ?>
                            <option value="<?php echo htmlspecialchars($room['room_id']); ?>" <?php echo ($search_room == $room['room_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($room['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
            </div>

            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th style="width:100px;">Ticket</th>
                            <th style="width:180px;">Room</th>
                            <th style="width:200px;">User</th>
                            <th>Purpose & Time</th>
                            <th style="width:120px;">Status</th>
                            <th style="width:140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo htmlspecialchars($row['ticket'] ?? $row['session_id']); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['room_name']); ?></strong>
                                        <span class="user-subtext"><?php echo htmlspecialchars($row['room_no']); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['user_fullname'] ?: $row['requested_by']); ?></strong>
                                        <span class="user-subtext"><?php echo htmlspecialchars($row['user_email']); ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight:600; color:var(--utm-maroon); margin-bottom:4px;"><?php echo htmlspecialchars($row['purpose']); ?></div>
                                        <div style="font-size:12px; color:var(--text-secondary); margin-bottom:4px;">
                                            <i class="fa-regular fa-calendar"></i> <?php echo htmlspecialchars($row['slot_date']); ?>
                                        </div>
                                        <div class="slot-list">
                                            <?php 
                                                // Explode the string "08:00-08:50, 09:00-09:50" into an array
                                                $slots = explode(',', $row['time_slots']);
                                                foreach($slots as $slot): 
                                            ?>
                                                <span class="slot-pill">
                                                    <i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars(trim($slot)); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status <?php echo strtolower($row['status']); ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn-icon view" title="View Schedule & Details"
                                                data-room-id="<?php echo htmlspecialchars($row['room_no']); ?>"
                                                data-room-name="<?php echo htmlspecialchars($row['room_name']); ?>"
                                                data-date="<?php echo htmlspecialchars($row['slot_date']); ?>"
                                                data-slots="<?php echo htmlspecialchars($row['time_slots']); ?>"
                                                data-user="<?php echo htmlspecialchars($row['user_fullname'] ?: $row['requested_by']); ?>"
                                                data-email="<?php echo htmlspecialchars($row['user_email']); ?>"
                                                data-purpose="<?php echo htmlspecialchars($row['purpose']); ?>"
                                                data-desc="<?php echo htmlspecialchars($row['description']); ?>"
                                                data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                                onclick="openTimetable(this)">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>

                                            <?php if($row['status'] == 'pending'): ?>
                                                <button class="btn-icon approve" title="Approve" onclick="openApprove('<?php echo htmlspecialchars($row['session_id']); ?>')">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                                <button class="btn-icon reject" title="Reject" onclick="openReject('<?php echo htmlspecialchars($row['session_id']); ?>')">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="loading-cell">
                                    <i class="fa-solid fa-inbox" style="font-size:24px; margin-bottom:10px; display:block; opacity:0.5;"></i> 
                                    No <?php echo $filter !== 'all' ? $filter : ''; ?> requests found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="pagination" class="pagination-container"></div>
        </div>
    </main>
</div>

<div id="timetableModal" class="modal">
    <div class="modal-card large">
        <div class="modal-header">
            <h3 id="ttModalTitle">Room Schedule</h3>
            <button class="btn-close" onclick="closeModal('timetableModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display:flex; gap:12px; margin-bottom:15px; font-size:11px; flex-wrap:wrap;">
                <span style="display:flex;align-items:center;gap:4px;"><span style="width:15px;height:15px;background:#dbeafe;border:1px dashed #2563eb;"></span> Current Request</span>
                <span style="display:flex;align-items:center;gap:4px;"><span style="width:15px;height:15px;background:#fee2e2;"></span> Booked</span>
                <span style="display:flex;align-items:center;gap:4px;"><span style="width:15px;height:15px;background:#ef4444;border:1px solid #991b1b;"></span> Conflict</span>
                <span style="display:flex;align-items:center;gap:4px;"><span style="width:15px;height:15px;background:#e0e7ff;border-left:2px solid #6366f1;"></span> Recurring</span>
            </div>
            
            <div id="timetableContainer" style="overflow-x:auto;">Loading...</div>

            <div class="booking-details-panel">
                <h4 style="font-size:14px; font-weight:700; color:var(--utm-maroon); border-bottom:1px solid var(--border); padding-bottom:8px; margin-bottom:8px;">
                    <i class="fa-solid fa-circle-info"></i> Booking Request Details
                </h4>
                <div class="detail-grid">
                    <div class="detail-item"><strong>Requested By</strong> <span id="detailUser">-</span></div>
                    <div class="detail-item"><strong>Contact Email</strong> <span id="detailEmail">-</span></div>
                    <div class="detail-item"><strong>Purpose</strong> <span id="detailPurpose">-</span></div>
                    <div class="detail-item"><strong>Date & Time</strong> <span id="detailDateTime">-</span></div>
                    <div class="detail-item detail-desc"><strong>Description</strong> <span id="detailDesc">-</span></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('timetableModal')">Close</button>
        </div>
    </div>
</div>

<div id="approveModal" class="modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Approve Request</h3>
            <button class="btn-close" onclick="closeModal('approveModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to approve this booking? This will occupy the slots on the timetable.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('approveModal')">Cancel</button>
            <button id="approveConfirm" class="btn btn-primary">Yes, Approve</button>
        </div>
    </div>
</div>

<div id="rejectModal" class="modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Reject Request</h3>
            <button class="btn-close" onclick="closeModal('rejectModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p>Please enter the reason for rejection (this will be sent to the user):</p>
            <textarea id="rejectReason" class="modal-textarea" placeholder="e.g. Room under maintenance, Schedule conflict..."></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('rejectModal')">Cancel</button>
            <button id="rejectConfirm" class="btn btn-primary" style="background:var(--danger);">Reject Booking</button>
        </div>
    </div>
</div>

<script>
// ... (Scripts remain unchanged) ...
const TIME_SLOTS = [
    "08:00-08:50", "09:00-09:50", "10:00-10:50", "11:00-11:50", 
    "12:00-12:50", "13:00-13:50", "14:00-14:50", "15:00-15:50", 
    "16:00-16:50", "17:00-17:50", "18:00-18:50", "19:00-19:50", 
    "20:00-20:50", "21:00-21:50", "22:00-22:50", "23:00-23:50"
];

let currentSession = null;

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
}

async function openTimetable(btn) {
    // ... (Keep existing timetable logic) ...
    // 1. Get Data Attributes
    const d = btn.dataset;
    const roomId = d.roomId;
    const roomName = d.roomName;
    const reqDate = d.date; 
    const reqSlotsStr = d.slots; 
    
    // 2. Populate Details Panel
    document.getElementById('ttModalTitle').textContent = `Schedule: ${roomName}`;
    document.getElementById('detailUser').textContent = d.user;
    document.getElementById('detailEmail').textContent = d.email;
    document.getElementById('detailPurpose').textContent = d.purpose;
    document.getElementById('detailDateTime').textContent = `${d.date} (${d.slots})`;
    document.getElementById('detailDesc').textContent = d.desc || 'No description provided.';
    
    // 3. Prepare Highlight Logic
    const reqSlots = [];
    if(reqSlotsStr) {
        reqSlotsStr.split(',').forEach(s => {
            const p = s.trim().split('-');
            if(p.length===2) reqSlots.push({ start: p[0], end: p[1] });
        });
    }

    // 4. Calculate Date Range (Monday - Sunday)
    const dateObj = new Date(reqDate + 'T12:00:00');
    const day = dateObj.getDay(); 
    const monday = new Date(dateObj);
    monday.setDate(dateObj.getDate() - (day === 0 ? 6 : day - 1));
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    
    const startISO = monday.toISOString().split('T')[0];
    const endISO = sunday.toISOString().split('T')[0];

    // 5. Open Modal & Show Loading
    const modal = document.getElementById('timetableModal');
    const container = document.getElementById('timetableContainer');
    modal.classList.add('show');
    container.innerHTML = '<div style="padding:40px; text-align:center;">Loading schedule...</div>';

    try {
        // 6. Fetch Data
        const res = await fetch(`admin_timetable.php?endpoint=bookings&room=${encodeURIComponent(roomId)}&start=${startISO}&end=${endISO}`);
        const data = await res.json();
        
        if (!data.success) throw new Error(data.msg || "Failed to load");

        // 7. Render Table
        let html = '<table class="grid"><thead><tr><th style="min-width:100px;">Date</th>';
        TIME_SLOTS.forEach(ts => html += `<th>${ts}</th>`);
        html += '</tr></thead><tbody>';

        const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

        for(let i=0; i<7; i++){
            const curr = new Date(monday);
            curr.setDate(monday.getDate() + i);
            const iso = curr.toISOString().split('T')[0];
            const dName = days[curr.getDay()];

            html += `<tr><td class="date-col">${dName}<br><span style="font-weight:400;color:#888;">${iso}</span></td>`;

            TIME_SLOTS.forEach(ts => {
                const [sStart, sEnd] = ts.split('-');
                let cls = 'available';
                let content = '';
                
                // A. Check if this is the Request being viewed (Highlight it)
                let isCurrentRequest = false;
                if(iso === reqDate) {
                    for(let r of reqSlots) {
                        if(sStart >= r.start && sStart < r.end) {
                            cls = 'highlight-request';
                            content = 'CURRENT VIEW';
                            isCurrentRequest = true;
                        }
                    }
                }

                // B. Check Existing Bookings
                for(let b of data.bookings) {
                    if(b.slot_date !== iso) continue;
                    
                    const bStatus = (b.status || '').toLowerCase().trim();
                    if(['cancelled','rejected','deleted'].includes(bStatus)) continue;
                    
                    const bStart = b.time_start.substring(0,5);
                    const bEnd = b.time_end.substring(0,5);

                    if(sStart >= bStart && sStart < bEnd) {
                        // --- REMOVED CONFLICT CHECK HERE ---
                        // Only show "Booked" if it is NOT currently highlighted as our request.
                        // If it IS our request (isCurrentRequest = true), we ignore the booking underneath visually.
                        if (!isCurrentRequest) {
                            cls = bStatus || 'booked';
                            if(b.recurring) cls = 'recurring';
                            content = escapeHtml(b.purpose || 'Occupied');
                        }
                    }
                }
                
                html += `<td class="slot ${cls}"><div class="cell-content">${content}</div></td>`;
            });
            html += '</tr>';
        }
        html += '</tbody></table>';
        container.innerHTML = html;

    } catch(e) {
        container.innerHTML = `<div style="color:red; text-align:center;">Error: ${e.message}</div>`;
    }
}

function openApprove(id) {
    currentSession = id;
    document.getElementById('approveModal').classList.add('show');
}

function openReject(id) {
    currentSession = id;
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModal').classList.add('show');
}

function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast-msg ${type}`;
    
    const icons = { success: '<i class="fa-solid fa-check-circle" style="color:#10b981"></i>', error: '<i class="fa-solid fa-circle-exclamation" style="color:#ef4444"></i>' };
    const icon = icons[type] || '<i class="fa-solid fa-info-circle"></i>';

    toast.innerHTML = `
        <span class="toast-icon">${icon}</span>
        <span class="toast-content">${message}</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

async function postAction(fd) {
    try {
        const r = await fetch('../api/process_request.php', { method:'POST', body:fd });
        return await r.json();
    } catch(e) { return {success:false, message:e.message}; }
}

document.getElementById('approveConfirm').onclick = async function() {
    if(!currentSession) return;
    const btn = this;
    const originalText = btn.innerText;
    btn.innerText = 'Processing...'; 
    btn.disabled = true;
    
    const fd = new FormData();
    fd.append('action', 'approve');
    fd.append('session_id', currentSession);
    
    const res = await postAction(fd);
    
    if(res.success) {
        closeModal('approveModal'); 
        showToast('Request Approved Successfully!', 'success'); 
        setTimeout(() => location.reload(), 1500); 
    } else {
        showToast(res.message || 'Failed to approve', 'error');
        btn.innerText = originalText; 
        btn.disabled = false; 
    }
};

document.getElementById('rejectConfirm').onclick = async function() {
    if(!currentSession) return;
    const reason = document.getElementById('rejectReason').value;
    
    if(!reason) {
        showToast('Please provide a rejection reason', 'error');
        return;
    }
    
    const btn = this;
    const originalText = btn.innerText;
    btn.innerText = 'Processing...'; 
    btn.disabled = true;
    
    const fd = new FormData();
    fd.append('action', 'reject');
    fd.append('session_id', currentSession);
    fd.append('reason', reason);
    
    const res = await postAction(fd);
    
    if(res.success) {
        closeModal('rejectModal'); 
        showToast('Request Rejected Successfully', 'success'); 
        setTimeout(() => location.reload(), 1500); 
    } else {
        showToast(res.message || 'Failed to reject', 'error');
        btn.innerText = originalText; 
        btn.disabled = false; 
    }
};

// ... (Pagination Logic remains unchanged) ...
document.addEventListener('DOMContentLoaded', function() {
    const table = document.querySelector('.list-table tbody');
    const rows = Array.from(table.querySelectorAll('tr'));
    const paginationContainer = document.getElementById('pagination');
    const rowsPerPage = 10; 
    let currentPage = 1;
    
    if (rows.length === 0 || rows[0].querySelector('.loading-cell') || rows[0].querySelector('.empty-state')) {
        paginationContainer.style.display = 'none';
        return;
    }

    function displayRows(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        rows.forEach((row, index) => {
            if (index >= start && index < end) {
                row.style.display = ''; 
            } else {
                row.style.display = 'none'; 
            }
        });
    }

    function setupPagination() {
        const pageCount = Math.ceil(rows.length / rowsPerPage);
        paginationContainer.innerHTML = '';
        if (pageCount <= 1) return; 

        const prevBtn = document.createElement('button');
        prevBtn.className = 'page-btn';
        prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
        prevBtn.disabled = currentPage === 1;
        prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; updatePagination(); } };
        paginationContainer.appendChild(prevBtn);

        for (let i = 1; i <= pageCount; i++) {
            const btn = document.createElement('button');
            btn.className = `page-btn ${i === currentPage ? 'active' : ''}`;
            btn.innerText = i;
            btn.onclick = () => { currentPage = i; updatePagination(); };
            paginationContainer.appendChild(btn);
        }

        const nextBtn = document.createElement('button');
        nextBtn.className = 'page-btn';
        nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
        nextBtn.disabled = currentPage === pageCount;
        nextBtn.onclick = () => { if (currentPage < pageCount) { currentPage++; updatePagination(); } };
        paginationContainer.appendChild(nextBtn);
    }

    function updatePagination() {
        displayRows(currentPage);
        setupPagination(); 
    }
    updatePagination();
});
</script>
</body>
</html>