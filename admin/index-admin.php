<?php
// index-admin.php - Dashboard with "Problem ID" Grouping
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// --- 1. ACCESS CONTROL ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || 
    !isset($_SESSION['User_Type']) || 
    (strcasecmp(trim($_SESSION['User_Type']), 'Admin') != 0 && 
     strcasecmp(trim($_SESSION['User_Type']), 'Technical Admin') != 0 && 
     strcasecmp(trim($_SESSION['User_Type']), 'SuperAdmin') != 0)) {
    
    header("Location: loginterface.html");
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin';
$admin_email = $_SESSION['Email'] ?? ($_SESSION['User_Type'] ?? 'Admin');
$username = $_SESSION['username'] ?? '';
$userType = $_SESSION['User_Type'] ?? '';

// Determine Roles
$isSuperAdmin = (strtolower($username) === 'superadmin' || $userType === 'SuperAdmin');
$isTechAdmin  = (strcasecmp($userType, 'Technical Admin') === 0);

// --- 2. DATA FETCHING ---

// Initialize variables
$total_rooms = 0;
$active_bookings_today = 0;
$pending_approvals = 0;
$bookings_this_week = 0;
$active_problems = 0; 
$recent_requests = [];
$popular_room = ['name' => 'N/A', 'count' => 0];
$recent_cancellations = [];
$upcoming_bookings = [];

// --- TECHNICAL ADMIN DATA ---
$tech_pending = 0;
$tech_completed = 0;
$tech_tasks = [];

if ($isTechAdmin) {
    // 1. Count Pending Repairs (Grouped by Problem ID)
    // We use linked_problem_id to count 1 Ticket as 1 Job, even if it has 5 slots.
    // If linked_problem_id is missing (manual block), we fallback to session_id.
    $sql = "SELECT COUNT(DISTINCT CASE WHEN linked_problem_id > 0 THEN linked_problem_id ELSE session_id END) 
            FROM bookings 
            WHERE tech_token IS NOT NULL 
            AND tech_status != 'Work Done'";
    $result = $conn->query($sql);
    if($result) { $row = $result->fetch_row(); $tech_pending = intval($row[0]); }

    // 2. Count Completed Jobs (Grouped by Problem ID)
    $sql = "SELECT COUNT(DISTINCT CASE WHEN linked_problem_id > 0 THEN linked_problem_id ELSE session_id END) 
            FROM bookings 
            WHERE tech_token IS NOT NULL 
            AND tech_status = 'Work Done'";
    $result = $conn->query($sql);
    if($result) { $row = $result->fetch_row(); $tech_completed = intval($row[0]); }

    // 3. Latest Maintenance Tasks Feed (Merged by Problem ID)
    // This query merges duplicate rows (like 290, 291) into one single row per Ticket.
    $sql = "SELECT 
                CASE WHEN b.linked_problem_id > 0 THEN b.linked_problem_id ELSE b.session_id END as unique_group,
                MAX(b.id) as id,
                MAX(r.name) AS room_name, 
                MAX(r.room_id) AS room_no, 
                MAX(b.purpose) as purpose, 
                MAX(b.description) as description, 
                MAX(b.slot_date) as slot_date, 
                MAX(b.tech_status) as tech_status, 
                MAX(b.technician) as technician 
            FROM bookings b 
            JOIN rooms r ON b.room_id = r.room_id 
            WHERE b.tech_token IS NOT NULL
            GROUP BY unique_group
            ORDER BY MAX(b.created_at) DESC 
            LIMIT 10";
    $res = $conn->query($sql);
    if($res) { while ($row = $res->fetch_assoc()) $tech_tasks[] = $row; }

} 
// --- NORMAL ADMIN DATA ---
else {
    // 1. Total rooms
    if ($r = $conn->query("SELECT COUNT(*) AS c FROM rooms")) {
        $row = $r->fetch_assoc();
        $total_rooms = intval($row['c'] ?? 0);
    }

    // 2. Active bookings today
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT session_id) FROM bookings WHERE slot_date = CURDATE() AND status = 'booked'");
    if ($stmt) { $stmt->execute(); $stmt->bind_result($cnt1); $stmt->fetch(); $active_bookings_today = intval($cnt1); $stmt->close(); }

    // 3. Pending approvals (Count Sessions)
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT session_id) FROM bookings WHERE status = 'pending'");
    if ($stmt) { $stmt->execute(); $stmt->bind_result($cnt2); $stmt->fetch(); $pending_approvals = intval($cnt2); $stmt->close(); }

    // 4. Bookings this week
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT session_id) FROM bookings WHERE YEARWEEK(slot_date, 1) = YEARWEEK(CURDATE(), 1)");
    if ($stmt) { $stmt->execute(); $stmt->bind_result($cnt_week); $stmt->fetch(); $bookings_this_week = intval($cnt_week); $stmt->close(); }

    // 5. Most popular room
    $stmt = $conn->prepare("SELECT r.name, COUNT(DISTINCT b.session_id) as booking_count FROM bookings b JOIN rooms r ON b.room_id = r.room_id WHERE YEARWEEK(b.slot_date, 1) = YEARWEEK(CURDATE(), 1) GROUP BY r.room_id ORDER BY booking_count DESC LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) { $popular_room = ['name' => $row['name'], 'count' => intval($row['booking_count'])]; }
        $stmt->close();
    }

    // 6. Recent requests
    $sql = "SELECT b.session_id, MAX(b.id) as id, MAX(r.name) AS room_name, MAX(r.room_id) AS room_no, MAX(u.username) AS requester, MAX(b.slot_date) as slot_date, MIN(b.time_start) as time_start, MAX(b.time_end) as time_end, MAX(b.status) as status, MAX(b.purpose) as purpose 
            FROM bookings b JOIN rooms r ON b.room_id = r.room_id JOIN users u ON b.user_id = u.id 
            GROUP BY b.session_id ORDER BY MAX(b.created_at) DESC LIMIT 6";
    $res = $conn->query($sql);
    if($res) { while ($row = $res->fetch_assoc()) $recent_requests[] = $row; }
    
    // 7. Recent Cancellations
    $sql = "SELECT MAX(r.name) AS room_name, MAX(u.username) as username, MAX(b.slot_date) as slot_date 
            FROM bookings b JOIN rooms r ON b.room_id = r.room_id JOIN users u ON b.user_id = u.id 
            WHERE b.status = 'cancelled' GROUP BY b.session_id ORDER BY MAX(b.updated_at) DESC LIMIT 3";
    $res = $conn->query($sql);
    if($res) { while ($row = $res->fetch_assoc()) $recent_cancellations[] = $row; }
    
    // 8. Upcoming
    $sql = "SELECT MAX(r.name) AS room_name, MAX(u.username) as username, MAX(b.slot_date) as slot_date, MIN(b.time_start) as time_start 
            FROM bookings b JOIN rooms r ON b.room_id = r.room_id JOIN users u ON b.user_id = u.id 
            WHERE b.slot_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND b.status = 'booked' 
            GROUP BY b.session_id ORDER BY slot_date ASC, time_start ASC LIMIT 3";
    $res = $conn->query($sql);
    if($res) { while ($row = $res->fetch_assoc()) $upcoming_bookings[] = $row; }

    // 9. Active Problems Badge
    $res_prob = $conn->query("SELECT COUNT(*) FROM room_problems WHERE status != 'Resolved'");
    if ($res_prob) { $row_prob = $res_prob->fetch_row(); $active_problems = intval($row_prob[0]); }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard - UTM Admin</title>
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
  --info: #0284c7;
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --nav-height: 70px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: var(--bg-light); color: var(--text-primary); }

