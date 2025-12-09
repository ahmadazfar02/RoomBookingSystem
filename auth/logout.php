<?php
session_start();
$_SESSION = array();
session_destroy();
setcookie('remember_me', '', time() - 3600, '/'); // Expire the cookie
header("location: ../loginterface.html");
exit;
?>
