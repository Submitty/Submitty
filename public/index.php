<?php umask (0027);
/*The user's umask is ignored for the user running php, so we need
to set it from inside of php to make sure the group read & execute
permissions aren't lost for newly created files & directories.*/


// ============================================
// GET THE USERNAME OF THE AUTHENTICATED USER
// ============================================

/* This is the old simple HTTP basic auth which doesn't support logout*/
/*
if (isset($_SERVER['PHP_AUTH_USER'])) {
    $user = $_SERVER['PHP_AUTH_USER'];
} else if (isset($_SERVER['REMOTE_USER'])) {
    $user = $_SERVER['PHP_AUTH_USER'];
} else {
    // if not already authenticated do it
    //
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
	header('WWW-Authenticate: Basic realm=HWServer'); 
	header('HTTP/1.0 401 Unauthorized'); 
	exit;
    } else { 
	$user = $_SERVER['PHP_AUTH_USER'];}
}
*/

// ==============================================
// HTTP BASIC AUTHENTICATION WITH "PSEUDO" LOGOUT
// ==============================================
session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    echo "Logged out";
    exit();
}
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['logged_in'] = false;
}
else {
    if (isset($_SERVER['PHP_AUTH_USER'])) {
        $user = $_SERVER['PHP_AUTH_USER'];
        $_SESSION['logged_in'] = true;
    } 
    else if (isset($_SERVER['REMOTE_USER'])) {
        $user = $_SERVER['PHP_AUTH_USER'];
        $_SESSION['logged_in'] = true;
    }
}

if (!$_SESSION['logged_in']) {
    header('WWW-Authenticate: Basic realm=HWServer'); 
    header('HTTP/1.0 401 Unauthorized'); 
    exit;
}


//Remove error reporting and ini set for production code
error_reporting(E_ALL);
ini_set('display_errors', 1);

/*session starts here if using the old HTTP Auth without 
                                        "pseudo" logout*/
//session_start();

$_SESSION["id"] = $user;

if (isset($_GET["page"])) {
    $page = htmlspecialchars($_GET["page"]);
} else {
    $page = "homework";
}

/*logout button for pseudo logout, comment out this block 
                              if old HTTP auth is in use*/
echo "<div align='right'>";
echo "<a class='btn btn-primary' href='?logout'>logout</a>";
echo "</div>";

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
