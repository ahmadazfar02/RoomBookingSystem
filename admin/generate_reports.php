<?php
/**
 * generate_reports.php
 * Unified Admin Style + Access Control
 */
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// --- 1. ACCESS CONTROL ---
$uType = trim($_SESSION['User_Type'] ?? '');
$admin_id = $_SESSION['User_ID'] ?? $_SESSION['id'] ?? null;

// Define Roles
$isTechAdmin  = (strcasecmp($uType, 'Technical Admin') === 0);
$isSuperAdmin = (strcasecmp($uType, 'SuperAdmin') === 0 || strtolower($_SESSION['username'] ?? '') === 'superadmin');

// Check Allowed
$allowed = (
    strcasecmp($uType, 'Admin') === 0 || 
    $isTechAdmin || 
    $isSuperAdmin
);

if (!$admin_id || !$allowed) {
    header("location: ../loginterface.html");
    exit;
}

$admin_name  = $_SESSION['Fullname'] ?? 'Admin';
$admin_email = $_SESSION['Email'] ?? ($_SESSION['User_Type'] ?? 'Admin');

// --- NOTIFICATION COUNTERS ---
// --- NOTIFICATION COUNTERS (Paste this into report, timetable, and user files) ---
$tech_pending = 0;
$pending_approvals = 0;
$active_problems = 0;

// Ensure we know who the user is
$uType = $_SESSION['User_Type'] ?? '';
$isTechAdmin_Check = (strcasecmp($uType, 'Technical Admin') === 0);

if ($isTechAdmin_Check) {
    // Tech Admin: Count Pending Repair JOBS (Grouped by Ticket ID)
    // This merges multiple slots (hours) into 1 notification if they belong to the same ticket
    $sql = "SELECT COUNT(DISTINCT CASE WHEN linked_problem_id > 0 THEN linked_problem_id ELSE session_id END) 
            FROM bookings 
            WHERE tech_token IS NOT NULL 
            AND tech_status != 'Work Done'";
    $result = $conn->query($sql);
    if($result) { $row = $result->fetch_row(); $tech_pending = intval($row[0]); }
} else {
    // Admin: Count Pending Request SESSIONS (Not Slots)
    $sql = "SELECT COUNT(DISTINCT session_id) FROM bookings WHERE status = 'pending'";
    $result = $conn->query($sql);
    if($result) { $row = $result->fetch_row(); $pending_approvals = intval($row[0]); }

    // Admin: Count Active Problems
    $sql = "SELECT COUNT(*) FROM room_problems WHERE status != 'Resolved'";
    $result = $conn->query($sql);
    if($result) { $row = $result->fetch_row(); $active_problems = intval($row[0]); }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Generate Reports — Admin</title>

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
    --success-green: #16a34a;
    --warning-orange: #d97706;
    --danger-red: #ef4444;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: var(--bg-light); min-height: 100vh; color: var(--text-primary); }

/* NAVBAR */
.nav-bar {
  position: fixed; top: 0; left: 0; right: 0; height: var(--nav-height); background: white;
  display: flex; align-items: center; justify-content: space-between; padding: 0 24px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08); z-index: 1000; border-bottom: 1px solid var(--border);
}
.nav-left { display: flex; align-items: center; gap: 16px; }
.nav-logo { height: 50px; }
.nav-title h1 { font-size: 16px; font-weight: 700; color: var(--utm-maroon); margin: 0; }
.nav-title p { font-size: 11px; color: var(--text-secondary); margin: 0; }
.btn-logout { 
  text-decoration: none; color: var(--text-secondary); font-size: 13px; font-weight: 500; 
  padding: 8px 16px; border-radius: 8px; transition: all 0.2s; 
  display: inline-flex; align-items: center; gap: 6px;
}
.btn-logout:hover { background: #fef2f2; color: var(--utm-maroon); }

/* LAYOUT */
.layout { display: flex; margin-top: var(--nav-height); min-height: calc(100vh - var(--nav-height)); }

/* SIDEBAR */
.sidebar {
  width: 260px; background: white; border-right: 1px solid var(--border); padding: 24px;
  flex-shrink: 0; position: sticky; top: var(--nav-height); height: calc(100vh - var(--nav-height));
  display: flex; flex-direction: column; box-shadow: 2px 0 8px rgba(0,0,0,0.02);
}
.sidebar-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px; margin-bottom: 16px; }
.sidebar-menu { list-style: none; flex: 1; padding: 0; }
.sidebar-menu li { margin-bottom: 4px; }
.sidebar-menu a {
  display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 8px;
  text-decoration: none; color: var(--text-primary); font-size: 14px; font-weight: 500; transition: all 0.2s;
}
.sidebar-menu a:hover { background: var(--bg-light); color: var(--utm-maroon); transform: translateX(2px); }
.sidebar-menu a.active { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: var(--utm-maroon); font-weight: 600; box-shadow: 0 1px 3px rgba(128,0,0,0.1); }
.sidebar-menu a i { width: 20px; text-align: center; }

