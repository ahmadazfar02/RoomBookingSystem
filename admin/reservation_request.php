<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// Access control
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true ||
   strcasecmp(trim($_SESSION["User_Type"]), 'Admin') != 0) {
    header("location: ../loginterface.html");
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
    --warning: #f59e0b;
    --purple: #7c3aed;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  body{
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
  }
  .nav-bar {
    background: white;
    padding: 16px 24px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 1000;
    height: 80px;
    display: flex;
    align-items: center;
  }
  .nav-logo { height:50px; }
  .layout {
    width: 100%; max-width: 2000px;
    padding: 24px; gap: 24px;
    margin: 100px auto 0;
    display: flex;
  }
  .sidebar {
    width: 260px; background: white; border-radius: 12px; padding: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    position: sticky; top: 100px;
  }
  .sidebar-title { font-size: 14px; font-weight: 700; text-transform: uppercase; color: var(--gray-600); margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid var(--gray-200); }
  .sidebar-menu { list-style: none; }
  .sidebar-menu li { margin-bottom: 8px; }
  .sidebar-menu a { display: flex; padding: 12px 16px; border-radius: 8px; text-decoration: none; color: var(--gray-700); font-size: 14px; font-weight: 500; }
  .sidebar-menu a:hover { background: var(--gray-100); color: var(--primary); }
  .sidebar-menu a.active { background: var(--primary-light); color: var(--primary); font-weight: 600; }
  .sidebar-profile { margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--gray-200); display: flex; gap: 12px; }
  .profile-icon { width: 36px; height: 36px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 700; }
  .profile-info { font-size: 13px; }
  .profile-name { font-weight: 600; color: var(--gray-800); }
  .profile-email { font-size: 11px; color: var(--gray-600); }
  .main { flex:1; }
  .header-card { background: white; border-radius: 12px; padding: 24px 32px; margin-bottom: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
  .header-title h1 { font-size: 24px; font-weight: 700; color: var(--gray-800); }
  .header-badge { background: var(--primary-light); color: var(--primary); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
  .header-subtitle { font-size: 14px; color: var(--gray-600); margin-top: 4px; }
  .card { background:#fff; border-radius:12px; padding:20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
  .top-controls { display:flex; gap:12px; justify-content:space-between; flex-wrap:wrap; margin-bottom:16px; }
  .room-select { padding:10px 12px; border-radius:8px; border:2px solid var(--gray-300); font-weight:600; background:#fff; }
  .btn { padding:10px 16px; border-radius:8px; border:0; cursor:pointer; font-weight:700; }
  .btn.primary { background:var(--primary); color:#fff; }
  .btn.outline { background:#fff; border:2px solid var(--gray-300); color:var(--gray-700); }
  .tabs { display:flex; gap:8px; margin-bottom:12px; }
  .tab { padding:8px 12px; border-radius:8px; background:var(--gray-50); border:1px solid var(--gray-200); text-decoration:none; color:var(--gray-700); font-weight:600; }
  .tab.active { background:var(--primary-light); color:var(--primary); }
  .table-wrap-list { overflow: auto; border-radius:10px; border:1px solid var(--gray-200); }
  table.list-table { width:100%; border-collapse:collapse; min-width:980px; background:#fff; }
  table.list-table th, table.list-table td { padding:12px 10px; border-bottom:1px solid var(--gray-100); text-align:left; }
  table.list-table th { background: var(--gray-100); font-weight:700; font-size:12px; text-transform:uppercase; color:var(--gray-700); }
  .status { display:inline-block; padding:6px 10px; border-radius:8px; font-weight:700; font-size:13px; }
  .status.pending { background:#fef3c7; color:#92400e; }
  .status.booked { background:#d1fae5; color:var(--success); }
  .status.rejected, .status.cancelled { background:#fee2e2; color:var(--danger); }
  .actions button { margin-right:8px; padding:8px 12px; border-radius:8px; border:0; cursor:pointer; font-weight:700; }
  .actions .approve { background:var(--success); color:#fff; }
  .actions .reject { background:var(--danger); color:#fff; }
  .btn-view-time { background: white; border: 2px solid var(--gray-300); color: var(--gray-700); padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; }
  .btn-view-time:hover { background: var(--gray-100); }
  
  /* MODAL */
  .modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; background: rgba(0,0,0,0.5); z-index:2000; }
  .modal.show { display:flex; }
  .modal-card { width:90%; max-width:1400px; max-height:90vh; background:#fff; border-radius:12px; padding:24px; box-shadow:0 20px 60px rgba(0,0,0,0.3); overflow-y:auto; }
  .modal-header-custom { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--gray-200); }
  .modal-header-custom h3 { margin: 0; font-size: 20px; color: var(--primary); }
  .btn-close-modal { background: none; border: none; font-size: 32px; cursor: pointer; color: var(--gray-600); padding: 0; width: 36px; height: 36px; line-height: 1; }
  .btn-close-modal:hover { color: var(--danger); }
  .legend-row { display: flex; gap: 15px; flex-wrap: wrap; padding: 12px; background: var(--gray-50); border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
  .legend-row span { display: inline-flex; align-items: center; gap: 6px; }
  .legend-box { width: 16px; height: 16px; border-radius: 3px; border: 1px solid var(--gray-300); }
  
  /* TABLE */
  .table-wrap { overflow: auto; border: 1px solid var(--gray-200); border-radius: 10px; max-height: 600px; }
  table.grid { width: 100%; border-collapse: collapse; background: #fff; min-width: 1400px; }
  table.grid th, table.grid td { padding: 10px 8px; border: 1px solid var(--gray-200); text-align: center; font-size: 12px; }
  table.grid thead th { background: var(--gray-100); font-weight: 700; text-transform: uppercase; position: sticky; top: 0; z-index: 10; }
  table.grid th:first-child, table.grid td:first-child { position: sticky; left: 0; background: #e0f2fe; font-weight: 700; min-width: 120px; text-align: left; padding-left: 12px; z-index: 5; }
  table.grid thead th:first-child { z-index: 15; background: var(--gray-100); }
  .slot { height: 60px; min-width: 90px; position: relative; }
  .available { background: #dcfce7; }
  .booked { background: #fecaca; color: var(--danger); font-weight: 600; }
  .pending { background: #fde68a; color: #92400e; font-weight: 600; }
  .maintenance { background: #fdba74; color: #9a3412; font-weight: 600; }
  .recurring { background: #c7d2fe; color: var(--purple); font-weight: 700; border-left: 3px solid var(--purple); }
  .past { background: var(--gray-200); color: var(--gray-600); opacity: 0.6; }
  .requested { background: #dbeafe !important; border: 2px dashed #2563eb !important; color: #1e40af; font-weight: 700; animation: pulse 1.5s infinite; }
  .conflict { background: #ef4444 !important; color: white !important; font-weight: 800; border: 2px solid #b91c1c !important; }
  @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
  .cell-content { font-size: 11px; line-height: 1.3; padding: 4px; }
  .day-head { font-weight: 700; font-size: 13px; color: #1e40af; }
  .day-name { font-size: 11px; color: var(--gray-600); margin-top: 2px; }
  .modal-actions { margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--gray-200); text-align: right; }
  @media (max-width:1200px) { .sidebar { display:none; } }
</style>
</head>
<body>

<nav class="nav-bar">
  <img class="nav-logo" src="../assets/images/utmlogo.png" alt="UTM Logo">
</nav>

<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-title">Main Menu</div>
    <ul class="sidebar-menu">
      <li><a href="index-admin.php">Dashboard</a></li>
      <li><a href="reservation_request.php" class="active">Reservation Request</a></li>
      <li><a href="admin_timetable.php">Regular Timetable</a></li>
      <li><a href="admin_recurring.php">Recurring Templates</a></li>
      <?php if ($username === 'superadmin'): ?>
          <li><a href="manage_users.php">Manage Users</a></li>
      <?php endif; ?>
      <li><a href="admin_logbook.php">Logbook</a></li>
    </ul>
    <div class="sidebar-profile">
      <div class="profile-icon"><?php echo strtoupper(substr($admin_name,0,1)); ?></div>
      <div class="profile-info">
        <div class="profile-name"><?php echo htmlspecialchars($admin_name); ?></div>
        <div class="profile-email"><?php echo htmlspecialchars($admin_email); ?></div>
      </div>
    </div>
  </aside>

  <div class="main">
    <div class="header-card">
      <div>
        <h1>Reservation Requests</h1>
        <div class="header-subtitle">Manage incoming bookings</div>
      </div>
      <span class="header-badge">Admin</span>
    </div>

    <section class="card">
      <div class="top-controls">
        <div class="tabs">
          <?php foreach ($allowed_filters as $tab): ?>
            <a class="tab <?php echo ($filter==$tab)?'active':'';?>" href="?filter=<?php echo $tab; ?>&search_room=<?php echo urlencode($search_room); ?>"><?php echo ucfirst($tab); ?></a>
          <?php endforeach; ?>
        </div>
        <form method="GET" style="display:flex; gap:10px;">
          <select name="search_room" class="room-select">
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

      <div class="table-wrap-list">
        <table class="list-table">
          <thead>
            <tr>
              <th>Ticket</th>
              <th>Room</th>
              <th>Requested By</th>
              <th>Purpose</th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
              <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['ticket'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                  <td><?php echo htmlspecialchars($row['requested_by']); ?></td>
                  <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                  <td><?php echo htmlspecialchars($row['slot_date']); ?></td>
                  <td><?php echo htmlspecialchars($row['time_slots']); ?></td>
                  <td><span class="status <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                  <td class="actions">
                      <button class="btn-view-time" onclick='openTimetable(<?php echo json_encode($row['room_no']); ?>, <?php echo json_encode($row['room_name']); ?>, <?php echo json_encode($row['slot_date']); ?>, <?php echo json_encode($row['time_slots']); ?>)'>📅 View</button>
                      <?php if($row['status']=='pending'): ?>
                        <button class="approve" data-session="<?php echo htmlspecialchars($row['session_id']); ?>">Approve</button>
                        <button class="reject" data-session="<?php echo htmlspecialchars($row['session_id']); ?>">Reject</button>
                      <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="8" style="text-align:center;padding:20px">No bookings found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>

<div id="timetableModal" class="modal">
  <div class="modal-card">
    <div class="modal-header-custom">
      <h3 id="ttModalTitle">Room Schedule</h3>
      <button class="btn-close-modal" onclick="closeModal('timetableModal')">&times;</button>
    </div>
    
    <div class="legend-row">
      <span><span class="legend-box" style="background:#dbeafe; border:2px dashed #2563eb;"></span> Requested</span>
      <span><span class="legend-box" style="background:#dcfce7;"></span> Available</span>
      <span><span class="legend-box" style="background:#fecaca;"></span> Booked</span>
      <span><span class="legend-box" style="background:#fde68a;"></span> Pending</span>
      <span><span class="legend-box" style="background:#fdba74;"></span> Maintenance</span>
      <span><span class="legend-box" style="background:#c7d2fe;"></span> Recurring</span>
      <span><span class="legend-box" style="background:#ef4444;"></span> CONFLICT!</span>
    </div>

    <div id="timetableContainer" class="table-wrap"></div>

    <div class="modal-actions">
      <button class="btn outline" onclick="closeModal('timetableModal')">Close</button>
    </div>
  </div>
</div>

<div id="approveModal" class="modal">
  <div class="modal-card" style="max-width:500px;">
    <h3>Approve Booking</h3>
    <p>Are you sure you want to approve this booking?</p>
    <div class="modal-actions">
      <button id="approveConfirm" class="btn primary">Yes, Approve</button>
      <button class="btn outline" onclick="closeModal('approveModal')">Cancel</button>
    </div>
  </div>
</div>

<div id="rejectModal" class="modal">
  <div class="modal-card" style="max-width:500px;">
    <h3>Reject Booking</h3>
    <p>Please enter the reason for rejection:</p>
    <textarea id="rejectReason" style="width:100%;height:100px;border:1px solid var(--gray-200);padding:10px;border-radius:8px;margin:10px 0;"></textarea>
    <div class="modal-actions">
      <button id="rejectConfirm" class="btn primary">Submit Rejection</button>
      <button class="btn outline" onclick="closeModal('rejectModal')">Cancel</button>
    </div>
  </div>
</div>

<script>
  // Complete Timetable Modal JavaScript for reservation_request.php

const TIME_SLOTS = [
    "08:00-08:50", "09:00-09:50", "10:00-10:50", "11:00-11:50", 
    "12:00-12:50", "13:00-13:50", "14:00-14:50", "15:00-15:50", 
    "16:00-16:50", "17:00-17:50", "18:00-18:50", "19:00-19:50", 
    "20:00-20:50", "21:00-21:50", "22:00-22:50", "23:00-23:50"
];

function escapeHtml(s) {
  if (!s) return '';
  return String(s).replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}

async function openTimetable(roomId, roomName, reqDate, reqTimeStr) {
    const modal = document.getElementById('timetableModal');
    const container = document.getElementById('timetableContainer');
    const title = document.getElementById('ttModalTitle');
    
    // Calculate week range (Monday to Sunday)
    const reqDateObj = new Date(reqDate + 'T12:00:00');
    const dayOfWeek = reqDateObj.getDay();
    const monday = new Date(reqDateObj);
    monday.setDate(reqDateObj.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    
    const startISO = monday.toISOString().split('T')[0];
    const endISO = sunday.toISOString().split('T')[0];
    
    title.textContent = `${roomName} - Week of ${startISO}`;
    container.innerHTML = '<div style="padding:40px; text-align:center; color:#666;">Loading schedule...</div>';
    
    modal.classList.add('show');

    try {
        // Fetch data using same endpoint as admin_timetable.php
        const res = await fetch(`admin_timetable.php?endpoint=bookings&room=${encodeURIComponent(roomId)}&start=${startISO}&end=${endISO}`);
        const data = await res.json();
        
        if (!data.success) {
            throw new Error(data.msg || "Failed to load schedule");
        }

        // Parse requested slots from time string like "08:00:00-08:50:00, 09:00:00-09:50:00"
        const reqSlots = [];
        reqTimeStr.split(',').forEach(p => {
            const trimmed = p.trim();
            const [start, end] = trimmed.split('-');
            if (start && end) {
                reqSlots.push({
                    start: start.substring(0, 5), // "08:00"
                    end: end.substring(0, 5)       // "08:50"
                });
            }
        });

        console.log('Requested slots:', reqSlots);
        console.log('Bookings:', data.bookings);

        // Build table
        let html = '<table class="grid">';
        
        // Header row
        html += '<thead><tr>';
        html += '<th>Date / Time</th>';
        TIME_SLOTS.forEach(ts => {
            html += `<th>${ts.replace('-', '<br>')}</th>`;
        });
        html += '</tr></thead>';

        // Body rows (7 days)
        html += '<tbody>';
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        for (let d = 0; d < 7; d++) {
            const currDate = new Date(monday);
            currDate.setDate(monday.getDate() + d);
            const isoDate = currDate.toISOString().split('T')[0];
            const dayName = days[currDate.getDay()];

            html += '<tr>';
            // First column - Date
            html += `<td>
                <div class="day-head">${isoDate}</div>
                <div class="day-name">${dayName}</div>
            </td>`;

            // Time slots
            TIME_SLOTS.forEach(timeSlot => {
                const [slotStart, slotEnd] = timeSlot.split('-');
                let statusClass = 'available';
                let content = '';
                let isRequested = false;
                let isOccupied = false;

                // 1. Check if this is a requested slot
                if (isoDate === reqDate) {
                    for (const req of reqSlots) {
                        // Check if time slot overlaps with request
                        if (slotStart >= req.start && slotStart < req.end) {
                            statusClass = 'requested';
                            content = '<div class="cell-content"><strong>REQUEST</strong></div>';
                            isRequested = true;
                            break;
                        }
                    }
                }

                // 2. Check existing bookings
                for (const b of data.bookings) {
                    if (b.slot_date !== isoDate) continue;
                    
                    // --- FIX 1: IGNORE CANCELLED/REJECTED ---
                    if (['cancelled', 'rejected', 'deleted'].includes(b.status)) continue;

                    const bookStart = b.time_start;
                    const bookEnd = b.time_end;
                    
                    // Check if slot overlaps with booking
                    if (slotStart >= bookStart && slotStart < bookEnd) {
                        
                        // --- FIX 2: ONLY "HARD" BOOKINGS CAUSE CONFLICTS ---
                        // We only set isOccupied if the status is 'booked', 'approved', or 'maintenance'.
                        // We IGNORE 'pending' here because 'isRequested' (above) already handles the visual for pending requests.
                        const isHardBooking = ['booked', 'approved', 'maintenance'].includes(b.status) || b.recurring;

                        if (isHardBooking) {
                            isOccupied = true;

                            // Check for conflict with request
                            if (isRequested) {
                                statusClass = 'conflict';
                                content = '<div class="cell-content"><strong>⚠️ CONFLICT</strong></div>';
                            } else {
                                // Regular booking display
                                if (b.recurring) {
                                    statusClass = 'recurring';
                                    content = `<div class="cell-content"><strong>Recurring</strong><br>${escapeHtml(b.purpose || '')}</div>`;
                                } else {
                                    statusClass = b.status || 'booked';
                                    let displayText = escapeHtml(b.purpose || 'Occupied');
                                    if (b.status === 'maintenance') {
                                        displayText = '🔧 ' + displayText;
                                    }
                                    content = `<div class="cell-content"><strong>${displayText}</strong></div>`;
                                }
                            }
                        }
                        // If it is 'pending', we do nothing. 
                        // The 'isRequested' logic above already drew the Blue Dashed box for us.
                        break; 
                    }
                }

                html += `<td class="slot ${statusClass}">${content}</td>`;
            });
            html += '</tr>';
        }
        
        html += '</tbody></table>';
        container.innerHTML = html;

    } catch (err) {
        console.error('Timetable load error:', err);
        container.innerHTML = `<div style="padding:20px; text-align:center; color:#dc2626;">
            <strong>Error loading schedule</strong><br>
            <span style="font-size:13px;">${escapeHtml(err.message)}</span>
        </div>`;
    }
}

// Modal management
function closeModal(modalId) {
    const el = document.getElementById(modalId);
    if (el) el.classList.remove('show');
}

// Approval/rejection logic
document.addEventListener('DOMContentLoaded', () => {
   let currentSession = null;

   function toElement(node) {
       let el = node;
       while (el && el.nodeType !== 1) el = el.parentNode;
       return el;
   }

   document.body.addEventListener('click', (e) => {
     const el = toElement(e.target);
     if (!el) return;
     const btn = el.closest('button.approve, button.reject');
     if (!btn) return;

     const action = btn.classList.contains('approve') ? 'approve' : 'reject';
     const session = btn.getAttribute('data-session');

     if (action === 'approve') openApprove(session);
     if (action === 'reject') openReject(session);
   });

   function openApprove(sessionId) {
     currentSession = String(sessionId);
     const m = document.getElementById('approveModal');
     m.classList.add('show');
   }

   function openReject(sessionId) {
     currentSession = String(sessionId);
     document.getElementById('rejectReason').value = '';
     const m = document.getElementById('rejectModal');
     m.classList.add('show');
   }

   window.closeModal = closeModal;

   async function postAction(fd) {
     try {
       const res = await fetch('../api/process_request.php', { method: 'POST', body: fd });
       return await res.json();
     } catch (err) {
       return { success: false, message: err.message };
     }
   }

   const approveBtn = document.getElementById('approveConfirm');
   if (approveBtn) {
     approveBtn.addEventListener('click', async function() {
        if (!currentSession) return alert('No session selected');
        this.disabled = true;
        this.textContent = 'Approving...';
        const fd = new FormData();
        fd.append('action', 'approve');
        fd.append('session_id', currentSession);
        const r = await postAction(fd);
        this.disabled = false;
        this.textContent = 'Yes, Approve';
        if (r.success) {
           location.reload(); 
        } else {
           alert('Error: ' + (r.message || 'Unable to approve'));
        }
     });
   }

   const rejectBtn = document.getElementById('rejectConfirm');
   if (rejectBtn) {
     rejectBtn.addEventListener('click', async function() {
        if (!currentSession) return alert('No session selected');
        const reason = document.getElementById('rejectReason').value.trim();
        if (reason.length < 3) {
            alert('Please enter a reason');
            return;
        }
        this.disabled = true;
        this.textContent = 'Submitting...';
        const fd = new FormData();
        fd.append('action', 'reject');
        fd.append('session_id', currentSession);
        fd.append('reason', reason);
        const r = await postAction(fd);
        this.disabled = false;
        this.textContent = 'Submit Rejection';
        if (r.success) {
           location.reload(); 
        } else {
           alert('Error: ' + (r.message || 'Unable to reject'));
        }
     });
   }
});
</script>
</body>
</html>


