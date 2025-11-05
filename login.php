<?php
require_once 'db_connect.php';

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

                    if($User_Type == 'Admin'){
                        header("location: index-admin.php");
                    } else{
                        header("location: timetable.html");
                    if (isset($_POST['remember'])) {
                        $token = bin2hex(random_bytes(16));
                        $token_hash = hash('sha256', $token);
                        $expiry = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30); // 30 days

                        $update_sql = "UPDATE users SET remember_token = ?, remember_token_expiry = ? WHERE id = ?";
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("ssi", $token_hash, $expiry, $id);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }

                        setcookie('remember_me', $token, time() + 60 * 60 * 24 * 30, '/');
                    }

                    if($User_Type == 'admin'){
                        header("location: index-admin.php");
                    } else{
                        header("location: index.php");
                    }
                    exit;
                } 
                else{
                    $login_err = "Invalid username or password.";
                    echo "<script>alert('Invalid password'); window.location.href='loginterface.html';</script>";
                    } 
            }
            else{
                    $login_err = "Invalid username or password.";
                    echo "<script>alert('User not found'); window.location.href='loginterface.html';</script>";
                }
        }
        $stmt->close();
    }
}
$conn->close();
?>
