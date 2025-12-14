<?php
require_once __DIR__ . '/../includes/db_connect.php';

// Check for remember me cookie
if (isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $token_hash = hash('sha256', $token);

    $sql = "SELECT id, username, Fullname, User_Type FROM users WHERE remember_token = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $username, $fullname, $User_Type);
            $stmt->fetch();

            session_start();
            $_SESSION["loggedin"] = true;
            $_SESSION["id"] = $id;
            $_SESSION["username"] = $username;
            $_SESSION["Fullname"] = $fullname;
            $_SESSION["User_Type"] = $User_Type;
            

            if (strcasecmp(trim($User_Type), 'Admin') == 0) {
                header("location: ../admin/index-admin.php");
            } else {
                header("location: ../timetable.html");
            }
            exit;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST"){
    $email = $_POST["username"];
    $password = $_POST["password"];

    $sql = "SELECT id, username, Fullname, password_hash, User_Type FROM users WHERE username = ?";

    if ($stmt = $conn->prepare($sql)){
        $stmt->bind_param("s", $email);

        if($stmt->execute()){
            $stmt->store_result();
            if($stmt->num_rows == 1){
                $stmt->bind_result($id, $username, $fullname, $hashed_password, $User_Type);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)){
                    session_start();
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $id;
                    $_SESSION["username"] = $username;
                    $_SESSION["Fullname"] = $fullname;
                    $_SESSION["User_Type"] = $User_Type;

                    if (isset($_POST['remember'])) {
                        $token = bin2hex(random_bytes(16));
                        $token_hash = hash('sha256', $token);

                        $update_sql = "UPDATE users SET remember_token = ? WHERE id = ?";
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("si", $token_hash, $id);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }

                        setcookie('remember_me', $token, time() + 60 * 60 * 24 * 30, '/'); // 30-day cookie
                    }

                    if(strcasecmp(trim($User_Type), 'Admin') == 0){
                        header("location: ../admin/index-admin.php");
                    } else{
                        header("location: ../timetable.html");
                    }
                    exit;
                } 
                else{
                    $login_err = "Invalid username or password.";
                    echo "<script>alert('Invalid password'); window.location.href='../loginterface.html';</script>";
                    } 
            }
            else{
                    $login_err = "Invalid username or password.";
                    echo "<script>alert('User not found'); window.location.href='../loginterface.html';</script>";
                }
        }
        $stmt->close();
    }
}
$conn->close();
?>
