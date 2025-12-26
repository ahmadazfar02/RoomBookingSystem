<?php
// admin_logbook.php - View system activity logs
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// --- 1. ACCESS CONTROL ---
$uType = trim($_SESSION['User_Type'] ?? '');
$admin_id = $_SESSION['User_ID'] ?? $_SESSION['id'] ?? null;
$username = $_SESSION['username'] ?? '';

$isTechAdmin  = (strcasecmp($uType, 'Technical Admin') === 0);
$isSuperAdmin = (strcasecmp($uType, 'SuperAdmin') === 0 || strtolower($username) === 'superadmin');
$isAdmin      = (strcasecmp($uType, 'Admin') === 0);

$allowed = ($isAdmin || $isTechAdmin || $isSuperAdmin);
if (!$admin_id || !$allowed) {
    header("Location: loginterface.html");
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin';
$admin_email = $_SESSION['Email'] ?? ($_SESSION['User_Type'] ?? 'Admin');

// --- 2. NOTIFICATION COUNTERS (NEW) ---
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
    $row = $result->fetch_row();
    $pending_approvals = intval($row[0]);

    // Admin: Count Active Problems
    $sql = "SELECT COUNT(*) FROM room_problems WHERE status != 'Resolved'";
    $result = $conn->query($sql);
    $row = $result->fetch_row();
    $active_problems = intval($row[0]);
}

// --- 3. LOGIC: FILTER & PAGINATION ---
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

// --- 4. EXPORT LOGIC ---
if (isset($_GET['export'])) {
    $filename = "admin_logs_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'Admin Name', 'Action', 'Booking Ref', 'Ticket', 'Room', 'Floor', 'Date', 'Purpose', 'Technician', 'Note/Details', 'IP Address', 'Timestamp']);
    
    $sql = "SELECT l.id, u.username, l.action, l.booking_id,
                   b.ticket, r.name AS room_name, r.floor AS room_floor, b.slot_date, b.purpose, b.technician,
                   l.note, l.ip_address, l.created_at
            FROM admin_logs l
            LEFT JOIN users u ON l.admin_id = u.id
            LEFT JOIN bookings b ON l.booking_id = b.id
            LEFT JOIN rooms r ON b.room_id = r.room_id
            WHERE $where_sql
            ORDER BY l.created_at DESC";
            
    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $rowOut = [
            $row['id'],
            $row['username'] ?? '',
            $row['action'] ?? '',
            $row['booking_id'] ?? '',
            $row['ticket'] ?? '',
            $row['room_name'] ?? '',
            $row['room_floor'] ?? '',
            $row['slot_date'] ?? '',
            $row['purpose'] ?? '',
            $row['technician'] ?? '',
            $row['note'] ?? '',
            $row['ip_address'] ?? '',
            $row['created_at'] ?? ''
        ];
        fputcsv($output, $rowOut);
    }
    fclose($output);
    exit;
}

// --- 5. FETCH DATA (Enhanced Query) ---
$count_sql = "SELECT COUNT(*) as total FROM admin_logs l LEFT JOIN users u ON l.admin_id = u.id WHERE $where_sql";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT l.id, u.username, l.action, l.booking_id, l.note, l.ip_address, l.created_at,
               b.ticket, b.slot_date, b.time_start, b.time_end, b.purpose, b.technician, b.status AS booking_status,
               r.name AS room_name, r.floor AS room_floor
        FROM admin_logs l
        LEFT JOIN users u ON l.admin_id = u.id
        LEFT JOIN bookings b ON l.booking_id = b.id
        LEFT JOIN rooms r ON b.room_id = r.room_id
        WHERE $where_sql
        ORDER BY l.created_at DESC
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$logs = [];
while ($row = $res->fetch_assoc()) $logs[] = $row;

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
<title>Admin Logbook</title>
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
    --danger: #dc2626;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: var(--bg-light); min-height: 100vh; color: var(--text-primary); }

