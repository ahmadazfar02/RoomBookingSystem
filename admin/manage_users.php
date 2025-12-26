<?php
/**
 * manage_users.php - User Management
 * Roles: 
 * - SuperAdmin: Full Access (All Roles)
 * - Technical Admin: Can ONLY view and create Technicians
 */
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';

// --- 1. ACCESS CONTROL ---
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['User_Type'])) {
    header("location: ../loginterface.html");
    exit;
}

$uType = trim($_SESSION['User_Type']);
$isSuperAdmin = (strcasecmp($uType, 'SuperAdmin') === 0);
$isTechAdmin  = (strcasecmp($uType, 'Technical Admin') === 0);

// Only allow SuperAdmin OR Technical Admin
if (!$isSuperAdmin && !$isTechAdmin) {
    header("location: index-admin.php");
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin';
$admin_email = $_SESSION['Email'] ?? ($_SESSION['User_Type'] ?? 'Admin');

// Security: Protected Accounts
$protected_usernames = ['superadmin', 'admin'];

$message = "";
$error = "";

// --- NOTIFICATION COUNTERS ---
$tech_pending = 0;
$pending_approvals = 0;
$active_problems = 0;

// Ensure we know who the user is
$uType = $_SESSION['User_Type'] ?? '';
$isTechAdmin_Check = (strcasecmp($uType, 'Technical Admin') === 0);

if ($isTechAdmin_Check) {
    // Tech Admin: Count Pending Repair JOBS (Grouped by Ticket ID)
    // This merges multiple slots (hours) into 1 notification if they belong to the same ticket
    $sql = "SELECT COUNT(DISTINCT CASE WHEN linked_problem_id > 0 THEN linked_problem_id ELSE session_id END) 
            FROM bookings 
            WHERE tech_token IS NOT NULL 
            AND tech_status != 'Work Done'";
    $result = $conn->query($sql);
    if($result) { $row = $result->fetch_row(); $tech_pending = intval($row[0]); }
} else {
    // Admin: Count Pending Request SESSIONS (Not Slots)
    $sql = "SELECT COUNT(DISTINCT session_id) FROM bookings WHERE status = 'pending'";
    $result = $conn->query($sql);
    if($result) { $row = $result->fetch_row(); $pending_approvals = intval($row[0]); }

    // Admin: Count Active Problems
    $sql = "SELECT COUNT(*) FROM room_problems WHERE status != 'Resolved'";
    $result = $conn->query($sql);
    if($result) { $row = $result->fetch_row(); $active_problems = intval($row[0]); }
}

// --- 2. HANDLE ACTIONS (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // A. CREATE USER
    if (isset($_POST['create_user'])) {
        $new_fullname = trim($_POST['new_fullname']);
        $new_email = trim($_POST['new_email']);
        
        // ROLE LOGIC: Tech Admin can ONLY create Technicians
        if ($isTechAdmin) {
            $new_role = 'Technician';
        } else {
            $new_role = $_POST['new_role']; 
        }

        // Check email uniqueness
        $stmt = $conn->prepare("SELECT id FROM users WHERE Email = ?");
        $stmt->bind_param("s", $new_email);
        $stmt->execute();
        if ($stmt->fetch()) {
            $error = "Error: That email is already registered.";
            $stmt->close();
        } else {
            $stmt->close();
            
            // Generate Username
            $username_base = explode('@', $new_email)[0];
            $new_username = $username_base;
            $counter = 1;
            while(true) {
                $chk = $conn->query("SELECT id FROM users WHERE username = '$new_username'");
                if ($chk->num_rows == 0) break;
                $new_username = $username_base . $counter++;
            }

            // Generate Token & Temp Password
            $token = generate_reset_token(); 
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $temp_password = bin2hex(random_bytes(8)); 
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

            // Insert
            $ins = $conn->prepare("INSERT INTO users (username, Fullname, Email, password_hash, User_Type, reset_token, reset_token_expiry) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("sssssss", $new_username, $new_fullname, $new_email, $hashed_password, $new_role, $token, $expiry);
            
            if ($ins->execute()) {
                $base = defined('SITE_BASE_URL') ? SITE_BASE_URL : "http://localhost/Roomreserve_New_Structure"; 
                $activation_link = $base . "/../auth/reset_password.php?token=" . $token;
                $sent = send_activation_email($new_email, $new_fullname, $new_username, $new_role, $activation_link);

                if ($sent) $message = "User ($new_role) created! Activation email sent.";
                else $message = "User created, but email failed to send.";
            } else {
                $error = "Database error: " . $conn->error;
            }
            $ins->close();
        }
    }

    // B. UPDATE ROLE (SuperAdmin Only)
    if (isset($_POST['user_id']) && isset($_POST['new_role']) && $isSuperAdmin) {
        $userIdToUpdate = intval($_POST['user_id']);
        $newRole = $_POST['new_role'];

        $check_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $userIdToUpdate);
        $check_stmt->execute();
        $check_stmt->bind_result($fetched_username);
        $check_stmt->fetch();
        $check_stmt->close();

        if (in_array(strtolower($fetched_username ?? ''), $protected_usernames)) {
            $error = "Action denied: Cannot modify Super Admin.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET User_Type = ? WHERE id = ?");
            $stmt->bind_param("si", $newRole, $userIdToUpdate);
            if ($stmt->execute()) {
                header("Location: manage_users.php?msg=updated");
                exit;
            }
            $stmt->close();
        }
    }

    // C. DELETE USER (SuperAdmin Only)
    if (isset($_POST['delete_user_id']) && $isSuperAdmin) {
        $userIdToDelete = intval($_POST['delete_user_id']);

        $check_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $userIdToDelete);
        $check_stmt->execute();
        $check_stmt->bind_result($fetched_username);
        $check_stmt->fetch();
        $check_stmt->close();

        if (in_array(strtolower($fetched_username ?? ''), $protected_usernames)) {
            $error = "Action denied: Cannot delete Protected Account.";
        } elseif ($userIdToDelete == $_SESSION['id']) {
            $error = "Action denied: Cannot delete yourself.";
        } else {
            try {
                $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $del_stmt->bind_param("i", $userIdToDelete);
                if ($del_stmt->execute()) {
                    header("Location: manage_users.php?msg=deleted");
                    exit;
                }
                $del_stmt->close();
            } catch (mysqli_sql_exception $e) {
                $error = "Cannot delete: User has linked records.";
            }
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'updated') $message = "User role successfully updated.";
    if ($_GET['msg'] == 'deleted') $message = "User successfully deleted.";
}

