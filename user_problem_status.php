<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// SECURITY: Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: loginterface.html");
    exit;
}

$user_id = $_SESSION['id'];

// Fetch the user's past problem reports
$sql = "
SELECT 
    rp.id, 
    rp.title, 
    rp.description, 
    rp.created_at, 
    rp.status, 
    r.name AS room_name
FROM room_problems rp
JOIN rooms r ON rp.room_id = r.room_id
WHERE rp.user_id = ?
ORDER BY rp.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reports_result = $stmt->get_result();

$reports = [];
while ($row = $reports_result->fetch_assoc()) {
    $reports[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Room Problem Status - UTM Room Booking</title>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root{ 
        --utm-maroon: #800000;
        --utm-maroon-light: #a31313;
        --utm-maroon-dark: #600000;
        --accent: #800000;
        --accent-dark: #600000;
        --bg-light: #f8fafc;
        --card-bg: #ffffff;
        --text-primary: #1e293b;
        --text-secondary: #64748b;
        --border: #e2e8f0;
        --pending: #f59e0b;
        --resolved: #16a34a;
        --in-progress: #3b82f6;
    }
    
    * { 
        box-sizing: border-box; 
        margin: 0; 
        padding: 0; 
    }
    
    body { 
        font-family: 'Inter', sans-serif;
        background: var(--bg-light);
        min-height: 100vh;
        color: var(--text-primary);
    }
    
    /* Header */
    .main-header {
        background: white;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
    }
    
    .header-content {
        max-width: 1400px;
        margin: 0 auto;
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
    }
    
    .logo-section {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .logo { 
        height: 60px; 
    }
    
    .logo-text {
        display: flex;
        flex-direction: column;
    }
    
    .logo-text h1 {
        font-size: 18px;
        font-weight: 700;
        color: var(--utm-maroon);
        margin: 0;
    }
    
    .logo-text p {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 0;
    }
    
    .header-controls {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: 'Inter', sans-serif;
    }
    
    .btn-secondary {
        background: var(--utm-maroon);
        color: white;
        box-shadow: 0 2px 4px rgba(128, 0, 0, 0.2);
    }
    
    .btn-secondary:hover {
        background: var(--utm-maroon-light);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(128, 0, 0, 0.3);
    }
    
    .btn-nav {
        background: white;
        border: 2px solid var(--border);
        color: var(--text-primary);
    }
    
    .btn-nav:hover {
        border-color: var(--utm-maroon);
        color: var(--utm-maroon);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
     /* Dropdown Menu */
    .dropdownmenu {
        position: relative;
        display: inline-block;
    }

    .dropbtn {
        background: var(--utm-maroon);
        color: white;
        box-shadow: 0 2px 4px rgba(128, 0, 0, 0.2);
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: 'Inter', sans-serif;
    }

    .dropbtn:hover {
        background: var(--utm-maroon-light);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(128, 0, 0, 0.3);
    }

    .dropdownmenu-content {
        display: none;
        position: absolute;
        right: 0;
        top: calc(100% + 1px);
        background-color: white;
        min-width: 200px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-radius: 8px;
        overflow: hidden;
        z-index: 1001;
        border: 1px solid var(--border);
    }

    .dropdownmenu-content::before {
        content: '';
        position: absolute;
        top: -10px;
        left: 0;
        right: 0;
        height: 10px;
        background: transparent;
    }

    .dropdownmenu-content a {
        color: var(--text-primary);
        padding: 12px 16px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        font-size: 14px;
        font-weight: 500;
    }

    .dropdownmenu-content a i {
        width: 16px;
        text-align: center;
    }

    .dropdownmenu-content a:hover {
        background: var(--utm-maroon);
        color: white;
    }

    .dropdownmenu:hover .dropdownmenu-content,
    .dropdownmenu-content:hover {
        display: block;
    }
    /* Main Container */
    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 24px;
    }
    
    /* Page Title Card */
    .page-title-card {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--border);
        margin-bottom: 20px;
    }
    
    .page-title-card h1 {
        font-size: 28px;
        font-weight: 700;
        color: var(--utm-maroon);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .page-title-card p {
        color: var(--text-secondary);
        font-size: 14px;
    }
    
    /* Filters Card */
    .filters-card {
        background: white;
        padding: 20px 24px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--border);
        margin-bottom: 20px;
    }
    
    .filters {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 10px 20px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: white;
        color: var(--text-primary);
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s ease;
        font-family: 'Inter', sans-serif;
    }
    
    .filter-btn:hover {
        border-color: var(--utm-maroon);
        color: var(--utm-maroon);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .filter-btn.active {
        background: var(--utm-maroon);
        color: white;
        border-color: var(--utm-maroon);
        box-shadow: 0 2px 4px rgba(128, 0, 0, 0.2);
    }
    
    .status-count {
        margin-left: auto;
        font-size: 14px;
        color: var(--text-secondary);
        font-weight: 600;
        padding: 8px 16px;
        background: var(--bg-light);
        border-radius: 8px;
    }
    
    /* Report List */
    .reports-container {
        display: grid;
        gap: 16px;
    }
    
    .report-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--border);
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .report-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }
    
    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        background: #fafafa;
    }
    
    .report-info {
        flex: 1;
    }
    
    .report-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--utm-maroon);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .report-meta {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        font-size: 13px;
        color: var(--text-secondary);
    }
    
    .report-meta-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .report-meta-item i {
        font-size: 12px;
    }
    
    /* Status Badges */
    .status-badge {
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }
    
    .status-pending {
        color: #92400e;
        background: #fef3c7;
        border: 1px solid #fde68a;
    }
    
    .status-resolved {
        color: #065f46;
        background: #d1fae5;
        border: 1px solid #a7f3d0;
    }
    
    .status-in-progress {
        color: #1e40af;
        background: #dbeafe;
        border: 1px solid #bfdbfe;
    }
    
    .report-body {
        padding: 20px 24px;
    }
    
    .report-description {
        color: var(--text-primary);
        font-size: 14px;
        line-height: 1.6;
        margin: 0;
    }
    
    .report-footer {
        padding: 16px 24px;
        background: #fafafa;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
        color: var(--text-secondary);
    }
    
    .report-date {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .report-id {
        font-family: 'Courier New', monospace;
        background: #f1f5f9;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
    }
    
    /* Empty State */
    .empty-state {
        background: white;
        padding: 60px 24px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--border);
        text-align: center;
    }
    
    .empty-state i {
        font-size: 64px;
        color: var(--border);
        margin-bottom: 20px;
    }
    
    .empty-state h3 {
        font-size: 20px;
        color: var(--text-primary);
        margin-bottom: 8px;
    }
    
    .empty-state p {
        color: var(--text-secondary);
        font-size: 14px;
        margin-bottom: 24px;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        padding-top: 24px;
        border-top: 1px solid var(--border);
    }
    
    .btn-back {
        flex: 1;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        text-align: center;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-family: 'Inter', sans-serif;
        background: white;
        border: 2px solid var(--border);
        color: var(--text-primary);
    }
    
    .btn-back:hover {
        border-color: var(--utm-maroon);
        color: var(--utm-maroon);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .header-content {
            flex-wrap: wrap;
        }
        
        .header-controls {
            width: 100%;
            justify-content: space-between;
        }
        
        .container {
            padding: 16px;
        }
        
        .filters {
            justify-content: center;
        }
        
        .status-count {
            margin-left: 0;
            width: 100%;
            text-align: center;
        }
        
        .report-header {
            flex-direction: column;
            gap: 12px;
        }
        
        .report-footer {
            flex-direction: column;
            gap: 8px;
            align-items: flex-start;
        }
        
        .page-title-card h1 {
            font-size: 22px;
        }
    }
  </style>
