<?php

namespace app\libraries\routers;

use app\libraries\response\RedirectResponse;
use app\libraries\response\MultiResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\WebResponse;
use Doctrine\Common\Annotations\PsrCachedReader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Doctrine\Common\Annotations\AnnotationReader;
use app\libraries\Utils;
use app\libraries\Core;
use app\libraries\FileUtils;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class WebRouter {
    /** @var Core  */
    protected $core;

    /** @var Request  */
    protected $request;

    /** @var PsrCachedReader */
    protected $reader;

    /** @var array */
    protected $parameters;

    /** @var string the controller to call */
    protected $controller_name;

    /** @var string the method to call */
    protected $method_name;

    private function __construct(Request $request, Core $core) {
        $this->core = $core;
        $this->request = $request;

        $cache_path = FileUtils::joinPaths(dirname(__DIR__, 3), 'cache', 'routes');
        $cache = new FilesystemAdapter("", 0, $cache_path);

        $access_control_cache_path = FileUtils::joinPaths(dirname(__DIR__, 3), 'cache', 'access_control');
        $ac_cache = new FilesystemAdapter("", 0, $access_control_cache_path);

        $this->reader = new PsrCachedReader(
            new AnnotationReader(),
            $ac_cache,
            $this->core->getConfig()->isDebug()
        );

        // This will fetch the cache for routes. If it doesn't find it then it will
        // compile them, set the cache, and set compiledRoutes to that.
        $compiledRoutes = $cache->get('routes', function (ItemInterface $item) {
            return $this->getCompiledRoutes();
        });

        $context = new RequestContext();
        $matcher = new CompiledUrlMatcher($compiledRoutes, $context->fromRequest($this->request));
        $this->parameters = $matcher->matchRequest($this->request);
    }

    /**
     * Returns Symfony compiled routes
     *
     * @return array
     * @throws \Exception
     */
    private function getCompiledRoutes(): array {
        $fileLocator = new FileLocator();
        $annotationLoader = new AnnotatedRouteLoader($this->reader);
        $loader = new AnnotationDirectoryLoader($fileLocator, $annotationLoader);
        $collection = $loader->load(realpath(__DIR__ . "/../../controllers"));
        return (new CompiledUrlMatcherDumper($collection))->getCompiledRoutes();
    }


    /**
     * If a request is a post request check to see if its less than the post_max_size
     * @return MultiResponse|bool
     */
    private function checkPostMaxSize(Request $request) {
        if ($request->isMethod('POST')) {
            $max_post_length = ini_get("post_max_size");
            $max_post_bytes = Utils::returnBytes($max_post_length);
            /** if a post request exceeds the max length set in the ini, php will drop everything set under $_POST, however the router might add routing information later so check both cases
            */
            if ($max_post_bytes > 0 && $_SERVER["CONTENT_LENGTH"] >= $max_post_bytes) {
                $msg = "POST request exceeds maximum size of " . $max_post_length;
                $this->core->addErrorMessage($msg);

                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse($msg)
                );
            }

            return true;
        }

        return true;
    }

    /**
     * @param Request $request
     * @param Core $core
     * @return MultiResponse|mixed should be of type Response only in the future
     */
    public static function getApiResponse(Request $request, Core $core) {
        try {
            $router = new self($request, $core);

            // Check if loadCourse returns true
            if (!$router->loadCourse()) {
                // If loadCourse returns false, return an error response
                return JsonResponse::getFailResponse("Failed to load course. Check course title and ensure it exists.");
            }

            $logged_in = $core->isApiLoggedIn($request);

            // prevent user that is not logged in from going anywhere except AuthenticationController
            if (
                !$logged_in
                && !str_ends_with($router->parameters['_controller'], 'AuthenticationController')
            ) {
                return new MultiResponse(JsonResponse::getFailResponse("Unauthenticated access. Please log in."));
            }

            /** @noinspection PhpUnhandledExceptionInspection */
            if (!$router->accessCheck()) {
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse("You don't have access to this endpoint.")
                );
            }

            $enabled = $router->getEnabled();
            if ($enabled !== null && !$router->checkEnabled($enabled)) {
                return JsonResponse::getFailResponse("The {$enabled->getFeature()} feature is not enabled.");
            }

            if (!$router->checkFeatureFlag()) {
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse('Feature is not yet available.')
                );
            }

            $check_post_max_size = $router->checkPostMaxSize($request);
            if ($check_post_max_size instanceof MultiResponse) {
                return $check_post_max_size;
            }
        }
        catch (ResourceNotFoundException $e) {
            return new MultiResponse(JsonResponse::getFailResponse("Endpoint not found."));
        }
        catch (MethodNotAllowedException $e) {
            return new MultiResponse(JsonResponse::getFailResponse("Method not allowed."));
        }
        catch (\Exception $e) {
            return new MultiResponse(JsonResponse::getErrorResponse($e->getMessage()));
        }

        $core->getOutput()->disableRender();
        $core->disableRedirects();
        return $router->run();
    }

    /**
     * @param Request $request
     * @param Core $core
     * @return MultiResponse|mixed should be of type Response only in the future
     * @throws \ReflectionException|\Exception
     */
    public static function getWebResponse(Request $request, Core $core) {
        $logged_in = false;
        try {
            $router = new self($request, $core);

            // Check if loadCourse returns true
            if (!$router->loadCourse()) {
                // If loadCourse returns false, redirect to home page
                return new RedirectResponse($core->buildUrl(['home']));
            }

            $logged_in = $core->isWebLoggedIn();

            $login_check_response = $router->loginRedirectCheck($logged_in);
            if ($login_check_response instanceof MultiResponse || $login_check_response instanceof WebResponse) {
                return $login_check_response;
            }

            $check_post_max_size = $router->checkPostMaxSize($request);
            if ($check_post_max_size instanceof MultiResponse) {
                return $check_post_max_size;
            }

            $csrf_check_response = $router->csrfCheck();
            if ($csrf_check_response instanceof MultiResponse || $login_check_response instanceof WebResponse) {
                return $csrf_check_response;
            }

            /** @noinspection PhpUnhandledExceptionInspection */
            if (!$router->accessCheck()) {
                return new MultiResponse(
                    JsonResponse::getFailResponse("You don't have access to this endpoint."),
                    new WebResponse("Error", "errorPage", "You don't have access to this page.")
                );
            }

            $enabled = $router->getEnabled();
            if ($enabled !== null && !$router->checkEnabled($enabled)) {
                $errorString = "The {$enabled->getFeature()} feature is not enabled.";
                return new MultiResponse(
                    JsonResponse::getFailResponse($errorString),
                    new WebResponse("Error", "courseErrorPage", $errorString)
                );
            }

            if (!$router->checkFeatureFlag()) {
                return new MultiResponse(
                    JsonResponse::getFailResponse('Feature is not yet available.'),
                    new WebResponse("Error", "errorPage", "Feature is not yet available.")
                );
            }
        }
        catch (ResourceNotFoundException | MethodNotAllowedException $e) {
            // Redirect to login page or home page
            if (!$logged_in) {
                return MultiResponse::RedirectOnlyResponse(
                    new RedirectResponse($core->buildUrl(['authentication', 'login']))
                );
            }
            else {
                return MultiResponse::RedirectOnlyResponse(
                    new RedirectResponse($core->buildUrl(['home']))
                );
            }
        }

        return $router->run();
    }

    private function run() {
        $this->controller_name = $this->parameters['_controller'];
        $this->method_name = $this->parameters['_method'];
        $controller = new $this->controller_name($this->core);

        $arguments = [];
        /** @noinspection PhpUnhandledExceptionInspection */
        $method = new \ReflectionMethod($this->controller_name, $this->method_name);
        foreach ($method->getParameters() as $param) {
            $param_name = $param->getName();
            $arguments[$param_name] = $this->parameters[$param_name] ?? null;
            if (!isset($arguments[$param_name])) {
                $arguments[$param_name] = $this->request->query->get($param_name);
            }
            if (!isset($arguments[$param_name])) {
                $arguments[$param_name] = $param->getDefaultValue();
            }
        }

        return call_user_func_array([$controller, $this->method_name], $arguments);
    }

    /**
     * Loads course config if they exist in the requested URL.
     * @throws \Exception
     */
    private function loadCourse() {
        // Check if course parameters are present in the request
        if (array_key_exists('_semester', $this->parameters) && array_key_exists('_course', $this->parameters)) {
            $semester = $this->parameters['_semester'];
            $course = $this->parameters['_course'];

            // Load the course configuration
            $this->core->loadCourseConfig($semester, $course);
            $this->core->loadGradingQueue();

            // Check if the course is successfully loaded
            if ($this->core->getConfig()->isCourseLoaded()) {
                $this->core->getOutput()->addBreadcrumb(
                    $this->core->getDisplayedCourseName(),
                    $this->core->buildCourseUrl(),
                    $this->core->getConfig()->getCourseHomeUrl()
                );

                // Load other course-related configurations
                $this->core->loadCourseDatabase();

                return true;
            }
            else {
                return false;
            }
        }

        return true;
    }



    /**
     * Check if the user needs a redirection depending on their login status.
     * @param bool $logged_in
     * @return MultiResponse|bool
     */
    private function loginRedirectCheck(bool $logged_in) {
        if (!$logged_in && !str_ends_with($this->parameters['_controller'], 'AuthenticationController')) {
            $old_request_url = $this->request->getUriForPath($this->request->getPathInfo());

            $query_obj = $this->request->query->all();
            $filtered_query_obj = array_filter($query_obj, function ($k) {
                return $k !== "url";
            }, ARRAY_FILTER_USE_KEY);

            $query_string = empty($filtered_query_obj) ? null : '?' . http_build_query($filtered_query_obj);

            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse(
                    $this->core->buildUrl(['authentication', 'login']) . '?old=' . urlencode($old_request_url . $query_string)
                )
            );
        }
        elseif (
            $this->core->getConfig()->isCourseLoaded()
            && !$this->core->getAccess()->canI("course.view", ["semester" => $this->core->getConfig()->getTerm(), "course" => $this->core->getConfig()->getCourse()])
            && !str_ends_with($this->parameters['_controller'], 'AuthenticationController')
            && $this->parameters['_method'] !== 'noAccess'
            && $this->parameters['_method'] !== 'rejoinCourse'
        ) {
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildCourseUrl(['no_access']))
            );
        }
        elseif (
            $logged_in
            && str_ends_with($this->parameters['_controller'], 'AuthenticationController')
            && $this->parameters['_method'] !== 'logout'
        ) {
            return MultiResponse::RedirectOnlyResponse(
                new RedirectResponse($this->core->buildUrl(['home']))
            );
        }

        if (!$this->core->getConfig()->isCourseLoaded() && !str_ends_with($this->parameters['_controller'], 'MiscController')) {
            if ($logged_in) {
                if (isset($this->parameters['_semester']) && isset($this->parameters['_course'])) {
                    return MultiResponse::RedirectOnlyResponse(
                        new RedirectResponse($this->core->buildUrl(['home']))
                    );
                }
            }
            elseif (!str_ends_with($this->parameters['_controller'], 'AuthenticationController')) {
                return MultiResponse::RedirectOnlyResponse(
                    new RedirectResponse($this->core->buildUrl(['authentication', 'login']))
                );
            }
        }

        return true;
    }

    /**
     * Check if the request carries a valid CSRF token for all POST requests.
     * @return MultiResponse|bool
     */
    private function csrfCheck() {
        if (
            $this->request->isMethod('POST')
            && !str_ends_with($this->parameters['_controller'], 'AuthenticationController')
            && !$this->core->checkCsrfToken()
        ) {
            $msg = "Invalid CSRF token. Expected " . $this->core->getCsrfToken(). ". Got " . var_dump($_POST);
            $this->core->addErrorMessage($msg);
            return new MultiResponse(
                JsonResponse::getFailResponse($msg),
                null,
                null
            );
        }

        return true;
    }

    /**
     * Check if the call passes access control defined
     * in @AccessControl() annotation.
     *
     * @return bool
     * @throws \ReflectionException
     */
    private function accessCheck() {
        /** @noinspection PhpUnhandledExceptionInspection */
        $access_control = $this->reader->getMethodAnnotation(
            new \ReflectionMethod($this->parameters['_controller'], $this->parameters['_method']),
            AccessControl::class
        );

        if (is_null($access_control)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $access_control = $this->reader->getClassAnnotation(
                new \ReflectionClass($this->parameters['_controller']),
                AccessControl::class
            );
        }

        if (is_null($access_control)) {
            return true;
        }

        $user = $this->core->getUser();
        $access = true;

        if ($access_control->getRole()) {
            switch ($access_control->getRole()) {
                case 'INSTRUCTOR':
                    $access = $user->accessAdmin();
                    break;
                case 'FULL_ACCESS_GRADER':
                    $access = $user->accessFullGrading();
                    break;
                case 'LIMITED_ACCESS_GRADER':
                    $access = $user->accessGrading();
                    break;
                case 'STUDENT':
                default:
                    $access = $user !== null;
                    break;
            }
        }

        if ($access_control->getLevel()) {
            $access_test = false;
            switch ($access_control->getLevel()) {
                case 'SUPERUSER':
                    $access_test = $user->isSuperUser();
                    break;
                case 'FACULTY':
                    $access_test = $user->accessFaculty();
                    break;
                case 'USER':
                    $access_test = $user !== null;
                    break;
            }
            $access = $access && $access_test;
        }

        if ($access_control->getPermission()) {
            $access = $access && $this->core->getAccess()->canI($access_control->getPermission());
        }

        return $access;
    }

    private function checkFeatureFlag() {
        $feature_flag = $this->reader->getMethodAnnotation(
            new \ReflectionMethod($this->parameters['_controller'], $this->parameters['_method']),
            FeatureFlag::class
        );

        if (is_null($feature_flag)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $feature_flag = $this->reader->getClassAnnotation(
                new \ReflectionClass($this->parameters['_controller']),
                FeatureFlag::class
            );
        }

        if (is_null($feature_flag)) {
            return true;
        }

        return $this->core->getConfig()->checkFeatureFlagEnabled($feature_flag->getFlag());
    }

    private function getEnabled(): ?Enabled {
        return $this->reader->getClassAnnotation(
            new \ReflectionClass($this->parameters['_controller']),
            Enabled::class
        );
    }

    private function checkEnabled(Enabled $enabled): bool {
        $method = "is" . ucFirst($enabled->getFeature()) . "Enabled";
        return $this->core->getConfig()->$method();
    }
}
