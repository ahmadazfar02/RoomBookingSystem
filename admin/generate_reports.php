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
$admin_email = $_SESSION['Email'] ?? 'Admin';
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
.sidebar-profile { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 12px; }
.profile-icon { width: 36px; height: 36px; background: #f3f4f6; color: var(--utm-maroon); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }
.profile-info { font-size: 13px; overflow: hidden; }
.profile-name { font-weight: 600; white-space: nowrap; text-overflow: ellipsis; }
.profile-email { font-size: 11px; color: var(--text-secondary); white-space: nowrap; text-overflow: ellipsis; }

/* MAIN CONTENT */
.main-content { flex: 1; padding: 32px; min-width: 0; }
.page-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: end; }
.page-title h2 { font-size: 24px; font-weight: 700; color: var(--utm-maroon); margin: 0; }
.page-title p { color: var(--text-secondary); font-size: 14px; margin: 4px 0 0 0; }

/* CARD & REPORTS */
.card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid var(--border); }

/* Report Specific Styles */
.report-block { margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px dashed var(--border); }
.report-block:last-child { border-bottom: none; }
.file-options { padding-left: 26px; margin-top: 10px; display: none; gap: 16px; }
.file-options label { font-size: 13px; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }

/* Period Selector */
.period-section { background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 20px; margin-bottom: 24px; }
.period-title { font-size: 14px; font-weight: 700; color: var(--utm-maroon); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.period-options { display: flex; flex-wrap: wrap; gap: 10px; }
.period-option { position: relative; }
.period-option input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
.period-option label {
  display: inline-block; padding: 8px 16px; background: white; border: 1px solid var(--border);
  border-radius: 6px; font-size: 13px; font-weight: 500; color: var(--text-secondary);
  cursor: pointer; transition: all 0.2s;
}
.period-option label:hover { border-color: var(--utm-maroon); color: var(--utm-maroon); }
.period-option input[type="radio"]:checked + label { background: var(--utm-maroon); border-color: var(--utm-maroon); color: white; }

/* Buttons */
.btn-download {
  padding: 12px 20px; border-radius: 8px; background: var(--utm-maroon); color: white;
  font-weight: 600; border: none; cursor: pointer; transition: 0.2s; font-size: 14px;
  display: inline-flex; align-items: center; gap: 8px;
}
.btn-download:hover { background: var(--utm-maroon-light); transform: translateY(-1px); }
.btn-download:disabled { background: var(--text-secondary); cursor: not-allowed; transform: none; }

/* Popup */
.popup-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(2px); }
.popup-overlay.active { display: flex; }
.popup-box { background: white; padding: 24px; width: 360px; border-radius: 12px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
.popup-box h2 { color: var(--utm-maroon); margin-top: 0; }

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
            <li><a href="index-admin.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
            
            <?php if (!$isTechAdmin): ?>
            <li><a href="reservation_request.php"><i class="fa-solid fa-inbox"></i> Requests</a></li>
            <?php endif; ?>

            <li><a href="admin_timetable.php"><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
            
            <?php if (!$isTechAdmin): ?>
            <li><a href="admin_recurring.php"><i class="fa-solid fa-rotate"></i> Recurring</a></li>
            <?php endif; ?>

            <li><a href="admin_logbook.php"><i class="fa-solid fa-book"></i> Logbook</a></li>
            <li><a href="generate_reports.php" class="active"><i class="fa-solid fa-chart-pie"></i> Reports</a></li>
            <li><a href="admin_problems.php"><i class="fa-solid fa-triangle-exclamation"></i> Problems</a></li>
            
            <?php if ($isSuperAdmin || $isTechAdmin): ?>
              <li><a href="manage_users.php"><i class="fa-solid fa-users-gear"></i> Users</a></li>
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
                <p>Select a data collection period, report types, and file formats.</p>
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
                </div>
                <div style="font-size:12px; color:var(--text-secondary); margin-top:10px;">
                    <i class="fa-solid fa-circle-info"></i> Reports will only include data from the selected time period.
                </div>
            </div>

            <div class="report-block">
                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" class="report-check" data-target="r1"> 
                    <strong style="font-weight:600; color:var(--text-primary);">Booking Summary Report</strong>
                </label>
                <div id="r1" class="file-options">
                    <label><input type="checkbox" class="file-check" value="pdf"> <i class="fa-solid fa-file-pdf" style="color:#ef4444;"></i> PDF</label>
                    <label><input type="checkbox" class="file-check" value="excel"> <i class="fa-solid fa-file-excel" style="color:#16a34a;"></i> Excel</label>
                    <label><input type="checkbox" class="file-check" value="ppt"> <i class="fa-solid fa-file-powerpoint" style="color:#d97706;"></i> PowerPoint</label>
                </div>
            </div>

            <div class="report-block">
                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" class="report-check" data-target="r2"> 
                    <strong style="font-weight:600; color:var(--text-primary);">Room Usage Report</strong>
                </label>
                <div id="r2" class="file-options">
                    <label><input type="checkbox" class="file-check" value="pdf"> <i class="fa-solid fa-file-pdf" style="color:#ef4444;"></i> PDF</label>
                    <label><input type="checkbox" class="file-check" value="excel"> <i class="fa-solid fa-file-excel" style="color:#16a34a;"></i> Excel</label>
                    <label><input type="checkbox" class="file-check" value="ppt"> <i class="fa-solid fa-file-powerpoint" style="color:#d97706;"></i> PowerPoint</label>
                </div>
            </div>

            <div class="report-block">
                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" class="report-check" data-target="r3"> 
                    <strong style="font-weight:600; color:var(--text-primary);">User Activity Report</strong>
                </label>
                <div id="r3" class="file-options">
                    <label><input type="checkbox" class="file-check" value="pdf"> <i class="fa-solid fa-file-pdf" style="color:#ef4444;"></i> PDF</label>
                    <label><input type="checkbox" class="file-check" value="excel"> <i class="fa-solid fa-file-excel" style="color:#16a34a;"></i> Excel</label>
                    <label><input type="checkbox" class="file-check" value="ppt"> <i class="fa-solid fa-file-powerpoint" style="color:#d97706;"></i> PowerPoint</label>
                </div>
            </div>

            <div class="report-block">
                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" class="report-check" data-target="r4"> 
                    <strong style="font-weight:600; color:var(--text-primary);">Admin Log Report</strong>
                </label>
                <div id="r4" class="file-options">
                    <label><input type="checkbox" class="file-check" value="pdf"> <i class="fa-solid fa-file-pdf" style="color:#ef4444;"></i> PDF</label>
                    <label><input type="checkbox" class="file-check" value="excel"> <i class="fa-solid fa-file-excel" style="color:#16a34a;"></i> Excel</label>
                </div>
            </div>

            <button id="downloadBtn" class="btn-download" style="margin-top:20px;">
                <i class="fa-solid fa-cloud-arrow-down"></i> Download Selected Reports
            </button>
        </div>
    </main>
</div>

<div id="successPopup" class="popup-overlay">
  <div class="popup-box">
    <div style="font-size:40px; color:#16a34a; margin-bottom:10px;"><i class="fa-solid fa-circle-check"></i></div>
    <h2>Download Complete</h2>
    <p id="fileCountText" style="color:var(--text-secondary); margin-bottom:20px;"></p>
    <button id="closePopupBtn" class="btn-download" style="width:100%; justify-content:center;">OK</button>
  </div>
</div>

<script>
document.querySelectorAll('.report-check').forEach(ch => {
  ch.addEventListener('change', () => {
    const box = document.getElementById(ch.dataset.target);
    if (!box) return;
    box.style.display = ch.checked ? "flex" : "none";
    if (!ch.checked) {
      box.querySelectorAll('.file-check').forEach(f => f.checked = false);
    }
  });
});

document.getElementById("downloadBtn").onclick = () => {
  // Validate time period selection
  const selectedPeriod = document.querySelector('input[name="period"]:checked');
  if (!selectedPeriod) {
    alert("Please select a data collection period before generating reports.");
    return;
  }
  const period = selectedPeriod.value;

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
        url: `../api/generate_reports_action.php?report=${code}&format=${fmt}&period=${period}`
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