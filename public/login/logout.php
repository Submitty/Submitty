<?php
session_start();
ob_start();
session_unset();
session_destroy();
header("location: login.php");
?>
