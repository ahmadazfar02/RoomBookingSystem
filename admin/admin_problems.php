<?php
/**
 * admin_problems.php
 * Admin page to view and manage room problems reported by users
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

$problems = $conn->query("
    SELECT p.*, r.name as room_name, u.username, 
           b.slot_date, b.time_start, b.time_end
    FROM room_problems p 
    JOIN rooms r ON p.room_id = r.room_id 
    JOIN users u ON p.user_id = u.id
    LEFT JOIN bookings b ON p.user_id = b.user_id AND p.room_id = b.room_id 
        AND b.slot_date >= CURDATE() - INTERVAL 7 DAY
        AND b.status IN ('booked', 'pending')
    GROUP BY p.id  -- <--- This ensures 1 row per problem report
    ORDER BY p.created_at DESC
");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Room Problems — Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --primary: #2563eb;
  --primary-dark: #1d4ed8;
  --primary-light: #dbeafe;
  --accent: #6e0b0b;
  --success: #059669;
  --danger: #dc2626;
  --warning: #f59e0b;
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
.btn { padding:8px 12px; border-radius:8px; border:0; cursor:pointer; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; transition: all 0.2s; }
.btn.primary { background: linear-gradient(135deg,var(--primary),var(--primary-dark)); color: #fff; }
.btn.outline { background: #fff; border:2px solid var(--gray-300); color: var(--gray-700); }
.btn:hover { 
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2); 
}

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
    box-shadow: var(--shadow-sm);
    z-index: 100;
    flex-shrink: 0;
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

.sidebar-profile {
  margin-top: auto;
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

/* card */
.card { 
  background:#fff; 
  border-radius:12px; 
  padding:24px; 
  box-shadow:var(--shadow-sm); 
}

.card h2 {
  margin: 0 0 20px 0;
  font-size: 18px;
  font-weight: 700;
  color: var(--gray-800);
}

/* table */
.table-wrap { 
  overflow:auto; 
  border-radius:8px; 
  border:1px solid var(--gray-200); 
  background:#fff; 
}

.table { 
  width:100%; 
  border-collapse:collapse; 
  min-width:900px; 
}

.table th, .table td { 
  padding:12px 10px; 
  border-bottom:1px solid #f1f5f9; 
  text-align:left; 
  vertical-align: top;
}

.table th { 
  background:linear-gradient(180deg,var(--gray-100),var(--gray-50)); 
  font-weight:700; 
  font-size:12px; 
  text-transform:uppercase; 
  letter-spacing:0.5px; 
  position:sticky; 
  top:0; 
  z-index:10; 
}

.status-badge { 
  display:inline-block; 
  padding:4px 10px; 
  border-radius:12px; 
  font-weight:600; 
  font-size:11px; 
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-pending { 
  background: #fef3c7; 
  color: #92400e; 
}

.status-in-progress { 
  background: #dbeafe; 
  color: #1e40af; 
}

.status-resolved { 
  background: #d1fae5; 
  color: #065f46; 
}

/* Action buttons in table */
.action-btns {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  text-decoration: none;
}

.btn-warning {
  background: linear-gradient(135deg, var(--warning), #d97706);
  color: white;
}

.btn-warning:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
}

.btn-success {
  background: linear-gradient(135deg, var(--success), #047857);
  color: white;
}

.btn-success:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(5, 150, 105, 0.3);
}

.btn-disabled {
  background: var(--gray-300);
  color: var(--gray-600);
  cursor: not-allowed;
  opacity: 0.6;
}

.btn-disabled:hover {
  transform: none;
  box-shadow: none;
}

/* Issue details */
.issue-title {
  font-weight: 700;
  font-size: 14px;
  color: var(--gray-800);
  margin-bottom: 4px;
}

.issue-desc {
  font-size: 13px;
  color: var(--gray-600);
  line-height: 1.4;
}

.booking-info {
  font-size: 12px;
  color: var(--gray-600);
  margin-top: 6px;
  padding: 6px 10px;
  background: var(--gray-50);
  border-radius: 4px;
  border-left: 3px solid var(--primary);
}

/* responsive */
@media (max-width:760px){
  .layout { padding:12px; margin-top: calc(var(--nav-height) + 10px); display:block; }
  .sidebar { display:none; }
  .table { min-width:800px; }
  .action-btns { flex-direction: column; }
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

      <li><a href="admin_logbook.php">Logbook</a></li>
      <li><a href="generate_reports.php">Generate Reports</a></li>
      <li><a href="admin_problems.php" class="active">Room Problems</a></li>
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
        <h1>Reported Room Problems</h1>
        <div class="header-sub">Manage and resolve facility issues</div>
      </div>
    </div>

    <div class="card">
      <h2>Problem Reports</h2>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Room</th>
              <th>Issue Details</th>
              <th>Reported By</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $problems->fetch_assoc()): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($row['room_name']); ?></strong></td>
              <td>
                <div class="issue-title"><?php echo htmlspecialchars($row['title']); ?></div>
                <div class="issue-desc"><?php echo htmlspecialchars($row['description']); ?></div>
                <?php if ($row['slot_date']): ?>
                  <div class="booking-info">
                    📅 Booking: <?php echo htmlspecialchars($row['slot_date']); ?> 
                    at <?php echo substr($row['time_start'], 0, 5); ?>-<?php echo substr($row['time_end'], 0, 5); ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars($row['username']); ?><br>
                <small style="color: var(--gray-600);"><?php echo htmlspecialchars($row['created_at']); ?></small>
              </td>
              <td>
                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>">
                  <?php echo htmlspecialchars($row['status']); ?>
                </span>
              </td>
              <td>
                <div class="action-btns">
                  <?php if($row['status'] != 'Resolved'): ?>
                    <!-- Schedule Maintenance Button -->
                    <a href="admin_timetable.php?maintenance=<?php echo $row['id']; ?>&room=<?php echo urlencode($row['room_id']); ?><?php echo $row['slot_date'] ? '&date='.urlencode($row['slot_date']).'&time_start='.urlencode(substr($row['time_start'],0,5)).'&time_end='.urlencode(substr($row['time_end'],0,5)) : ''; ?>" 
                       class="btn-sm btn-warning">
                      🔧 Schedule Maintenance
                    </a>
                    
                    <!-- Mark Resolved Button -->
                    <form method="POST" style="display: inline;">
                      <input type="hidden" name="resolve_id" value="<?php echo $row['id']; ?>">
                      <button type="submit" class="btn-sm btn-success" onclick="return confirm('Mark this problem as resolved?')">
                        ✓ Mark Resolved
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="btn-sm btn-disabled">✓ Resolved</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

</body>
</html>