.nav-bar { position: fixed; top: 0; left: 0; right: 0; height: var(--nav-height); background: white; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; box-shadow: var(--shadow-sm); z-index: 1000; border-bottom: 1px solid var(--border); }
.nav-left { display: flex; align-items: center; gap: 16px; }
.nav-logo { height: 50px; }
.nav-title h1 { font-size: 16px; font-weight: 700; color: var(--utm-maroon); margin: 0; }
.nav-title p { font-size: 11px; color: var(--text-secondary); margin: 0; }
.btn-logout { text-decoration: none; color: var(--text-secondary); font-size: 13px; font-weight: 500; padding: 8px 12px; border-radius: 6px; transition: 0.2s; }
.btn-logout:hover { background: #fef2f2; color: var(--utm-maroon); }

.layout { display: flex; margin-top: var(--nav-height); min-height: calc(100vh - var(--nav-height)); }
.sidebar { width: 260px; background: white; border-right: 1px solid var(--border); padding: 24px; flex-shrink: 0; position: sticky; top: var(--nav-height); height: calc(100vh - var(--nav-height)); display: flex; flex-direction: column; }
.sidebar-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px; margin-bottom: 16px; }
.sidebar-menu { list-style: none; flex: 1; padding: 0; }
.sidebar-menu li { margin-bottom: 4px; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 6px; text-decoration: none; color: var(--text-primary); font-size: 14px; font-weight: 500; transition: all 0.2s; }
.sidebar-menu a:hover { background: var(--bg-light); color: var(--utm-maroon); }
.sidebar-menu a.active { background: #fef2f2; color: var(--utm-maroon); font-weight: 600; }
.sidebar-menu a i { width: 20px; text-align: center; }

.nav-badge { background-color: var(--danger); color: white; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 99px; margin-left: auto; }

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

.main-content { flex: 1; padding: 32px; min-width: 0; }
.welcome-banner { background: linear-gradient(135deg, var(--utm-maroon) 0%, #a31313 100%); color: white; padding: 24px 32px; border-radius: 12px; margin-bottom: 32px; box-shadow: 0 10px 15px -3px rgba(128, 0, 0, 0.2); }
.welcome-banner h2 { font-size: 24px; font-weight: 700; margin-bottom: 6px; }
.welcome-banner p { opacity: 0.9; font-size: 14px; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 32px; }
.stat-card { background: white; border-radius: 12px; padding: 20px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between; }
.stat-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.stat-icon.blue { background: #dbeafe; color: #1e40af; }
.stat-icon.green { background: #dcfce7; color: #166534; }
.stat-icon.orange { background: #ffedd5; color: #9a3412; }
.stat-value { font-size: 28px; font-weight: 800; color: var(--text-primary); line-height: 1; }
.stat-label { font-size: 13px; color: var(--text-secondary); font-weight: 500; }

.dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
.dashboard-grid.full { grid-template-columns: 1fr; }

.card { background: white; border-radius: 12px; box-shadow: var(--shadow-sm); border: 1px solid var(--border); padding: 0; overflow: hidden; height: 100%; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.card-title { font-size: 16px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
.card-action { font-size: 13px; color: var(--utm-maroon); text-decoration: none; font-weight: 600; }
.card-action:hover { text-decoration: underline; }

.activity-list { padding: 0; }
.activity-item { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; gap: 12px; align-items: flex-start; }
.activity-item:last-child { border-bottom: none; }
.act-icon { font-size: 14px; margin-top: 3px; }
.act-content strong { display: block; font-size: 13px; color: var(--text-primary); margin-bottom: 2px; }
.act-content p { font-size: 12px; color: var(--text-secondary); margin: 0; }
.act-time { font-size: 11px; color: var(--text-secondary); margin-left: auto; white-space: nowrap; }

.pop-room-card { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 16px; margin: 20px; display: flex; gap: 12px; align-items: center; }
.pop-icon { font-size: 24px; color: #d97706; }

.table-wrap { overflow-x: auto; }
.list-table { width: 100%; border-collapse: collapse; min-width: 600px; }
.list-table th { background: var(--bg-light); text-align: left; padding: 12px 20px; font-size: 11px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
.list-table td { padding: 14px 20px; border-top: 1px solid var(--border); font-size: 13px; vertical-align: middle; color: var(--text-primary); }
.list-table tr:hover { background: #fafafa; }

.status { display: inline-flex; padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
.status.pending { background: #fef3c7; color: #92400e; }
.status.booked, .status.approved { background: #dcfce7; color: #166534; }
.status.rejected, .status.cancelled { background: #fee2e2; color: #991b1b; }
.status.work.done { background: #d1fae5; color: #047857; }

@media (max-width: 1024px) { .dashboard-grid { grid-template-columns: 1fr; } .sidebar { display: none; } .layout { margin-left: 0; } }
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
                <a href="index-admin.php" class="active">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard
                </a>
            </li>
            
            <?php if (!$isTechAdmin): ?>
            <li>
                <a href="reservation_request.php">
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
        <div class="welcome-banner">
            <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?>!</h2>
            <p>
                <?php if($isTechAdmin): ?>
                    System Maintenance Overview. You have <strong><?php echo $tech_pending; ?></strong> outstanding repairs system-wide.
                <?php else: ?>
                    Here is an overview of the room booking activities today.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($isTechAdmin): ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Pending Repairs</span>
                        <div class="stat-icon orange"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                    </div>
                    <div class="stat-value"><?php echo number_format($tech_pending); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Solved Issues</span>
                        <div class="stat-icon green"><i class="fa-solid fa-check-double"></i></div>
                    </div>
                    <div class="stat-value"><?php echo number_format($tech_completed); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Total Maintenance</span>
                        <div class="stat-icon blue"><i class="fa-solid fa-clipboard-list"></i></div>
                    </div>
                    <div class="stat-value"><?php echo number_format($tech_pending + $tech_completed); ?></div>
                </div>
            </div>

            <div class="dashboard-grid full">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-helmet-safety"></i> Latest Maintenance Tasks</div>
                    </div>
                    <div class="table-wrap">
                        <table class="list-table">
                            <thead>
                                <tr>
                                    <th>#ID</th>
                                    <th>Room</th>
                                    <th>Task / Issue</th>
                                    <th>Assigned To</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tech_tasks) > 0): ?>
                                    <?php foreach ($tech_tasks as $task): ?>
                                    <tr>
                                        <td><strong>#<?php echo $task['id']; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($task['room_name']); ?></strong>
                                            <div style="font-size:11px; color:#6b7280;"><?php echo htmlspecialchars($task['room_no']); ?></div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($task['purpose']); ?></strong>
                                            <p style="margin:0; font-size:12px; color:#6b7280;"><?php echo htmlspecialchars(substr($task['description'], 0, 50)); ?>...</p>
                                        </td>
                                        <td><?php echo htmlspecialchars($task['technician']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($task['slot_date'])); ?></td>
                                        <td>
                                            <?php if($task['tech_status'] == 'Work Done'): ?>
                                                <span class="status work done">Solved</span>
                                            <?php else: ?>
                                                <span class="status pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#6b7280;">No maintenance tasks found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Total Rooms</span>
                        <div class="stat-icon blue"><i class="fa-solid fa-door-open"></i></div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_rooms); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Active Bookings (Today)</span>
                        <div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div>
                    </div>
                    <div class="stat-value"><?php echo number_format($active_bookings_today); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Pending Requests</span>
                        <div class="stat-icon orange"><i class="fa-solid fa-clock"></i></div>
                    </div>
                    <div class="stat-value"><?php echo number_format($pending_approvals); ?></div>
                </div>
                
                 <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Total Bookings (Week)</span>
                        <div class="stat-icon blue"><i class="fa-solid fa-chart-line"></i></div>
                    </div>
                    <div class="stat-value"><?php echo number_format($bookings_this_week); ?></div>
                </div>
            </div>

            <div class="dashboard-grid">
                
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-list-check"></i> Recent Requests</div>
                        <a href="reservation_request.php" class="card-action">View All</a>
                    </div>
                    <div class="table-wrap">
                        <table class="list-table">
                            <thead>
                                <tr>
                                    <th>#ID</th>
                                    <th>Room</th>
                                    <th>Requester</th>
                                    <th>Date / Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_requests) > 0): ?>
                                    <?php foreach ($recent_requests as $rq): ?>
                                    <tr>
                                        <td><strong>#<?php echo $rq['id']; ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($rq['room_name']); ?></strong>
                                            <div style="font-size:11px; color:#6b7280;"><?php echo htmlspecialchars($rq['room_no']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($rq['requester']); ?></td>
                                        <td>
                                            <div><?php echo date('M d, Y', strtotime($rq['slot_date'])); ?></div>
                                            <div style="font-size:11px; color:#6b7280;">
                                                <?php echo substr($rq['time_start'],0,5) . ' - ' . substr($rq['time_end'],0,5); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status <?php echo strtolower($rq['status']); ?>">
                                                <?php echo ucfirst($rq['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#6b7280;">No recent requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-bolt"></i> Activity Feed</div>
                    </div>
                    
                    <div class="activity-list">
                        <?php if ($popular_room['count'] > 0): ?>
                        <div class="pop-room-card">
                            <div class="pop-icon"><i class="fa-solid fa-fire"></i></div>
                            <div>
                                <div style="font-size:11px; text-transform:uppercase; color:#92400e; font-weight:700;">Most Popular</div>
                                <div style="font-weight:700; color:#1f2937;"><?php echo htmlspecialchars($popular_room['name']); ?></div>
                                <div style="font-size:12px; color:#6b7280;"><?php echo $popular_room['count']; ?> bookings this week</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div style="padding: 12px 20px 4px; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase;">Upcoming 7 Days</div>
                        
                        <?php if(count($upcoming_bookings) > 0): ?>
                            <?php foreach($upcoming_bookings as $up): ?>
                            <div class="activity-item">
                                <div class="act-icon" style="color:#16a34a;"><i class="fa-solid fa-circle-check"></i></div>
                                <div class="act-content">
                                    <strong><?php echo htmlspecialchars($up['room_name']); ?></strong>
                                    <p><?php echo htmlspecialchars($up['username']); ?></p>
                                </div>
                                <div class="act-time">
                                    <?php echo date('M d', strtotime($up['slot_date'])); ?><br>
                                    <?php echo substr($up['time_start'],0,5); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="activity-item" style="color:#6b7280; font-size:13px;">No upcoming bookings.</div>
                        <?php endif; ?>

                        <div style="padding: 20px 20px 4px; font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase;">Recent Cancellations</div>
                        
                        <?php if(count($recent_cancellations) > 0): ?>
                            <?php foreach($recent_cancellations as $rc): ?>
                            <div class="activity-item">
                                <div class="act-icon" style="color:#dc2626;"><i class="fa-solid fa-circle-xmark"></i></div>
                                <div class="act-content">
                                    <strong><?php echo htmlspecialchars($rc['room_name']); ?></strong>
                                    <p>Cancelled by <?php echo htmlspecialchars($rc['username']); ?></p>
                                </div>
                                <div class="act-time"><?php echo date('M d', strtotime($rc['slot_date'])); ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="activity-item" style="color:#6b7280; font-size:13px;">No recent cancellations.</div>
                        <?php endif; ?>
                    </div>
                </div>

            </div> 
        <?php endif; ?> </main>
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

</body>
</html>