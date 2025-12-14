<?php
// index-admin.php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Access control: require logged in + Admin role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true ||
    !isset($_SESSION['User_Type']) || strcasecmp(trim($_SESSION['User_Type']), 'Admin') != 0) {
    header("Location: ../loginterface.html");
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin';
$admin_email = $_SESSION['Email'] ?? 'Admin';
$username = $_SESSION['username'] ?? '';
$userType = $_SESSION['User_Type'] ?? '';

// determine whether this user is superadmin (by username or explicit role)
$isSuperAdmin = (strtolower($username) === 'superadmin' || $userType === 'SuperAdmin');

// --- Fetch dashboard data ---
// total rooms
$total_rooms = 0;
if ($r = $conn->query("SELECT COUNT(*) AS c FROM rooms")) {
    $row = $r->fetch_assoc();
    $total_rooms = intval($row['c'] ?? 0);
    $r->free();
}

// active bookings today (booked)
$active_bookings_today = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE slot_date = CURDATE() AND status = 'booked'");
if ($stmt) {
    $stmt->execute();
    $stmt->bind_result($cnt1);
    $stmt->fetch();
    $active_bookings_today = intval($cnt1);
    $stmt->close();
}

// pending approvals (status = pending)
$pending_approvals = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
if ($stmt) {
    $stmt->execute();
    $stmt->bind_result($cnt2);
    $stmt->fetch();
    $pending_approvals = intval($cnt2);
    $stmt->close();
}

// recent notifications (latest 5 bookings)
$notifications = [];
$stmt = $conn->prepare("
    SELECT b.id, r.name AS room_name, u.username AS requester, b.slot_date, b.time_start, b.time_end, b.status
    FROM bookings b
    JOIN rooms r ON b.room_id = r.room_id
    JOIN users u ON b.user_id = u.id
    ORDER BY b.slot_date DESC, b.time_start DESC
    LIMIT 5
");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($n = $res->fetch_assoc()) $notifications[] = $n;
    $stmt->close();
}

// recent booking requests table (latest 6)
$recent_requests = [];
$stmt = $conn->prepare("
    SELECT b.id, r.name AS room_name, r.room_id AS room_no, u.username AS requester, b.slot_date, b.time_start, b.time_end, b.status, b.purpose
    FROM bookings b
    JOIN rooms r ON b.room_id = r.room_id
    JOIN users u ON b.user_id = u.id
    ORDER BY b.slot_date DESC, b.time_start DESC
    LIMIT 6
");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $recent_requests[] = $row;
    $stmt->close();
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard — Reservation System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --primary: #2563eb;
  --primary-dark: #1d4ed8;
  --primary-light: #dbeafe;
  --accent: #6e0b0b;
  --success: #059669;
  --danger: #dc2626;
  --gray-50: #f9fafb;
  --gray-100: #f3f4f6;
  --gray-200: #e5e7eb;
  --gray-300: #d1d5db;
  --gray-600: #4b5563;
  --gray-700: #374151;
  --shadow-sm: 0 6px 22px rgba(18, 38, 63, 0.10);
  --nav-height: 100px;
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Arial;
  min-height:100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: var(--gray-700);
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}

/* NAVBAR */
.nav-bar {
  position: fixed;
  top:0; left:0; right:0;
  height: var(--nav-height);
  background: #fff;
  display:flex;
  align-items:center;
  gap:16px;
  padding:12px 22px;
  box-shadow: 0 10px 30px rgba(2,6,23,0.12);
  z-index:1400;
}
.nav-logo { height: 80px;}

.nav-actions { margin-left: auto; display:flex; gap:12px; align-items:center; }
.btn { padding:8px 12px; border-radius:8px; border:0; cursor:pointer; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
.btn.primary { background: linear-gradient(135deg,var(--primary),var(--primary-dark)); color: #fff; }
.btn.outline { background: #fff; border:2px solid var(--gray-300); color: var(--gray-700); }
/* layout */
.layout {
  width: 100%;
  max-width: 2000px;
  margin: calc(var(--nav-height) + 18px) auto 48px;
  padding: 20px;
  display:flex;
  gap:20px;
  align-items:flex-start;
}

/* sidebar */
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
    top: 100px; 
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

/* main */
.main { flex:1; min-width:0; }

/* header card */
.header-card {
  display:flex; justify-content:space-between; align-items:center;
  background:#fff; padding:20px; border-radius:12px; box-shadow:var(--shadow-sm); margin-bottom:18px;
}
.header-title h1 { margin:0; font-size:20px; color:var(--gray-800); }
.header-sub { color:var(--gray-600); font-size:13px; margin-top:6px; }

/* stats grid */
.stats-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-bottom:18px; }
@media (max-width:900px){ .stats-grid { grid-template-columns: 1fr; } }

.stat-card { background:#fff; padding:18px; border-radius:10px; box-shadow:var(--shadow-sm); }
.stat-card h4 { margin:0 0 8px 0; font-size:13px; color:var(--gray-600); font-weight:700; }
.stat-card .value { font-size:2rem; font-weight:800; color:var(--gray-800); margin-top:6px; }

/* notifications + table layout */
.row { display:flex; gap:16px; }
@media (max-width:1000px){ .row { flex-direction:column; } }

.card { background:#fff; border-radius:12px; padding:16px; box-shadow:var(--shadow-sm); }

/* notifications */
.notifications { flex:1; max-width:420px; }
.notifications .item { padding:10px 0; border-bottom:1px dashed #eee; font-size:13px; color:var(--gray-700); }
.notifications .meta { font-size:12px; color:var(--gray-500); margin-top:6px; }

/* requests table */
.table-wrap { overflow:auto; border-radius:8px; border:1px solid var(--gray-200); background:#fff; }
.table { width:100%; border-collapse:collapse; min-width:800px; }
.table th, .table td { padding:12px 10px; border-bottom:1px solid #f1f5f9; text-align:left; }
.table th { background:linear-gradient(180deg,var(--gray-100),var(--gray-50)); font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; position:sticky; top:0; z-index:10; }
.status { display:inline-block; padding:6px 10px; border-radius:8px; font-weight:700; font-size:12px; }
.status.pending { background:linear-gradient(135deg,#fff7ed,#fff1c2); color:#92400e; }
.status.booked { background:linear-gradient(135deg,#ecfdf5,#dcfce7); color:var(--success); }
.status.approve { background:linear-gradient(135deg,#dbeafe,#cfe0ff); color:var(--primary); }
.status.cancel { background:linear-gradient(135deg,#fff1f2,#fee2e2); color:var(--danger); }

/* small helpers */
.link-secondary { color:var(--accent); text-decoration:none; font-weight:700; }

/* responsive */
@media (max-width:760px){
  .layout { padding:12px; margin-top: calc(var(--nav-height) + 10px); display:block; }
  .sidebar { display:none; }
  .table { min-width:700px; }
}
</style>
</head>
<body>
<nav class="nav-bar" role="navigation" aria-label="Main navigation">
  <img class="nav-logo" src="../assets/images/utmlogo.png" alt="UTM Logo">
    <div class="nav-actions">
    <a href="../auth/logout.php" class="btn outline">Logout</a>
  </div>
</nav>

<div class="layout" role="main">
  <!-- Sidebar -->
  <aside class="sidebar" aria-label="Sidebar">
    <div class="sidebar-title">Main Menu</div>
    <ul class="sidebar-menu">
      <li><a href="index-admin.php" class="active">Dashboard</a></li>
      <li><a href="reservation_request.php">Reservation Request</a></li>
      <li><a href="admin_timetable.php">Regular Timetable</a></li>
      <li><a href="admin_recurring.php">Recurring Templates</a></li>

      <?php if ($isSuperAdmin): ?>
        <li><a href="manage_users.php">Manage Users</a></li>
      <?php endif; ?>
      <li><a href="admin_logbook.php">Logbook</a></li>
      <li><a href="generate_reports.php">Generate Reports</a></li>
      <li><a href="admin_problems.php">Room Problems</a></li>
    </ul>

    <div class="sidebar-profile">
      <div class="profile-icon"><?php echo strtoupper(substr($admin_name,0,1)); ?></div>
      <div class="profile-info">
        <div class="profile-name"><?php echo htmlspecialchars($admin_name); ?></div>
        <div class="profile-email"><?php echo htmlspecialchars($admin_email); ?></div>
      </div>
    </div>
  </aside>

  <main class="main">
    <div class="header-card">
      <div>
        <div class="header-title"><h1>Admin Dashboard</h1></div>
        <div class="header-sub">Welcome back — manage rooms & bookings</div>
      </div>
      <div style="text-align:right">
        <div style="font-weight:700;color:var(--gray-700)"><?php echo htmlspecialchars($admin_name); ?></div>
        <div style="font-size:12px;color:var(--gray-500)"><?php echo htmlspecialchars($admin_email); ?></div>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card card">
        <h4>Total Rooms</h4>
        <div class="value"><?php echo number_format($total_rooms); ?></div>
        <div style="margin-top:8px;color:var(--gray-500);font-size:13px">Registered rooms in the system</div>
      </div>

      <div class="stat-card card">
        <h4>Active Bookings Today</h4>
        <div class="value"><?php echo number_format($active_bookings_today); ?></div>
        <div style="margin-top:8px;color:var(--gray-500);font-size:13px">Confirmed bookings for today</div>
      </div>

      <div class="stat-card card">
        <h4>Pending Approvals</h4>
        <div class="value"><?php echo number_format($pending_approvals); ?></div>
        <div style="margin-top:8px;color:var(--gray-500);font-size:13px">Requests waiting for admin action</div>
      </div>
    </div>

    <div class="row" style="margin-bottom:16px">
      <div class="card notifications">
        <h4 style="margin:0 0 12px 0">Notifications</h4>
        <?php if (count($notifications) === 0): ?>
          <div class="item">No recent notifications.</div>
        <?php else: ?>
          <?php foreach ($notifications as $n): ?>
            <div class="item">
              <strong><?php echo htmlspecialchars($n['room_name']); ?></strong> reserved by <strong><?php echo htmlspecialchars($n['requester']); ?></strong>
              <div class="meta"><?php echo date('d M Y', strtotime($n['slot_date'])); ?> — <?php echo htmlspecialchars($n['time_start'] . ' - ' . $n['time_end']); ?> • <span class="status <?php echo htmlspecialchars($n['status']); ?>"><?php echo ucfirst(htmlspecialchars($n['status'])); ?></span></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <div style="margin-top:12px"><a class="link-secondary" href="reservation_request.php">View all requests →</a></div>
      </div>

      <div class="card" style="flex:1">
        <h4 style="margin:0 0 12px 0">Recent Booking Requests</h4>
        <div class="table-wrap">
          <table class="table" role="table">
            <thead>
              <tr>
                <th>Ticket</th>
                <th>Room</th>
                <th>Room No.</th>
                <th>User</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($recent_requests) === 0): ?>
                <tr><td colspan="7" style="text-align:center;padding:18px">No recent requests</td></tr>
              <?php else: ?>
                <?php foreach ($recent_requests as $rq): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($rq['id']); ?></td>
                    <td><strong><?php echo htmlspecialchars($rq['room_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($rq['room_no']); ?></td>
                    <td><?php echo htmlspecialchars($rq['requester']); ?></td>
                    <td><?php echo date('d M Y', strtotime($rq['slot_date'])); ?></td>
                    <td><?php echo htmlspecialchars($rq['time_start'] . ' - ' . $rq['time_end']); ?></td>
                    <td><span class="status <?php echo htmlspecialchars($rq['status']); ?>"><?php echo ucfirst(htmlspecialchars($rq['status'])); ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>
</div>

<script>
// small auto-hide for any flash message you might add later
document.addEventListener('DOMContentLoaded', () => {
  const flash = document.querySelector('.alert-success, .alert-error');
  if (flash) setTimeout(()=> flash.remove(), 3500);
});
</script>

</body>
</html>
