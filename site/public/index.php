<?php

use app\exceptions\BaseException;
use app\libraries\AutoLoader;
use app\libraries\Core;
use app\libraries\ExceptionHandler;
use app\libraries\Logger;
use app\libraries\Utils;

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
/**
 * Register custom expection and error handlers that will get run anytime our application
 * throws something or suffers a fatal error. This allows us to print a very generic error
 * page instead of the actual exception/stack trace during execution, both logging the error
 * and preventing the user from knowing exactly how our system is failing.
 *
 * @param Throwable $throwable
 */
function exception_handler($throwable) {
    global $core;
    $message = ExceptionHandler::handleException($throwable);

    // Any exceptions that always get shown we need to make sure to escape, especially for production
    if (is_a($throwable, '\app\exceptions\BaseException')) {
        /** @var BaseException $throwable */
        if ($throwable->displayMessage()) {
            $message = htmlentities($message, ENT_QUOTES);
        }
    }
    $core->getOutput()->showException($message);
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

if(empty($_REQUEST['semester']) || empty($_REQUEST['course'])){
    $_REQUEST['semester'] = $_REQUEST['course'] = "";
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

/** @noinspection PhpUnhandledExceptionInspection */
$core->loadConfig($semester, $course);
/** @noinspection PhpUnhandledExceptionInspection */
$core->loadAuthentication();

if($core->getConfig()->getInstitutionName() !== ""){
    $core->getOutput()->addBreadcrumb($core->getConfig()->getInstitutionName(), "");
    $core->getOutput()->addBreadcrumb("", $core->getConfig()->getInstitutionHomepage(),false, true);
}
$core->getOutput()->addBreadcrumb("Submitty", $core->getConfig()->getHomepageUrl());
if($core->getConfig()->isCourseLoaded()){
    $core->getOutput()->addBreadcrumb($core->getDisplayedCourseName(), $core->buildUrl());
    $core->getOutput()->addBreadcrumb("", $core->getConfig()->getCourseHomeUrl(),false, true);
}

date_default_timezone_set($core->getConfig()->getTimezone()->getName());
Logger::setLogPath($core->getConfig()->getLogPath());
ExceptionHandler::setLogExceptions($core->getConfig()->shouldLogExceptions());
ExceptionHandler::setDisplayExceptions($core->getConfig()->isDebug());

/** @noinspection PhpUnhandledExceptionInspection */
$core->loadDatabases();

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
$cookie_key = 'submitty_session_id';
if (isset($_COOKIE[$cookie_key])) {
    $cookie = json_decode($_COOKIE[$cookie_key], true);
    $logged_in = $core->getSession($cookie['session_id']);
    if (!$logged_in) {
        // delete the stale and invalid cookie
        Utils::setCookie($cookie_key, "", time() - 3600);
    }
    else {
        if ($cookie['expire_time'] > 0) {
            $cookie['expire_time'] = time() + (7 * 24 * 60 * 60);
            Utils::setCookie($cookie_key, $cookie, $cookie['expire_time']);
        }
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
elseif ($core->getUser() === null) {
    $core->loadSubmittyUser();
    if ($_REQUEST['component'] !== 'authentication') {
        $_REQUEST['component'] = 'navigation';
        $_REQUEST['page'] = 'no_access';
    }
}
// Log the user action if they were logging in, logging out, or uploading something
if ($core->getUser() !== null) {
    $log = false;
    $action = "";
    if ($_REQUEST['component'] === "authentication" && $_REQUEST['page'] === "logout") {
        $log = true;
        $action = "logout";
    }
    else if (in_array($_REQUEST['component'], array('student', 'submission')) && $_REQUEST['page'] === "submission" &&
        $_REQUEST['action'] === "upload") {
        $log = true;
        $action = "submission:{$_REQUEST['gradeable_id']}";
    }
    else if (isset($_REQUEST['success_login']) && $_REQUEST['success_login'] === "true") {
        $log = true;
        $action = "login";
    }
    if ($log && $action !== "") {
        Logger::logAccess($core->getUser()->getId(), $action);
    }
}

if(!$core->getConfig()->isCourseLoaded()) {
    if ($logged_in){
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === 'logout'){
            $_REQUEST['component'] = 'authentication';
        }
        else {
            $_REQUEST['component'] = 'home';
        }
    }
    else {
        $_REQUEST['component'] = 'authentication';
    }
}

if (empty($_REQUEST['component']) && $core->getUser() !== null) {
    if ($core->getConfig()->isCourseLoaded()) {
        $_REQUEST['component'] = 'navigation';
    }
    else {
        $_REQUEST['component'] = 'home';
    }
}

/********************************************
* END LOGIN CODE
*********************************************/
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
    case 'home':
        $control = new app\controllers\HomePageController($core);
        $control->run();
        break;
    case 'misc':
        $control = new app\controllers\MiscController($core);
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
    case 'navigation':
        $control = new app\controllers\NavigationController($core);
        $control->run();
        break;
    case 'forum':
        $control = new app\controllers\forum\ForumHomeController($core);
        $control->run();
        break;
    default:
        $control = new app\controllers\AuthenticationController($core, $logged_in);
        $control->run();
        break;
}

$core->getOutput()->displayOutput();
