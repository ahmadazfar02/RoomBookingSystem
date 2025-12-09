<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
// Access control
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true ||
   strcasecmp(trim($_SESSION["User_Type"]), 'Admin') != 0) {
    header("location: loginterface.html");
    exit;
}



$admin_name = $_SESSION['Fullname'] ?? 'Admin'; 
$admin_email = $_SESSION['Email'] ?? 'Admin';
$username = $_SESSION['username'] ?? 'superadmin';


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
    GROUP_CONCAT(CONCAT(b.time_start,'-',b.time_end) ORDER BY b.time_start ASC SEPARATOR ', ') AS time_slots,
    b.slot_date,
    r.room_id AS room_no,
    r.name AS room_name,
    u.username AS requested_by,
    b.purpose,
    b.description,
    MAX(b.ticket) AS ticket,
    b.status
FROM bookings b
JOIN rooms r ON b.room_id = r.room_id
JOIN users u ON b.user_id = u.id
$where_sql
GROUP BY b.session_id, r.room_id, b.slot_date, r.name, u.username, b.purpose, b.description, b.status
ORDER BY b.slot_date DESC
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
<title>Reservation Requests — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --primary-light: #dbeafe;
    --success: #059669;
    --danger: #dc2626;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --shadow-sm: 0 4px 12px rgba(18, 38, 63, 0.08);
    --nav-height: 80px; /* height of fixed navbar */
  }

  *{box-sizing:border-box}
  body{
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    min-height: 100vh;
    padding: 0;
    margin: 0;
  }

  /* Fixed top nav */
  .nav-bar {
    background: white;
    padding: 16px 24px;
    box-shadow: var(--shadow-md);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    height: 80px;
  }
  .nav-logo { height:50px; width:auto; display:block; }

  /* layout: container centered and below navbar */
  .layout {
    width: 100%;
    max-width: 2000px;   /* allow up to 2000px */
    padding: 24px;
    gap: 24px;
    margin: 100px auto 0; /* keep centered and below fixed navbar */
    display: flex;
    align-items: flex-start;
  }

  /* Sidebar */
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
    top: 100px; /* Sticks 100px from top of viewport */
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

  /* Main area */
  .main {
    flex:1;
    min-width:0;
  }

  /* Header Card */
  .header-card {
    background: white;
    border-radius: 12px;
    padding: 24px 32px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-md);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .header-title {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  
  .header-title h1 {
    font-size: 24px;
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
  }

  
  .header-badge {
    background: var(--primary-light);
    color: var(--primary);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .header-subtitle {
    font-size: 14px;
    color: var(--gray-600);
    margin-top: 4px;
  }

  .card {
    background:#fff;
    border-radius:12px;
    padding:18px;
    box-shadow: var(--shadow-sm);
  }

  /* Controls & Tabs */
  .top-controls { display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap; margin-bottom:14px; }
  .left-controls { display:flex; gap:12px; align-items:center; }
  .room-select { padding:10px 12px; border-radius:8px; border:2px solid var(--gray-300); font-weight:600; background:#fff; }
  .btn { padding:10px 14px; border-radius:8px; border:0; cursor:pointer; font-weight:700; }
  .btn.primary { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; }
  .btn.outline { background:#fff; border:2px solid var(--gray-300); color:var(--gray-700); }

  .tabs { display:flex; gap:8px; margin-bottom:12px; }
  .tab { padding:8px 12px; border-radius:8px; background:var(--gray-50); border:1px solid var(--gray-200); text-decoration:none; color:var(--gray-700); font-weight:600; }
  .tab.active { background:linear-gradient(135deg,var(--primary-light),#cfe0ff); color:var(--primary); }

  /* Table */
 .table-wrap {
  overflow: auto;
  border-radius:10px;
  border:1px solid var(--gray-200);
  margin-top:8px;
  /* ensure the scroll container establishes the stacking context for sticky */
  position: relative;
  -webkit-overflow-scrolling: touch;
}

/* base table */
table.grid {
  width:100%;
  border-collapse:collapse;
  min-width:980px;
  background:#fff;
}

/* cells */
table.grid th,
table.grid td {
  padding:12px 10px;
  border-bottom:1px solid var(--gray-100);
  text-align:left;
  vertical-align:middle;
  background: #fff; /* ensure opaque background when sticky overlaps */
}

/* header: make sticky relative to the scrolling container (.table-wrap) */
table.grid thead th {
  background: linear-gradient(180deg, var(--gray-100) 0%, var(--gray-50) 100%);
  font-weight:700;
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:0.5px;
  position: sticky;
  top: 0; /* stick to top of the scroll container */
  z-index: 140; /* above body cells */
}

/* first column: make both header and body cells sticky to the left */
table.grid th:first-child,
table.grid td:first-child {
  position: sticky;
  left: 0;
  z-index: 150; /* higher than normal cells; header's z-index (140) will take precedence for the header cell because th has its own higher z-index in thead */
  background: #fff; /* important to avoid text show-through */
  box-shadow: 2px 0 6px rgba(2,6,23,0.05); /* subtle separator when sticky */
}

/* if header first-cell needs to be on top of other header cells */
table.grid thead th:first-child {
  z-index: 160;
}

  /* status badges */
  .status { display:inline-block; padding:6px 10px; border-radius:8px; font-weight:700; font-size:13px; }
  .status.pending { background:linear-gradient(135deg,#fff7ed,#fff1c2); color:#92400e; }
  .status.booked { background:linear-gradient(135deg,#ecfdf5,#dcfce7); color:var(--success); }
  .status.rejected, .status.cancelled { background:linear-gradient(135deg,#fff1f2,#fee2e2); color:var(--danger); }

  /* action buttons */
  .actions button { margin-right:8px; padding:8px 10px; border-radius:8px; border:0; cursor:pointer; font-weight:700; }
  .actions .approve { background:linear-gradient(135deg,#10b981,#059669); color:#fff; }
  .actions .reject { background:linear-gradient(135deg,#f97316,#ef4444); color:#fff; }
  .meta { color:var(--gray-600); font-size:13px; }

  /* Modals */
  .modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; background: rgba(2,6,23,0.45); z-index:1500; }
  .modal.show { display:flex; }
  .modal-card { width:460px; max-width:94%; background:#fff; border-radius:12px; padding:18px; box-shadow:0 12px 40px rgba(2,6,23,0.25); }
  .modal-card h3 { margin:0 0 8px 0; }
  .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:14px; }

  /* Responsive */
  @media (max-width:1200px) {
    .sidebar { display:none; }
    .layout { margin-left:18px; margin-right:18px; display:block; }
  }
  @media (max-width:720px) {
    table.grid { min-width:860px; }
  }
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav-bar" role="navigation" aria-label="Main navigation">
  <img class="nav-logo" src="../assets/images/utmlogo.png" alt="UTM Logo">
  <div style="flex:1"></div>
</nav>

<!-- LAYOUT -->
<div class="layout" role="main">

  <!-- Sidebar (Sticky) -->
  <aside class="sidebar">
    <div class="sidebar-title">Main Menu</div>
    <ul class="sidebar-menu">
      <li><a href="index-admin.php">Dashboard</a></li>
      <li><a href="reservation_request.php" class="active">Reservation Request</a></li>
      <li><a href="admin_timetable.php">Regular Timetable</a></li>
      <li><a href="admin_recurring.php">Recurring Templates</a></li>
         <!-- Only SuperAdmin can see this -->
      <?php if ($username === 'superadmin'): ?>
          <li><a href="manage_users.php">Manage Users</a></li>
      <?php endif; ?>
    </ul>

    <div class="sidebar-profile">
      <div class="profile-icon"><?php echo strtoupper(substr($admin_name,0,1)); ?></div>
      <div class="profile-info">
        <div class="profile-name"> <?php echo htmlspecialchars($admin_name); ?></div>
        <div class="profile-email"><?php echo htmlspecialchars($admin_email); ?></div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main">

   <!-- Header (Modified from container-fluid content) -->
    <div class="header-card">
      <div class="header-title">
        <div>
          <h1>Reservation Requests</h1>
          <div class="header-subtitle">Manage incoming bookings</div>
        </div>
        <span class="header-badge">Admin</span>
      </div>
    </div>

    <section class="card">

      <div class="top-controls">
        <div class="left-controls">
          <div class="tabs" role="tablist" aria-label="Filters">
            <?php foreach ($allowed_filters as $tab): ?>
              <a class="tab <?php echo ($filter==$tab)?'active':'';?>" href="?filter=<?php echo urlencode($tab); ?>&search_room=<?php echo urlencode($search_room); ?>"><?php echo ucfirst($tab); ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="right-controls" style="display:flex; gap:12px; align-items:center;">
          <form method="GET" style="display:flex; gap:10px; align-items:center; margin:0;">
            <select name="search_room" class="room-select" aria-label="Select room">
              <option value="">-- All Rooms --</option>
              <?php foreach($rooms as $room): ?>
                <option value="<?php echo htmlspecialchars($room['room_id']); ?>" <?php echo ($search_room == $room['room_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($room['name'] . " ({$room['room_id']})"); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <button type="submit" class="btn primary">Search</button>
          </form>
        </div>
      </div>

      <div class="table-wrap" aria-live="polite">
        <table class="grid" role="table" aria-label="Reservation requests table">
          <thead>
            <tr>
              <th>Ticket</th>
              <th>Room Name</th>
              <th>Room No</th>
              <th>Requested By</th>
              <th>Purpose</th>
              <th>Description</th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
              <?php while($row = $result->fetch_assoc()): ?>
                <tr data-session="<?php echo htmlspecialchars($row['session_id']); ?>">
                  <td><?php echo htmlspecialchars($row['ticket'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                  <td><?php echo htmlspecialchars($row['room_no']); ?></td>
                  <td><?php echo htmlspecialchars($row['requested_by']); ?></td>
                  <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                  <td><?php echo htmlspecialchars($row['description']); ?></td>
                  <td><?php echo htmlspecialchars($row['slot_date']); ?></td>
                  <td><?php echo htmlspecialchars($row['time_slots']); ?></td>
                  <td><span class="status <?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst(htmlspecialchars($row['status'])); ?></span></td>
                  <td class="actions">
                    <?php if($row['status']=='pending'): ?>
                      <button type="button" class="approve" data-action="approve" data-session="<?php echo htmlspecialchars($row['session_id']); ?>">Approve</button>
                      <button type="button" class="reject" data-action="reject" data-session="<?php echo htmlspecialchars($row['session_id']); ?>">Reject</button>
                    <?php else: ?>
                      <span class="meta">N/A</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="10" style="text-align:center;padding:22px">No bookings found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </section>
  </div>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="modal" aria-hidden="true" role="dialog">
  <div class="modal-card" role="document" aria-modal="true">
    <h3>Approve Booking</h3>
    <p>Are you sure you want to approve this booking?</p>
    <div class="modal-actions">
      <button id="approveConfirm" type="button" class="btn primary">Yes, Approve</button>
      <button type="button" class="btn outline" onclick="closeModal('approveModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal" aria-hidden="true" role="dialog">
  <div class="modal-card" role="document" aria-modal="true">
    <h3>Reject Booking</h3>
    <p>Please enter the reason for rejection:</p>
    <textarea id="rejectReason" style="width:100%;height:110px;border:1px solid var(--gray-200);padding:10px;border-radius:8px"></textarea>
    <div class="modal-actions">
      <button id="rejectConfirm" type="button" class="btn primary">Submit Rejection</button>
      <button type="button" class="btn outline" onclick="closeModal('rejectModal')">Cancel</button>
    </div>
  </div>
</div>

<script>
/* Client behaviour:
   - Delegated click handling for approve/reject
   - Safe extraction of session id from data attributes
   - Sends POST to process_request.php and expects JSON { success: true, message: '' }
   - Updates row UI in-place on success (no full reload)
   - If you prefer full-page reload after action, change the success branch to `location.reload()`
*/

document.addEventListener('DOMContentLoaded', () => {
  let currentSession = null;
  const demoMode = false; // set true only for testing without backend

  // normalize event target to element (handle text nodes)
  function toElement(node){ let el = node; while(el && el.nodeType !== 1) el = el.parentNode; return el; }

  // delegate clicks for approve/reject
  document.body.addEventListener('click', (e) => {
    const el = toElement(e.target);
    if (!el) return;
    const btn = el.closest('button[data-action], button.approve, button.reject');
    if (!btn) return;

    const action = btn.getAttribute('data-action') || (btn.classList.contains('approve') ? 'approve' : (btn.classList.contains('reject') ? 'reject' : null));
    const session = btn.getAttribute('data-session') || btn.closest('tr')?.dataset?.session;
    if (!action || !session) return;

    if (action === 'approve') openApprove(session);
    if (action === 'reject') openReject(session);
  });

  // attach direct handlers to existing buttons (fallback)
  function attachButtons(){
    document.querySelectorAll('button.approve').forEach(b=>{
      if (b._bound) return;
      b.addEventListener('click', (ev)=> { ev.stopPropagation(); openApprove(b.dataset.session || b.getAttribute('data-session')); });
      b._bound = true;
    });
    document.querySelectorAll('button.reject').forEach(b=>{
      if (b._bound) return;
      b.addEventListener('click', (ev)=> { ev.stopPropagation(); openReject(b.dataset.session || b.getAttribute('data-session')); });
      b._bound = true;
    });
  }
  attachButtons();

  // show/hide modal
  function openApprove(sessionId){
    currentSession = String(sessionId);
    const m = document.getElementById('approveModal');
    if (!m) return;
    m.classList.add('show');
    m.setAttribute('aria-hidden','false');
  }
  function openReject(sessionId){
    currentSession = String(sessionId);
    document.getElementById('rejectReason').value = '';
    const m = document.getElementById('rejectModal');
    if (!m) return;
    m.classList.add('show');
    m.setAttribute('aria-hidden','false');
  }
  window.closeModal = function(id){
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('show');
    el.setAttribute('aria-hidden','true');
    currentSession = null;
  };

  // backend posting
  async function postAction(fd){
    if (demoMode) {
      await new Promise(r=>setTimeout(r,600));
      return { success: true, message: 'demo' };
    }
    try {
      const res = await fetch('process_request.php', { method:'POST', body: fd, credentials: 'same-origin' });
      const text = await res.text();
      // Try parse JSON
      try {
        const json = JSON.parse(text);
        return json;
      } catch(parseErr) {
        console.error('process_request.php returned non-JSON:', text);
        return { success:false, message: 'Invalid server response (not JSON). See console for raw response.' };
      }
    } catch (err) {
      console.error('postAction network error', err);
      return { success:false, message: err.message || 'Network error' };
    }
  }


  // approve confirm
  const approveBtn = document.getElementById('approveConfirm');
  if (approveBtn) {
    approveBtn.addEventListener('click', async function(){
      if (!currentSession) return alert('No session selected');
      this.disabled = true; this.textContent = 'Approving...';
      const fd = new FormData(); fd.append('action','approve'); fd.append('session_id', currentSession);
      const r = await postAction(fd);
      this.disabled = false; this.textContent = 'Yes, Approve';
      if (r.success) {
        // update row: set status to Booked and remove actions
        const row = document.querySelector('tr[data-session="' + CSS.escape(currentSession) + '"]');
        if (row) {
          const statusEl = row.querySelector('.status');
          if (statusEl) { statusEl.className = 'status booked'; statusEl.textContent = 'Booked'; }
          const actions = row.querySelector('.actions'); if (actions) actions.innerHTML = '<span class="meta">N/A</span>';
        }
        closeModal('approveModal');
      } else {
        alert('Error: ' + (r.message || 'Unable to approve'));
      }
    });
  }

  // reject confirm
  const rejectBtn = document.getElementById('rejectConfirm');
  if (rejectBtn) {
    rejectBtn.addEventListener('click', async function(){
      if (!currentSession) return alert('No session selected');
      const reason = document.getElementById('rejectReason').value.trim();
      if (reason.length < 3) { alert('Please enter a short reason'); return; }
      this.disabled = true; this.textContent = 'Submitting...';
      const fd = new FormData(); fd.append('action','reject'); fd.append('session_id', currentSession); fd.append('reason', reason);
      const r = await postAction(fd);
      this.disabled = false; this.textContent = 'Submit Rejection';
      if (r.success) {
        const row = document.querySelector('tr[data-session="' + CSS.escape(currentSession) + '"]');
        if (row) {
          const statusEl = row.querySelector('.status');
          if (statusEl) { statusEl.className = 'status rejected'; statusEl.textContent = 'Rejected'; }
          const actions = row.querySelector('.actions'); if (actions) actions.innerHTML = '<span class="meta">N/A</span>';
        }
        closeModal('rejectModal');
      } else {
        alert('Error: ' + (r.message || 'Unable to reject'));
      }
    });
  }

}); // DOMContentLoaded
</script>
</body>
</html>