// --- 3. SEARCH, FILTER & PAGINATION ---
$search_query = trim($_GET['search'] ?? '');
$role_filter = $_GET['role_filter'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE
$placeholders = implode(',', array_fill(0, count($protected_usernames), '?'));
$where_clauses = ["username NOT IN ($placeholders)"];
$params = $protected_usernames; 
$types = str_repeat('s', count($protected_usernames));

// Search Filter
if (!empty($search_query)) {
    $where_clauses[] = "(username LIKE ? OR Fullname LIKE ? OR Email LIKE ?)";
    $like_term = "%" . $search_query . "%";
    $params[] = $like_term; $params[] = $like_term; $params[] = $like_term;
    $types .= "sss";
}

// Role Filter Logic
if ($isTechAdmin) {
    // 1. Technical Admin: FORCE filter to ONLY 'Technician'
    $where_clauses[] = "User_Type = 'Technician'";
} elseif (!empty($role_filter)) {
    // 2. SuperAdmin: Allow selected filter
    $where_clauses[] = "User_Type = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Count
$count_sql = "SELECT COUNT(*) as total FROM users WHERE $where_sql";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$stmt->close();

// Fetch Data
$sql = "SELECT id, username, Fullname, User_Type, Email FROM users WHERE $where_sql ORDER BY User_Type ASC, username ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$users = [];
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
if ($result) while($row = $result->fetch_assoc()) $users[] = $row;
$stmt->close();

function getQueryLink($newPage) {
    global $search_query, $role_filter;
    return "?page=$newPage&search=" . urlencode($search_query) . "&role_filter=" . urlencode($role_filter);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Users</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
:root { 
    --utm-maroon: #800000;
    --utm-maroon-light: #a31313;
    --bg-light: #f9fafb;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --border: #e2e8f0;
    --nav-height: 70px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: var(--bg-light); min-height: 100vh; color: var(--text-primary); }

/* Standard UI Components */
.nav-bar { position: fixed; top: 0; left: 0; right: 0; height: var(--nav-height); background: white; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); z-index: 1000; border-bottom: 1px solid var(--border); }
.nav-left { display: flex; align-items: center; gap: 16px; }
.nav-logo { height: 50px; }
.nav-title h1 { font-size: 16px; font-weight: 700; color: var(--utm-maroon); margin: 0; }
.nav-title p { font-size: 11px; color: var(--text-secondary); margin: 0; }
.btn-logout { text-decoration: none; color: var(--text-secondary); font-size: 13px; font-weight: 500; padding: 8px 12px; border-radius: 6px; transition: 0.2s; }
.btn-logout:hover { background: #fef2f2; color: var(--utm-maroon); }

.layout { display: flex; margin-top: var(--nav-height); min-height: calc(100vh - var(--nav-height)); }
.sidebar { width: 260px; background: white; border-right: 1px solid var(--border); padding: 24px; flex-shrink: 0; position: sticky; top: var(--nav-height); height: calc(100vh - var(--nav-height)); display: flex; flex-direction: column; }
.sidebar-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px; margin-bottom: 16px; }
.sidebar-menu { list-style: none; flex: 1; padding: 0; }
.sidebar-menu li { margin-bottom: 4px; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 6px; text-decoration: none; color: var(--text-primary); font-size: 14px; font-weight: 500; transition: all 0.2s; }
.sidebar-menu a:hover { background: var(--bg-light); color: var(--utm-maroon); }
.sidebar-menu a.active { background: #fef2f2; color: var(--utm-maroon); font-weight: 600; }
.sidebar-menu a i { width: 20px; text-align: center; }

/* NOTIFICATION BADGE */
.nav-badge {
    background-color: #dc2626; /* Red */
    color: white; 
    font-size: 10px; 
    font-weight: 700;
    padding: 2px 8px; 
    border-radius: 99px; 
    margin-left: auto; /* Pushes badge to the right */
}

.sidebar-profile { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 12px; }
.profile-icon { 
  width: 40px; height: 40px; 
  background: linear-gradient(135deg, var(--utm-maroon) 0%, var(--utm-maroon-light) 100%); 
  color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; 
  font-weight: 700; font-size: 15px; box-shadow: 0 2px 8px rgba(128,0,0,0.2);
}
.profile-info { font-size: 13px; overflow: hidden; }
.profile-name { font-weight: 600; white-space: nowrap; text-overflow: ellipsis; }
.profile-email { font-size: 11px; color: var(--text-secondary); white-space: nowrap; text-overflow: ellipsis; }

.main-content { flex: 1; padding: 32px; min-width: 0; }
.page-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: end; }
.page-title h2 { font-size: 24px; font-weight: 700; color: var(--utm-maroon); margin: 0; }
.page-title p { color: var(--text-secondary); font-size: 14px; margin: 4px 0 0 0; }

.card { background: white; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid var(--border); padding: 24px; }
.filters-container { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
.search-input { padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; width: 300px; font-size: 14px; outline: none; }
.role-select { padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; background: white; }
.search-input:focus, .role-select:focus { border-color: var(--utm-maroon); }

.btn { padding: 10px 16px; border-radius: 8px; border: 1px solid var(--border); background: white; color: var(--text-primary); font-weight: 600; cursor: pointer; font-size: 13px; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
.btn:hover { border-color: var(--utm-maroon); color: var(--utm-maroon); }
.btn-primary { background: var(--utm-maroon); color: white; border-color: var(--utm-maroon); }
.btn-primary:hover { background: var(--utm-maroon-light); color: white; border-color: var(--utm-maroon-light); }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-delete { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
.btn-update { background: #dcfce7; color: #166534; border-color: #bbf7d0; }

.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

.table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; }
.table { width: 100%; border-collapse: collapse; min-width: 900px; }
.table th, .table td { padding: 16px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: middle; }
.table th { background: #f8fafc; font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px; }
.role-select-inline { padding: 6px; border-radius: 6px; border: 1px solid var(--border); font-size: 13px; margin-right: 6px; }

.pagination { display: flex; align-items: center; justify-content: space-between; padding-top: 20px; border-top: 1px solid var(--border); margin-top: 20px; }
.page-info { font-size: 13px; color: var(--text-secondary); }
.page-nav { display: flex; gap: 6px; }
.page-link { padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; text-decoration: none; color: var(--text-primary); }
.page-link:hover { border-color: var(--utm-maroon); color: var(--utm-maroon); }
.page-link.active { background: var(--utm-maroon); color: white; border-color: var(--utm-maroon); }

/* Modal */
.modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(2px); }
.modal.show { display: flex; }
.modal-content { background: white; width: 95%; max-width: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); padding: 24px; animation: popIn 0.2s ease-out; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 16px; }
.modal-header h3 { margin: 0; font-size: 18px; color: var(--utm-maroon); font-weight: 700; }
.btn-close { background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary); }
.form-control, .form-select { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; }
.form-control:focus { border-color: var(--utm-maroon); }

@keyframes popIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
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
            <li>
                <a href="index-admin.php">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard                    
                </a>
            </li>
            
            <?php if (!$isTechAdmin): ?>
            <li>
                <a href="reservation_request.php" <?php echo basename($_SERVER['PHP_SELF']) == 'reservation_request.php' ? 'class="active"' : ''; ?>>
                    <i class="fa-solid fa-inbox"></i> Requests
                    <?php if ($pending_approvals > 0): ?>
                        <span class="nav-badge"><?php echo $pending_approvals; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

            <li><a href="admin_timetable.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_timetable.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
            
            <?php if (!$isTechAdmin): ?>
            <li><a href="admin_recurring.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_recurring.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-rotate"></i> Recurring</a></li>
            <li><a href="admin_logbook.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_logbook.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-book"></i> Logbook</a></li>
            <?php endif; ?>

            <li><a href="generate_reports.php" <?php echo basename($_SERVER['PHP_SELF']) == 'generate_reports.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-chart-pie"></i> Reports</a></li>
            
            <li>
                <a href="admin_problems.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin_problems.php' ? 'class="active"' : ''; ?>>
                    <i class="fa-solid fa-triangle-exclamation"></i> Problems
                    <?php if ($isTechAdmin && $tech_pending > 0): ?>
                        <span class="nav-badge"><?php echo $tech_pending; ?></span>
                    <?php elseif (!$isTechAdmin && $active_problems > 0): ?>
                        <span class="nav-badge"><?php echo $active_problems; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <?php if ($isSuperAdmin || $isTechAdmin): ?>
                <li><a href="manage_users.php" <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'class="active"' : ''; ?>><i class="fa-solid fa-users-gear"></i> Users</a></li>
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
                <h2>User Management</h2>
                <p>Create and manage system users.</p>
            </div>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fa-solid fa-user-plus"></i> Create User
            </button>
        </div>

        <div class="card">
            <?php if ($message): ?>
                <div class="alert alert-success" id="flashMsg"><i class="fa-solid fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error" id="flashMsg"><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="GET" class="filters-container">
                <input type="text" name="search" class="search-input" placeholder="Search users..." value="<?php echo htmlspecialchars($search_query); ?>">
                
                <?php if (!$isTechAdmin): ?>
                <select name="role_filter" class="role-select">
                    <option value="">All Roles</option>
                    <option value="Admin" <?php if($role_filter == 'Admin') echo 'selected'; ?>>Admin</option>
                    <option value="Technical Admin" <?php if($role_filter == 'Technical Admin') echo 'selected'; ?>>Technical Admin</option>
                    <option value="Technician" <?php if($role_filter == 'Technician') echo 'selected'; ?>>Technician</option>
                    <option value="Lecturer" <?php if($role_filter == 'Lecturer') echo 'selected'; ?>>Lecturer</option>
                    <option value="Staff" <?php if($role_filter == 'Staff') echo 'selected'; ?>>Staff</option>
                    <option value="Student" <?php if($role_filter == 'Student') echo 'selected'; ?>>Student</option>
                </select>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">Search</button>
                <?php if (!empty($search_query) || !empty($role_filter)): ?>
                    <a href="manage_users.php" class="btn">Reset</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <?php if ($isSuperAdmin): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                            <?php $is_protected = in_array(strtolower($user['username']), $protected_usernames); ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($user['id']); ?></td>
                                <td style="font-weight:600; color:var(--text-primary);"><?php echo htmlspecialchars($user['Fullname']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td style="color:var(--text-secondary);"><?php echo htmlspecialchars($user['Email']); ?></td>
                                <td>
                                    <?php if ($is_protected): ?>
                                        <span class="badge-protected"><i class="fa-solid fa-lock"></i> Protected</span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($user['User_Type']); ?>
                                    <?php endif; ?>
                                </td>
                                
                                <?php if ($isSuperAdmin): ?>
                                <td>
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <form method="POST" style="display:inline-flex; align-items:center;" onsubmit="return confirmRoleChange(this);">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="new_role" class="role-select-inline" <?php echo $is_protected ? 'disabled' : ''; ?>>
                                                <option value="Admin" <?php echo ($user['User_Type'] == 'Admin') ? 'selected' : ''; ?>>Admin</option>
                                                <option value="Technical Admin" <?php echo ($user['User_Type'] == 'Technical Admin') ? 'selected' : ''; ?>>Technical Admin</option>
                                                <option value="Technician" <?php echo ($user['User_Type'] == 'Technician') ? 'selected' : ''; ?>>Technician</option>
                                                <option value="Lecturer" <?php echo ($user['User_Type'] == 'Lecturer') ? 'selected' : ''; ?>>Lecturer</option>
                                                <option value="Staff" <?php echo ($user['User_Type'] == 'Staff') ? 'selected' : ''; ?>>Staff</option>
                                                <option value="Student" <?php echo ($user['User_Type'] == 'Student') ? 'selected' : ''; ?>>Student</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-update" <?php echo $is_protected ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>
                                                <i class="fa-solid fa-rotate"></i>
                                            </button>
                                        </form>

                                        <form method="POST" style="display:inline;" onsubmit="return confirmDelete(this);">
                                            <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-delete" <?php echo ($is_protected || $user['id'] == $_SESSION['id']) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <?php endif; ?> </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="<?php echo $isSuperAdmin ? '6' : '5'; ?>" style="text-align:center; padding:32px; color:var(--text-secondary);">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="page-info">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> users</div>
                <div class="page-nav">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo getQueryLink($page - 1); ?>" class="page-link">Previous</a>
                    <?php endif; ?>
                    
                    <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                        <a href="<?php echo getQueryLink($i); ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo getQueryLink($page + 1); ?>" class="page-link">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<div id="createUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create New User</h3>
            <button class="btn-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="new_fullname" class="form-control" required placeholder="e.g. Ahmad Ali">
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="new_email" class="form-control" required placeholder="e.g. ahmad@utm.my">
                <div style="font-size:11px; color:var(--text-secondary); margin-top:4px;">
                    <i class="fa-solid fa-circle-info"></i> An activation link will be sent to this email.
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <?php if ($isTechAdmin): ?>
                    <input type="text" class="form-control" value="Technician" readonly style="background:#f3f4f6; color:#64748b;">
                    <input type="hidden" name="new_role" value="Technician">
                <?php else: ?>
                    <select name="new_role" class="form-control">
                        <option value="Admin">Admin</option>
                        <option value="Technical Admin">Technical Admin</option>
                        <option value="Technician">Technician</option>
                        <option value="Lecturer">Lecturer</option>
                        <option value="Staff">Staff</option>
                        <option value="Student">Student</option>
                    </select>
                <?php endif; ?>
            </div>
            <div style="margin-top:24px; text-align:right; border-top:1px solid var(--border); padding-top:16px;">
                <button type="button" onclick="closeModal()" class="btn">Cancel</button>
                <button type="submit" name="create_user" class="btn btn-primary" style="margin-left:8px;">Create & Send Email</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() { document.getElementById('createUserModal').classList.add('show'); }
function closeModal() { document.getElementById('createUserModal').classList.remove('show'); }

window.onclick = function(event) {
    var modal = document.getElementById('createUserModal');
    if (event.target == modal) closeModal();
}

window.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flashMsg');
    if (flash) {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.6s ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 700);
        }, 3500);
    }
});

function confirmRoleChange(form) {
    const select = form.querySelector('select[name="new_role"]');
    if (!select) return true;
    return confirm('Change user role to "' + select.value + '"?');
}

function confirmDelete(form) {
    return confirm('WARNING: Are you sure you want to delete this user?');
}
</script>
</body>
</html>