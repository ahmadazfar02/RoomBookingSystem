<?php
// user_report_problem.php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
if (!isset($_SESSION['loggedin'])) { header("Location: loginterface.html"); exit; }

$submitSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room = $_POST['room_id'];
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $uid = $_SESSION['id'];
    
    $stmt = $conn->prepare("INSERT INTO room_problems (user_id, room_id, title, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $uid, $room, $title, $desc);
    if ($stmt->execute()) {
        $submitSuccess = true;
    }
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
    
    .btn-primary:hover:not(:disabled) {
        background: var(--utm-maroon-light);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(128, 0, 0, 0.3);
    }
    
    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
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

    /* Loading Overlay */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(4px);
    }

    .loading-overlay.active {
        display: flex;
    }

    .loading-card {
        background: white;
        padding: 40px;
        border-radius: 16px;
        text-align: center;
        max-width: 400px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .spinner {
        width: 60px;
        height: 60px;
        border: 4px solid var(--border);
        border-top: 4px solid var(--utm-maroon);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 24px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .loading-card h3 {
        color: var(--text-primary);
        font-size: 20px;
        margin-bottom: 8px;
    }

    .loading-card p {
        color: var(--text-secondary);
        font-size: 14px;
    }

    /* Success Card */
    .success-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(4px);
    }

    .success-overlay.active {
        display: flex;
    }

    .success-card {
        background: white;
        padding: 48px;
        border-radius: 16px;
        text-align: center;
        max-width: 450px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideIn 0.3s ease;
        position: relative;
    }

    .close-btn {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 32px;
        height: 32px;
        border: none;
        background: var(--bg-light);
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        color: var(--text-secondary);
    }

    .close-btn:hover {
        background: var(--utm-maroon);
        color: white;
        transform: scale(1.1);
    }

    .success-icon {
        width: 80px;
        height: 80px;
        background: #dcfce7;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 24px;
        animation: scaleIn 0.5s ease;
    }

    @keyframes scaleIn {
        0% {
            transform: scale(0);
        }
        50% {
            transform: scale(1.1);
        }
        100% {
            transform: scale(1);
        }
    }

    .success-icon i {
        font-size: 40px;
        color: #16a34a;
    }

    .success-card h3 {
        color: var(--text-primary);
        font-size: 24px;
        margin-bottom: 12px;
        font-weight: 700;
    }

    .success-card p {
        color: var(--text-secondary);
        font-size: 15px;
        margin-bottom: 32px;
        line-height: 1.6;
    }

    .success-actions {
        display: flex;
        gap: 12px;
    }

    .success-actions .btn-report {
        flex: 1;
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

        .loading-card, .success-card {
            margin: 20px;
            padding: 32px 24px;
        }
        
        .btn-group, .success-actions {
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
  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="loading-card">
      <div class="spinner"></div>
      <h3>Submitting Report</h3>
      <p>Please wait while we process your report...</p>
    </div>
  </div>

  <!-- Success Overlay -->
  <div class="success-overlay" id="successOverlay">
    <div class="success-card">
      <button class="close-btn" onclick="closeSuccessOverlay()">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <div class="success-icon">
        <i class="fa-solid fa-check"></i>
      </div>
      <h3>Report Submitted Successfully!</h3>
      <p>Your report has been received. Our maintenance team will review it and take appropriate action. You can track the status of your report anytime.</p>
      <div class="success-actions">
        <a href="user_report_problem.php" class="btn-report btn-ghost">
          <i class="fa-solid fa-plus"></i>
          Report Another Issue
        </a>
        <a href="user_problem_status.php" class="btn-report btn-primary">
          <i class="fa-solid fa-list-check"></i>
          View Problem Status
        </a>
      </div>
    </div>
  </div>

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

      <form method="POST" id="reportForm">
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
          <button type="submit" class="btn-report btn-primary" id="submitBtn">
            <i class="fa-solid fa-paper-plane"></i>
            Submit Report
          </button>
        </div>
      </form>
    </div>
  </main>

  <script>
    // Handle form submission with loading state
    document.getElementById('reportForm').addEventListener('submit', function(e) {
      // Show loading overlay
      document.getElementById('loadingOverlay').classList.add('active');
      document.getElementById('submitBtn').disabled = true;
    });

    // Show success overlay if submission was successful
    <?php if ($submitSuccess): ?>
    window.addEventListener('load', function() {
      // Hide loading, show success
      document.getElementById('loadingOverlay').classList.remove('active');
      document.getElementById('successOverlay').classList.add('active');
    });
    <?php endif; ?>

    // Close success overlay function
    function closeSuccessOverlay() {
      document.getElementById('successOverlay').classList.remove('active');
    }

    // Allow clicking outside the card to close
    document.getElementById('successOverlay').addEventListener('click', function(e) {
      if (e.target === this) {
        closeSuccessOverlay();
      }
    });
  </script>

</body>
</html>