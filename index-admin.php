<?php
session_start(); 

if (!isset($_SESSION['loggedin']) || $_SESSION['User_Type'] !== 'Admin') {
    // Redirect if not logged in or not an admin
    header("location: login.php"); 
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin'; 
?>
 
<style>
.user-control-area {
    position: absolute;
    top: 25px; 
    right: 150px; 
    text-align: right;
    color: #333; /
    font-family: Arial, sans-serif;
    z-index: 100;
}

.user-control-area .greeting {
    font-weight: bold;
    font-size: 1.1rem;
    margin-bottom: 5px;
    color: #6e0b0b; 
}

.user-control-area a {
    color: #1b2ea6; 
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.2s;
}

.user-control-area a:hover {
    color: #c03a3a;
    text-decoration: underline;
}
</style>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title>Admin Page</title>
		<link rel="stylesheet" href="AdminDashboard.css" />
		<link href="style.css" rel="stylesheet">
		<link rel="preconnect" href="https://fonts.googleapis.com" />
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
		<link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet" />
	</head>
	<body style="background: url(../roomreserve/img/site-bg.png); object-position: fixed; background-size: cover; background-repeat: no-repeat; min-height: 100vh">
		<div
			style="position: absolute; content: ''; top: 0; left: 0; width: 100%; height: 100%; background-color: white; opacity: 0.4; z-index: -1"
		></div>

		<nav class="nav-bar">
			<img src="img/utmlogo.png" alt="utm logo" width="250px" />
		</nav>
		<div class="user-control-area">
            <div class="greeting">
                Hi, <?php echo htmlspecialchars($admin_name); ?>
            </div>
            <a href="logout.php">
                Logout <i class="fa-solid fa-right-from-bracket"></i>
            </a>
			
        </div>
        
		<div class="nav-links-container">
			<div class="nav-links-selected nav-links">Dashboard</div>
			<div class="nav-links-separator"></div>
			<div class="nav-links">Reservation Request</div>
			<div class="nav-links-separator"></div>
			<div class="nav-links">Timetable</div>
			<div class="nav-links-separator"></div>
			<div class="nav-links">Logbook</div>
			
		</div>

		<div class="dashboard-grid-display">
			<div class="a">
				<h4 style="text-wrap-mode: nowrap; margin-bottom: 1rem">Total Rooms</h4>
				<p style="font-size: 2.5rem">20</p>
			</div>
			<div class="b">
				<h4 style="text-wrap-mode: nowrap; margin-bottom: 1rem">Active Booking Today</h4>
				<p style="font-size: 2.5rem">8</p>
			</div>
			<div class="c">
				<h4 style="text-wrap-mode: nowrap; margin-bottom: 1rem">Pending Approvals</h4>
				<p style="font-size: 2.5rem">3</p>
			</div>
			<div class="d">
				<h4 style="text-wrap-mode: nowrap; margin-bottom: 1rem; padding: 1rem 1rem 0rem 1rem">Notifications</h4>
				<div style="border-top: 0.5px black solid; padding: 1rem; font-size: 0.8rem">Room 02.31.01 reserved by Amir has been approved.</div>
				<div style="border-top: 0.5px black solid; padding: 1rem; font-size: 0.8rem">
					Room 02.31.01 under maintenance today &#40;8-10am&#41;.
				</div>
			</div>
			<div class="e">
				<h4 style="text-wrap-mode: nowrap; margin-bottom: 1rem; padding: 1rem 1rem 0rem 1rem">Booking Requests</h4>
				<table>
					<thead>
						<tr>
							<th>Ticket</th>
							<th>Room Name</th>
							<th>Room No.</th>
							<th>User</th>
							<th>Date</th>
							<th>Time</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>1001</td>
							<td><strong>Bilik Kuliah 1</strong></td>
							<td>02.31.01</td>
							<td>Khairul</td>
							<td>7/6/2025</td>
							<td>8:00–9:00 am</td>
							<td><span class="status pending">Pending</span></td>
						</tr>
						<tr>
							<td>1002</td>
							<td><strong>Bilik Kuliah 2</strong></td>
							<td>02.31.02</td>
							<td>Amir</td>
							<td>7/6/2025</td>
							<td>8:00–9:00 am</td>
							<td><span class="status approve">Approve</span></td>
						</tr>
						<tr>
							<td>1003</td>
							<td><strong>Bilik Kuliah 3</strong></td>
							<td>02.31.03</td>
							<td>Amsyar</td>
							<td>7/6/2025</td>
							<td>8:00–9:00 am</td>
							<td><span class="status cancel">Cancel</span></td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="f">
				<h4 style="text-wrap-mode: nowrap; margin-bottom: 1rem">Reports</h4>
				<div
					style="
						display: flex;
						flex-direction: row;
						align-content: center;
						justify-content: space-between;
						border-radius: 5px;
						padding: 2px 12px;
						border: 1px solid black;
						background-color: white;
					"
				>
					<div>Generate Reports</div>
					<img src="../roomreserve/img/arrow-down-svgrepo-com.svg" alt="svg" style="rotate: -90deg" width="25px" />
				</div>
			</div>
		</div>
	</body>
</html>