</head>
<body>
  <header class="main-header">
    <div class="header-content">
      <div class="logo-section">
        <img src="assets/images/utmlogo.png" alt="UTM Logo" class="logo">
        <div class="logo-text">
          <h1>Room Booking System</h1>
          <p>Universiti Teknologi Malaysia</p>
        </div>
      </div>
      
      <div class="header-controls">
        <a href="booking_status.html" class="btn btn-secondary">
          <i class="fa-solid fa-calendar-check"></i>
          Booking Status
        </a>
        
        <div class="dropdownmenu">
          <button class="dropbtn">
            <i class="fa-solid fa-tools"></i>
            Room Problem
            <i class="fa-solid fa-chevron-down" style="font-size: 10px;"></i>
          </button>
          <div class="dropdownmenu-content">
            <a href="user_report_problem.php"><i class="fa-solid fa-triangle-exclamation"></i> Report Issue</a>
            <a href="user_problem_status.php"><i class="fa-solid fa-list-check"></i> Problem Status</a> 
          </div>
        </div>
        
        <a href="timetable.html" class="btn btn-nav">
          <i class="fa-solid fa-calendar"></i>
          Timetable
        </a>
        
        <a href="auth/logout.php" class="btn btn-nav">
          <i class="fa-solid fa-sign-out-alt"></i>
          Logout
        </a>
      </div>
    </div>
  </header>

  <div class="container">
    <div class="page-title-card">
      <h1>
        <i class="fa-solid fa-clipboard-list"></i>
        My Reported Problems
      </h1>
      <p>Track the status of your room problem reports</p>
    </div>

    <div class="filters-card">
      <div class="filters" aria-label="Problem filters">
        <button class="filter-btn active" onclick="filterReports('all')">
          <i class="fa-solid fa-list"></i>
          All Reports
        </button>
        <button class="filter-btn" onclick="filterReports('pending')">
          <i class="fa-solid fa-clock"></i>
          Pending
        </button>
        <button class="filter-btn" onclick="filterReports('in-progress')">
          <i class="fa-solid fa-spinner"></i>
          In Progress
        </button>
        <button class="filter-btn" onclick="filterReports('resolved')">
          <i class="fa-solid fa-check-circle"></i>
          Resolved
        </button>
        <div class="status-count" id="statusCount">
          <?php echo count($reports); ?> report<?php echo count($reports) !== 1 ? 's' : ''; ?>
        </div>
      </div>
    </div>

    <div class="reports-container" id="reportsContainer">
      <?php if (!empty($reports)): ?>
        <?php foreach ($reports as $report): ?>
          <div class="report-card" data-status="<?php echo strtolower($report['status']); ?>">
            <div class="report-header">
              <div class="report-info">
                <h3 class="report-title">
                  <i class="fa-solid fa-tools"></i>
                  <?php echo htmlspecialchars($report['title']); ?>
                </h3>
                <div class="report-meta">
                  <span class="report-meta-item">
                    <i class="fa-solid fa-door-open"></i>
                    <strong><?php echo htmlspecialchars($report['room_name']); ?></strong>
                  </span>
                  <span class="report-meta-item">
                    <i class="fa-solid fa-hashtag"></i>
                    ID: <span class="report-id"><?php echo str_pad($report['id'], 4, '0', STR_PAD_LEFT); ?></span>
                  </span>
                </div>
              </div>
              <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $report['status'])); ?>">
                <?php 
                  $status = strtolower($report['status']);
                  if ($status === 'pending') {
                      echo '<i class="fa-solid fa-clock"></i>';
                  } elseif ($status === 'resolved') {
                      echo '<i class="fa-solid fa-check-circle"></i>';
                  } elseif ($status === 'in-progress' || $status === 'in progress') {
                      echo '<i class="fa-solid fa-spinner"></i>';
                  }
                  echo ucfirst(htmlspecialchars($report['status'])); 
                ?>
              </span>
            </div>
            
            <div class="report-body">
              <p class="report-description">
                <?php echo nl2br(htmlspecialchars($report['description'])); ?>
              </p>
            </div>
            
            <div class="report-footer">
              <div class="report-date">
                <i class="fa-solid fa-calendar"></i>
                Reported: <?php echo date("F j, Y", strtotime($report['created_at'])); ?>
              </div>
              <div class="report-date">
                <i class="fa-solid fa-clock"></i>
                <?php echo date("g:i A", strtotime($report['created_at'])); ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <i class="fa-solid fa-inbox"></i>
          <h3>No Reports Yet</h3>
          <p>You haven't reported any room problems yet.</p>
          <a href="user_report_problem.php" class="btn btn-secondary">
            <i class="fa-solid fa-plus"></i>
            Report a Problem
          </a>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($reports)): ?>
    <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); border: 1px solid var(--border); margin-top: 20px;">
      <div class="action-buttons">
        <a href="timetable.html" class="btn-back">
          <i class="fa-solid fa-arrow-left"></i>
          Back to Timetable
        </a>
        <a href="user_report_problem.php" class="btn btn-secondary" style="flex: 1; justify-content: center;">
          <i class="fa-solid fa-plus"></i>
          Report New Problem
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <script>
    function filterReports(status) {
      const cards = document.querySelectorAll('.report-card');
      const filterBtns = document.querySelectorAll('.filter-btn');
      const statusCount = document.getElementById('statusCount');
      
      // Update active button
      filterBtns.forEach(btn => btn.classList.remove('active'));
      event.target.closest('.filter-btn').classList.add('active');
      
      let visibleCount = 0;
      
      cards.forEach(card => {
        const cardStatus = card.dataset.status.replace(' ', '-');
        if (status === 'all' || cardStatus === status) {
          card.style.display = 'block';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });
      
      statusCount.textContent = `${visibleCount} report${visibleCount !== 1 ? 's' : ''}`;
    }
  </script>

</body>
</html>