/* NAVBAR */
.nav-bar {
  position: fixed; top: 0; left: 0; right: 0; height: var(--nav-height); background: white;
  display: flex; align-items: center; justify-content: space-between; padding: 0 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1); z-index: 1000; border-bottom: 1px solid var(--border);
}
.nav-left { display: flex; align-items: center; gap: 16px; }
.nav-logo { height: 50px; }
.nav-title h1 { font-size: 16px; font-weight: 700; color: var(--utm-maroon); margin: 0; }
.nav-title p { font-size: 11px; color: var(--text-secondary); margin: 0; }
.btn-logout { text-decoration: none; color: var(--text-secondary); font-size: 13px; font-weight: 500; padding: 8px 12px; border-radius: 6px; transition: 0.2s; }
.btn-logout:hover { background: #fef2f2; color: var(--utm-maroon); }

/* LAYOUT */
.layout { display: flex; margin-top: var(--nav-height); min-height: calc(100vh - var(--nav-height)); }

/* SIDEBAR */
.sidebar {
  width: 260px; background: white; border-right: 1px solid var(--border); padding: 24px;
  flex-shrink: 0; position: sticky; top: var(--nav-height); height: calc(100vh - var(--nav-height));
  display: flex; flex-direction: column;
}
.sidebar-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px; margin-bottom: 16px; }
.sidebar-menu { list-style: none; flex: 1; padding: 0; }
.sidebar-menu li { margin-bottom: 4px; }
.sidebar-menu a {
  display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 6px;
  text-decoration: none; color: var(--text-primary); font-size: 14px; font-weight: 500; transition: all 0.2s;
}
.sidebar-menu a:hover { background: var(--bg-light); color: var(--utm-maroon); }
.sidebar-menu a.active { background: #fef2f2; color: var(--utm-maroon); font-weight: 600; }
.sidebar-menu a i { width: 20px; text-align: center; }

/* NOTIFICATION BADGE */
.nav-badge {
    background-color: var(--danger); color: white; font-size: 10px; font-weight: 700;
    padding: 2px 8px; border-radius: 99px; margin-left: auto;
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

/* CARD & FILTERS */
.card { background: white; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid var(--border); padding: 24px; }

.filters-container { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border); }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; }
.form-control { padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; min-width: 150px; outline: none; }
.form-control:focus { border-color: var(--utm-maroon); }

