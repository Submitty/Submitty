<?php 

//Remove error reporting and ini set for production code
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION["id"] = "sengs";//TEMPORARY

if (!isset($_SESSION["id"])) {
    require_once("../private/controller/homework.php");//Should direct to login instead
    exit();
}

/*if (!isset($_GET["page"])) {
    require_once("private/controller/login.php");
    exit;
}*/
if (isset($_GET["page"])) {
    $page = htmlspecialchars($_GET["page"]);
} else {
    $page = "homework";
}
// Temporary page for testing server operations

if ($page == "serverop") {

	require_once("../private/controller/serverop.php");

} else {

	//This needs to be wrapped around session Ids and logins
    if ($page == "upload") {
        require_once("../private/controller/upload.php");
    } else if ($page == "update") {
        require_once("../private/controller/update.php");
    } else {
	    require_once("../private/controller/homework.php");
    }
}
?>
