<?php
require 'db_connect.php';

$username = 'superadmin';
$email = 'superadmin@utm.my';
$password = 'Admin123*';
$hashed_password = password_hash($password, PASSWORD_BCRYPT);
$User_Type = 'Admin';


$sql = "INSERT INTO users (username, email, password_hash, User_Type) VALUES ('$username', '$email', '$hashed_password', '$User_Type')";

if ($conn->query($sql) === TRUE) {
    echo "Admin user created successfully!";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
