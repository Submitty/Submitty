<?php umask (0027);
/*The user's umask is ignored for the user running php, so we need
to set it from inside of php to make sure the group read & execute
permissions aren't lost for newly created files & directories.*/


// ============================================
// GET THE USERNAME OF THE AUTHENTICATED USER
// ============================================


if (isset($_SERVER['PHP_AUTH_USER'])) {
    $user = $_SERVER['PHP_AUTH_USER'];
} else if (isset($_SERVER['REMOTE_USER'])) {
    $user = $_SERVER['PHP_AUTH_USER'];
} else {
    // if not already authenticated do it
    //
    if (!isset($_SERVER['PHP_AUTH_USER'])) {header('WWW-Authenticate: Basic realm=HWServer'); header('HTTP/1.0 401 Unauthorized'); exit;} else { $user = $_SERVER['PHP_AUTH_USER'];}
    //
}

//Remove error reporting and ini set for production code
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION["id"] = $user;

if (isset($_GET["page"])) {
    $page = htmlspecialchars($_GET["page"]);
} else {
    $page = "homework";
}

//This needs to be wrapped around session Ids and logins
if ($page == "upload") {
    require_once("controller/upload.php");
} else if ($page == "update") {
    require_once("controller/update.php");
} else if ($page == "checkrefresh") {
    require_once("controller/check_refresh.php");
} else if ($page == "viewfile") {
    require_once("controller/viewfile.php");
} else {
    require_once("controller/homework.php");
}
?>
