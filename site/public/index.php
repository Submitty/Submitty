<?php

use app\exceptions\BaseException;
use app\libraries\Core;
use app\libraries\ExceptionHandler;
use app\libraries\Logger;
use app\libraries\Utils;
use app\libraries\routers\WebRouter;
use app\libraries\response\ResponseInterface;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Component\HttpFoundation\Request;

/*
 * The user's umask is ignored for the user running php, so we need
 * to set it from inside of php to make sure the group read & execute
 * permissions aren't lost for newly created files & directories.
*/
umask(0027);

session_start();
/*
 * Show any notices, warnings, or errors as any of these appearing in a bootup
 * class (AutoLoader, Config, etc.) could indicate a more serious issue down the
 * line
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$loader = require_once(__DIR__ . '/../vendor/autoload.php');
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

/*
 * This sets up our Core (which in turn loads the config, database, etc.) for the application
 * and then we initialize our Output engine (as it requires Core to run) and then set the
 * paths for the Logger and ExceptionHandler
 */

/** @noinspection PhpUnhandledExceptionInspection */
$core->loadMasterConfig();
Logger::setLogPath($core->getConfig()->getLogPath());
ExceptionHandler::setLogExceptions($core->getConfig()->shouldLogExceptions());
ExceptionHandler::setDisplayExceptions($core->getConfig()->isDebug());

/** @noinspection PhpUnhandledExceptionInspection */
$core->loadMasterDatabase();
/** @noinspection PhpUnhandledExceptionInspection */
$core->loadAuthentication();
//Load Twig templating engine after the config is loaded but before any output is shown
$core->getOutput()->loadTwig();

$core->getOutput()->setInternalResources();

if ($core->getConfig()->getInstitutionName() !== "") {
    $core->getOutput()->addBreadcrumb(
        $core->getConfig()->getInstitutionName(),
        null,
        $core->getConfig()->getInstitutionHomepage()
    );
}

$core->getOutput()->addBreadcrumb("Submitty", $core->getConfig()->getBaseUrl());

date_default_timezone_set($core->getConfig()->getTimezone()->getName());

// We only want to show notices and warnings in debug mode, as otherwise errors are important
ini_set('display_errors', 1);
if ($core->getConfig()->isDebug()) {
    error_reporting(E_ALL);
}
else {
    error_reporting(E_ERROR);
}

if (empty($_COOKIE['submitty_token'])) {
    /** @noinspection PhpUnhandledExceptionInspection */
    Utils::setCookie('submitty_token', \Ramsey\Uuid\Uuid::uuid4()->toString());
}

$is_api = explode('/', $request->getPathInfo())[1] === 'api';
if ($is_api) {
    if (!empty($_SERVER['CONTENT_TYPE']) && Utils::startsWith($_SERVER['CONTENT_TYPE'], 'application/json')) {
        $_POST = json_decode(file_get_contents('php://input'), true);
    }
    $response = WebRouter::getApiResponse($request, $core);
}
else {
    $response = WebRouter::getWebResponse($request, $core);
}

if ($response instanceof ResponseInterface) {
    $response->render($core);
}

$core->getOutput()->displayOutput();
