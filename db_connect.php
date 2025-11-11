<?php
$servername = "localhost";
$username = "root"; 
$password = "";     
$dbname = "room_reservation_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}



if (empty($_SESSION["loggedin"]) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $token_hash = hash('sha256', $token);

    $sql = "SELECT id, username, Fullname, User_Type FROM users WHERE remember_token = ? AND remember_token_expiry > NOW()";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $username, $fullname, $User_Type);
            $stmt->fetch();

            $_SESSION["loggedin"] = true;
            $_SESSION["id"] = $id;
            $_SESSION["username"] = $username;
            $_SESSION["Fullname"] = $fullname;
            $_SESSION["User_Type"] = $User_Type;
        }
        $stmt->close();
    }
}
?>
