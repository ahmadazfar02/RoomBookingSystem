<?php
/**
 * admin_problems.php
 * Admin page to view and manage room problems reported by users
 * PLACEHOLDER - Full implementation pending
 */
session_start();
require_once 'db_connect.php';

// Security check - Admin only
$admin_id = $_SESSION['User_ID'] ?? $_SESSION['id'] ?? null;
$user_type = $_SESSION['User_Type'] ?? null;

if (!$admin_id || strcasecmp(trim($user_type ?? ''), 'Admin') !== 0) {
    header('Location: loginterface.html');
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin';
$admin_email = $_SESSION['Email'] ?? 'admin@utm.my';
$username = $_SESSION['username'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Room Problems - Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --primary-light: #dbeafe;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.nav-bar {
    background: white;
    padding: 16px 24px;
    box-shadow: var(--shadow-md);
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 80px;
    z-index: 1000;
    display: flex;
    align-items: center;
}
.nav-bar img { height: 50px; }

.layout {
    width: 100%;
    max-width: 2000px;
    margin: 100px auto 0;
    padding: 24px;
    display: flex;
    gap: 24px;
}

.sidebar {
    width: 260px;
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: var(--shadow-md);
    position: sticky;
    top: 100px;
    height: fit-content;
}

.sidebar-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--gray-600);
    text-transform: uppercase;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--gray-200);
}

.sidebar-menu { list-style: none; }
.sidebar-menu li { margin-bottom: 8px; }
.sidebar-menu a {
    display: block;
    padding: 12px 16px;
    border-radius: 8px;
    text-decoration: none;
    color: var(--gray-700);
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}
.sidebar-menu a:hover { background: var(--gray-100); color: var(--primary); }
.sidebar-menu a.active { background: var(--primary-light); color: var(--primary); font-weight: 600; }

.sidebar-profile {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 12px;
}
.profile-icon {
    width: 36px; height: 36px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-weight: 700;
}
.profile-info { font-size: 13px; }
.profile-name { font-weight: 600; color: var(--gray-800); }
.profile-email { font-size: 11px; color: var(--gray-600); }

.main { flex: 1; }

.card {
    background: white;
    border-radius: 12px;
    padding: 32px;
    box-shadow: var(--shadow-md);
}

.card h1 {
    font-size: 24px;
    color: var(--gray-800);
    margin-bottom: 8px;
}

.card .subtitle {
    color: var(--gray-600);
    margin-bottom: 24px;
}

.coming-soon {
    text-align: center;
    padding: 60px 20px;
}

.coming-soon-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.coming-soon h2 {
    font-size: 28px;
    color: var(--gray-800);
    margin-bottom: 12px;
}

.coming-soon p {
    color: var(--gray-600);
    font-size: 16px;
    max-width: 400px;
    margin: 0 auto;
}

.badge {
    display: inline-block;
    background: #fef3c7;
    color: #92400e;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-top: 20px;
}
</style>
</head>
<body>

<nav class="nav-bar">
    <img src="img/utmlogo.png" alt="UTM Logo">
</nav>

<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-title">Main Menu</div>
        <ul class="sidebar-menu">
            <li><a href="index-admin.php">Dashboard</a></li>
            <li><a href="reservation_request.php">Reservation Request</a></li>
            <li><a href="admin_timetable.php">Regular Timetable</a></li>
            <li><a href="admin_recurring.php">Recurring Templates</a></li>
            <?php if ($username === 'superadmin'): ?>
                <li><a href="manage_users.php">Manage Users</a></li>
            <?php endif; ?>
            <li><a href="admin_logbook.php">Logbook</a></li>
            <li><a href="generate_reports.php">Generate Reports</a></li>
            <li><a href="admin_problems.php" class="active">Room Problems</a></li>
        </ul>

        <div class="sidebar-profile">
            <div class="profile-icon"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
            <div class="profile-info">
                <div class="profile-name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="profile-email"><?php echo htmlspecialchars($admin_email); ?></div>
            </div>
        </div>
    </aside>

    <main class="main">
        <div class="card">
            <h1>🔧 Room Problems</h1>
            <p class="subtitle">View and manage room issues reported by users</p>

            <div class="coming-soon">
                <div class="coming-soon-icon">🚧</div>
                <h2>Feature Coming Soon</h2>
                <p>This page will display all room problems and issues reported by users. You'll be able to view, respond to, and resolve reported issues.</p>
                <span class="badge">Under Development</span>
            </div>
        </div>
    </main>
</div>

</body>
</html>


