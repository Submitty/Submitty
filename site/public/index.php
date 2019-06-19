<?php

use app\exceptions\BaseException;
use app\libraries\Core;
use app\libraries\ExceptionHandler;
use app\libraries\Logger;
use app\libraries\Utils;
use app\libraries\Access;
use app\libraries\TokenManager;
use app\libraries\routers\ClassicRouter;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Component\HttpFoundation\Request;

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

$loader = require_once(__DIR__.'/../vendor/autoload.php');
AnnotationRegistry::registerLoader([$loader, 'loadClass']);

$request = Request::createFromGlobals();

$core = new Core();
$core->setRouter(new ClassicRouter($_GET['url'] ?? ''));

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

$semester = '';
$course = '';
$is_api = False;

if ($core->getRouter()->hasNext()) {
    $first = $core->getRouter()->getNext();
    if ($first === 'api') {
        $is_api = True;
    }
    elseif (in_array($first, ['authentication', 'home'])) {
        $_REQUEST['component'] = $first;
    }
    else {
        $semester = $first ?? '';
        $course = $core->getRouter()->getNext() ?? '';
    }
}

/*
 * Check that we have a semester and a course specified by the user and then that there's no
 * potential for path trickery by using basename which will return only the last part of a
 * given path (such that /../../test would become just test)
 */

if (empty($_REQUEST['semester'])) {
    $_REQUEST['semester'] = $semester;
}

if (empty($_REQUEST['course'])) {
    $_REQUEST['course'] = $course;
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
//Load Twig templating engine after the config is loaded but before any output is shown
$core->getOutput()->loadTwig();
/** @noinspection PhpUnhandledExceptionInspection */
$core->loadGradingQueue();

if($core->getConfig()->getInstitutionName() !== ""){
    $core->getOutput()->addBreadcrumb($core->getConfig()->getInstitutionName(), null, $core->getConfig()->getInstitutionHomepage());
}
$core->getOutput()->addBreadcrumb("Submitty", $core->getConfig()->getHomepageUrl());
if($core->getConfig()->isCourseLoaded()){
    $core->getOutput()->addBreadcrumb($core->getDisplayedCourseName(), $core->buildNewCourseUrl(), $core->getConfig()->getCourseHomeUrl());
}

date_default_timezone_set($core->getConfig()->getTimezone()->getName());

Logger::setLogPath($core->getConfig()->getLogPath());
ExceptionHandler::setLogExceptions($core->getConfig()->shouldLogExceptions());
ExceptionHandler::setDisplayExceptions($core->getConfig()->isDebug());

/** @noinspection PhpUnhandledExceptionInspection */
$core->loadDatabases();

if($core->getConfig()->isCourseLoaded() && $core->getConfig()->isForumEnabled()) {
    $core->loadForum();
}

$core->getOutput()->setInternalResources();

// We only want to show notices and warnings in debug mode, as otherwise errors are important
ini_set('display_errors', 1);
if($core->getConfig()->isDebug()) {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}

// Check if we have a saved cookie with a session id and then that there exists
// a session with that id. If there is no session, then we delete the cookie.
$logged_in = false;
$cookie_key = 'submitty_session';
if (isset($_COOKIE[$cookie_key])) {
    try {
        $token = TokenManager::parseSessionToken(
            $_COOKIE[$cookie_key],
            $core->getConfig()->getBaseUrl(),
            $core->getConfig()->getSecretSession()
        );
        $session_id = $token->getClaim('session_id');
        $expire_time = $token->getClaim('expire_time');
        $logged_in = $core->getSession($session_id, $token->getClaim('sub'));
        // make sure that the session exists and it's for the user they're claiming
        // to be
        if (!$logged_in) {
            // delete cookie that's stale
            Utils::setCookie($cookie_key, "", time() - 3600);
        }
        else {
            if ($expire_time > 0 || $reset_cookie) {
                Utils::setCookie(
                    $cookie_key,
                    (string) TokenManager::generateSessionToken(
                        $session_id,
                        $token->getClaim('sub'),
                        $core->getConfig()->getBaseUrl(),
                        $core->getConfig()->getSecretSession()
                    ),
                    $expire_time
                );
            }
        }
    }
    catch (\InvalidArgumentException $exc) {
        // Invalid cookie data, delete it
        Utils::setCookie($cookie_key, "", time() - 3600);
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
else if ($core->getConfig()->isCourseLoaded()
         && !$core->getAccess()->canI("course.view", ["semester" => $core->getConfig()->getSemester(), "course" => $core->getConfig()->getCourse()])
         && $_REQUEST['component'] !== 'authentication') {

    $_REQUEST['component'] = 'navigation';
    $_REQUEST['page'] = 'no_access';
}

// Log the user action if they were logging in, logging out, or uploading something
if ($core->getUser() !== null) {
    if (empty($_COOKIE['submitty_token'])) {
        Utils::setCookie('submitty_token', \Ramsey\Uuid\Uuid::uuid4()->toString());
    }
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
        if ($core->getConfig()->isCourseLoaded()) {
            $action = $core->getConfig()->getSemester().':'.$core->getConfig()->getCourse().':'.$action;
        }
        Logger::logAccess($core->getUser()->getId(), $_COOKIE['submitty_token'], $action);
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

$supported_by_new_router = in_array($_REQUEST['component'], ['authentication', 'home']) ||
    ($_REQUEST['component'] == 'navigation' && !in_array($_REQUEST['page'], ['notifications', 'notification_settings']));

if (!$supported_by_new_router) {
    switch($_REQUEST['component']) {
        case 'admin':
            $control = new app\controllers\AdminController($core);
            $control->run();
            break;
        case 'grading':
            $control = new app\controllers\GradingController($core);
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
            $control = new app\controllers\forum\ForumController($core);
            $control->run();
            break;
        case 'notification':
            $control = new app\controllers\NotificationController($core);
            $control->run();
            break;
        case 'pdf':
            $control = new app\controllers\pdf\PDFController($core);
            $control->run();
            break;
        default:
            $control = new app\controllers\AuthenticationController($core, $logged_in);
            $control->run();
            break;
    }
}
else {
    $router = new app\libraries\routers\WebRouter($request, $core, $logged_in);
    $router->run();
}

$core->getOutput()->displayOutput();
