<?php
// admin_logbook.php - View system activity logs
session_start();
require_once 'db_connect.php';

// Access Control
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || 
    strcasecmp(trim($_SESSION['User_Type']), 'Admin') != 0) {
    header("Location: loginterface.html");
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin';
$admin_email = $_SESSION['Email'] ?? 'Admin';
$username = $_SESSION['username'] ?? '';
$userType = $_SESSION['User_Type'] ?? '';

// Fetch Logs (Joined with users to get Admin Name)
$logs = [];
$sql = "SELECT l.id, u.username, l.action, l.booking_id, l.note, l.ip_address, l.created_at 
        FROM admin_logs l 
        LEFT JOIN users u ON l.admin_id = u.id 
        ORDER BY l.created_at DESC LIMIT 100"; // Limit to last 100 for performance
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) $logs[] = $row;
}

// determine whether this user is superadmin (by username or explicit role)
$isSuperAdmin = (strtolower($username) === 'superadmin' || $userType === 'SuperAdmin');

// Filter & Pagination Logic Setup
$search = $_GET['search'] ?? '';
$action_filter = $_GET['action'] ?? 'all';
$start_date = $_GET['start'] ?? '';
$end_date = $_GET['end'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15; // Rows per page
$offset = ($page - 1) * $limit;

$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "u.username LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if ($action_filter !== 'all') {
    $where_clauses[] = "l.action = ?";
    $params[] = $action_filter;
    $types .= "s";
}
if (!empty($start_date)) {
    $where_clauses[] = "DATE(l.created_at) >= ?";
    $params[] = $start_date;
    $types .= "s";
}
if (!empty($end_date)) {
    $where_clauses[] = "DATE(l.created_at) <= ?";
    $params[] = $end_date;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// 3. EXPORT LOGIC (Handle Export Request before any HTML output)
if (isset($_GET['export'])) {
    // Determine filename
    $filename = "admin_logs_" . date('Y-m-d') . ".csv";
    
    // Send headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    // Header Row
    fputcsv($output, ['Log ID', 'Admin Name', 'Action', 'Booking Ref', 'Note/Details', 'IP Address', 'Date']);
    
    // Fetch ALL matching rows (no limit)
    $sql = "SELECT l.id, u.username, l.action, l.booking_id, l.note, l.ip_address, l.created_at 
            FROM admin_logs l 
            LEFT JOIN users u ON l.admin_id = u.id 
            WHERE $where_sql 
            ORDER BY l.created_at DESC";
            
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit; // Stop execution so no HTML is appended to the CSV
}

// 4. PAGINATION: Get Total Count
$count_sql = "SELECT COUNT(*) as total FROM admin_logs l LEFT JOIN users u ON l.admin_id = u.id WHERE $where_sql";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// 5. FETCH DATA: Get specific page rows
$sql = "SELECT l.id, u.username, l.action, l.booking_id, l.note, l.ip_address, l.created_at 
        FROM admin_logs l 
        LEFT JOIN users u ON l.admin_id = u.id 
        WHERE $where_sql 
        ORDER BY l.created_at DESC LIMIT ? OFFSET ?";

// Add limit/offset parameters
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$logs = [];
while ($row = $res->fetch_assoc()) $logs[] = $row;

// Helper to keep filter params in links
function getQueryLink($newPage = 1) {
    global $search, $action_filter, $start_date, $end_date;
    return "?page=$newPage&search=" . urlencode($search) . "&action=" . urlencode($action_filter) . "&start=" . urlencode($start_date) . "&end=" . urlencode($end_date);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard — Reservation System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<!-- Inline CSS matching index-admin.php -->
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
.tag { padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; text-transform:uppercase; }
.tag.create { background: #d1fae5; color: #065f46; }
.tag.approve { background: #b0fab3ff; color: #015f44ff; }
.tag.delete { background: #fee2e2; color: #991b1b; }
.tag.update, .tag.reject { background: #fff7ed; color: #9a3412; }

/* --- NEW STYLES FOR FILTERS, MODAL & PAGINATION --- */
.filters-container { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-group label { font-size: 12px; font-weight: 600; color: var(--gray-600); text-transform: uppercase; }
.form-control { padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
.btn-primary { background: var(--primary); color: white; border: none; }
.btn-primary:hover { background: var(--primary-dark); }
.btn-success { background: #059669; color: white; border: none; }
.btn-secondary { background: var(--gray-200); color: var(--gray-700); border: none; }

.pagination { display: flex; gap: 5px; margin-top: 20px; justify-content: center; }
.page-link { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: var(--gray-700); background: white; }
.page-link.active { background: var(--primary); color: white; border-color: var(--primary); }
.page-link:hover:not(.active) { background: var(--gray-100); }

/* Modal Styles */
.modal-backdrop { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
.modal { background: white; width: 90%; max-width: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; animation: popIn 0.3s ease; }
.modal-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: var(--gray-100); }
.modal-header h3 { margin: 0; font-size: 18px; color: var(--gray-700); }
.modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
.modal-body { padding: 20px; font-size: 14px; line-height: 1.6; }
.modal-row { margin-bottom: 10px; display: flex; }
.modal-label { width: 120px; font-weight: 600; color: var(--gray-600); flex-shrink: 0; }
.modal-value { color: #111827; flex-grow: 1; }
.modal-full-note { background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; margin-top: 5px; font-family: monospace; white-space: pre-wrap; word-break: break-all; }

@keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
</style>
</head>
<body>

<nav class="nav-bar">
  <img class="nav-logo" src="img/utmlogo.png" alt="UTM Logo">
  <div class="nav-actions"><a href="logout.php" class="btn outline">Logout</a></div>
</nav>

<div class="layout">
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
      <!---add this in page-->
      <li><a href="admin_logbook.php" class="active">Logbook</a></li>
      <li><a href="generate_reports.php">Generate Reports</a></li>
      <li><a href="admin_problems.php">Room Problems</a></li>
      <!---add this in page-->
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
        <h2 style="margin-top:0;">System Audit Log</h2>
        <p style="color:#666; font-size:14px;">Recording administrative actions for security and tracking.</p>
        
       <form method="GET" class="filters-container">
        <div class="form-group">
            <label>Admin Name</label>
            <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search);?>">
        </div>
        <div class="form-group">
            <label>Action</label>
            <select name="action" class="form-control">
                <option value="all">All Actions</option>
                <option value="create"<?php if($action_filter=='create')echo 'selected';?>>Create</option>
                <option value="approve"<?php if($action_filter=='approve')echo 'selected';?>>Approve</option>
                <option value="reject"<?php if($action_filter=='reject')echo 'selected';?>>Reject</option>
                <option value="delete"<?php if($action_filter=='delete')echo 'selected';?>>Delete</option>
                <option value="update"<?php if($action_filter=='update')echo 'selected';?>>Update</option>
            </select>
        </div>
        <div class="form-group">
            <lable>Start Date</lable>
            <input type="date" name="start" class="form-control" value="<?php echo $start_date; ?>">
        </div>
        <div class="form-group">
            <lable>End Date</lable>
            <input type="date" name="start" class="form-control" value="<?php echo $end_date; ?>">
        </div>
        <div class="form-group" style="justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
            <div class="form-group">
                <a href="admin_logbook.php" class="btn btn-secondary">Reset</a>
            </div>
            <div class="form-group" style="margin-left: auto;">
                <a href="<?php echo getQueryLink($page) . '&export=true'; ?>" class="btn btn-success">Export CSV</a>
            </div>
        </form>
        <p style="color:#666; font-size:14px;">Total Logs: <?php echo $total_rows; ?></p>

      <!--Table for the logs-->
        <table class ="table">
          <thead>
            <tr>
              <th width="15%">Date/Time</th>
              <th width="15%">Admin</th>
              <th width="10%">Action</th>
              <th width="40%">Summary</th>
              <th width="10%">Details</th>
            </tr>
          </thead>
          <tbody>
            <?php if(count($logs)>0): ?>
              <?php foreach($logs as $log): ?>
                <tr>
                  <td><?php echo date('d M Y H:i', strtotime($log['created_at'])); ?></td>
                  <td><strong><?php echo htmlspecialchars($log['username']);?></strong></td>
                  <td>
                    <?php
                      $cls = 'update';
                      if(stripos($log['action'], 'create')!==false) $cls='create';
                      if(stripos($log['action'], needle: 'approve')!==false) $cls='approve';
                      if(stripos($log['action'], 'delete')!==false) $cls='delete';
                      if(stripos($log['action'], 'reject')!==false) $cls='reject';
                    ?>
                    <span class="tag <?php echo $cls;?>"><?php echo htmlspecialchars($log['action']); ?></span>
                  </td>
                  <td>
                    <?php
                      $note = $log['note'];
                      echo strlen($note) > 50 ? htmlspecialchars(substr($note, 0, 50)) . '...' : htmlspecialchars($note);
                      if($log['booking_id']) echo "<small style = 'color:#888;'>(ID: #{$log['booking_id']})</small>";
                    ?>
                  </td>
                  <td>
                    <button class="btn btn-secondary" style="padding:4px 10px; font-size:12px;" onclick ='openModal(<?php echo json_encode($log);?>)'>
                      View
                    </button>
                  </td>
                </tr>
              <?php endforeach;?>
              <?php else: ?>
                <tr><td colspan="5" style="text-align:center; padding: 20px;">No logs found matching criteria</td></tr>
            <?php endif; ?>
          </tbody>
        </table>

        <!--Paging controls-->
        <?php if ($total_pages > 1): ?>
          <div class = "pagination">
            <!--Previous link-->
            <?php if ($page > 1):?>
                <a href="<?php echo getQueryLink($page-1); ?>" class="page-link">&laquo; Prev</a>
            <?php endif; ?>

            <!--Page num-->
            <?php
            $start_p = max(1, $page - 2);
            $end_p = min($total_pages, $page + 2);
            for ($i = $start_p; $i <= $end_p; $i++): ?>
                <a href="<?php echo getQueryLink($i); ?>" class = "page-link <?php if($i == $page) echo 'active'; ?>">
                  <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <!--Next link-->
            <?php if ($page < $total_pages): ?>
              <a href="<?php echo getQueryLink($page + 1); ?>" class = "page-link">Next &raquo;</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
    </div>
  </main>
</div>

<div id="logModal" class="modal-backdrop">
  <div class="modal">
    <div class="modal-header">
      <h3>Log Details</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div class="modal-row">
        <div class="modal-label">Log ID:</div>
        <div class="modal-value" id="m_id"></div>
      </div>
      <div class="modal-row">
        <div class="modal-label">Date/Time:</div>
        <div class="modal-value" id="m_date"></div>
      </div>
      <div class="modal-row">
        <div class="modal-label">Admin User:</div>
        <div class="modal-value" id="m_admin"></div>
      </div>
      <div class="modal-row">
        <div class="modal-label">Action:</div>
        <div class="modal-value" id="m_action" style="font-weight:bold; text-transform:uppercase;"></div>
      </div>
      <div class="modal-row">
        <div class="modal-label">Booking ID:</div>
        <div class="modal-value" id="m_booking"></div>
      </div>
      <div class="modal-row">
        <div class="modal-label">IP Address:</div>
        <div class="modal-value" id="m_ip" style="font-family:monospace;"></div>
      </div>
      <div class="modal-top:15px;">
        <div class="modal-label">Full Details / Note:</div>
        <div class="modal-full-note" id="m_note"></div>
      </div>
    </div>
  </div><!-- end modal-->

</div>

<script>

//Logic
function openModal(log){
  document.getElementById('m_id').textContent = '#' + log.id;
  document.getElementById('m_date').textContent = log.created_at;
  document.getElementById('m_admin').textContent = log.username;
  document.getElementById('m_action').textContent = log.action;
  document.getElementById('m_booking').textContent = log.booking_id ? '#' + log.booking_id : 'N/A';
  document.getElementById('m_ip').textContent = log.ip_address || 'Unknown';
  document.getElementById('m_note').textContent = log.note;
  
  document.getElementById('logModal').style.display = 'flex';
}

function closeModal(){
  document.getElementById('logModal').style.display = 'none';
}

window.onclick = function(event){
  var modal = document.getElementById('logModal');
  if(event.target == modal){
    closeModal();
  }
}
</script>
</body>
</html>