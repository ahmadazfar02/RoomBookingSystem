<?php
session_start();
$_SESSION = array();
session_destroy();
header("location: loginterface.html");
exit;
?>