<?php

use app\libraries\AutoLoader;
use app\libraries\ExceptionHandler;
use app\libraries\Output;
use app\models\Config;
use app\models\User;

/*
The user's umask is ignored for the user running php, so we need
to set it from inside of php to make sure the group read & execute
permissions aren't lost for newly created files & directories.
*/
umask (0027);
date_default_timezone_set('America/New_York');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . "/app/libraries/AutoLoader.php");
AutoLoader::registerDirectory(__DIR__."/app", true, "app");

function exception_handler($throwable) {
    Output::showError(ExceptionHandler::throwException($throwable));
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

Config::loadCourse($semester, $course);

if(Config::$debug) {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}

if (Config::$debug && isset($_GET['useUser'])) {
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

// attempt to load rcs as both student and user
User::loaduser($username);

// Path components are Section, Controller, Action, Method
$section = isset($_GET['component']) ? strtolower($_GET['component']) : 'submission';

switch($section) {
    case 'grading':
        if (!User::accessGrading()) {
            Output::showError("Unrecognized username '{$username}'");
        }
        $control = new app\controllers\GradingController();
        $control->run();
        break;
    case 'submission':
        if (!User::userLoaded()) {
            Output::showError("Unrecognized username '{$username}'");
        }
        $control = new app\controllers\SubmissionController();
        $control->run();
        break;
    default:
        Output::showError("Invalid specified component");
        break;
}

print(Output::getOutput());
