<?php

use app\libraries\AutoLoader;
use app\libraries\Core;
use app\libraries\ExceptionHandler;
use app\libraries\Logger;
use app\libraries\Output;

/*
The user's umask is ignored for the user running php, so we need
to set it from inside of php to make sure the group read & execute
permissions aren't lost for newly created files & directories.
*/
umask (0027);
date_default_timezone_set('America/New_York');

session_start();
$start = microtime(true);

/*
Show any notices, warnings, or errors as any of these appearing in a bootup
class (AutoLoader, Config, etc.) could indicate a more serious issue down the
line
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . "/app/libraries/AutoLoader.php");
AutoLoader::registerDirectory(__DIR__."/app", true, "app");

function exception_handler($throwable) {
    Output::showException(ExceptionHandler::throwException($throwable));
}
set_exception_handler("exception_handler");

if (!isset($_REQUEST['semester'])) {
    Output::showError("Need to specify a semester (ex: &semester=s16) in the URL");
}
if (!isset($_REQUEST['course'])) {
    Output::showError("Need to specify a course (ex: &course=csci1100) in the URL");
}

$semester = basename($_REQUEST['semester']);
$course = basename($_REQUEST['course']);

$core = new Core($semester, $course);
Output::initializeOutput($core);
Logger::setLogPath($core->getConfig()->getHssLogPath());
ExceptionHandler::setLogExceptions($core->getConfig()->getLogExceptions());
ExceptionHandler::setDisplayExceptions($core->getConfig()->isDebug());

if($core->getConfig()->isDebug()) {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}

if ($core->getConfig()->isDebug() && isset($_GET['useUser'])) {
    $username = $_GET['useUser'];
}
elseif (isset($_SERVER['PHP_AUTH_USER'])) {
    $username = $_SERVER['PHP_AUTH_USER'];
}
elseif (isset($_SERVER['REMOTE_USER'])) {
    $username = $_SERVER['REMOTE_USER'];
}
else {
    header('WWW-Authenticate: Basic realm=HWServer');
    header('HTTP/1.0 401 Unauthorized');
    exit;
}

$core->loadUser($username);

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
