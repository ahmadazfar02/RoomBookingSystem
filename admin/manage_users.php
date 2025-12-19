<?php
/**
 * manage_users.php - COMPLETE VERSION
 * Features: 
 * 1. Search & Filter (Existing)
 * 2. Edit Role & Delete User (Existing)
 * 3. Create User & Send Activation Email (New)
 */
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php'; // <--- ADD THIS LINE (adjust path if needed)
require_once __DIR__ . '/../includes/config.php';    // <--- ENSURE CONFIG IS LOADED

// 1. SECURITY CHECK: Only allow 'Admin' to access this page
if (!isset($_SESSION['loggedin']) || 
    !isset($_SESSION['User_Type']) || 
    strcasecmp($_SESSION['User_Type'], 'Admin') !== 0) {
    header("location: ../loginterface.html");
    exit;
}

$admin_name = $_SESSION['Fullname'] ?? 'Admin';

// --- SUPER ADMIN CONFIGURATION ---
$protected_usernames = ['superadmin', 'admin'];
$allowed_superAdmins = ['superadmin']; // usernames allowed to access this page

if (!in_array(strtolower($_SESSION['username']), $allowed_superAdmins)) {
    header("Location: index-admin.php"); 
    exit;
}

$message = "";
$error = "";

// 2. HANDLE FORM SUBMISSIONS (POST Request)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- A. NEW: CREATE USER (Admin/Technician) ---
    if (isset($_POST['create_user'])) {
        $new_fullname = trim($_POST['new_fullname']);
        $new_email = trim($_POST['new_email']);
        $new_role = $_POST['new_role'];

        // 1. Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE Email = ?");
        $stmt->bind_param("s", $new_email);
        $stmt->execute();
        if ($stmt->fetch()) {
            $error = "Error: That email is already registered.";
            $stmt->close();
        } else {
            $stmt->close();
            
            // 2. Generate Username
            $username_base = explode('@', $new_email)[0];
            $new_username = $username_base;
            $counter = 1;
            while(true) {
                $chk = $conn->query("SELECT id FROM users WHERE username = '$new_username'");
                if ($chk->num_rows == 0) break;
                $new_username = $username_base . $counter++;
            }

            // 3. Generate Token using your helper function
            $token = generate_reset_token(); // From functions.php
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // 4. Temporary Password (placeholder, user will reset it anyway)
            $temp_password = bin2hex(random_bytes(8)); 
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

            // 5. Insert User
            $ins = $conn->prepare("INSERT INTO users (username, Fullname, Email, password_hash, User_Type, reset_token, reset_token_expiry) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("sssssss", $new_username, $new_fullname, $new_email, $hashed_password, $new_role, $token, $expiry);
            
            if ($ins->execute()) {
                // 6. GENERATE LINK & SEND EMAIL
                // Ensure SITE_BASE_URL is defined in config.php, otherwise build it dynamically
                $base = defined('SITE_BASE_URL') ? SITE_BASE_URL : "http://localhost/roomreserve"; 
                
                // Point to your existing reset_password.php page
                $activation_link = $base . "/../auth/reset_password.php?token=" . $token;

                // CALL THE NEW FUNCTION
                $sent = send_activation_email($new_email, $new_fullname, $new_username, $new_role, $activation_link);

                if ($sent) {
                    $message = "User created successfully! Activation email sent to $new_email.";
                } else {
                    $message = "User created, but email failed to send. Check server logs.";
                }
            } else {
                $error = "Database error: " . $conn->error;
            }
            $ins->close();
        }
    }

    // --- B. EXISTING: UPDATE ROLE ---
    if (isset($_POST['user_id']) && isset($_POST['new_role'])) {
        $userIdToUpdate = intval($_POST['user_id']);
        $newRole = $_POST['new_role'];

        // Protection check
        $check_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $userIdToUpdate);
        $check_stmt->execute();
        $check_stmt->bind_result($fetched_username);
        $check_stmt->fetch();
        $check_stmt->close();

        if (in_array(strtolower($fetched_username ?? ''), $protected_usernames)) {
            $error = "Action denied: You cannot modify the Super Admin account.";
        } else {
            // Added Technician to allowed roles
            $allowedRoles = ['Admin', 'Lecturer', 'Staff', 'Student', 'Technician'];
            if (in_array($newRole, $allowedRoles)) {
                $stmt = $conn->prepare("UPDATE users SET User_Type = ? WHERE id = ?");
                $stmt->bind_param("si", $newRole, $userIdToUpdate);
                if ($stmt->execute()) {
                    header("Location: manage_users.php?msg=updated");
                    exit;
                } else {
                    $error = "Error updating role: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error = "Invalid role selected.";
            }
        }
    }

    // --- C. EXISTING: DELETE USER ---
    if (isset($_POST['delete_user_id'])) {
        $userIdToDelete = intval($_POST['delete_user_id']);

        // Protection check
        $check_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $userIdToDelete);
        $check_stmt->execute();
        $check_stmt->bind_result($fetched_username);
        $check_stmt->fetch();
        $check_stmt->close();

        if (in_array(strtolower($fetched_username ?? ''), $protected_usernames)) {
            $error = "Action denied: You cannot delete a Protected Admin account.";
        } elseif ($userIdToDelete == $_SESSION['id']) {
            $error = "Action denied: You cannot delete your own account while logged in.";
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
                $error = "Cannot delete user. They might have existing reservations linked to them.";
            }
        }
    }
}

