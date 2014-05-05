<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

if ($page == "serverop"){

	require_once("private/controller/serverop.php");

}else{

	//This needs to be wrapped around session Ids and logins

	    require_once("private/controller/homework.php");

}
?>
