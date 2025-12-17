<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php'; // Adjust path as needed

// SECURITY: Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: loginterface.html"); // Redirect if not logged in
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
<title>My Reported Problems</title>
<link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --accent: #5c6bc0;
        --accent-dark: #3f51b5;
        --bg-gradient: linear-gradient(135deg, #7986cb 10%, #B3E5FC 50%, #FF8A80 100%);
        --card-bg: #ffffff;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --border: #e5e7eb;
        --pending-bg: #fffbe6;
        --pending-text: #b45309;
        --resolved-bg: #dcfce7;
        --resolved-text: #059669;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body { 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: var(--bg-gradient);
        min-height: 100vh;
        color: var(--text-primary);
    }
    .main-header {
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
    }
    .booking-status-btn {
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        position: relative;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .booking-status-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }
    

    .notification-badge {
        display: none;
        position: absolute;
        top: -8px;
        right: -8px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        border-radius: 10px;
        padding: 2px 6px;
        font-size: 11px;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4);
    }
    
    .notification-badge.active { display: block; }
    
    .header-content {
        max-width: 1400px;
        margin: 0 auto;
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .logo { height: 72px; }
    .main-content {
        max-width: 1400px;
        margin: 40px auto;
        padding: 24px;
    }
    
    .card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        margin-bottom: 25px;
    }

    h2 {
        color: var(--accent);
        font-size: 28px;
        margin-bottom: 25px;
        border-bottom: 2px solid var(--border);
        padding-bottom: 10px;
    }

    .report-list {
        display: grid;
        gap: 20px;
    }

    .report-item {
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        background: var(--card-bg);
        transition: all 0.3s ease;
    }
    .report-item:hover {
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px dashed var(--border);
    }

    .report-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--accent-dark);
        margin: 0;
    }

    .report-room {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .report-status {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-pending {
        background: var(--pending-bg);
        color: var(--pending-text);
    }
    
    .status-resolved {
        background: var(--resolved-bg);
        color: var(--resolved-text);
    }

    .report-body p {
        font-size: 14px;
        color: var(--text-secondary);
        margin-top: 10px;
        margin-bottom: 15px;
    }
    
    .report-footer {
        font-size: 12px;
        color: var(--text-secondary);
        text-align: right;
    }

    .btn-back {
        background: var(--accent);
        color: white;
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        display: inline-block;
        margin-top: 20px;
        transition: background 0.3s;
    }
    .btn-back:hover {
        background: var(--accent-dark);
    }
    .btn {
        padding: 10px 18px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-nav {
        background: white;
        border: 2px solid var(--border);
        color: var(--text-primary);
    }
    
    .btn-nav:hover {
        border-color: var(--accent);
        color: var(--accent);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }
    /*Dropdown for Room Problem*/
    .dropdownmenu {
      display: inline-block;
    }
    .dropbtn{
      background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        position: relative;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .dropbtn:hover{ 
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }
    .dropdownmenu-content {
        display: none; /* Hidden by default */
        position: absolute;
        background-color: #f9f9f9;
        min-width: 160px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1;
      }

    .dropdownmenu-content a {
        color: black;
        padding: 12px 16px;
        text-decoration: none;
        display: block;
        border-radius: 8px;
    }
    .dropdownmenu-content a:hover {
        transform: translate(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
        color: white;
    }

/* Show the dropdown menu on hover */
    .dropdownmenu:hover .dropdownmenu-content {
      display: block;
    }
</style>
</head>
<body>
<header class="main-header">
    <div class="header-content">
      <img src="assets/images/utmlogo.png" alt="UTM Logo" class="logo">
      <div class="header-controls">
        <a href="booking_status.html" style="text-decoration: none;">
          <button class="booking-status-btn">
            Booking Status
            <span class="notification-badge" id="notificationBadge">0</span>
          </button>
        </a>
        <div class="dropdownmenu">
        <button class="dropbtn">Room Problem</button>
        <div class="dropdownmenu-content">
          <a href="user_report_problem.php">Report Issue</a>
          <a href="user_problem_status.php">Room Problem Status</a> 
        </div>
        </div>
        <!-- add this where your header controls are -->
        <a href="auth/logout.php">
          <button class="btn btn-nav">
            Logout
          </button>
        </a>
      </div>
    </div>
  </header>
    <main class="main-content">
        <div class="card">
            <h2>My Reported Problems</h2>

            <div class="report-list">
                <?php if (!empty($reports)): ?>
                    <?php foreach ($reports as $report): ?>
                        <div class="report-item">
                            <div class="report-header">
                                <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                    <h3 class="report-title"><?php echo htmlspecialchars($report['title']); ?></h3>
                                    <span class="report-room">Room: <?php echo htmlspecialchars($report['room_name']); ?></span>
                                </div>
                                <span class="report-status status-<?php echo strtolower($report['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($report['status'])); ?>
                                </span>
                            </div>
                            <div class="report-body">
                                <p><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
                            </div>
                            <div class="report-footer">
                                Reported: <?php echo date("F j, Y, g:i a", strtotime($report['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; padding: 30px; color: var(--text-secondary);">
                        You have not reported any room problems yet.
                    </p>
                <?php endif; ?>
            </div>

            <a href="timetable.html" class="btn-back">← Back to Timetable</a>

        </div>
    </main>

</body>
</html>