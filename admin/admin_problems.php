<?php
/**
 * admin_problems.php
 * Admin page to view and manage room problems reported by users
 * PLACEHOLDER - Full implementation pending
 */
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true ||
    !isset($_SESSION['User_Type']) || strcasecmp(trim($_SESSION['User_Type']), 'Admin') != 0) {
    header("Location: loginterface.html");
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin';
$admin_email = $_SESSION['Email'] ?? 'Admin';
$username = $_SESSION['username'] ?? '';
$userType = $_SESSION['User_Type'] ?? '';

// determine whether this user is superadmin (by username or explicit role)
$isSuperAdmin = (strtolower($username) === 'superadmin' || $userType === 'SuperAdmin');

if(isset($_POST['resolve_id'])) {
    $conn->query("UPDATE room_problems SET status='Resolved', resolved_at=NOW() WHERE id=".intval($_POST['resolve_id']));
}

$problems = $conn->query("SELECT p.*, r.name as room_name, u.username FROM room_problems p JOIN rooms r ON p.room_id = r.room_id JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
?>
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
.btn:hover { 
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2); }

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
      <li><a href="index-admin.php">Dashboard</a></li>
      <li><a href="reservation_request.php">Reservation Request</a></li>
      <li><a href="admin_timetable.php">Regular Timetable</a></li>
      <li><a href="admin_recurring.php">Recurring Templates</a></li>

      <?php if ($isSuperAdmin): ?>
        <li><a href="manage_users.php">Manage Users</a></li>
      <?php endif; ?>

      <li><a href="admin_logbook.php">Logbook</a>
    </li><li><a href="generate_reports.php" >Generate Reports</a></li>
    <li><a href="admin_problems" class="active">Room Problems</a></li>
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
   <div class="card">
    <h2>Reported Room Problems</h2>
    <table class="table">
        <tr><th>Room</th><th>Issue</th><th>Reported By</th><th>Status</th><th>Action</th></tr>
        <?php while($row = $problems->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['room_name']; ?></td>
            <td><strong><?php echo $row['title']; ?></strong><br><small><?php echo $row['description']; ?></small></td>
            <td><?php echo $row['username']; ?></td>
            <td><?php echo $row['status']; ?></td>
            <td>
                <?php if($row['status'] != 'Resolved'): ?>
                <form method="POST"><input type="hidden" name="resolve_id" value="<?php echo $row['id']; ?>"><button class="btn">Mark Resolved</button></form>
                <?php else: echo "Done"; endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
  </main>
</div>



</body>
</html>
