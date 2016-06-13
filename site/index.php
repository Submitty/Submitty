<?php

use app\libraries\AutoLoader;
use app\libraries\Core;
use app\libraries\ExceptionHandler;
use app\libraries\Logger;
use app\libraries\Output;


/*
 * The user's umask is ignored for the user running php, so we need
 * to set it from inside of php to make sure the group read & execute
 * permissions aren't lost for newly created files & directories.
*/
umask (0027);

session_start();
$start = microtime(true);

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
require_once(__DIR__ . "/app/libraries/AutoLoader.php");
AutoLoader::registerDirectory(__DIR__."/app", true, "app");

/*
 * Register a custom expection handler that will get run anytmie our application
 * throws something. This allows us to print a very generic error page instead of
 * the actual exception/stack trace during execution
 */
function exception_handler($throwable) {
    Output::showException(ExceptionHandler::throwException($throwable));
}
set_exception_handler("exception_handler");

/*
 * Check that we have a semester and a course specified by the user and then that there's no
 * potential for path trickery by using basename which will return only the last part of a
 * given path (such that /../../test would become just test)
 */
if (!isset($_REQUEST['semester'])) {
    // @todo: should check for a default semester if one is not specified, opposed to throwing an exception
    Output::showError("Need to specify a semester (ex: &semester=s16) in the URL");
}
if (!isset($_REQUEST['course'])) {
    Output::showError("Need to specify a course (ex: &course=csci1100) in the URL");
}

$semester = basename($_REQUEST['semester']);
$course = basename($_REQUEST['course']);

/*
 * This sets up our Core (which in turn loads the config, database, etc.) for the application
 * and then we initialize our Output engine (as it requires Core to run) and then set the
 * paths for the Logger and ExceptionHandler
 */
$core = new Core($semester, $course);
date_default_timezone_set($core->getConfig()->getTimezone());
Output::initializeOutput($core);
Logger::setLogPath($core->getConfig()->getHssLogPath());
ExceptionHandler::setLogExceptions($core->getConfig()->getLogExceptions());
ExceptionHandler::setDisplayExceptions($core->getConfig()->isDebug());

// We only want to show notices and warnings in debug mode, as otherwise errors are important
if($core->getConfig()->isDebug()) {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}


/*
 * This is how we should handle authentication if not using apache...

$session = $core->getSession($_COOKIE['session_id']);
$user_id = isset($_POST['user_id']) ? $_POST['user_id'] : "";
$password = isset($_POST['password']) ? $_POST['password'] : "";
if ($session === false || !$core->authenticate($user_id, $password)) {
    $_REQUEST['old_component'] = $_REQUEST['component'];
    $_REQUEST['component'] = 'login';
}
else {
    if ($_REQUEST['component'] == 'login') {
        $_REQUEST['component'] = 'submission';
    }
}

then not have lines 76 - 93 and have an "unrecognized user" error
 */
if ($core->getConfig()->isDebug() && isset($_GET['useUser'])) {
    $user_id = $_GET['useUser'];
}
elseif (isset($_SERVER['PHP_AUTH_USER'])) {
    $user_id = $_SERVER['PHP_AUTH_USER'];
}
elseif (isset($_SERVER['REMOTE_USER'])) {
    $user_id = $_SERVER['REMOTE_USER'];
}
else {
    header('WWW-Authenticate: Basic realm=HWServer');
    header('HTTP/1.0 401 Unauthorized');
    exit;
}

if (!$core->authenticate($user_id, $_SERVER['PHP_AUTH_PW'])) {
    Output::showError("Unrecognized user id: {$user_id}");
}

Output::render("Global", 'header');
switch($_REQUEST['component']) {
    case 'admin':
        $control = new app\controllers\AdminController($core);
        $control->run();
        break;
    case 'grading':
        $control = new app\controllers\GradingController($core);
        $control->run();
        break;
    case 'submission':
    default:
        $control = new app\controllers\SubmissionController($core);
        $control->run();
        break;
}

Output::render("Global", 'footer', (microtime(true) - $start));
echo(Output::getOutput());
