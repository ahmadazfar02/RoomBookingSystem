<?php
// technician_task.php - Secure Version (Fixed Email Lookup)
require_once __DIR__ . '/../includes/db_connect.php';

$token = $_GET['token'] ?? '';
$msg = '';
$msgType = ''; // success, danger, warning
$task = null;

if ($token) {
    // 1. Fetch the Task Details using the Token
    $stmt = $conn->prepare("
        SELECT id, session_id, purpose, description, slot_date, time_start, 
               tech_status, room_id, user_id, linked_problem_id, technician
        FROM bookings 
        WHERE tech_token = ? 
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($task = $res->fetch_assoc()) {
        
        // --- HANDLE FORM SUBMISSION ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            $input_email = trim($_POST['verify_email'] ?? '');
            $reason = trim($_POST['reason'] ?? '');

            // --- A. SECURITY CHECK (FIXED) ---
            // We need to find the REAL email associated with the assigned technician's name
            
            $assigned_name = $task['technician']; // e.g. "Ali Ahmad"
            $valid_email = '';

            // Query the Users table to find the email for this name
            // We check Fullname, Username, or Email columns just to be safe
            $u_stmt = $conn->prepare("SELECT Email FROM users WHERE Fullname = ? OR username = ? OR Email = ? LIMIT 1");
            $u_stmt->bind_param("sss", $assigned_name, $assigned_name, $assigned_name);
            $u_stmt->execute();
            $u_res = $u_stmt->get_result();

            if ($u_row = $u_res->fetch_assoc()) {
                // Found the user! Get their registered email.
                $valid_email = $u_row['Email'];
            } else {
                // If not found in users table, assume the booking stored the email directly
                $valid_email = $assigned_name;
            }

            // Compare Input vs Real Email (Case Insensitive)
            if (strcasecmp($input_email, $valid_email) !== 0) {
                $msg = "Security Check Failed: The email entered ($input_email) does not match the assigned technician ($assigned_name).";
                $msgType = "danger";
            } 
            else {
                // --- B. PROCESS ACTIONS (Identity Verified) ---
                $session_id = $task['session_id'];
                $pid = intval($task['linked_problem_id']);

                // ACTION 1: MARK AS COMPLETED
                if ($action === 'complete') {
                    // Update Booking Status
                    $sql = "UPDATE bookings SET tech_status = 'Work Done', tech_completed_at = NOW(), updated_at = NOW() ";
                    if (!empty($session_id)) {
                        $sql .= "WHERE session_id = ?";
                        $upd = $conn->prepare($sql);
                        $upd->bind_param("s", $session_id);
                    } else {
                        $sql .= "WHERE id = ?";
                        $upd = $conn->prepare($sql);
                        $upd->bind_param("i", $task['id']);
                    }
                    $upd->execute();

                    // Notify Admin (Green Badge)
                    if ($pid > 0) {
                        $conn->query("UPDATE room_problems SET admin_notice = 1, notice_msg = 'Technician completed task' WHERE id = $pid");
                    }

                    $msg = "Success! Task marked as completed.";
                    $msgType = "success";
                    $task['tech_status'] = 'Work Done'; // Update UI
                } 
                
                // ACTION 2: REPORT ISSUE
                elseif ($action === 'issue') {
                    if (empty($reason)) {
                        $msg = "Please provide a reason why the task cannot be completed.";
                        $msgType = "warning";
                    } else {
                        // 1. Append reason to existing description (Preserves History)
                        $timestamp = date('d M Y, H:i');
                        $new_desc = $task['description'] . "\n\n[Issue Reported $timestamp]: " . $reason;
                        
                        // Update Description
                        $sql = "UPDATE bookings SET description = ?, updated_at = NOW() ";
                        if (!empty($session_id)) {
                            $sql .= "WHERE session_id = ?";
                            $upd = $conn->prepare($sql);
                            $upd->bind_param("ss", $new_desc, $session_id);
                        } else {
                            $sql .= "WHERE id = ?";
                            $upd = $conn->prepare($sql);
                            $upd->bind_param("si", $new_desc, $task['id']);
                        }
                        $upd->execute();

                        // 2. Notify Admin (Orange Badge)
                        if ($pid > 0) {
                            $safe_reason = $conn->real_escape_string(substr($reason, 0, 80)); // Short preview
                            $conn->query("UPDATE room_problems SET admin_notice = 1, notice_msg = 'Issue: $safe_reason' WHERE id = $pid");
                        }

                        $msg = "Issue reported. The Admin has been notified.";
                        $msgType = "warning";
                        $task['description'] = $new_desc; // Show updated desc in UI
                    }
                }
            }
        }
    } else {
        $msg = "Invalid or expired task link.";
        $msgType = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Task Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #800000; --bg: #f3f4f6; }
        body { background: var(--bg); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; }
        .card { background: white; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); width: 100%; max-width: 480px; overflow: hidden; }
        .card-header { background: var(--primary); color: white; padding: 20px; text-align: center; }
        .card-header h2 { margin: 0; font-size: 18px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .card-body { padding: 24px; }
        
        .info-group { margin-bottom: 20px; }
        .info-label { font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; display: block; }
        .info-value { font-size: 15px; font-weight: 600; color: #1f2937; }
        .info-desc { background: #f9fafb; padding: 12px; border-radius: 8px; border: 1px solid #e5e7eb; font-size: 14px; color: #4b5563; line-height: 1.5; white-space: pre-wrap; }

        .form-control { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box; margin-bottom: 16px; transition: 0.2s; }
        .form-control:focus { border-color: var(--primary); outline: none; }
        
        .btn { width: 100%; padding: 12px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-success { background: #059669; color: white; }
        .btn-success:hover { background: #047857; }
        .btn-warning { background: #d97706; color: white; margin-top: 12px; }
        .btn-warning:hover { background: #b45309; }
        .btn-cancel { background: transparent; color: #6b7280; margin-top: 8px; font-weight: normal; }
        .btn-cancel:hover { color: #1f2937; text-decoration: underline; }
        
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .alert-warning { background: #fef3c7; color: #92400e; }

        .hidden { display: none; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-screwdriver-wrench"></i> Task Update</h2>
        </div>
        <div class="card-body">
            
            <?php if ($msg): ?>
                <div class="alert alert-<?php echo $msgType; ?>">
                    <?php if($msgType=='success') echo '<i class="fa-solid fa-check-circle"></i> '; ?>
                    <?php if($msgType=='danger') echo '<i class="fa-solid fa-triangle-exclamation"></i> '; ?>
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <?php if ($task): ?>
                <div class="info-group">
                    <label class="info-label">Assigned Technician</label>
                    <div class="info-value"><?php echo htmlspecialchars($task['technician']); ?></div>
                </div>

                <div class="info-group">
                    <label class="info-label">Task Title</label>
                    <div class="info-value"><?php echo htmlspecialchars($task['purpose']); ?></div>
                </div>

                <div class="info-group">
                    <div class="info-value">
                        <i class="fa-regular fa-calendar"></i> <?php echo date('d M Y', strtotime($task['slot_date'])); ?> &nbsp;|&nbsp; 
                        <i class="fa-regular fa-clock"></i> <?php echo substr($task['time_start'], 0, 5); ?>
                    </div>
                </div>

                <div class="info-group">
                    <label class="info-label">Description / History</label>
                    <div class="info-desc"><?php echo $task['description'] ? htmlspecialchars($task['description']) : 'No description provided.'; ?></div>
                </div>

                <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 20px 0;">

                <?php if ($task['tech_status'] !== 'Work Done' && $task['tech_status'] !== 'Verified'): ?>
                    
                    <form method="POST" id="taskForm">
                        <h4 style="margin: 0 0 12px 0; font-size: 14px; color: #374151;">Step 1: Confirm Identity</h4>
                        
                        <input type="email" name="verify_email" class="form-control" placeholder="Enter your email to confirm..." required>

                        <div id="mainButtons">
                            <h4 style="margin: 0 0 12px 0; font-size: 14px; color: #374151;">Step 2: Update Status</h4>
                            
                            <button type="submit" name="action" value="complete" class="btn btn-success">
                                <i class="fa-solid fa-check"></i> I Have Finished This Job
                            </button>
                            
                            <button type="button" onclick="showIssueField()" class="btn btn-warning">
                                <i class="fa-solid fa-triangle-exclamation"></i> Unable to Complete
                            </button>
                        </div>

                        <div id="issueField" class="hidden fade-in" style="margin-top: 16px;">
                            <label class="info-label" style="color:#d97706;">Why can't you finish?</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="e.g. Spare parts missing, room is occupied..."></textarea>
                            
                            <button type="submit" name="action" value="issue" class="btn btn-warning">
                                Submit Report
                            </button>
                            <button type="button" onclick="hideIssueField()" class="btn btn-cancel">
                                Cancel
                            </button>
                        </div>
                    </form>

                <?php else: ?>
                    <div style="text-align:center; padding: 20px; color: #059669;">
                        <i class="fa-solid fa-lock" style="font-size: 32px; margin-bottom: 10px;"></i>
                        <p style="font-weight:600; margin:0;">Task Closed</p>
                        <p style="font-size:13px; opacity:0.8;">Thank you for your work.</p>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <script>
        function showIssueField() {
            document.getElementById('mainButtons').classList.add('hidden');
            document.getElementById('issueField').classList.remove('hidden');
            document.querySelector('textarea[name="reason"]').focus();
        }
        function hideIssueField() {
            document.getElementById('issueField').classList.add('hidden');
            document.getElementById('mainButtons').classList.remove('hidden');
        }
    </script>
</body>
</html>