<?php
require_once 'dbconfig.php';

if ($_SERVER["REQUEST_METHOD"] == "POST"){
    $email = $_POST["email"];
    $password = $_POST["password"];

    $sql = "SELECT id, email, password_hash, role FROM users WHERE email = ?";

    if ($stmt = $conn->prepare($sql)){
        $stmt->bind_param("s", $email);

        if($stmt->execute()){
            $stmt->store_result();
            if($stmt->num_rows == 1){
                $stmt->bind_result($id, $email, $hashed_password, $role);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)){
                    session_start();
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $id;
                    $_SESSION["email"] = $email;
                    $_SESSION["role"] = $role;

                    if($role == 'admin'){
                        header("location: index-admin.html");
                    } else{
                        header("location: index.html");
                    }
                    exit;
                } 
                else{
                    $login_err = "Invalid username or password.";
                    } 
            }
            else{
                    $login_err = "Invalid username or password.";
                }
        }
        $stmt->close();
    }
}
$conn->close();
?>