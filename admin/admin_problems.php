<?php
/**
 * admin_problems.php - Fixed Query to prevent "Unknown Column" error
 */
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// --- 1. ACCESS CONTROL ---
$uType = trim($_SESSION['User_Type'] ?? '');
$admin_id = $_SESSION['User_ID'] ?? $_SESSION['id'] ?? null;

$allowed = (
    strcasecmp($uType, 'Admin') === 0 || 
    strcasecmp($uType, 'Technical Admin') === 0 || 
    strcasecmp($uType, 'SuperAdmin') === 0
);

if (!$admin_id || !$allowed) {
    header('Location: loginterface.html');
    exit;
}

// User Details
$admin_name = $_SESSION['Fullname'] ?? 'Admin'; 
$admin_email = $_SESSION['Email'] ?? ($_SESSION['User_Type'] ?? 'Admin');
$username = $_SESSION['username'] ?? '';
$isTechAdmin  = (strcasecmp($uType, 'Technical Admin') === 0);
$isSuperAdmin = (strcasecmp($uType, 'SuperAdmin') === 0 || strtolower($username) === 'superadmin');

// --- NOTIFICATION COUNTERS ---
$tech_pending = 0;
$pending_approvals = 0;
$active_problems = 0;

if ($isTechAdmin) {
    // Tech Admin: Count Pending Repair SESSIONS
    $sql = "SELECT COUNT(DISTINCT COALESCE(linked_problem_id, session_id)) FROM bookings WHERE tech_token IS NOT NULL AND tech_status != 'Work Done'";
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

// --- 2. HANDLE FORM SUBMISSIONS ---

// Create Ticket
if (isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    $room_id = $conn->real_escape_string($_POST['room_id']);
    $title = $conn->real_escape_string($_POST['title']);
    $desc = $conn->real_escape_string($_POST['description']);
    $priority = $conn->real_escape_string($_POST['priority']);
    $uid = $admin_id;

    $sql = "INSERT INTO room_problems (user_id, room_id, title, description, status, report_source, priority, created_at) 
            VALUES ('$uid', '$room_id', '$title', '$desc', 'Pending', 'Admin', '$priority', NOW())";
    
    if ($conn->query($sql)) {
        $new_ticket_id = $conn->insert_id;
        header("Location: admin_timetable.php?maintenance=" . $new_ticket_id . "&room=" . urlencode($room_id));
        exit;
    }
}

// Resolve Issue
if(isset($_POST['resolve_id'])) {
    $problem_id = intval($_POST['resolve_id']);
    $conn->query("UPDATE room_problems SET status='Resolved', resolved_at=NOW(), admin_notice=0 WHERE id={$problem_id}");
    $conn->query("DELETE FROM bookings WHERE linked_problem_id={$problem_id}");
    header("Location: admin_problems.php");
    exit;
}

// --- 3. FILTERS & PAGINATION LOGIC ---

$filter_source = $_GET['source'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$filter_room   = $_GET['room'] ?? 'all';
$filter_date   = $_GET['date'] ?? '';

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = [];

if ($filter_source === 'user') $where_clauses[] = "p.report_source = 'User'";
elseif ($filter_source === 'admin') $where_clauses[] = "p.report_source = 'Admin'";

if ($filter_status === 'Pending') $where_clauses[] = "p.status = 'Pending'";
elseif ($filter_status === 'In Progress') $where_clauses[] = "p.status = 'In Progress'";
elseif ($filter_status === 'Resolved') $where_clauses[] = "p.status = 'Resolved'";

if ($filter_room !== 'all' && !empty($filter_room)) {
    $safe_room = $conn->real_escape_string($filter_room);
    $where_clauses[] = "p.room_id = '$safe_room'";
}

if (!empty($filter_date)) {
    $safe_date = $conn->real_escape_string($filter_date);
    $where_clauses[] = "DATE(p.created_at) = '$safe_date'";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

$total_result = $conn->query("SELECT COUNT(*) as total FROM room_problems p $where_sql");
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// FIX: Removed b.notice_msg and b.admin_notice causing the crash.
// p.* covers them if they exist in room_problems.
$problems = $conn->query("
    SELECT p.*, r.name AS room_name, u.username, 
           MAX(b.slot_date) as slot_date, 
           MIN(b.time_start) as time_start, 
           MAX(b.time_end) as time_end, 
           MAX(b.tech_status) as tech_status
    FROM room_problems p
    JOIN rooms r ON p.room_id = r.room_id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN bookings b ON b.linked_problem_id = p.id AND b.status != 'deleted'
    $where_sql
    GROUP BY p.id
    ORDER BY FIELD(p.status, 'In Progress', 'Pending', 'Resolved'), p.created_at DESC
    LIMIT $limit OFFSET $offset
");

function buildUrl($newParams = []) {
    $params = $_GET;
    foreach ($newParams as $k => $v) { $params[$k] = $v; }
    return '?' . http_build_query($params);
}

$room_list = $conn->query("SELECT room_id, name FROM rooms ORDER BY name");
$filter_rooms_list = $conn->query("SELECT room_id, name FROM rooms ORDER BY name");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Room Problems — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root { 
    --utm-maroon: #800000;
    --utm-maroon-light: #a31313;
    --bg-light: #f9fafb;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --border: #e2e8f0;
    --nav-height: 70px;
    --danger: #dc2626;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: var(--bg-light); min-height: 100vh; color: var(--text-primary); }

/* NAVBAR & LAYOUT */
.nav-bar { position: fixed; top: 0; left: 0; right: 0; height: var(--nav-height); background: white; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); z-index: 1000; border-bottom: 1px solid var(--border); }
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

.nav-badge {
    background-color: var(--danger);
    color: white; 
    font-size: 10px; 
    font-weight: 700;
    padding: 2px 8px; 
    border-radius: 99px; 
    margin-left: auto; /* Pushes badge to the right */
}

.sidebar-profile { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 12px; }
.profile-icon { width: 36px; height: 36px; background: #f3f4f6; color: var(--utm-maroon); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }
.profile-info { font-size: 13px; overflow: hidden; }
.profile-name { font-weight: 600; white-space: nowrap; text-overflow: ellipsis; }
.profile-email { font-size: 11px; color: var(--text-secondary); white-space: nowrap; text-overflow: ellipsis; }

/* MAIN */
.main-content { flex: 1; padding: 32px; min-width: 0; }
.page-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: end; }
.page-title h2 { font-size: 24px; font-weight: 700; color: var(--utm-maroon); margin: 0; }
.page-title p { color: var(--text-secondary); font-size: 14px; margin: 4px 0 0 0; }
.btn { padding: 10px 16px; border-radius: 8px; border: 1px solid var(--border); background: white; color: var(--text-primary); font-weight: 600; cursor: pointer; font-size: 13px; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
.btn:hover { border-color: var(--utm-maroon); color: var(--utm-maroon); }
.btn-primary { background: var(--utm-maroon); color: white; border-color: var(--utm-maroon); }
.btn-primary:hover { background: var(--utm-maroon-light); color: white; border-color: var(--utm-maroon-light); }
.btn-sm { padding: 6px 12px; font-size: 12px; }

/* FILTERS */
.filters-card { background: white; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid var(--border); }
.filters-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border); }
.filters-header h3 { font-size: 14px; font-weight: 700; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 8px; }
.filters-header h3 i { color: var(--utm-maroon); }
.btn-clear { padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border); background: white; color: var(--text-secondary); font-size: 12px; font-weight: 500; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.btn-clear:hover { background: #fef2f2; color: var(--utm-maroon); border-color: #fecaca; }
.filter-section { margin-bottom: 20px; }
.filter-section-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px; margin-bottom: 10px; display: block; }
.filter-pills { display: flex; gap: 8px; flex-wrap: wrap; }
.filter-pill { padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; color: var(--text-secondary); background: #f8fafc; border: 1px solid var(--border); transition: all 0.2s; cursor: pointer; }
.filter-pill:hover { border-color: var(--utm-maroon); color: var(--utm-maroon); background: #fef2f2; }
.filter-pill.active { background: var(--utm-maroon); color: white; border-color: var(--utm-maroon); }
.filter-advanced { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; padding-top: 16px; border-top: 1px solid var(--border); }
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-group label { font-size: 12px; font-weight: 600; color: var(--text-primary); }
.filter-input { padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); font-size: 13px; outline: none; background: white; color: var(--text-primary); }
.filter-input:focus { border-color: var(--utm-maroon); box-shadow: 0 0 0 3px rgba(128,0,0,0.1); }

/* TABLE */
.card { background: white; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid var(--border); overflow: hidden; }
.table-wrap { overflow-x: auto; min-height: 300px; }
.table { width: 100%; border-collapse: collapse; min-width: 900px; }
.table th, .table td { padding: 16px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: top; }
.table th { background: #f8fafc; font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px; }

/* BADGES */
.badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
.badge-pending { background: #fef3c7; color: #92400e; }
.badge-progress { background: #dbeafe; color: #1e40af; }
.badge-resolved { background: #dcfce7; color: #166534; }
.badge-source-user { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
.badge-source-admin { background: #fae8ff; color: #86198f; border: 1px solid #e9d5ff; }
.badge-tech-done { background: #d1fae5; color: #047857; border: 1px solid #6ee7b7; display: inline-block; margin-top: 4px; padding: 2px 6px; font-size: 10px; border-radius: 4px; }

/* NEW: TECHNICIAN COMMENT BOX STYLE */
.tech-reply-box {
    background: #fff7ed;
    border: 1px solid #fdba74;
    border-radius: 8px;
    padding: 10px 12px;
    margin-top: 10px;
    position: relative;
    font-size: 13px;
    color: #7c2d12;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    max-width: 250px;
}
.tech-reply-box::after {
    content: '';
    position: absolute;
    top: -6px;
    left: 20px;
    width: 10px;
    height: 10px;
    background: #fff7ed;
    border-top: 1px solid #fdba74;
    border-left: 1px solid #fdba74;
    transform: rotate(45deg);
}
.tech-reply-header {
    font-weight: 700;
    font-size: 11px;
    text-transform: uppercase;
    color: #c2410c;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* PAGINATION */
.pagination { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-top: 1px solid var(--border); background: white; }
.page-info { font-size: 13px; color: var(--text-secondary); font-weight: 500; }
.page-numbers { display: flex; gap: 6px; align-items: center; }
.page-link { min-width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid var(--border); background: white; color: var(--text-primary); font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s; }
.page-link:hover:not(.disabled):not(.active) { border-color: var(--utm-maroon); color: var(--utm-maroon); background: #fef2f2; }
.page-link.active { background: var(--utm-maroon); color: white; border-color: var(--utm-maroon); }
.page-link.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
.page-ellipsis { padding: 0 8px; color: var(--text-secondary); }

/* MODAL */
.modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(2px); }
.modal.show { display: flex; }
.modal-content { background: white; width: 95%; max-width: 500px; border-radius: 12px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 16px; }
.modal-header h3 { margin: 0; font-size: 18px; color: var(--utm-maroon); font-weight: 700; }
.btn-close { background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary); }
.form-control, .form-select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; }
.form-control:focus { border-color: var(--utm-maroon); }

@media (max-width: 1024px) { .sidebar { display: none; } }
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
                <a href="admin_problems.php" class="active">
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
                <h2>Room Problems</h2>
                <p>Manage reported issues and routine maintenance.</p>
            </div>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fa-solid fa-plus"></i> New Task
            </button>
        </div>

        <div class="filters-card">
            <div class="filters-header">
                <h3><i class="fa-solid fa-filter"></i> Filters</h3>
                <?php if ($filter_source != 'all' || $filter_status != 'all' || $filter_room != 'all' || !empty($filter_date)): ?>
                    <a href="admin_problems.php" class="btn-clear">
                        <i class="fa-solid fa-xmark"></i> Clear All
                    </a>
                <?php endif; ?>
            </div>

            <div class="filter-section">
                <span class="filter-section-label"><i class="fa-regular fa-flag"></i> Report Source</span>
                <div class="filter-pills">
                    <a href="<?php echo buildUrl(['source'=>'all', 'page'=>1]); ?>" class="filter-pill <?php echo $filter_source=='all'?'active':''; ?>">All Sources</a>
                    <a href="<?php echo buildUrl(['source'=>'user', 'page'=>1]); ?>" class="filter-pill <?php echo $filter_source=='user'?'active':''; ?>"><i class="fa-solid fa-user"></i> User Reports</a>
                    <a href="<?php echo buildUrl(['source'=>'admin', 'page'=>1]); ?>" class="filter-pill <?php echo $filter_source=='admin'?'active':''; ?>"><i class="fa-solid fa-wrench"></i> Routine Maintenance</a>
                </div>
            </div>

            <div class="filter-section">
                <span class="filter-section-label"><i class="fa-regular fa-circle-dot"></i> Status</span>
                <div class="filter-pills">
                    <a href="<?php echo buildUrl(['status'=>'all', 'page'=>1]); ?>" class="filter-pill <?php echo $filter_status=='all'?'active':''; ?>">All Statuses</a>
                    <a href="<?php echo buildUrl(['status'=>'Pending', 'page'=>1]); ?>" class="filter-pill <?php echo $filter_status=='Pending'?'active':''; ?>"><i class="fa-solid fa-clock"></i> Pending</a>
                    <a href="<?php echo buildUrl(['status'=>'In Progress', 'page'=>1]); ?>" class="filter-pill <?php echo $filter_status=='In Progress'?'active':''; ?>"><i class="fa-solid fa-gear"></i> In Progress</a>
                    <a href="<?php echo buildUrl(['status'=>'Resolved', 'page'=>1]); ?>" class="filter-pill <?php echo $filter_status=='Resolved'?'active':''; ?>"><i class="fa-solid fa-check"></i> Resolved</a>
                </div>
            </div>

            <form method="GET" id="advancedFilters">
                <input type="hidden" name="source" value="<?php echo htmlspecialchars($filter_source); ?>">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                
                <div class="filter-advanced">
                    <div class="filter-group">
                        <label><i class="fa-solid fa-door-open"></i> Room</label>
                        <select name="room" class="filter-input" onchange="this.form.submit()">
                            <option value="all">All Rooms</option>
                            <?php 
                                $filter_rooms_list->data_seek(0);
                                while($fr = $filter_rooms_list->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $fr['room_id']; ?>" <?php echo $filter_room == $fr['room_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($fr['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fa-solid fa-calendar"></i> Date Created</label>
                        <input type="date" name="date" class="filter-input" value="<?php echo htmlspecialchars($filter_date); ?>" onchange="this.form.submit()">
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Source / Date</th>
                            <th>Room</th>
                            <th>Issue Details</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($problems->num_rows > 0): ?>
                            <?php while($row = $problems->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-source-<?php echo strtolower($row['report_source']); ?>">
                                        <?php echo htmlspecialchars($row['report_source']); ?>
                                    </span>
                                    <div style="font-size:12px; color:var(--text-secondary); margin-top:6px;">
                                        <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color:var(--utm-maroon);"><?php echo htmlspecialchars($row['room_name']); ?></strong>
                                </td>
                                <td>
                                    <div style="font-weight:600; margin-bottom:4px;"><?php echo htmlspecialchars($row['title']); ?></div>
                                    <div style="font-size:13px; color:var(--text-secondary); line-height:1.4; margin-bottom:8px;">
                                        <?php echo htmlspecialchars($row['description']); ?>
                                    </div>
                                    <?php if ($row['slot_date']): ?>
                                      <div style="font-size:11px; background:#f8fafc; padding:6px 10px; border-radius:6px; display:inline-block; border:1px solid var(--border);">
                                        <i class="fa-regular fa-calendar"></i> <?php echo htmlspecialchars($row['slot_date']); ?> 
                                        <span style="color:var(--text-secondary);">(<?php echo substr($row['time_start'], 0, 5); ?> - <?php echo substr($row['time_end'], 0, 5); ?>)</span>
                                      </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $statusClass = 'badge-pending';
                                        if($row['status'] == 'In Progress') $statusClass = 'badge-progress';
                                        if($row['status'] == 'Resolved') $statusClass = 'badge-resolved';
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                    
                                    <?php if(!empty($row['admin_notice']) && $row['admin_notice'] == 1): ?>
                                        <?php 
                                            // CHECK IF ISSUE OR COMPLETION
                                            $noticeMsg = $row['notice_msg'] ?? '';
                                            
                                            if (stripos($noticeMsg, 'Issue:') !== false) {
                                                // 1. EXTRACT REASON
                                                $issueText = htmlspecialchars(str_ireplace('Issue:', '', $noticeMsg));
                                                
                                                // 2. SHOW STYLED COMMENT BOX
                                                echo '<div class="tech-reply-box">';
                                                echo '  <div class="tech-reply-header"><i class="fa-solid fa-triangle-exclamation"></i> Technician Reported:</div>';
                                                echo '  <div>'. trim($issueText) .'</div>';
                                                echo '</div>';
                                            } else {
                                                echo '<br><span class="badge-tech-done"><i class="fa-solid fa-check"></i> Tech Finished</span>';
                                            }
                                        ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['status'] != 'Resolved'): ?>
                                        <?php if($row['tech_status'] === 'Work Done'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="resolve_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-sm" style="background:#dcfce7; color:#166534; border-color:#86efac;">
                                                    <i class="fa-solid fa-check-double"></i> Verify & Close
                                                </button>
                                            </form>
                                        <?php elseif($row['status'] === 'In Progress'): ?>
                                            <a href="admin_timetable.php?room=<?php echo urlencode($row['room_id']); ?>&date=<?php echo $row['slot_date']; ?>" class="btn btn-sm">
                                                <i class="fa-solid fa-eye"></i> View
                                            </a>
                                        <?php else: ?>
                                            <a href="admin_timetable.php?maintenance=<?php echo $row['id']; ?>&room=<?php echo urlencode($row['room_id']); ?><?php echo $row['slot_date'] ? '&date='.urlencode($row['slot_date']) : ''; ?>" 
                                               class="btn btn-sm" style="background:#fff7ed; color:#9a3412; border-color:#fdba74;">
                                              <i class="fa-solid fa-screwdriver-wrench"></i> Schedule
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="font-size:12px; color:var(--text-secondary);">
                                            <i class="fa-solid fa-lock"></i> Closed
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:32px; color:var(--text-secondary);">
                                    <i class="fa-solid fa-inbox" style="font-size:48px; opacity:0.3; margin-bottom:12px;"></i>
                                    <div style="font-size:14px; font-weight:600;">No issues found matching these filters.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="page-info">
                    Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_rows); ?></strong> of <strong><?php echo $total_rows; ?></strong> entries
                </div>
                <div class="page-numbers">
                    <?php if($page > 1): ?>
                        <a href="<?php echo buildUrl(['page' => $page - 1]); ?>" class="page-link"><i class="fa-solid fa-chevron-left"></i></a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fa-solid fa-chevron-left"></i></span>
                    <?php endif; ?>

                    <?php
                    $range = 2;
                    $start = max(1, $page - $range);
                    $end = min($total_pages, $page + $range);

                    if ($start > 1) {
                        echo '<a href="' . buildUrl(['page' => 1]) . '" class="page-link">1</a>';
                        if ($start > 2) echo '<span class="page-ellipsis">...</span>';
                    }

                    for ($i = $start; $i <= $end; $i++) {
                        $activeClass = ($i == $page) ? 'active' : '';
                        echo '<a href="' . buildUrl(['page' => $i]) . '" class="page-link ' . $activeClass . '">' . $i . '</a>';
                    }

                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) echo '<span class="page-ellipsis">...</span>';
                        echo '<a href="' . buildUrl(['page' => $total_pages]) . '" class="page-link">' . $total_pages . '</a>';
                    }
                    ?>

                    <?php if($page < $total_pages): ?>
                        <a href="<?php echo buildUrl(['page' => $page + 1]); ?>" class="page-link"><i class="fa-solid fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fa-solid fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<div id="createTicketModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create Maintenance Task</h3>
            <button class="btn-close" onclick="closeModal()">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="create_ticket">
            <div class="form-group">
                <label class="form-label">Select Room</label>
                <select name="room_id" class="form-control" required>
                    <?php while($r = $room_list->fetch_assoc()): ?>
                        <option value="<?php echo $r['room_id']; ?>"><?php echo $r['name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Task Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Aircon Leaking" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Details about what needs to be fixed..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="Normal">Normal</option>
                    <option value="High">High</option>
                    <option value="Critical">Critical</option>
                </select>
            </div>
            <div style="text-align:right; margin-top:24px; padding-top:16px; border-top:1px solid var(--border);">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="margin-left:8px;">Create & Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('createTicketModal').classList.add('show');
}
function closeModal() {
    document.getElementById('createTicketModal').classList.remove('show');
}
window.onclick = function(event) {
    var modal = document.getElementById('createTicketModal');
    if (event.target == modal) {
        modal.classList.remove('show');
    }
}
</script>
</body>
</html>