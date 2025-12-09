<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

// SECURITY: Only Admin can access
if (!isset($_SESSION['loggedin']) || 
    !isset($_SESSION['User_Type']) ||
    strcasecmp($_SESSION['User_Type'], 'Admin') !== 0) {
    header("location: ../loginterface.html");
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin';
$admin_email = 'Admin';   // <= HERE
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
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

*{ box-sizing:border-box }
body{
  font-family:'Inter',sans-serif;
  background:linear-gradient(135deg,#667eea,#764ba2);
  display:flex;
  min-height:100vh;
  margin:0;
  padding:0;
}

/* Top Logo Bar */
.nav-bar {
  background:white;
  padding:16px 24px;
  box-shadow:var(--shadow-md);
  position:fixed;
  top:0; left:0; right:0;
  height:80px;
  z-index:1000;
  display:flex;
  align-items:center;
}
.nav-logo { height:50px; }

/* Layout */
.layout {
  width:100%;
  max-width:2000px;
  margin:100px auto 0;
  padding:24px;
  display:flex;
  gap:24px;
}

/* Sidebar */
.sidebar {
  width:260px;
  background:white;
  border-radius:12px;
  padding:20px;
  box-shadow:var(--shadow-sm);
  flex-shrink:0;
  position:sticky;
  top:100px;
}
.sidebar-title{
  font-size:14px;
  font-weight:700;
  color:var(--gray-600);
  text-transform:uppercase;
  margin-bottom:16px;
  border-bottom:2px solid var(--gray-200);
  padding-bottom:10px;
}
.sidebar-menu li{ list-style:none; margin-bottom:8px; }
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

/* Profile */
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

/* Main area */
.main{ flex:1; }

/* Header card */
.header-card{
  background:white;
  padding:24px 32px;
  border-radius:12px;
  margin-bottom:24px;
  box-shadow:var(--shadow-md);
  display:flex;
  justify-content:space-between;
}
.header-title { display:flex; gap:12px; align-items:center; }
.header-title h1{ margin:0; color:var(--gray-800); font-size:24px; }
.header-badge{
  background:var(--primary-light);
  color:var(--primary);
  padding:4px 12px;
  border-radius:20px;
  font-size:12px;
  font-weight:600;
}

/* Card */
.card{
  background:white;
  border-radius:12px;
  padding:24px;
  box-shadow:var(--shadow-sm);
}

/* Wizard styles */
.wizard-step{ display:none; }
.wizard-step.active{ display:block; }

.btn-primary{
  padding:12px 15px;
  border:0;
  background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  color:white;
  border-radius:8px;
  font-weight:700;
  width:100%;
  cursor:pointer;
  margin-top:20px;
}

.btn-back{
  padding:12px;
  width:48%;
  background:var(--gray-200);
  border-radius:8px;
  font-weight:700;
  cursor:pointer;
}

.btn-download{
  padding:12px;
  width:48%;
  background:linear-gradient(135deg,#10b981,#059669);
  border-radius:8px;
  color:white;
  font-weight:700;
  cursor:pointer;
}

.checkbox-list label{
  display:block;
  margin-bottom:10px;
  font-size:15px;
  font-weight:500;
}
.card-title{
  font-size:20px;
  font-weight:700;
  margin-bottom:12px;
}
</style>

</head>
<body>

<!-- TOP NAV -->
<nav class="nav-bar">
  <img src="../assets/images/utmlogo.png" class="nav-logo">
</nav>

<!-- LAYOUT -->
<div class="layout">

  <!-- SIDEBAR -->
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
            <div class="profile-email"><?php echo htmlspecialchars($_SESSION['Email'] ?? 'Admin'); ?></div>
        </div>
    </div>
</aside>


  <!-- MAIN AREA -->
  <div class="main">

    <!-- HEADER -->
    <div class="header-card">
      <div class="header-title">
        <h1>Generate Reports</h1>
        <span class="header-badge">Admin</span>
      </div>
    </div>

    <!-- CARD -->
    <div class="card">

  <div class="card-title">Generate Reports</div>
  <p>Select report(s) and choose the file format(s) for each report:</p>

  <!-- REPORT 1 -->
  <div class="report-block">
    <label>
      <input type="checkbox" class="report-check" data-target="r1">
      <strong>Booking Summary Report</strong>
    </label>

    <div id="r1" class="file-options">
      <label><input type="checkbox" class="file-check" value="pdf"> PDF</label>
      <label><input type="checkbox" class="file-check" value="excel"> Excel (.xlsx)</label>
      <label><input type="checkbox" class="file-check" value="ppt"> PowerPoint (.pptx)</label>
    </div>
  </div>

  <!-- REPORT 2 -->
  <div class="report-block">
    <label>
      <input type="checkbox" class="report-check" data-target="r2">
      <strong>Room Usage Report</strong>
    </label>

    <div id="r2" class="file-options">
      <label><input type="checkbox" class="file-check" value="pdf"> PDF</label>
      <label><input type="checkbox" class="file-check" value="excel"> Excel (.xlsx)</label>
      <label><input type="checkbox" class="file-check" value="ppt"> PowerPoint (.pptx)</label>
    </div>
  </div>

  <!-- REPORT 3 -->
  <div class="report-block">
    <label>
      <input type="checkbox" class="report-check" data-target="r3">
      <strong>User Activity Report</strong>
    </label>

    <div id="r3" class="file-options">
      <label><input type="checkbox" class="file-check" value="pdf"> PDF</label>
      <label><input type="checkbox" class="file-check" value="excel"> Excel (.xlsx)</label>
      <label><input type="checkbox" class="file-check" value="ppt"> PowerPoint (.pptx)</label>
    </div>
  </div>

  <!-- REPORT 4 -->
  <div class="report-block">
    <label>
      <input type="checkbox" class="report-check" data-target="r4">
      <strong>Admin Log Report</strong>
    </label>

    <div id="r4" class="file-options">
      <label><input type="checkbox" class="file-check" value="pdf"> PDF</label>
      <label><input type="checkbox" class="file-check" value="ppt"> PowerPoint (.pptx)</label>
    </div>
  </div>


  <button id="downloadBtn" class="btn-download" style="width:100%; margin-top:20px;">
    Download Report
  </button>

</div>

<style>
.file-options {
  padding-left: 25px;
  margin-top: 8px;
  margin-bottom: 18px;
  display: none;
}
.report-block input[type="checkbox"].report-check:checked ~ .file-options {
  display: block;
}
</style>
</script>
<script>
// When a report checkbox is clicked, show or hide file options
document.querySelectorAll(".report-check").forEach(ch => {
    ch.addEventListener("change", e => {
        let target = document.getElementById(ch.dataset.target);
        if (ch.checked) target.style.display = "block";
        else {
            target.style.display = "none";
            target.querySelectorAll("input[type=checkbox]").forEach(x => x.checked = false);
        }
    });
});

/* =====================================================================
   DOWNLOAD BUTTON - Handles ALL report types
   Implemented: Booking Summary, Room Usage, User Activity, Admin Log, Cancellation
   Formats: PDF, Excel (CSV), PowerPoint (PPTX)
   Uses hidden iframes to avoid pop-up blockers
   ===================================================================== */
document.getElementById("downloadBtn").onclick = () => {
    
    // Map report checkboxes to their report type names
    const reportMap = {
        'r1': 'booking',      // Booking Summary Report
        'r2': 'room',         // Room Usage Report
        'r3': 'user',         // User Activity Report
        'r4': 'adminlog'      // Admin Log Report
    };
    
    // Collect all downloads needed
    let downloads = [];
    document.querySelectorAll('.report-check:checked').forEach(checkbox => {
        const targetId = checkbox.dataset.target;
        const reportType = reportMap[targetId];
        const formatContainer = document.getElementById(targetId);
        const selectedFormats = [...formatContainer.querySelectorAll('.file-check:checked')].map(x => x.value);
        
        selectedFormats.forEach(format => {
            downloads.push({
                report: reportType,
                format: format,
                url: `../api/generate_reports_action.php?report=${reportType}&format=${format}`
            });
        });
    });
    
    // Validate selection
    if (downloads.length === 0) {
        alert("Please select at least one report and choose a file format.");
        return;
    }
    
    // Show progress
    const btn = document.getElementById("downloadBtn");
    const originalText = btn.innerHTML;
    btn.disabled = true;
    
    // Download files sequentially using hidden iframes (avoids pop-up blockers)
    let currentIndex = 0;
    
    function downloadFile(index) {
        if (index >= downloads.length) {
            // All downloads complete
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert(`✅ Downloaded ${downloads.length} file(s) successfully!`);
            return;
        }
        
        const download = downloads[index];
        btn.innerHTML = `⏳ Downloading ${index + 1} of ${downloads.length}...`;
        
        // Create hidden iframe for download
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = download.url;
        document.body.appendChild(iframe);
        
        // Remove iframe after download starts and proceed to next
        setTimeout(() => {
            document.body.removeChild(iframe);
            downloadFile(index + 1);
        }, 1500); // 1.5 second delay between downloads
    }
    
    // Start downloading
    downloadFile(0);
};

</script>

</body>
</html>


