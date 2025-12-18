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
  <title>Report Room Problem - UTM Room Booking</title>
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
    .dropdown-menu {
        position: relative;
        display: inline-block;
    }
    
    .dropdown-btn {
        background: var(--utm-maroon);
        color: white;
        box-shadow: 0 2px 4px rgba(128, 0, 0, 0.2);
        position: relative;
    }
    
    .dropdown-btn:hover {
        background: var(--utm-maroon-light);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(128, 0, 0, 0.3);
    }
    
    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        background-color: white;
        min-width: 200px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-radius: 8px;
        overflow: hidden;
        z-index: 1001;
        margin-top: 8px;
    }
    
    .dropdown-content a {
        color: var(--text-primary);
        padding: 12px 16px;
        text-decoration: none;
        display: block;
        transition: all 0.2s ease;
        font-size: 14px;
        font-weight: 500;
    }
    
    .dropdown-content a:hover {
        background: var(--utm-maroon);
        color: white;
    }
    
    .dropdown-menu:hover .dropdown-content {
        display: block;
    }
    
    /* Main Container */
    .container {
        max-width: 800px;
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
    
    /* Form Card */
    .form-card {
        background: white;
        padding: 32px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--border);
    }
    
    .form-group {
        margin-bottom: 24px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 14px;
        color: var(--text-primary);
    }
    
    .form-control {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
        background: white;
        transition: all 0.3s ease;
        font-family: 'Inter', sans-serif;
        color: var(--text-primary);
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--utm-maroon);
        box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 140px;
    }
    
    select.form-control {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 40px;
    }
    
    /* Button Group */
    .btn-group {
        display: flex;
        gap: 12px;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid var(--border);
    }
    
    .btn-report {
        flex: 1;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        text-align: center;
        transition: all 0.3s ease;
        border: none;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-family: 'Inter', sans-serif;
    }
    
    .btn-primary {
        background: var(--utm-maroon);
        color: white;
        box-shadow: 0 2px 4px rgba(128, 0, 0, 0.2);
    }
    
    .btn-primary:hover {
        background: var(--utm-maroon-light);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(128, 0, 0, 0.3);
    }
    
    .btn-ghost {
        background: white;
        border: 2px solid var(--border);
        color: var(--text-secondary);
    }
    
    .btn-ghost:hover {
        border-color: var(--utm-maroon);
        color: var(--utm-maroon);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    /* Info Box */
    .info-box {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 24px;
        display: flex;
        gap: 12px;
        align-items: start;
    }
    
    .info-box i {
        color: #0284c7;
        font-size: 18px;
        margin-top: 2px;
    }
    
    .info-box-content {
        flex: 1;
    }
    
    .info-box-content strong {
        display: block;
        color: #0c4a6e;
        margin-bottom: 4px;
        font-size: 14px;
    }
    
    .info-box-content p {
        color: #075985;
        font-size: 13px;
        margin: 0;
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
        
        .form-card {
            padding: 24px 20px;
        }
        
        .btn-group {
            flex-direction: column-reverse;
        }
        
        .btn-report {
            width: 100%;
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
        
        <div class="dropdown-menu">
          <button class="btn btn-secondary dropdown-btn">
            <i class="fa-solid fa-tools"></i>
            Room Problem
            <i class="fa-solid fa-chevron-down" style="font-size: 10px;"></i>
          </button>
          <div class="dropdown-content">
            <a href="user_report_problem.php">
              <i class="fa-solid fa-exclamation-circle"></i> Report Issue
            </a>
            <a href="user_problem_status.php">
              <i class="fa-solid fa-list-check"></i> Problem Status
            </a>
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

  <main class="container">
    <div class="page-title-card">
      <h1>
        <i class="fa-solid fa-exclamation-triangle"></i>
        Report Room Problem
      </h1>
      <p>Help us maintain our facilities by reporting any issues you encounter</p>
    </div>

    <div class="form-card">
      <div class="info-box">
        <i class="fa-solid fa-info-circle"></i>
        <div class="info-box-content">
          <strong>What to report?</strong>
          <p>Report any maintenance issues, equipment malfunctions, cleanliness concerns, or safety hazards you notice in any room.</p>
        </div>
      </div>

      <form method="POST">
        <!-- Room Select -->
        <div class="form-group">
          <label>
            <i class="fa-solid fa-door-open"></i>
            Select Room
          </label>
          <select class="form-control" name="room_id" required>
            <option value="" disabled selected>-- Choose a Room --</option>
            <?php 
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
          <label>
            <i class="fa-solid fa-heading"></i>
            Issue Title
          </label>
          <input 
            type="text" 
            class="form-control" 
            name="title" 
            placeholder="e.g., Air Conditioner Leaking" 
            required
          >
        </div>

        <!-- Description -->
        <div class="form-group">
          <label>
            <i class="fa-solid fa-file-lines"></i>
            Description
          </label>
          <textarea 
            class="form-control" 
            name="description" 
            placeholder="Please describe the issue in detail. Include information such as:&#10;- What is the problem?&#10;- When did you notice it?&#10;- Is it affecting room usage?&#10;- Any other relevant details" 
            required
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="btn-group">
          <a href="timetable.html" class="btn-report btn-ghost">
            <i class="fa-solid fa-times"></i>
            Cancel
          </a>
          <button type="submit" class="btn-report btn-primary">
            <i class="fa-solid fa-paper-plane"></i>
            Submit Report
          </button>
        </div>
      </form>
    </div>
  </main>

</body>
</html>