.btn { padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border); background: white; color: var(--text-primary); font-weight: 600; cursor: pointer; font-size: 13px; transition: 0.2s; text-decoration: none; align-items: center; justify-content: center; display: inline-flex; gap: 6px; }
.btn-primary { background: var(--utm-maroon); color: white; border-color: var(--utm-maroon); }
.btn-primary:hover { background: var(--utm-maroon-light); color: white; border-color: var(--utm-maroon-light); }
.btn-success { background: #059669; color: white; border-color: #059669; }
.btn-success:hover { background: #047857; border-color: #047857; }

/* TABLE */
.table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 20px; }
.table { width: 100%; border-collapse: collapse; min-width: 900px; }
.table th, .table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: middle; }
.table th { background: #f8fafc; font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px; }
.table tr:last-child td { border-bottom: none; }

/* TAGS */
.tag { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-block; }
.tag.create { background: #dcfce7; color: #166534; }
.tag.approve { background: #dbeafe; color: #1e40af; }
.tag.delete { background: #fee2e2; color: #991b1b; }
.tag.reject { background: #ffedd5; color: #9a3412; }
.tag.update { background: #f3f4f6; color: #374151; }

/* PAGINATION */
.pagination { display: flex; gap: 6px; justify-content: center; margin-top: 20px; }
.page-link { padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; text-decoration: none; color: var(--text-secondary); background: white; font-size: 13px; font-weight: 500; }
.page-link.active { background: var(--utm-maroon); color: white; border-color: var(--utm-maroon); }
.page-link:hover:not(.active) { background: var(--bg-light); color: var(--utm-maroon); }

/* MODAL */
.modal-backdrop { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.modal { background: white; width: 95%; max-width: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; animation: popIn 0.2s ease-out; }
.modal-header { padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
.modal-header h3 { margin: 0; font-size: 16px; color: var(--utm-maroon); font-weight: 700; }
.modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-secondary); }
.modal-body { padding: 24px; font-size: 14px; }
.modal-row { margin-bottom: 12px; display: flex; }
.modal-label { width: 100px; font-weight: 600; color: var(--text-secondary); flex-shrink: 0; font-size: 13px; }
.modal-value { color: var(--text-primary); flex-grow: 1; font-weight: 500; }
.modal-full-note { background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid var(--border); margin-top: 8px; font-family: monospace; white-space: pre-wrap; color: #334155; font-size: 13px; }

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
              <?php if ($isTechAdmin && isset($tech_pending) && $tech_pending > 0): ?>
                  <span class="nav-badge"><?php echo $tech_pending; ?></span>
              <?php endif; ?>
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
        <li><a href="admin_logbook.php" class="active"><i class="fa-solid fa-book"></i> Logbook</a></li>
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
            <h2>System Logbook</h2>
            <p>Audit trail of administrative actions for security and tracking.</p>
        </div>
    </div>

    <div class="card">
       <form method="GET" class="filters-container">
        <div class="form-group">
            <label>Admin Name</label>
            <input type="text" name="search" class="form-control" placeholder="Search user..." value="<?php echo htmlspecialchars($search);?>">
        </div>
        <div class="form-group">
            <label>Action Type</label>
            <select name="action" class="form-control">
                <option value="all"<?php if($action_filter=='all')echo ' selected';?>>All Actions</option>
                <option value="create"<?php if($action_filter=='create')echo ' selected';?>>Create</option>
                <option value="approve"<?php if($action_filter=='approve')echo ' selected';?>>Approve</option>
                <option value="reject"<?php if($action_filter=='reject')echo ' selected';?>>Reject</option>
                <option value="delete"<?php if($action_filter=='delete')echo ' selected';?>>Delete</option>
            </select>
        </div>
        <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div class="form-group">
            <label>End Date</label>
            <input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        
        <div style="margin-left: auto; display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="admin_logbook.php" class="btn"><i class="fa-solid fa-rotate-left"></i> Reset</a>
            <a href="<?php echo getQueryLink($page) . '&export=true'; ?>" class="btn btn-success"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        </div>
       </form>

        <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th width="20%">Date / Time</th>
                  <th width="20%">Admin User</th>
                  <th width="15%">Action</th>
                  <th width="35%">Summary</th>
                  <th width="10%">Details</th>
                </tr>
              </thead>
              <tbody>
                <?php if(count($logs)>0): ?>
                  <?php foreach($logs as $log): ?>
                    <tr>
                      <td style="color:var(--text-secondary); font-size:13px;">
                          <?php echo date('d M Y, H:i', strtotime($log['created_at'])); ?>
                      </td>
                      <td><strong><?php echo htmlspecialchars($log['username']);?></strong></td>
                      <td>
                        <?php
                          $cls = 'update';
                          $act = strtolower($log['action']);
                          if(strpos($act, 'create')!==false) $cls='create';
                          elseif(strpos($act, 'approve')!==false) $cls='approve';
                          elseif(strpos($act, 'delete')!==false) $cls='delete';
                          elseif(strpos($act, 'reject')!==false) $cls='reject';
                        ?>
                        <span class="tag <?php echo $cls;?>"><?php echo htmlspecialchars($log['action']); ?></span>
                      </td>
                      <td>
                        <?php
                          // Smart summary: prefer booking details if available, otherwise show note excerpt
                          if (!empty($log['ticket']) || !empty($log['room_name'])) {
                              $summaryParts = [];
                              if (!empty($log['ticket'])) $summaryParts[] = "Ticket: <strong>" . htmlspecialchars($log['ticket']) . "</strong>";
                              if (!empty($log['room_name'])) $summaryParts[] = "Room: " . htmlspecialchars($log['room_name']);
                              if (!empty($log['slot_date'])) $summaryParts[] = "Date: " . htmlspecialchars($log['slot_date']);
                              echo implode(' • ', $summaryParts);
                              if(!empty($log['note'])) echo " <span style='color:#94a3b8; font-size:11px;'>(Note: " . htmlspecialchars(substr($log['note'],0,60)) . (strlen($log['note'])>60?'...':'') . ")</span>";
                          } else {
                              $note = $log['note'] ?? '';
                              echo strlen($note) > 60 ? htmlspecialchars(substr($note, 0, 60)) . '...' : htmlspecialchars($note);
                          }
                          if($log['booking_id']) echo " <span style='color:#94a3b8; font-size:11px;'>(Ref: #{$log['booking_id']})</span>";
                        ?>
                      </td>
                      <td>
                        <button class="btn" style="padding:4px 10px; font-size:11px;" onclick='openModal(<?php echo htmlspecialchars(json_encode($log), ENT_QUOTES, 'UTF-8'); ?>)'>
                          <i class="fa-solid fa-eye"></i> View
                        </button>
                      </td>
                    </tr>
                  <?php endforeach;?>
                  <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding: 40px; color:var(--text-secondary);">No logs found matching your criteria.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px;">
            <div style="font-size:13px; color:var(--text-secondary);">
                Showing <?php echo count($logs); ?> of <?php echo $total_rows; ?> records
            </div>
            
            <?php if ($total_pages > 1): ?>
              <div class="pagination">
                <?php if ($page > 1):?>
                    <a href="<?php echo getQueryLink($page-1); ?>" class="page-link"><i class="fa-solid fa-chevron-left"></i> Prev</a>
                <?php endif; ?>

                <?php
                $start_p = max(1, $page - 2);
                $end_p = min($total_pages, $page + 2);
                for ($i = $start_p; $i <= $end_p; $i++): ?>
                    <a href="<?php echo getQueryLink($i); ?>" class="page-link <?php if($i == $page) echo 'active'; ?>">
                      <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                  <a href="<?php echo getQueryLink($page + 1); ?>" class="page-link">Next <i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
        </div>
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
      <div class="modal-row"><div class="modal-label">Log ID:</div><div class="modal-value" id="m_id"></div></div>
      <div class="modal-row"><div class="modal-label">Date/Time:</div><div class="modal-value" id="m_date"></div></div>
      <div class="modal-row"><div class="modal-label">Admin User:</div><div class="modal-value" id="m_admin"></div></div>
      <div class="modal-row"><div class="modal-label">Action:</div><div class="modal-value" id="m_action" style="font-weight:bold; text-transform:uppercase;"></div></div>

      <div id="booking_details" style="background:#f1f5f9; padding:12px; border-radius:8px; margin:12px 0; display:none;">
        <div class="modal-row"><div class="modal-label">Ticket:</div><div class="modal-value" id="m_ticket"></div></div>
        <div class="modal-row"><div class="modal-label">Room:</div><div class="modal-value" id="m_room"></div></div>
        <div class="modal-row"><div class="modal-label">Floor:</div><div class="modal-value" id="m_floor"></div></div>
        <div class="modal-row"><div class="modal-label">Date/Time:</div><div class="modal-value" id="m_datetime"></div></div>
        <div class="modal-row"><div class="modal-label">Purpose:</div><div class="modal-value" id="m_purpose"></div></div>
        <div class="modal-row"><div class="modal-label">Technician:</div><div class="modal-value" id="m_tech"></div></div>
        <div class="modal-row"><div class="modal-label">Status:</div><div class="modal-value"><span id="m_status" class="status-pill"></span></div></div>
      </div>

      <div style="margin-top:15px;">
        <div class="modal-label" style="margin-bottom:6px;">Full Details / Note:</div>
        <div class="modal-full-note" id="m_note"></div>
      </div>
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
function openModal(log){
  document.getElementById('m_id').textContent = '#' + (log.id ?? '');
  document.getElementById('m_date').textContent = log.created_at ?? '';
  document.getElementById('m_admin').textContent = log.username ?? '';
  document.getElementById('m_action').textContent = log.action ?? '';
  document.getElementById('m_note').textContent = log.note ?? '';

  const detDiv = document.getElementById('booking_details');
  if (log.booking_id || log.ticket) {
      detDiv.style.display = 'block';
      document.getElementById('m_ticket').textContent = log.ticket ?? '-';
      let roomText = log.room_name ?? '-';
      document.getElementById('m_room').textContent = roomText;
      document.getElementById('m_floor').textContent = log.room_floor ?? '-';
      let ts = '';
      if (log.slot_date) {
          ts = log.slot_date;
          if (log.time_start || log.time_end) {
              ts += ' (' + (log.time_start ? log.time_start.slice(0,5) : '') + ' - ' + (log.time_end ? log.time_end.slice(0,5) : '') + ')';
          }
      }
      document.getElementById('m_datetime').textContent = ts || '-';
      document.getElementById('m_purpose').textContent = log.purpose ?? '-';
      document.getElementById('m_tech').textContent = log.technician ?? '-';
      document.getElementById('m_status').textContent = log.booking_status ?? '-';
  } else {
      detDiv.style.display = 'none';
  }

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