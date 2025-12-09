<?php
session_start();
require_once 'db_connect.php';

// 1. SECURITY CHECK: Only allow 'Admin' to access this page
if (!isset($_SESSION['loggedin']) || 
    !isset($_SESSION['User_Type']) || 
    strcasecmp($_SESSION['User_Type'], 'Admin') !== 0) {
    header("location: loginterface.html");
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin';

// --- SUPER ADMIN CONFIGURATION ---
$protected_usernames = ['superadmin', 'admin'];
// ---------------------------------

// EXTRA CHECK: Allow only superadmin to access this page
$allowed_superAdmins = ['superadmin']; // usernames allowed

if (!in_array(strtolower($_SESSION['username']), $allowed_superAdmins)) {
    // if not superadmin → block access
    header("Location: index-admin.php"); 
    exit;
}


$message = "";
$error = "";

// 2. HANDLE FORM SUBMISSIONS (POST Request)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF protection would be recommended here (left out for brevity)

    // --- A. HANDLE UPDATE ROLE ---
    if (isset($_POST['user_id']) && isset($_POST['new_role'])) {
        $userIdToUpdate = intval($_POST['user_id']);
        $newRole = $_POST['new_role'];

        // Fetch username for protection check
        $target_username = "";
        $check_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        if ($check_stmt) {
            $check_stmt->bind_param("i", $userIdToUpdate);
            $check_stmt->execute();
            $check_stmt->bind_result($fetched_username);
            if ($check_stmt->fetch()) {
                $target_username = $fetched_username;
            }
            $check_stmt->close();
        }

        if (in_array(strtolower($target_username), $protected_usernames)) {
            $error = "Action denied: You cannot modify the Super Admin account.";
        } else {
            $allowedRoles = ['Admin', 'Lecturer', 'Staff', 'Student'];
            if (in_array($newRole, $allowedRoles)) {
                $sql = "UPDATE users SET User_Type = ? WHERE id = ?";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("si", $newRole, $userIdToUpdate);
                    if ($stmt->execute()) {
                        header("Location: manage_users.php?msg=updated");
                        exit;
                    } else {
                        $error = "Error updating role: " . $conn->error;
                    }
                    $stmt->close();
                }
            } else {
                $error = "Invalid role selected.";
            }
        }
    }

    // --- B. HANDLE DELETE USER ---
    if (isset($_POST['delete_user_id'])) {
        $userIdToDelete = intval($_POST['delete_user_id']);

        $target_username = "";
        $check_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        if ($check_stmt) {
            $check_stmt->bind_param("i", $userIdToDelete);
            $check_stmt->execute();
            $check_stmt->bind_result($fetched_username);
            if ($check_stmt->fetch()) {
                $target_username = $fetched_username;
            }
            $check_stmt->close();
        }

        if (in_array(strtolower($target_username), $protected_usernames)) {
            $error = "Action denied: You cannot delete a Protected Admin account.";
        } elseif ($userIdToDelete == $_SESSION['id']) {
            $error = "Action denied: You cannot delete your own account while logged in.";
        } else {
            try {
                $del_sql = "DELETE FROM users WHERE id = ?";
                if ($del_stmt = $conn->prepare($del_sql)) {
                    $del_stmt->bind_param("i", $userIdToDelete);
                    if ($del_stmt->execute()) {
                        header("Location: manage_users.php?msg=deleted");
                        exit;
                    } else {
                        $error = "Error deleting user. (SQL Error)";
                    }
                    $del_stmt->close();
                }
            } catch (mysqli_sql_exception $e) {
                $error = "Cannot delete user. They might have existing reservations or logs linked to them.";
            }
        }
    }
}

// Check for success messages from the redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'updated') $message = "User role successfully updated.";
    if ($_GET['msg'] == 'deleted') $message = "User successfully deleted.";
}

// 3. SEARCH AND FILTER LOGIC
$search_query = "";
$role_filter = "";

