<?php
// technician_task.php
require_once __DIR__ . '/../includes/db_connect.php';

$token = $_GET['token'] ?? '';
$msg = '';
$success = false;
$task = null;

if ($token) {
    // 1. Fetch the session_id along with the booking details
    $stmt = $conn->prepare("
        SELECT id, session_id, purpose, description, slot_date, time_start, tech_status, room_id, user_id, linked_problem_id
        FROM bookings 
        WHERE tech_token = ? 
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($task = $res->fetch_assoc()) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            // 2. UPDATE ALL SLOTS WITH THE SAME SESSION ID
            // This is the key fix: It updates the whole group at once.
            $session_id = $task['session_id'];
            
            if (!empty($session_id)) {
                $upd = $conn->prepare("UPDATE bookings SET tech_status = 'Work Done', updated_at = NOW() WHERE session_id = ?");
                $upd->bind_param("s", $session_id);
                $upd->execute();
            } else {
                // Fallback for old data with no session_id
                $upd = $conn->prepare("UPDATE bookings SET tech_status = 'Work Done', updated_at = NOW() WHERE id = ?");
                $upd->bind_param("i", $task['id']);
                $upd->execute();
            }

            $success = true;
            $msg = "Success! The Admin has been notified.";
            $task['tech_status'] = 'Work Done';

            // 3. Update the Linked Problem (if exists)
            if (!empty($task['linked_problem_id'])) {
                $pid = intval($task['linked_problem_id']);
                $conn->query("UPDATE room_problems SET admin_notice = 1, notice_msg = 'Technician completed task' WHERE id = $pid");
            }
        }
    } else {
        $msg = "Invalid or expired link.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Task</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f3f4f6; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: sans-serif; }
        .card { border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 12px; }
    </style>
</head>
<body>
    <div class="container" style="max-width: 500px;">
        <div class="card p-4">
            <div class="text-center mb-4">
                <h4 class="fw-bold text-dark">Maintenance Task Update</h4>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success text-center">
                    <h4>✅ Good Job!</h4>
                    <p><?php echo $msg; ?></p>
                </div>
            <?php elseif ($msg && !$task): ?>
                <div class="alert alert-danger text-center"><?php echo $msg; ?></div>
            <?php elseif ($task): ?>
                
                <?php if ($task['tech_status'] === 'Work Done' || $task['tech_status'] === 'Verified'): ?>
                    <div class="alert alert-success text-center">
                        This task is already marked as completed.
                    </div>
                <?php else: ?>
                    <div class="mb-4">
                        <label class="text-uppercase text-muted fw-bold" style="font-size: 12px;">Task</label>
                        <div class="fs-5 fw-bold text-primary mb-2"><?php echo htmlspecialchars($task['purpose']); ?></div>
                        
                        <?php if($task['description']): ?>
                        <div class="p-3 bg-light rounded text-secondary small mb-3">
                            <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between text-muted small">
                            <span>📅 <?php echo $task['slot_date']; ?></span>
                            <span>⏰ <?php echo substr($task['time_start'],0,5); ?></span>
                        </div>
                    </div>

                    <form method="POST">
                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
                            ✓ I Have Finished This Job
                        </button>
                    </form>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</body>
</html>