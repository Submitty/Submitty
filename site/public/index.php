<?php

use app\exceptions\BaseException;
use app\libraries\AutoLoader;
use app\libraries\Core;
use app\libraries\ExceptionHandler;
use app\libraries\Logger;

/*
 * The user's umask is ignored for the user running php, so we need
 * to set it from inside of php to make sure the group read & execute
 * permissions aren't lost for newly created files & directories.
*/
umask (0027);

session_start();

/*
 * Show any notices, warnings, or errors as any of these appearing in a bootup
 * class (AutoLoader, Config, etc.) could indicate a more serious issue down the
 * line
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
 * Load up the autoloader and register the /app directory to the application
 * such that we can easily and quickly load classes on the fly as needed. All
 * classes should follow the PSR-4 namespace naming conventions
 */
require_once(__DIR__ . "/../app/libraries/AutoLoader.php");
AutoLoader::registerDirectory(__DIR__ . "/../app", true, "app");

$core = new Core();
/*
 * Register custom expection and error handlers that will get run anytime our application
 * throws something or suffers a fatal error. This allows us to print a very generic error
 * page instead of the actual exception/stack trace during execution, both logging the error
 * and preventing the user from knowing exactly how our system is failing.
 */
function exception_handler($throwable) {
    global $core;
    $core->getOutput()->showException(ExceptionHandler::handleException($throwable));
}
set_exception_handler("exception_handler");

function error_handler() {
    $error = error_get_last();
    if ($error['type'] === E_ERROR) {
        exception_handler(new BaseException("Fatal Error: " . $error['message'] . " in file 
        " . $error['file'] . " on line " . $error['line']));
    }
}
register_shutdown_function("error_handler");
/*
 * Check that we have a semester and a course specified by the user and then that there's no
 * potential for path trickery by using basename which will return only the last part of a
 * given path (such that /../../test would become just test)
 */
if (!isset($_REQUEST['semester'])) {
    // @todo: should check for a default semester if one is not specified, opposed to throwing an exception
    $core->getOutput()->showError("Need to specify a semester in the URL");
}
if (!isset($_REQUEST['course'])) {
    $core->getOutput()->showError("Need to specify a course in the URL");
}

// Sanitize the inputted semester & course to prevent directory attacks
$semester = basename($_REQUEST['semester']);
$course = basename($_REQUEST['course']);

if ($semester != $_REQUEST['semester'] || $course != $_REQUEST['course']) {
    $url = "http" . (($_SERVER['SERVER_PORT'] == 443) ? "s://" : "://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $url = str_replace("course={$_REQUEST['course']}", "course={$course}", $url);
    $url = str_replace("semester={$_REQUEST['semester']}", "semester={$semester}", $url);
    header("Location: {$url}");
    exit();
}

/*
 * This sets up our Core (which in turn loads the config, database, etc.) for the application
 * and then we initialize our Output engine (as it requires Core to run) and then set the
 * paths for the Logger and ExceptionHandler
 */
$master_ini_path = \app\libraries\FileUtils::joinPaths("..", "config", "master.ini");
$core->loadConfig($semester, $course, $master_ini_path);
$core->getOutput()->addBreadcrumb($core->getFullCourseName(), $core->getConfig()->getCourseHomeUrl(),true);
$core->getOutput()->addBreadcrumb("Submitty", $core->buildUrl());


date_default_timezone_set($core->getConfig()->getTimezone());
Logger::setLogPath($core->getConfig()->getLogPath());
ExceptionHandler::setLogExceptions($core->getConfig()->getLogExceptions());
ExceptionHandler::setDisplayExceptions($core->getConfig()->isDebug());
$core->loadDatabase();

// We only want to show notices and warnings in debug mode, as otherwise errors are important
ini_set('display_errors', 1);
if($core->getConfig()->isDebug()) {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}

// Check if we have a saved cookie with a session id and then that there exists a session with that id
// If there is no session, then we delete the cookie
$logged_in = false;
$cookie_key = $semester."_".$course."_session_id";
if (isset($_COOKIE[$cookie_key])) {
    $logged_in = $core->getSession($_COOKIE[$cookie_key]);
    if (!$logged_in) {
        // delete the stale and invalid cookie
        setcookie($cookie_key, "", time() - 3600);
    }
    else {
        setcookie($cookie_key, $_COOKIE[$cookie_key], time() + (7 * 24 * 60 * 60), "/");
    }
}

// Prevent anyone who isn't logged in from going to any other controller than authentication
if (!$logged_in) {
    if ($_REQUEST['component'] != 'authentication') {
        foreach ($_REQUEST as $key => $value) {
            if ($key == "semester" || $key == "course") {
                continue;
            }
            if ($value === "") {
                continue;
            }
            if(isset($_REQUEST[$key])) {
                $_REQUEST['old'][$key] = $value;
            }
            else if(substr($key, 0, 4) === "old_") {
                $_REQUEST['old'][substr($key, 4)] = $value;
                
            }
            unset($_REQUEST[$key]);
        }
        $_REQUEST['component'] = 'authentication';
        $_REQUEST['page'] = 'login';
    }
}

switch($_REQUEST['component']) {
    case 'admin':
        $control = new app\controllers\AdminController($core);
        $control->run();
        break;
    case 'authentication':
        $control = new app\controllers\AuthenticationController($core, $logged_in);
        $control->run();
        break;
    case 'grading':
        $control = new app\controllers\GradingController($core);
        $control->run();
        break;
    case 'student':
        $control = new app\controllers\StudentController($core);
        $control->run();
        break;
    case 'submission':
        $control = new app\controllers\StudentController($core);
        $control->run();
        break;
    default:
        $control = new app\controllers\NavigationController($core);
        $control->run();
        break;
}

echo($core->getOutput()->getOutput());