$placeholders = implode(',', array_fill(0, count($protected_usernames), '?'));
$where_clauses = ["username NOT IN ($placeholders)"];
$params = $protected_usernames; 
$types = str_repeat('s', count($protected_usernames));

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
    $where_clauses[] = "(username LIKE ? OR Fullname LIKE ? OR Email LIKE ?)";
    $like_term = "%" . $search_query . "%";
    $params[] = $like_term;
    $params[] = $like_term;
    $params[] = $like_term;
    $types .= "sss";
}

if (isset($_GET['role_filter']) && !empty($_GET['role_filter'])) {
    $role_filter = $_GET['role_filter'];
    $where_clauses[] = "User_Type = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$sql = "SELECT id, username, Fullname, User_Type, Email FROM users WHERE " . implode(" AND ", $where_clauses) . " ORDER BY User_Type ASC, username ASC";

$users = [];
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    $stmt->close();
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Users — Admin</title>
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
    --nav-height: 80px;
  }

  *{box-sizing:border-box}
  body{
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    min-height: 100vh;
    padding: 0;
    margin: 0;
  }

  /* Fixed top nav */
  .nav-bar {
    background: white;
    padding: 16px 24px;
    box-shadow: var(--shadow-md);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    height: 80px;
  }
  .nav-logo { height:50px; width:auto; display:block; }

  /* layout: container centered and below navbar */
  .layout {
    width: 100%;
    max-width: 2000px;
    padding: 24px;
    gap: 24px;
    margin: 100px auto 0;
    display: flex;
    align-items: flex-start;
  }

  /* Sidebar */
  .sidebar {
    width: 260px;
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: var(--shadow-sm);
    z-index: 100;
    flex-shrink: 0;
    position: sticky;
    top: 100px;
  }
  
  .sidebar-title {
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gray-600);
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--gray-200);
  }
  
  .sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
  }
  
  .sidebar-menu li {
    margin-bottom: 8px;
  }
  
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
  .main {
    flex:1;
    min-width:0;
  }

  /* Header Card */
  .header-card {
    background: white;
    border-radius: 12px;
    padding: 24px 32px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-md);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .header-title {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  
  .header-title h1 {
    font-size: 24px;
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
  }
  
  .header-badge {
    background: var(--primary-light);
    color: var(--primary);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .card {
    background:#fff;
    border-radius:12px;
    padding:24px;
    box-shadow: var(--shadow-sm);
  }

  /* Alerts */
  .alert {
    padding: 14px 18px;
    margin-bottom: 20px;
    border-radius: 8px;
    font-weight: 500;
  }

  .alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border-left: 4px solid var(--success);
  }

  .alert-error {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border-left: 4px solid var(--danger);
  }

  /* Controls */
  .top-controls { 
    display:flex; 
    gap:12px; 
    align-items:center; 
    justify-content:space-between; 
    flex-wrap:wrap; 
    margin-bottom:20px; 
    padding-bottom: 16px;
    border-bottom: 2px solid var(--gray-200);
  }
  
  .left-controls { display:flex; gap:12px; align-items:center; flex-wrap: wrap; }
  
  .search-input { 
    padding:10px 14px; 
    border-radius:8px; 
    border:2px solid var(--gray-300); 
    font-weight:500; 
    background:#fff;
    min-width: 250px;
  }
  
  .role-select { 
    padding:10px 12px; 
    border-radius:8px; 
    border:2px solid var(--gray-300); 
    font-weight:600; 
    background:#fff; 
  }
  
  .btn { 
    padding:10px 14px; 
    border-radius:8px; 
    border:0; 
    cursor:pointer; 
    font-weight:700;
    text-decoration: none;
    display: inline-block;
  }
  
  .btn.primary { 
    background:linear-gradient(135deg,var(--primary),var(--primary-dark)); 
    color:#fff; 
  }
  
  .btn.outline { 
    background:#fff; 
    border:2px solid var(--gray-300); 
    color:var(--gray-700); 
  }

  .btn.danger {
    background: linear-gradient(135deg, var(--danger) 0%, #b91c1c 100%);
    color: white;
  }

  /* Table */
  .table-wrap {
    overflow: auto;
    border-radius:10px;
    border:1px solid var(--gray-200);
    margin-top:8px;
    position: relative;
    -webkit-overflow-scrolling: touch;
  }

  table.grid {
    width:100%;
    border-collapse:collapse;
    min-width:980px;
    background:#fff;
  }

  table.grid th,
  table.grid td {
    padding:12px 10px;
    border-bottom:1px solid var(--gray-100);
    text-align:left;
    vertical-align:middle;
    background: #fff;
  }

  table.grid thead th {
    background: linear-gradient(180deg, var(--gray-100) 0%, var(--gray-50) 100%);
    font-weight:700;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:0.5px;
    position: sticky;
    top: 0;
    z-index: 140;
  }

  table.grid th:first-child,
  table.grid td:first-child {
    position: sticky;
    left: 0;
    z-index: 150;
    background: #fff;
    box-shadow: 2px 0 6px rgba(2,6,23,0.05);
  }

  table.grid thead th:first-child {
    z-index: 160;
  }

  table.grid tbody tr:hover {
    background: var(--gray-50);
  }

  /* Badges */
  .badge-protected {
    display:inline-block; 
    padding:6px 10px; 
    border-radius:8px; 
    font-weight:700; 
    font-size:13px;
    background:linear-gradient(135deg,#fef3c7,#fde68a); 
    color:#92400e;
  }

  /* Form elements in table */
  .role-select-inline {
    padding: 6px 10px;
    border-radius: 6px;
    border: 2px solid var(--gray-300);
    font-size: 13px;
    font-weight: 500;
  }

  .role-select-inline:disabled {
    background: var(--gray-100);
    opacity: 0.6;
    cursor: not-allowed;
  }

  /* Action buttons */
  .actions { 
    display: flex;
    gap: 8px;
    align-items: center;
  }
  
  .actions button { 
    padding:8px 12px; 
    border-radius:8px; 
    border:0; 
    cursor:pointer; 
    font-weight:700; 
  }
  
  .actions .btn-update { 
    background:linear-gradient(135deg,#10b981,#059669); 
    color:#fff; 
  }
  
  .actions .btn-delete { 
    background:linear-gradient(135deg,#ef4444,#dc2626); 
    color:#fff; 
  }

  .actions button:disabled {
    background: var(--gray-300);
    cursor: not-allowed;
    opacity: 0.6;
  }

  .footer-note {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
    font-size: 13px;
    color: var(--gray-600);
    font-style: italic;
  }

  /* Responsive */
  @media (max-width:1200px) {
    .sidebar { display:none; }
    .layout { margin-left:18px; margin-right:18px; display:block; }
  }
  
  @media (max-width:720px) {
    table.grid { min-width:860px; }
    .left-controls { flex-direction: column; width: 100%; }
    .search-input { width: 100%; }
  }
</style>
</head>
<body>

<nav class="nav-bar">
  <img class="nav-logo" src="img/utmlogo.png" alt="UTM Logo">
</nav>

<div class="layout">

  <aside class="sidebar">
    <div class="sidebar-title">Main Menu</div>
    <ul class="sidebar-menu">
      <li><a href="index-admin.php">Dashboard</a></li>
      <li><a href="reservation_request.php">Reservation Request</a></li>
      <li><a href="admin_timetable.php">Regular Timetable</a></li>
      <li><a href="admin_recurring.php">Recurring Templates</a></li>
      <li><a href="manage_users.php" class="active">Manage Users</a></li>
      <li><a href="admin_logbook.php">Logbook</a></li>
      <li><a href="generate_reports.php">Generate Reports</a></li>
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

  <div class="main">
    <div class="header-card">
      <div class="header-title">
        <h1>User Management</h1>
        <span class="header-badge">Admin</span>
      </div>
    </div>

    <div class="card">
      <?php if ($message): ?>
        <div class="alert alert-success" id="flashMsg"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>
      
      <?php if ($error): ?>
        <div class="alert alert-error" id="flashMsg"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="GET" class="top-controls">
        <div class="left-controls">
          <input type="text" name="search" class="search-input" placeholder="Search by name, username, or email..." value="<?php echo htmlspecialchars($search_query); ?>">
          <select name="role_filter" class="role-select">
            <option value="">All Roles</option>
            <option value="Admin" <?php if($role_filter == 'Admin') echo 'selected'; ?>>Admin</option>
            <option value="Lecturer" <?php if($role_filter == 'Lecturer') echo 'selected'; ?>>Lecturer</option>
            <option value="Staff" <?php if($role_filter == 'Staff') echo 'selected'; ?>>Staff</option>
            <option value="Student" <?php if($role_filter == 'Student') echo 'selected'; ?>>Student</option>
          </select>
          <button type="submit" class="btn primary">Search</button>
          <?php if (!empty($search_query) || !empty($role_filter)): ?>
            <a href="manage_users.php" class="btn outline">Reset</a>
          <?php endif; ?>
        </div>
      </form>

      <div class="table-wrap">
        <table class="grid">
          <thead>
            <tr>
              <th>ID</th>
              <th>Full Name</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($users) > 0): ?>
              <?php foreach ($users as $user): ?>
              <?php $is_protected = in_array(strtolower($user['username']), $protected_usernames); ?>
              <tr>
                <td><?php echo htmlspecialchars($user['id']); ?></td>
                <td><?php echo htmlspecialchars($user['Fullname']); ?></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['Email']); ?></td>
                <td>
                  <?php if ($is_protected): ?>
                    <span class="badge-protected">Protected</span>
                  <?php else: ?>
                    <?php echo htmlspecialchars($user['User_Type']); ?>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="actions">
                    <form method="POST" style="display:inline-flex;gap:8px;margin:0;" onsubmit="return confirmRoleChange(this);">
                      <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                      <select name="new_role" class="role-select-inline" <?php echo $is_protected ? 'disabled' : ''; ?>>
                        <option value="Admin" <?php echo (strcasecmp($user['User_Type'], 'Admin') == 0) ? 'selected' : ''; ?>>Admin</option>
                        <option value="Lecturer" <?php echo (strcasecmp($user['User_Type'], 'Lecturer') == 0) ? 'selected' : ''; ?>>Lecturer</option>
                        <option value="Staff" <?php echo (strcasecmp($user['User_Type'], 'Staff') == 0) ? 'selected' : ''; ?>>Staff</option>
                        <option value="Student" <?php echo (strcasecmp($user['User_Type'], 'Student') == 0) ? 'selected' : ''; ?>>Student</option>
                      </select>
                      <button type="submit" class="btn-update" <?php echo $is_protected ? 'disabled' : ''; ?>>Update</button>
                    </form>

                    <form method="POST" style="display:inline;margin:0;" onsubmit="return confirmDelete(this);">
                      <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                      <button type="submit" class="btn-delete" <?php echo ($is_protected || $user['id'] == $_SESSION['id']) ? 'disabled' : ''; ?>>Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--gray-600)">No users found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="footer-note">* Protected accounts cannot be modified or deleted from this interface.</div>
    </div>
  </div>
</div>

<script>
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
  const newRole = select.value;
  if (!confirm('Change role to "' + newRole + '"?')) return false;
  return true;
}

function confirmDelete(form) {
  if (!confirm('WARNING: Are you sure you want to delete this user? This action cannot be undone.')) return false;
  return true;
}
</script>

</body>
</html>