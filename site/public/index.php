<?php

use app\exceptions\BaseException;
use app\libraries\Core;
use app\libraries\ExceptionHandler;
use app\libraries\Logger;
use app\libraries\Utils;
use app\libraries\TokenManager;
use app\libraries\routers\WebRouter;
use app\libraries\response\Response;

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

/**
 * Assume there are only 6 kinds of URLs:
 * 1. /semester/course/*
 * 2. /api/semester/course/*
 * 3. /authentication/*
 * 4. /home/*
 * 5. /api/*
 * 6. /*
 */

$semester = '';
$course = '';

$url_parts = explode('/', $request->getPathInfo());

$is_api = $url_parts[1] === 'api';

if ($is_api) {
    $semester = $url_parts[2] ?? '';
    $course = $url_parts[3] ?? '';
}
else {
    $semester = $url_parts[1] ?? '';
    if (!in_array($semester, ['authentication', 'home'])) {
        $course = $url_parts[2] ?? '';
    }
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
$core->getOutput()->addBreadcrumb("Submitty", $core->getConfig()->getBaseUrl());
if($core->getConfig()->isCourseLoaded()){
    $core->getOutput()->addBreadcrumb($core->getDisplayedCourseName(), $core->buildCourseUrl(), $core->getConfig()->getCourseHomeUrl());
}

date_default_timezone_set($core->getConfig()->getTimezone()->getName());

Logger::setLogPath($core->getConfig()->getLogPath());
ExceptionHandler::setLogExceptions($core->getConfig()->shouldLogExceptions());
ExceptionHandler::setDisplayExceptions($core->getConfig()->isDebug());

/** @noinspection PhpUnhandledExceptionInspection */
$core->loadDatabases();

if($core->getConfig()->isCourseLoaded() && $core->getConfig()->isForumEnabled()) {
    /** @noinspection PhpUnhandledExceptionInspection */
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
            if ($expire_time > 0) {
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

// check if the user has a valid jwt in the header
$api_logged_in = false;
$jwt = $request->headers->get("authorization");
if (!empty($jwt)) {
    try {
        $token = TokenManager::parseApiToken(
            $request->headers->get("authorization"),
            $core->getConfig()->getBaseUrl(),
            $core->getConfig()->getSecretSession()
        );
        $api_key = $token->getClaim('api_key');
        $api_logged_in = $core->loadApiUser($api_key);
    }
    catch (\InvalidArgumentException $exc) {
        $core->getOutput()->renderJsonFail("Invalid token.");
        $core->getOutput()->displayOutput();
        return;
    }
}

if (empty($_COOKIE['submitty_token'])) {
    /** @noinspection PhpUnhandledExceptionInspection */
    Utils::setCookie('submitty_token', \Ramsey\Uuid\Uuid::uuid4()->toString());
}

if ($is_api) {
    $response = WebRouter::getApiResponse($request, $core, $api_logged_in);
}
else {
    $response = WebRouter::getWebResponse($request, $core, $logged_in);
}

if ($response instanceof Response) {
    $response->render($core);
}

$core->getOutput()->displayOutput();