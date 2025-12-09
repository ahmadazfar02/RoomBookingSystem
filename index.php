<?php
session_start(); 
require_once 'db_connect.php';

if (!isset($_SESSION['loggedin'])) {
    header("location: loginterface.html"); 
    exit;
}

$user_name = $_SESSION['Fullname'] ?? $_SESSION['username'] ?? 'User'; 

// Fetch rooms from database
$rooms = [];
$result = $conn->query("SELECT room_id, name, capacity FROM rooms WHERE active = 1 ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Room Reservation - Booking</title>
<link href="style.css" rel="stylesheet">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <img src="img/utmlogo.png" alt="UTM Logo" class="logo">
            <div class="header-controls">
                <div class="user-control-area">
                    <div class="greeting">
                        Hi, <?php echo htmlspecialchars($user_name); ?>
                    </div>
                    <a href="logout.php">Logout</a>
                </div>

                <button class="booking-status-btn"> 
                    Booking Status 
                    <span class="notification-badge"></span>
                </button>
            </div>
        </div>
    </header>
    
    <main class="main-content">
        <div class="search-container">
            <select class="dropdown" name="rooms" id="room">
                <option value="">-- Select a Room --</option>
                <?php foreach ($rooms as $room): ?>
                    <option value="<?php echo htmlspecialchars($room['room_id']); ?>">
                        <?php echo htmlspecialchars($room['name']); ?> (<?php echo $room['capacity']; ?> pax)
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="show-btn">Show</button>
        </div>
    </main>
    
</body>
</html>
