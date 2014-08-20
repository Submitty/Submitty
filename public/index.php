<?php


// ============================================
// GET THE USERNAME OF THE AUTHENTICATED USER
// ============================================

if (isset($_SERVER['PHP_AUTH_USER'])) {
    $user = $_SERVER['PHP_AUTH_USER'];
} else if (isset($_SERVER['REMOTE_USER'])) {
    $user = $_SERVER['PHP_AUTH_USER'];
} else {
    echo 'Internal Error - Not Authenticated';
    // if not already authenticated do it
    //
    exit();//here
    //
}


//Remove error reporting and ini set for production code
error_reporting(E_ALL);
ini_set('display_errors', 1);


session_start();
$_SESSION["id"] = $user;


if (!isset($_SESSION["id"])) {
    require_once("../private/controller/homework.php");

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
    } else if ($page == "checkrefresh") {
        require_once("../private/controller/check_refresh.php");
    } else {
        require_once("../private/controller/homework.php");
    }
}
?>
