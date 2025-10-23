<?php
require 'db_connect.php';

$username = 'user';
$password = 'user1234';
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

$sql = "INSERT INTO users (username, password_hash) VALUES ('$username', '$hashed_password')";

if ($conn->query($sql) === TRUE) {
    echo "User created successfully!";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
