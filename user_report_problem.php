<?php
// user_report_problem.php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
if (!isset($_SESSION['loggedin'])) { header("Location: loginterface.html"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room = $_POST['room_id'];
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $uid = $_SESSION['id'];
    
    $stmt = $conn->prepare("INSERT INTO room_problems (user_id, room_id, title, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $uid, $room, $title, $desc);
    if ($stmt->execute()) echo "<script>alert('Report submitted successfully!');</script>";
}
// Fetch Rooms
$rooms = $conn->query("SELECT room_id, name FROM rooms");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Room Booking System</title>
  <style>
    :root {
        --accent: #5c6bc0;
        --accent-dark: #3f51b5;
        --bg-gradient: linear-gradient(135deg, #7986cb 10%, #B3E5FC 50%, #FF8A80 100%);
        --card-bg: #ffffff;
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --border: #e5e7eb;
    }
    
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body { 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: var(--bg-gradient);
        min-height: 100vh;
        color: var(--text-primary);
    }
    
    /* Header */
    .main-header {
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
    }
    
    .logo { height: 72px; }
    
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
    
    .main-content {
        max-width: 1400px;
        margin: 0 auto;
        padding: 24px;
    }
    
    .search-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }
    
    
    .dropdown {
        padding: 10px 14px;
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
        min-width: 300px;
        transition: all 0.3s ease;
        background: white;
    }
    
    .dropdown:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(92, 107, 192, 0.1);
    }
    
    .input[type="text"] {
        padding: 10px 14px;
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
        min-width: 300px;
        transition: all 0.3s ease;
        background: white;
    }
    
    .input[type="text"]:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(92, 107, 192, 0.1);
    }
    
    input[type="date"] {
        padding: 10px 14px;
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
    }
    
    input[type="date"]:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(92, 107, 192, 0.1);
    }
    
    .nav-controls {
        display: flex;
        gap: 8px;
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
    
    .btn-primary {
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }
    
    .btn-disabled {
        background: #d1d5db;
        cursor: not-allowed;
        box-shadow: none;
    }
    
    .btn-disabled:hover {
        transform: none;
    }
    
    .btn-ghost {
        background: white;
        border: 2px solid var(--border);
    }
    
    .btn-ghost:hover {
        border-color: var(--accent);
        color: var(--accent);
    }
    
    
    .legend {
        display: flex;
        gap: 20px;
        margin-bottom: 16px;
        font-size: 14px;
        flex-wrap: wrap;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        background: rgba(255, 255, 255, 0.7);
        border-radius: 8px;
    }
    
    .legend-color {
        width: 20px;
        height: 20px;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .legend-available { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); }
    .legend-selected { background: linear-gradient(135deg, #fde68a 0%, #fbbf24 100%); }
    .legend-pending { background: linear-gradient(135deg, #fef3c7 0%, #fde047 100%); }
    .legend-booked { background: linear-gradient(135deg, #fecaca 0%, #f87171 100%); }
    .slot.maintenance {
        background: linear-gradient(135deg, #fed7aa 0%, #fb923c 100%);
        cursor: not-allowed;
        color: #7c2d12;
    }

    .legend-maintenance { 
        background: linear-gradient(135deg, #fed7aa 0%, #fb923c 100%); 
    }
    
    .card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }
    
    .card h2 {
        margin-bottom: 20px;
        color: var(--accent);
        font-size: 24px;
    }
    
    /* Main Layout */
    .main-content {
        max-width: 1400px;
        width: 100%;
        margin: 0 auto;
        padding: 40px 24px;
        display: flex;
        justify-content: center; /* Center the form */
    }

    /* Card Style */
    .card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        width: 100%;
        max-width: 600px; /* Limit width for readability */
    }

    .card h2 {
        margin-bottom: 24px;
        color: var(--accent);
        font-size: 28px;
        font-weight: 700;
        text-align: center;
    }

    /* Form Elements */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 14px;
        color: var(--text-primary);
    }

    /* Unified Input Styles */
    .form-control {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
        background: white;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(92, 107, 192, 0.1);
    }

    textarea.form-control {
        resize: vertical;
        min-height: 120px;
    }

    
    .cell-title {
        font-weight: 600;
        font-size: 12px;
    }
    
    .cell-meta {
        font-size: 11px;
        color: var(--text-secondary);
    }
    
    .cell-capacity {
        font-size: 11px;
        color: #6b7280;
        margin-top: 4px;
    }
    
    .card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
    }
    
    .footer-info {
        font-size: 14px;
        color: var(--text-secondary);
    }
    
    .search-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    
    .search-modal.visible {
        display: flex;
    }
    
    .search-modal-content {
        background: white;
        border-radius: 16px;
        padding: 32px;
        max-width: 800px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    }
    
    .search-modal-content h3 {
        color: var(--accent);
        font-size: 24px;
        margin-bottom: 20px;
    }
    
    .search-form-group {
        margin-bottom: 16px;
    }
    
    .search-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 14px;
        color: var(--text-primary);
    }
    
    .search-form-group input {
        width: 100%;
        padding: 12px 14px;
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
    }
    
    .search-form-group input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(92, 107, 192, 0.1);
    }
    
    .search-time-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    
    .search-modal-actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
    }
    
    .btn-close,
    .btn-search {
        flex: 1;
        padding: 12px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-close {
        background: white;
        border: 2px solid var(--border);
        color: var(--text-primary);
    }
    
    .btn-close:hover {
        border-color: var(--accent);
        color: var(--accent);
    }
    
    .btn-search {
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .btn-report {
        flex: 1;
        padding: 12px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        text-align: center;
        transition: all 0.3s ease;
        border: none;
        text-decoration: none;
        display: inline-block;
    }
    .btn-group {
        display: flex;
        gap: 12px;
        margin-top: 30px;
    }
    .btn-search:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }
    .btn-primary {
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }
    .btn-ghost {
        background: white;
        border: 2px solid var(--border);
        color: var(--text-secondary);
    }

    .btn-ghost:hover {
        border-color: var(--accent);
        color: var(--accent);
        background: #f9fafb;
    }
    .actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
    }
    
    .small {
        font-size: 13px;
        color: var(--text-secondary);
    }
    
    /* Responsive */
    @media (max-width: 800px) {
        .search-container {
            flex-direction: column;
            align-items: stretch;
        }
        
        .nav-controls {
            flex-direction: column;
        }
        
        .dropdown {
            min-width: 100%;
        }
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
            <h2>Report a Room Problem</h2>
            
            <form method="POST">
                
                <!-- Room Select -->
                <div class="form-group">
                    <label>Select Room</label>
                    <select class="form-control" name="room_id" required>
                        <option value="" disabled selected>-- Choose a Room --</option>
                        <?php 
                        // Reset pointer in case it was used elsewhere
                        if(isset($rooms) && $rooms->num_rows > 0) {
                            $rooms->data_seek(0); 
                            while($r = $rooms->fetch_assoc()): 
                        ?>
                            <option value="<?php echo htmlspecialchars($r['room_id']); ?>">
                                <?php echo htmlspecialchars($r['name']); ?>
                            </option>
                        <?php 
                            endwhile; 
                        }
                        ?>
                    </select>
                </div>

                <!-- Issue Title -->
                <div class="form-group">
                    <label>Issue Title</label>
                    <input type="text" class="form-control" name="title" placeholder="e.g. Air Conditioner Leaking" required>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="description" placeholder="Describe the issue in detail..." required></textarea>
                </div>

                <!-- Actions -->
                <div class="btn-group">
                    <!-- Note: 'href' works on <a> tags. I wrapped the back button in an <a> tag -->
                    <a href="timetable.html" class="btn-report btn-ghost">Cancel</a>
                    <button type="submit" class="btn-report btn-primary">Submit Report</button>
                </div>

            </form>
        </div>
    </main>

</body>
</html>