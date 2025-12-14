<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// SECURITY: Admin only
if (!isset($_SESSION['loggedin']) ||
    !isset($_SESSION['User_Type']) ||
    strcasecmp($_SESSION['User_Type'], 'Admin') !== 0) {
    header("location: ../loginterface.html");
    exit;
}

$admin_name  = $_SESSION['Fullname'] ?? 'Admin';
$admin_email = $_SESSION['Email'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Generate Reports — Admin</title>

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
  --gray-800: #1f2937;
  --shadow-sm: 0 4px 12px rgba(18, 38, 63, 0.08);
  --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
}
*{ box-sizing:border-box; }
body{
 margin:0;
  font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Arial;
  min-height:100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: var(--gray-700);
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
}

.nav-bar{
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
.nav-logo{ height:50px; }
.btn-download:hover { 
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2); }
.layout{
  width:100%; max-width:2000px;
  margin:100px auto 0;
  padding:24px;
  display:flex;
  gap:24px;
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
.main{ flex:1; }

.header-card{
  background:white;
  border-radius:12px;
  padding:24px 32px;
  box-shadow:var(--shadow-md);
  margin-bottom:24px;
  display:flex; justify-content:space-between; align-items:center;
}

.header-title h1{ margin:0; color:var(--gray-800); font-size:24px; }
.header-badge{
  background:var(--primary-light);
  color:var(--primary);
  padding:4px 12px;
  border-radius:20px;
  font-size:12px;
}

.card{
  background:white;
  border-radius:12px;
  padding:24px;
  box-shadow:var(--shadow-sm);
}

.card-title{ font-size:20px; font-weight:700; }

.report-block{
  margin-bottom:18px;
  padding-bottom:12px;
  border-bottom:1px dashed var(--gray-200);
}

.file-options{
  padding-left:22px;
  margin-top:8px;
  display:none;
}

.btn-download{
  padding:12px 14px;
  border-radius:8px;
  background:linear-gradient(135deg,#10b981,#059669);
  color:white;
  font-weight:700;
  border:0;
  cursor:pointer;
}

/* Popup */
.popup-overlay{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,0.4);
  display:flex;
  align-items:center;
  justify-content:center;
  opacity:0;
  visibility:hidden;
  transition:.2s;
}
.popup-overlay.active{
  opacity:1;
  visibility:visible;
}
.popup-box{
  background:white;
  padding:24px;
  width:360px;
  border-radius:12px;
  text-align:center;
}
</style>
</head>
<body>

<nav class="nav-bar">
  <img src="../assets/images/utmlogo.png" class="nav-logo">
</nav>

<div class="layout">

<aside class="sidebar">
  <div class="sidebar-title">Main Menu</div>
  <ul class="sidebar-menu">
    <li><a href="index-admin.php">Dashboard</a></li>
    <li><a href="reservation_request.php">Reservation Request</a></li>
    <li><a href="admin_timetable.php">Regular Timetable</a></li>
    <li><a href="admin_recurring.php">Recurring Templates</a></li>
    <li><a href="manage_users.php">Manage Users</a></li>
    <li><a href="admin_logbook.php">Logbook</a></li>
    <li><a href="generate_reports.php" class="active">Generate Reports</a></li>
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

<div class="main">

  <div class="header-card">
    <div class="header-title">
      <h1>Generate Reports</h1>
      <span class="header-badge">Admin</span>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Generate Reports</div>
    <p>Select report(s) and choose the file format(s).</p>

    <!-- Booking -->
    <div class="report-block">
      <label><input type="checkbox" class="report-check" data-target="r1"> <strong>Booking Summary Report</strong></label>
      <div id="r1" class="file-options">
        <label><input type="checkbox" class="file-check" value="pdf"> PDF</label>
        <label><input type="checkbox" class="file-check" value="excel"> Excel (.xlsx)</label>
        <label><input type="checkbox" class="file-check" value="ppt"> PowerPoint (.pptx)</label>
      </div>
    </div>

    <!-- Room -->
    <div class="report-block">
      <label><input type="checkbox" class="report-check" data-target="r2"> <strong>Room Usage Report</strong></label>
      <div id="r2" class="file-options">
        <label><input type="checkbox" class="file-check" value="pdf"> PDF</label>
        <label><input type="checkbox" class="file-check" value="excel"> Excel (.xlsx)</label>
        <label><input type="checkbox" class="file-check" value="ppt"> PowerPoint (.pptx)</label>
      </div>
    </div>

    <!-- User -->
    <div class="report-block">
      <label><input type="checkbox" class="report-check" data-target="r3"> <strong>User Activity Report</strong></label>
      <div id="r3" class="file-options">
        <label><input type="checkbox" class="file-check" value="pdf"> PDF</label>
        <label><input type="checkbox" class="file-check" value="excel"> Excel (.xlsx)</label>
        <label><input type="checkbox" class="file-check" value="ppt"> PowerPoint (.pptx)</label>
      </div>
    </div>

    <!-- Admin Log -->
    <div class="report-block">
      <label><input type="checkbox" class="report-check" data-target="r4"> <strong>Admin Log Report</strong></label>
      <div id="r4" class="file-options">
        <label><input type="checkbox" class="file-check" value="pdf"> PDF</label>
        <label><input type="checkbox" class="file-check" value="excel"> Excel (.xlsx)</label>
      </div>
    </div>

    <button id="downloadBtn" class="btn-download" style="margin-top:20px;">Download Report</button>
  </div>

</div>
</div>

<!-- POPUP -->
<div id="successPopup" class="popup-overlay">
  <div class="popup-box">
    <h2>Download Complete</h2>
    <p id="fileCountText"></p>
    <button id="closePopupBtn" class="btn-download">OK</button>
  </div>
</div>

<script>
document.querySelectorAll('.report-check').forEach(ch => {
  ch.addEventListener('change', () => {
    const box = document.getElementById(ch.dataset.target);
    if (!box) return;
    box.style.display = ch.checked ? "block" : "none";
    if (!ch.checked) {
      box.querySelectorAll('.file-check').forEach(f => f.checked = false);
    }
  });
});

document.getElementById("downloadBtn").onclick = () => {
  const map = { r1:"booking", r2:"room", r3:"user", r4:"adminlog" };
  let downloads = [];

  document.querySelectorAll(".report-check:checked").forEach(r => {
    const id = r.dataset.target;
    const code = map[id];
    const formats = [...document.getElementById(id).querySelectorAll(".file-check:checked")].map(x=>x.value);

    if (formats.length === 0) {
      alert("Please select at least one file format for the report.");
      throw "validation";
    }

    formats.forEach(fmt=>{
      downloads.push({
        url: `../api/generate_reports_action.php?report=${code}&format=${fmt}`
      });
    });
  });

  if (downloads.length === 0) {
    alert("Please select at least one report and format.");
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
    btn.innerHTML = `Downloading ${i+1} of ${downloads.length}...`;
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
  const p = document.getElementById("successPopup");
  p.classList.add("active");
}
document.getElementById("closePopupBtn").onclick = ()=>{
  document.getElementById("successPopup").classList.remove("active");
};
</script>

</body>
</html>