// Check for success messages from redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'updated') $message = "User role successfully updated.";
    if ($_GET['msg'] == 'deleted') $message = "User successfully deleted.";
}

// 3. SEARCH AND FILTER LOGIC (Existing)
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
    $params[] = $like_term; $params[] = $like_term; $params[] = $like_term;
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
    --primary: #2563eb; --primary-dark: #1d4ed8; --primary-light: #dbeafe;
    --success: #059669; --danger: #dc2626;
    --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb; --gray-300: #d1d5db;
    --gray-600: #4b5563; --gray-700: #374151; --gray-800: #1f2937;
    --shadow-sm: 0 4px 12px rgba(18, 38, 63, 0.08); --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --nav-height: 80px;
  }
  *{box-sizing:border-box}
  body{ font-family: 'Inter', system-ui, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin:0; min-height:100vh; display:flex; }
  
  /* Navbar & Layout */
  .nav-bar { background: white; padding: 16px 24px; box-shadow: var(--shadow-md); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; height: 80px; display: flex; align-items: center; }
  .nav-logo { height:50px; }
  .layout { width: 100%; max-width: 2000px; padding: 24px; gap: 24px; margin: 100px auto 0; display: flex; align-items: flex-start; }
  
  /* Sidebar */
  .sidebar { width: 260px; background: white; border-radius: 12px; padding: 20px; box-shadow: var(--shadow-sm); z-index: 100; flex-shrink: 0; position: sticky; top: 100px; }
  .sidebar-menu { list-style:none; padding:0; margin:0; }
  .sidebar-menu a { display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:8px; text-decoration:none; color:var(--gray-700); font-size:14px; font-weight:500; margin-bottom:8px; }
  .sidebar-menu a:hover { background:var(--gray-100); color:var(--primary); }
  .sidebar-menu a.active { background:var(--primary-light); color:var(--primary); font-weight:600; }
  .sidebar-profile { margin-top:20px; padding-top:20px; border-top:1px solid var(--gray-200); display:flex; gap:12px; align-items:center; }
  .profile-icon { width:36px; height:36px; background:var(--primary-light); color:var(--primary); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; }
  
  /* Main */
  .main { flex:1; min-width:0; }
  .header-card { background: white; border-radius: 12px; padding: 24px 32px; margin-bottom: 24px; box-shadow: var(--shadow-md); display: flex; justify-content: space-between; align-items: center; }
  .header-title h1 { font-size: 24px; font-weight: 700; margin: 0; color: var(--gray-800); }
  .header-badge { background: var(--primary-light); color: var(--primary); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
  .card { background:#fff; border-radius:12px; padding:24px; box-shadow: var(--shadow-sm); }

  /* Alerts */
  .alert { padding: 14px 18px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; }
  .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid var(--success); }
  .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid var(--danger); }

  /* Controls */
  .top-controls { display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap; margin-bottom:20px; padding-bottom: 16px; border-bottom: 2px solid var(--gray-200); }
  .search-input { padding:10px 14px; border-radius:8px; border:2px solid var(--gray-300); background:#fff; min-width: 250px; }
  .role-select { padding:10px 12px; border-radius:8px; border:2px solid var(--gray-300); background:#fff; }
  
  /* Buttons */
  .btn { padding:10px 14px; border-radius:8px; border:0; cursor:pointer; font-weight:700; text-decoration: none; display: inline-block; transition:0.2s; }
  .btn.primary { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; }
  .btn.primary:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(37,99,235,0.3); }
  .btn.outline { background:#fff; border:2px solid var(--gray-300); color:var(--gray-700); }
  .btn.danger { background:linear-gradient(135deg,var(--danger),#b91c1c); color:white; }

  /* Table */
  .table-wrap { overflow: auto; border-radius:10px; border:1px solid var(--gray-200); position: relative; }
  table.grid { width:100%; border-collapse:collapse; min-width:980px; background:#fff; }
  table.grid th, table.grid td { padding:12px 10px; border-bottom:1px solid var(--gray-100); text-align:left; vertical-align:middle; }
  table.grid thead th { background: var(--gray-50); font-weight:700; font-size:12px; text-transform:uppercase; position: sticky; top: 0; z-index: 10; }
  
  /* Inline Forms */
  .role-select-inline { padding: 6px 10px; border-radius: 6px; border: 2px solid var(--gray-300); font-size: 13px; font-weight: 500; }
  .actions { display: flex; gap: 8px; align-items: center; }
  .btn-update { background:#10b981; color:#fff; padding:8px 12px; border-radius:8px; border:0; cursor:pointer; font-weight:700; }
  .btn-delete { background:#ef4444; color:#fff; padding:8px 12px; border-radius:8px; border:0; cursor:pointer; font-weight:700; }
  .badge-protected { background:#fef3c7; color:#92400e; padding:6px 10px; border-radius:8px; font-weight:700; font-size:13px; }

  /* MODAL CSS */
  .modal-overlay { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5); z-index: 2000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
  .modal-overlay.active { display: flex; }
  .modal-box { background: white; width: 100%; max-width: 500px; border-radius: 16px; padding: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: slideUp 0.3s ease; }
  @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
  .form-group { margin-bottom: 16px; }
  .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: var(--gray-700); }
  .form-control { width: 100%; padding: 12px; border: 2px solid var(--gray-200); border-radius: 8px; font-size: 14px; transition: border-color 0.2s; }
  .form-control:focus { outline:none; border-color:var(--primary); }
  .modal-header { display: flex; justify-content: space-between; align-items:center; margin-bottom: 24px; }
  .modal-title { font-size: 20px; font-weight: 700; margin:0; color:var(--gray-800); }
  .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--gray-600); }

  @media (max-width:1200px) { .sidebar { display:none; } .layout { display:block; } }
</style>
</head>
<body>

<nav class="nav-bar">
  <img class="nav-logo" src="../assets/images/utmlogo.png" alt="UTM Logo">
</nav>

<div class="layout">
  <aside class="sidebar">
    <div style="font-weight:700; color:#666; margin-bottom:16px; text-transform:uppercase; font-size:12px;">Main Menu</div>
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
      <div style="font-size:13px;">
        <div style="font-weight:600;"><?php echo htmlspecialchars($admin_name); ?></div>
        <div style="font-size:11px; color:#666;">SuperAdmin</div>
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
        <div style="display:flex; gap:10px; width:100%;">
          <input type="text" name="search" class="search-input" placeholder="Search by name, username, or email..." value="<?php echo htmlspecialchars($search_query); ?>" style="flex-grow:1;">
          <select name="role_filter" class="role-select">
            <option value="">All Roles</option>
            <option value="Admin" <?php if($role_filter == 'Admin') echo 'selected'; ?>>Admin</option>
            <option value="Technician" <?php if($role_filter == 'Technician') echo 'selected'; ?>>Technician</option>
            <option value="Lecturer" <?php if($role_filter == 'Lecturer') echo 'selected'; ?>>Lecturer</option>
            <option value="Staff" <?php if($role_filter == 'Staff') echo 'selected'; ?>>Staff</option>
            <option value="Student" <?php if($role_filter == 'Student') echo 'selected'; ?>>Student</option>
          </select>
          <button type="submit" class="btn primary">Search</button>
              <?php if (!empty($search_query) || !empty($role_filter)): ?>
                <a href="manage_users.php" class="btn outline">Reset</a>
              <?php endif; ?>
            </div>
          <button type="button" onclick="openModal()" class="btn primary" style="display:flex; align-items:center; gap:8px;">
            <span>+</span> Create User
          </button>
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
                        <option value="Admin" <?php echo ($user['User_Type'] == 'Admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="Technician" <?php echo ($user['User_Type'] == 'Technician') ? 'selected' : ''; ?>>Technician</option>
                        <option value="Lecturer" <?php echo ($user['User_Type'] == 'Lecturer') ? 'selected' : ''; ?>>Lecturer</option>
                        <option value="Staff" <?php echo ($user['User_Type'] == 'Staff') ? 'selected' : ''; ?>>Staff</option>
                        <option value="Student" <?php echo ($user['User_Type'] == 'Student') ? 'selected' : ''; ?>>Student</option>
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
      <div style="margin-top:20px; color:#666; font-size:13px; font-style:italic;">* Protected accounts cannot be modified here.</div>
    </div>
  </div>
</div>

<div id="createUserModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h3 class="modal-title">Create New User</h3>
      <button onclick="closeModal()" class="close-btn">&times;</button>
    </div>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="new_fullname" class="form-control" required placeholder="e.g. Ahmad Technician">
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="new_email" class="form-control" required placeholder="e.g. tech@utm.my">
        <small style="color:#666; font-size:12px;">Activation link will be sent to this email.</small>
      </div>
      <div class="form-group">
        <label class="form-label">Role</label>
        <select name="new_role" class="form-control">
            <option value="Admin">Admin</option>
            <option value="Technician">Technician</option>
        </select>
      </div>
      <div style="margin-top:24px; text-align:right;">
        <button type="button" onclick="closeModal()" class="btn outline" style="margin-right:8px;">Cancel</button>
        <button type="submit" name="create_user" class="btn primary">Create & Send Email</button>
      </div>
    </form>
  </div>
</div>

<script>
// Modal Logic
function openModal() { document.getElementById('createUserModal').classList.add('active'); }
function closeModal() { document.getElementById('createUserModal').classList.remove('active'); }

// Click outside to close
document.getElementById('createUserModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// Existing Alerts fade out
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
  return confirm('Change role to "' + newRole + '"?');
}

function confirmDelete(form) {
  return confirm('WARNING: Are you sure you want to delete this user? This action cannot be undone.');
}
</script>

</body>
</html>