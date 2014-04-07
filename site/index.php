<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!isset($_GET["page"])) {
    require_once("private/controller/login.php");
    exit;
}
$page = htmlspecialchars($_GET["page"]);

//This needs to be wrapped around session Ids and logins
if ($page == "home") {
    require_once("private/controller/home.php");
} else {
    require_once("private/controller/login.php");
}
