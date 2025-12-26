<?php
// index.php
// This is the entry point of your website.

// 1. Start Session to check if user is already logged in
session_start();


// 3. If NOT logged in, redirect to Login Page
header("Location: loginterface.html");
exit;
?>