<?php
/**
 * admin_problems.php
 * Unified Maintenance Dashboard
 */
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Access Control
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true ||
    !isset($_SESSION['User_Type']) || strcasecmp(trim($_SESSION['User_Type']), 'Admin') != 0) {
    header("Location: loginterface.html");
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin';
$admin_email = $_SESSION['Email'] ?? 'Admin';
$username = $_SESSION['username'] ?? '';
$userType = $_SESSION['User_Type'] ?? '';
$isSuperAdmin = (strtolower($username) === 'superadmin' || $userType === 'SuperAdmin');

// --- 1. HANDLE FORM SUBMISSIONS ---

// A. Create New Admin Ticket
if (isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    $room_id = $conn->real_escape_string($_POST['room_id']);
    $title = $conn->real_escape_string($_POST['title']);
    $desc = $conn->real_escape_string($_POST['description']);
    $priority = $conn->real_escape_string($_POST['priority']);
    
    // Use Admin ID for the record
    $uid = $_SESSION['id'] ?? $_SESSION['User_ID'];

    // Insert the ticket
    $sql = "INSERT INTO room_problems (user_id, room_id, title, description, status, report_source, priority, created_at) 
            VALUES ('$uid', '$room_id', '$title', '$desc', 'Pending', 'Admin', '$priority', NOW())";
    
    if ($conn->query($sql)) {
        // --- THE FIX IS HERE ---
        // Get the ID of the ticket we just created
        $new_ticket_id = $conn->insert_id;
        
        // Redirect directly to the Timetable page, passing the Ticket ID and Room
        header("Location: admin_timetable.php?maintenance=" . $new_ticket_id . "&room=" . urlencode($room_id));
        exit;
    } else {
        $error = "Error: " . $conn->error;
    }
}

// B. Resolve Issue / Verify Tech Work
if(isset($_POST['resolve_id'])) {
    $problem_id = intval($_POST['resolve_id']);

    // 1. Mark problem Resolved
    $conn->query("UPDATE room_problems SET status='Resolved', resolved_at=NOW(), admin_notice=0 WHERE id={$problem_id}");

    // 2. Free up the slot (Delete the linked maintenance booking)
    $conn->query("DELETE FROM bookings WHERE linked_problem_id={$problem_id}");
}

// --- 2. FETCH DATA WITH FILTERS ---

$filter_source = $_GET['source'] ?? 'all';
$where_clauses = [];

// Filter by Source (User vs Admin)
if ($filter_source === 'user') {
    $where_clauses[] = "p.report_source = 'User'";
} elseif ($filter_source === 'admin') {
    $where_clauses[] = "p.report_source = 'Admin'";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

// Main Query
$problems = $conn->query("
    SELECT p.*, r.name AS room_name, u.username, 
           b.slot_date, b.time_start, b.time_end, b.tech_status
    FROM room_problems p
    JOIN rooms r ON p.room_id = r.room_id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN bookings b 
      ON b.linked_problem_id = p.id 
      AND b.status != 'deleted'
    $where_sql
    GROUP BY p.id
    ORDER BY FIELD(p.status, 'In Progress', 'Pending', 'Resolved'), p.created_at DESC
");

// Fetch rooms for Dropdown
$room_list = $conn->query("SELECT room_id, name FROM rooms ORDER BY name");
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

.sidebar-menu { list-style: none; padding: 0; margin: 0; }
.sidebar-menu li { margin-bottom: 8px; }
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
.sidebar-menu a:hover { background: var(--gray-100); color: var(--primary); }
.sidebar-menu a.active { background: var(--primary-light); color: var(--primary); font-weight: 600; }

.sidebar-profile {
  margin-top: auto;
  padding-top: 20px;
  border-top: 1px solid var(--gray-200);
  display: flex;
  align-items: center;
  gap: 12px;
}
.profile-icon {
 width: 36px; height: 36px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 700;
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
.card { background:#fff; border-radius:12px; padding:24px; box-shadow:var(--shadow-sm); }
.card h2 { margin: 0 0 20px 0; font-size: 18px; font-weight: 700; color: var(--gray-800); }

/* table */
.table-wrap { overflow:auto; border-radius:8px; border:1px solid var(--gray-200); background:#fff; }
.table { width:100%; border-collapse:collapse; min-width:900px; }
.table th, .table td { padding:12px 10px; border-bottom:1px solid #f1f5f9; text-align:left; vertical-align: top; }
.table th { background:linear-gradient(180deg,var(--gray-100),var(--gray-50)); font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; position:sticky; top:0; z-index:10; }

/* STATUS BADGES */
.status-badge { display:inline-block; padding:4px 10px; border-radius:12px; font-weight:600; font-size:11px; text-transform: uppercase; letter-spacing: 0.5px; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-in-progress { background: #dbeafe; color: #1e40af; }
.status-resolved { background: #d1fae5; color: #065f46; }

/* NEW: SOURCE BADGES */
.source-badge { font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; text-transform:uppercase; margin-right:5px; border:1px solid rgba(0,0,0,0.1); }
.source-user { background: #e0e7ff; color: #3730a3; }
.source-admin { background: #fae8ff; color: #86198f; }

/* ACTION BUTTONS */
.action-btns { display: flex; gap: 6px; flex-wrap: wrap; }
.btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; }
.btn-warning { background: linear-gradient(135deg, var(--warning), #d97706); color: white; }
.btn-warning:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3); }
.btn-success { background: linear-gradient(135deg, var(--success), #047857); color: white; }
.btn-success:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(5, 150, 105, 0.3); }
.btn-disabled { background: var(--gray-300); color: var(--gray-600); cursor: not-allowed; opacity: 0.6; }
.btn-disabled:hover { transform: none; box-shadow: none; }

/* Issue details */
.issue-title { font-weight: 700; font-size: 14px; color: var(--gray-800); margin-bottom: 4px; }
.issue-desc { font-size: 13px; color: var(--gray-600); line-height: 1.4; }
.booking-info { font-size: 12px; color: var(--gray-600); margin-top: 6px; padding: 6px 10px; background: var(--gray-50); border-radius: 4px; border-left: 3px solid var(--primary); }

/* --- NEW STYLES FOR FILTERS & MODAL --- */
.filter-bar { margin-bottom: 20px; display: flex; gap: 8px; }
.filter-link { text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; border: 1px solid var(--gray-300); color: var(--gray-600); background: white; transition: all 0.2s; }
.filter-link:hover { background: var(--gray-100); }
.filter-link.active { background: var(--primary); color: white; border-color: var(--primary); }

/* Modal Styles */
.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 9999; }
.modal-content { background: white; width: 500px; max-width: 90%; border-radius: 12px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
.form-group { margin-bottom: 15px; }
.form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: var(--gray-700); }
.form-control { width: 100%; padding: 10px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 14px; transition: border-color 0.2s; }
.form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }

/* Responsive */
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
        <h1>Maintenance Dashboard</h1>
        <div class="header-sub">Manage User Reports and Admin Tasks</div>
      </div>
      <button class="btn primary" onclick="openModal()">
        + New Maintenance Task
      </button>
    </div>

    <div class="filter-bar">
        <a href="?source=all" class="filter-link <?php echo $filter_source=='all'?'active':''; ?>">All Tasks</a>
        <a href="?source=user" class="filter-link <?php echo $filter_source=='user'?'active':''; ?>">User Reports</a>
        <a href="?source=admin" class="filter-link <?php echo $filter_source=='admin'?'active':''; ?>">Routine Maintenance</a>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Source</th>
              <th>Room</th>
              <th>Issue Details</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $problems->fetch_assoc()): ?>
            <tr>
              <td>
                  <span class="source-badge source-<?php echo strtolower($row['report_source']); ?>">
                      <?php echo htmlspecialchars($row['report_source']); ?>
                  </span><br>
                  <small style="color:#666;"><?php echo date('M d', strtotime($row['created_at'])); ?></small>
              </td>
              <td><strong><?php echo htmlspecialchars($row['room_name']); ?></strong></td>
              <td>
                <div class="issue-title"><?php echo htmlspecialchars($row['title']); ?></div>
                <div class="issue-desc"><?php echo htmlspecialchars($row['description']); ?></div>
                
                <?php if ($row['slot_date']): ?>
                  <div class="booking-info">
                    📅 Scheduled: <?php echo htmlspecialchars($row['slot_date']); ?> 
                    (<?php echo substr($row['time_start'], 0, 5); ?>-<?php echo substr($row['time_end'], 0, 5); ?>)
                  </div>
                <?php endif; ?>
              </td>
              <td>
                  <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>">
                      <?php echo htmlspecialchars($row['status']); ?>
                  </span>
                  
                  <?php if(!empty($row['admin_notice']) && $row['admin_notice'] == 1): ?>
                    <br><span class="status-badge" style="background:#dcfce7; color:#166534; margin-top:4px; border:1px solid #86efac;">
                        ✓ Tech Done
                    </span>
                  <?php endif; ?>
              </td>
              <td>
                <div class="action-btns">
                  <?php if($row['status'] != 'Resolved'): ?>
                    
                    <?php if($row['tech_status'] === 'Work Done'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="resolve_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="btn-sm btn-success">✓ Verify & Close</button>
                        </form>
                    
                    <?php elseif($row['status'] === 'In Progress'): ?>
                        <span class="btn-sm btn-disabled">⏳ Scheduled</span>
                        <a href="admin_timetable.php?room=<?php echo urlencode($row['room_id']); ?>&date=<?php echo $row['slot_date']; ?>" style="font-size:11px; text-decoration:none; margin-left:5px;">(View)</a>

                    <?php else: ?>
                        <a href="admin_timetable.php?maintenance=<?php echo $row['id']; ?>&room=<?php echo urlencode($row['room_id']); ?><?php echo $row['slot_date'] ? '&date='.urlencode($row['slot_date']) : ''; ?>" 
                           class="btn-sm btn-warning">
                          🔧 Schedule
                        </a>
                    <?php endif; ?>

                  <?php else: ?>
                    <span class="btn-sm btn-disabled">✓ Closed</span>
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

<div id="createTicketModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-top:0; color:var(--primary);">Create Maintenance Task</h3>
        <p style="color:#666; font-size:13px; margin-bottom:20px;">Add a routine task or report an issue yourself.</p>
        
        <form method="POST">
            <input type="hidden" name="action" value="create_ticket">
            
            <div class="form-group">
                <label class="form-label">Select Room</label>
                <select name="room_id" class="form-control" required>
                    <?php while($r = $room_list->fetch_assoc()): ?>
                        <option value="<?php echo $r['room_id']; ?>"><?php echo $r['name']; ?> (<?php echo $r['room_id']; ?>)</option>
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
                <select name="priority" class="form-control">
                    <option value="Normal">Normal</option>
                    <option value="High">High</option>
                    <option value="Critical">Critical</option>
                </select>
            </div>

            <div style="text-align:right; margin-top:24px;">
                <button type="button" class="btn outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn primary" style="margin-left:8px;">Create Ticket</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('createTicketModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('createTicketModal').style.display = 'none';
}
// Close if clicked outside
window.onclick = function(event) {
    var modal = document.getElementById('createTicketModal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>
</body>
</html>