/* NOTIFICATION BADGE */
.nav-badge {
    background-color: #dc2626; /* Red */
    color: white; 
    font-size: 10px; 
    font-weight: 700;
    padding: 2px 8px; 
    border-radius: 99px; 
    margin-left: auto; /* Pushes badge to the right */
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
.page-header { margin-bottom: 28px; }
.page-title h2 { 
  font-size: 24px; font-weight: 700; color: var(--utm-maroon); margin: 0 0 6px 0;
  display: flex; align-items: center; gap: 12px;
}
.page-title h2 i { font-size: 26px; }
.page-title p { color: var(--text-secondary); font-size: 14px; margin: 0; line-height: 1.5; }

/* CARD & REPORTS */
.card { 
  background: white; border-radius: 16px; padding: 32px; 
  box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid var(--border);
}

/* Report Specific Styles */
.report-block { 
  margin-bottom: 20px; padding: 18px; 
  border: 1px solid var(--border); border-radius: 12px;
  background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
  transition: all 0.3s ease;
}
.report-block:hover { 
  border-color: var(--utm-maroon); 
  box-shadow: 0 4px 12px rgba(128,0,0,0.08);
  transform: translateY(-2px);
}
.report-block.selected {
  border-color: var(--utm-maroon);
  background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
  box-shadow: 0 4px 12px rgba(128,0,0,0.12);
}
.report-label {
  cursor: pointer; display: flex; align-items: center; gap: 12px;
  padding: 4px 0;
}
.report-label input[type="checkbox"] {
  width: 20px; height: 20px; cursor: pointer;
  accent-color: var(--utm-maroon);
}
.report-label strong { 
  font-weight: 600; color: var(--text-primary); font-size: 15px;
  display: flex; align-items: center; gap: 8px;
}
.report-label .report-icon {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; background: white; border: 1px solid var(--border);
}
.file-options { 
  padding-left: 44px; margin-top: 14px; display: none; gap: 12px; flex-wrap: wrap;
  animation: slideDown 0.3s ease;
}
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
.file-options label { 
  font-size: 13px; color: var(--text-secondary); 
  display: inline-flex; align-items: center; gap: 8px; 
  cursor: pointer; padding: 8px 14px; border-radius: 8px;
  border: 1px solid var(--border); background: white;
  transition: all 0.2s; font-weight: 500;
}
.file-options label:hover {
  border-color: var(--utm-maroon);
  background: #fef2f2;
  transform: translateY(-2px);
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.file-options label input[type="checkbox"] {
  width: 16px; height: 16px; cursor: pointer;
  accent-color: var(--utm-maroon);
}
.file-options label i { font-size: 16px; }

/* Period Selector */
.period-section { 
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); 
  border: 2px solid var(--border); border-radius: 12px; 
  padding: 24px; margin-bottom: 28px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.period-title { 
  font-size: 15px; font-weight: 700; color: var(--utm-maroon); 
  margin-bottom: 16px; display: flex; align-items: center; gap: 10px;
}
.period-title i { font-size: 18px; }
.period-options { display: flex; flex-wrap: wrap; gap: 12px; }
.period-option { position: relative; }
.period-option input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
.period-option label {
  display: inline-block; padding: 12px 20px; background: white; border: 2px solid var(--border);
  border-radius: 10px; font-size: 14px; font-weight: 600; color: var(--text-secondary);
  cursor: pointer; transition: all 0.2s; min-width: 140px; text-align: center;
}
.period-option label:hover { 
  border-color: var(--utm-maroon); color: var(--utm-maroon); 
  transform: translateY(-2px); box-shadow: 0 4px 12px rgba(128,0,0,0.1);
}
.period-option input[type="radio"]:checked + label { 
  background: linear-gradient(135deg, var(--utm-maroon) 0%, var(--utm-maroon-light) 100%); 
  border-color: var(--utm-maroon-dark); color: white;
  box-shadow: 0 4px 12px rgba(128,0,0,0.25);
  transform: translateY(-2px);
}
.period-info {
  font-size: 13px; color: var(--text-secondary); margin-top: 14px;
  display: flex; align-items: center; gap: 8px; padding: 10px 14px;
  background: white; border-radius: 8px; border-left: 3px solid var(--utm-maroon);
}

/* Buttons */
.btn-download {
  padding: 14px 28px; border-radius: 10px; 
  background: linear-gradient(135deg, var(--utm-maroon) 0%, var(--utm-maroon-light) 100%); 
  color: white; font-weight: 600; border: none; cursor: pointer; 
  transition: all 0.3s; font-size: 15px;
  display: inline-flex; align-items: center; gap: 10px;
  box-shadow: 0 4px 12px rgba(128,0,0,0.25);
}
.btn-download:hover { 
  background: linear-gradient(135deg, var(--utm-maroon-light) 0%, var(--utm-maroon) 100%); 
  transform: translateY(-2px); 
  box-shadow: 0 6px 16px rgba(128,0,0,0.35);
}
.btn-download:active { transform: translateY(0); }
.btn-download:disabled { 
  background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%); 
  cursor: not-allowed; transform: none; 
  box-shadow: none; opacity: 0.6;
}

/* Popup */
.popup-overlay { 
  position: fixed; inset: 0; background: rgba(0,0,0,0.6); 
  display: none; align-items: center; justify-content: center; 
  z-index: 2000; backdrop-filter: blur(4px);
  animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
.popup-overlay.active { display: flex; }
.popup-box { 
  background: white; padding: 32px; width: 400px; border-radius: 16px; 
  text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.3);
  animation: popIn 0.3s ease;
}
@keyframes popIn {
  from { transform: scale(0.9); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
.popup-box .icon-wrapper {
  width: 80px; height: 80px; margin: 0 auto 20px;
  background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(22,163,74,0.2);
}
.popup-box .icon-wrapper i { font-size: 40px; color: var(--success-green); }
.popup-box h2 { color: var(--utm-maroon); margin: 0 0 12px 0; font-size: 24px; }
.popup-box p { color: var(--text-secondary); margin: 0 0 24px 0; font-size: 15px; line-height: 1.5; }

@media (max-width: 1024px) { 
  .sidebar { display: none; } 
  .main-content { padding: 20px; }
  .card { padding: 24px; }
  .period-section { padding: 20px; }
}
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
                <a href="reservation_request.php" <?php echo basename($_SERVER['PHP_SELF']) == 'reservation_request.php' ? 'class="active"' : ''; ?>>
                    <i class="fa-solid fa-inbox"></i> Requests
                    <?php if ($pending_approvals > 0): ?>
                        <span class="nav-badge"><?php echo $pending_approvals; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

            <li><a href="admin_timetable.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_timetable.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
            
            <?php if (!$isTechAdmin): ?>
            <li><a href="admin_recurring.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_recurring.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-rotate"></i> Recurring</a></li>
            <li><a href="admin_logbook.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_logbook.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-book"></i> Logbook</a></li>
            <?php endif; ?>

            <li><a href="generate_reports.php" <?php echo basename($_SERVER['PHP_SELF']) == 'generate_reports.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-chart-pie"></i> Reports</a></li>
            
            <li>
                <a href="admin_problems.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_problems.php' ? 'class="active"' : ''; ?>>
                    <i class="fa-solid fa-triangle-exclamation"></i> Problems
                    <?php if ($isTechAdmin && $tech_pending > 0): ?>
                        <span class="nav-badge"><?php echo $tech_pending; ?></span>
                    <?php elseif (!$isTechAdmin && $active_problems > 0): ?>
                        <span class="nav-badge"><?php echo $active_problems; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <?php if ($isSuperAdmin || $isTechAdmin): ?>
                <li><a href="manage_users.php" <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-users-gear"></i> Users</a></li>
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
                <h2>Generate Reports</h2>
                <p>Select a data collection period, report types, and file formats to generate comprehensive analytics.</p>
            </div>
        </div>

        <div class="card">
            
            <div class="period-section">
                <div class="period-title">
                    <i class="fa-solid fa-clock"></i> Select Data Collection Period
                </div>
                <div class="period-options">
                    <div class="period-option">
                        <input type="radio" name="period" id="period7" value="7days">
                        <label for="period7">Last 7 Days</label>
                    </div>
                    <div class="period-option">
                        <input type="radio" name="period" id="period30" value="30days">
                        <label for="period30">Last 30 Days</label>
                    </div>
                    <div class="period-option">
                        <input type="radio" name="period" id="period6m" value="6months">
                        <label for="period6m">Last 6 Months</label>
                    </div>
                    <div class="period-option">
                        <input type="radio" name="period" id="period12m" value="12months">
                        <label for="period12m">Last 12 Months</label>
                    </div>
                    <div class="period-option">
                        <input type="radio" name="period" id="period2y" value="2years">
                        <label for="period2y">Last 2 Years</label>
                    </div>
                </div>
                <div class="period-info">
                    <i class="fa-solid fa-circle-info"></i> Reports will only include data from the selected time period.
                </div>
            </div>

            <div class="report-block">
                <label class="report-label">
                    <input type="checkbox" class="report-check" data-target="r1">
                    <strong>
                        <span class="report-icon"><i class="fa-solid fa-calendar-check" style="color: var(--utm-maroon);"></i></span>
                        Booking Summary Report
                    </strong>
                </label>
                <div id="r1" class="file-options">
                    <label><input type="checkbox" class="file-check" value="pdf"> <i class="fa-solid fa-file-pdf" style="color:#ef4444;"></i> PDF</label>
                    <label><input type="checkbox" class="file-check" value="excel"> <i class="fa-solid fa-file-excel" style="color:#16a34a;"></i> Excel</label>
                    <label><input type="checkbox" class="file-check" value="ppt"> <i class="fa-solid fa-file-powerpoint" style="color:#d97706;"></i> PowerPoint</label>
                </div>
            </div>

            <div class="report-block">
                <label class="report-label">
                    <input type="checkbox" class="report-check" data-target="r2">
                    <strong>
                        <span class="report-icon"><i class="fa-solid fa-door-open" style="color: var(--success-green);"></i></span>
                        Room Usage Report
                    </strong>
                </label>
                <div id="r2" class="file-options">
                    <label><input type="checkbox" class="file-check" value="pdf"> <i class="fa-solid fa-file-pdf" style="color:#ef4444;"></i> PDF</label>
                    <label><input type="checkbox" class="file-check" value="excel"> <i class="fa-solid fa-file-excel" style="color:#16a34a;"></i> Excel</label>
                    <label><input type="checkbox" class="file-check" value="ppt"> <i class="fa-solid fa-file-powerpoint" style="color:#d97706;"></i> PowerPoint</label>
                </div>
            </div>

            <div class="report-block">
                <label class="report-label">
                    <input type="checkbox" class="report-check" data-target="r3">
                    <strong>
                        <span class="report-icon"><i class="fa-solid fa-user-clock" style="color: #3b82f6;"></i></span>
                        User Activity Report
                    </strong>
                </label>
                <div id="r3" class="file-options">
                    <label><input type="checkbox" class="file-check" value="pdf"> <i class="fa-solid fa-file-pdf" style="color:#ef4444;"></i> PDF</label>
                    <label><input type="checkbox" class="file-check" value="excel"> <i class="fa-solid fa-file-excel" style="color:#16a34a;"></i> Excel</label>
                    <label><input type="checkbox" class="file-check" value="ppt"> <i class="fa-solid fa-file-powerpoint" style="color:#d97706;"></i> PowerPoint</label>
                </div>
            </div>

            <div class="report-block">
                <label class="report-label">
                    <input type="checkbox" class="report-check" data-target="r4">
                    <strong>
                        <span class="report-icon"><i class="fa-solid fa-clipboard-list" style="color: var(--warning-orange);"></i></span>
                        Admin Log Report
                    </strong>
                </label>
                <div id="r4" class="file-options">
                    <label><input type="checkbox" class="file-check" value="pdf"> <i class="fa-solid fa-file-pdf" style="color:#ef4444;"></i> PDF</label>
                    <label><input type="checkbox" class="file-check" value="excel"> <i class="fa-solid fa-file-excel" style="color:#16a34a;"></i> Excel</label>
                </div>
            </div>

            <button id="downloadBtn" class="btn-download" style="margin-top:24px;">
                <i class="fa-solid fa-cloud-arrow-down"></i> Download Selected Reports
            </button>
        </div>
    </main>
</div>

<div id="successPopup" class="popup-overlay">
  <div class="popup-box">
    <div class="icon-wrapper">
      <i class="fa-solid fa-circle-check"></i>
    </div>
    <h2>Download Complete</h2>
    <p id="fileCountText"></p>
    <button id="closePopupBtn" class="btn-download" style="width:100%; justify-content:center;">OK</button>
  </div>
</div>

<div id="errorPopup" class="popup-overlay">
  <div class="popup-box">
    <div class="icon-wrapper" style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);">
      <i class="fa-solid fa-circle-exclamation" style="color: var(--danger-red);"></i>
    </div>
    <h2>Validation Error</h2>
    <p id="errorText"></p>
    <button id="closeErrorBtn" class="btn-download" style="width:100%; justify-content:center;">OK</button>
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
document.querySelectorAll('.report-check').forEach(ch => {
  ch.addEventListener('change', () => {
    const box = document.getElementById(ch.dataset.target);
    const reportBlock = ch.closest('.report-block');
    if (!box) return;
    
    if (ch.checked) {
      box.style.display = "flex";
      reportBlock.classList.add('selected');
    } else {
      box.style.display = "none";
      reportBlock.classList.remove('selected');
      box.querySelectorAll('.file-check').forEach(f => f.checked = false);
    }
  });
});

// Error popup function
function showError(message) {
  document.getElementById("errorText").textContent = message;
  document.getElementById("errorPopup").classList.add("active");
}

document.getElementById("closeErrorBtn").onclick = () => {
  document.getElementById("errorPopup").classList.remove("active");
};

document.getElementById("downloadBtn").onclick = () => {
  // Validate time period selection
  const selectedPeriod = document.querySelector('input[name="period"]:checked');
  if (!selectedPeriod) {
    showError("Please select a data collection period before generating reports.");
    return;
  }
  const period = selectedPeriod.value;

  const map = { r1:"booking", r2:"room", r3:"user", r4:"adminlog" };
  let downloads = [];

  try {
    document.querySelectorAll(".report-check:checked").forEach(r => {
      const id = r.dataset.target;
      const code = map[id];
      const formats = [...document.getElementById(id).querySelectorAll(".file-check:checked")].map(x=>x.value);

      if (formats.length === 0) {
        showError("Please select at least one file format for each selected report.");
        throw "validation";
      }

      formats.forEach(fmt=>{
        downloads.push({
          url: `../api/generate_reports_action.php?report=${code}&format=${fmt}&period=${period}`
        });
      });
    });
  } catch(e) {
    if (e === "validation") return;
    throw e;
  }

  if (downloads.length === 0) {
    showError("Please select at least one report and file format.");
    return;
  }

  const btn = document.getElementById("downloadBtn");
  const original = btn.innerHTML;
  btn.disabled = true;

  let i = 0;
  function next() {
    if (i >= downloads.length) {
      btn.disabled = false;
      btn.innerHTML = original;
      showPopup(downloads.length);
      return;
    }
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Downloading ${i+1} of ${downloads.length}...`;
    const iframe = document.createElement("iframe");
    iframe.style.display="none";
    iframe.src = downloads[i].url;
    document.body.appendChild(iframe);
    setTimeout(()=>{ document.body.removeChild(iframe); i++; next(); }, 1300);
  }
  next();
};

function showPopup(count){
  document.getElementById("fileCountText").textContent = `${count} file(s) downloaded successfully.`;
  document.getElementById("successPopup").classList.add("active");
}
document.getElementById("closePopupBtn").onclick = ()=>{
  document.getElementById("successPopup").classList.remove("active");
};
</script>

</body>
</html>