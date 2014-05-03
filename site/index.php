<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!isset($_GET["page"])) {
    require_once("private/controller/login.php");
    exit;
}
$page = htmlspecialchars($_GET["page"]);

// Temporary page for testing server operations

if ($page == "serverop"){

	require_once("private/controller/serverop.php");

}else{

	//This needs to be wrapped around session Ids and logins

	if ($page == "home" || $page == "announcements" || $page == "grades" || $page == "homework") {
	    require_once("private/controller/main_container.php");
	} else {
	    require_once("private/controller/login.php");
	}

}
?>
