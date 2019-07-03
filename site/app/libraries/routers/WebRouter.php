<?php


namespace app\libraries\routers;

use app\libraries\response\RedirectResponse;
use app\libraries\response\Response;
use app\libraries\response\JsonResponse;
use app\exceptions\AuthenticationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Doctrine\Common\Annotations\AnnotationReader;
use app\libraries\Utils;
use app\libraries\Core;


class WebRouter {
    /** @var Core  */
    protected $core;

    /** @var Request  */
    protected $request;

    /** @var bool */
    protected $logged_in;

    /** @var UrlMatcher  */
    protected $matcher;

    /** @var bool */
    protected $course_loaded = false;

    /** @var bool */
    protected $is_api = false;

    /** @var array */
    public $parameters;

    /** @var string the controller to call */
    public $controller_name;

    /** @var string the method to call */
    public $method_name;

    public function __construct(Request $request, Core $core, $logged_in, $is_api = false) {
        $this->core = $core;
        $this->request = $request;
        $this->logged_in = $logged_in;
        $this->is_api = $is_api;

        $fileLocator = new FileLocator();
        /** @noinspection PhpUnhandledExceptionInspection */
        $annotationLoader = new AnnotatedRouteLoader(new AnnotationReader());
        $loader = new AnnotationDirectoryLoader($fileLocator, $annotationLoader);
        $collection = $loader->load(realpath(__DIR__ . "/../../controllers"));
        $context = new RequestContext();

        $this->matcher = new UrlMatcher($collection, $context->fromRequest($this->request));
        if ($is_api) {
            $this->parameters = $this->matcher->matchRequest($this->request);
            // prevent user that is not logged in from going anywhere except AuthenticationController
            if (!$this->logged_in &&
                !Utils::endsWith($this->parameters['_controller'], 'AuthenticationController')) {
                throw new AuthenticationException("Unauthenticated access. Please log in.");
            }
        }
        else {
            try {
                $this->parameters = $this->matcher->matchRequest($this->request);
                $this->loadCourses();
                $this->loginCheck();
            }
            catch (ResourceNotFoundException $e) {
                // redirect to login page or home page
                $this->loginCheck();
            }
        }
    }

    /**
     * @param Request $request
     * @param Core $core
     * @param $logged_in
     * @return Response|mixed should be of type Response only in the future
     */
    static public function getApiResponse(Request $request, Core $core, $logged_in) {
        try {
            $router = new self($request, $core, $logged_in, true);
        }
        catch (ResourceNotFoundException $e) {
            return new Response(JsonResponse::getFailResponse("Endpoint not found."));
        }
        catch (AuthenticationException $e) {
            return new Response(JsonResponse::getFailResponse($e->getMessage()));
        }
        catch (MethodNotAllowedException $e) {
            return new Response(JsonResponse::getFailResponse("Method not allowed."));
        }
        catch (\Exception $e) {
            return new Response(JsonResponse::getErrorResponse($e->getMessage()));
        }

        $core->getOutput()->disableRender();
        $core->disableRedirects();
        return $router->run();
    }

    public function run() {
        if (!$this->is_api &&
            $this->request->isMethod('POST') &&
            !Utils::endsWith($this->parameters['_controller'], 'AuthenticationController') &&
            !$this->core->checkCsrfToken()
        ) {
            $msg = "Invalid CSRF token.";
            $this->core->addErrorMessage($msg);
            return new Response(
                JsonResponse::getFailResponse($msg),
                null,
                new RedirectResponse($this->core->buildNewUrl())
            );
        }

        $this->controller_name = $this->parameters['_controller'];
        $this->method_name = $this->parameters['_method'];
        $controller = new $this->controller_name($this->core);

        foreach ($this->parameters as $key => $value) {
            if (Utils::startsWith($key, "_")) {
                unset($this->parameters[$key]);
            }
        }

        // pass $_GET to controllers
        // the user-specified $_GET should NOT override the controller name and method name matched
        $this->request->query->remove('url');
        $this->parameters = array_merge($this->parameters, $this->request->query->all());

        return call_user_func_array([$controller, $this->method_name], $this->parameters);
    }

    private function loadCourses() {
        if (array_key_exists('_semester', $this->parameters) &&
            array_key_exists('_course', $this->parameters)) {
            $semester = $this->parameters['_semester'];
            $course = $this->parameters['_course'];
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->core->loadConfig($semester, $course);
            $this->course_loaded = true;
        }
    }

    private function loginCheck() {
        // This is a workaround for backward compatibility
        // Should be removed after ClassicRouter is killed completely
        if ($this->core->getConfig()->isCourseLoaded() && !$this->course_loaded) {
            if ($this->core->getConfig()->isDebug()) {
                throw new \RuntimeException("Attempted to use router for invalid URL. Please report the sequence of pages/actions you took to get to this exception to API developers.");
            }
            $this->core->redirect($this->core->getConfig()->getBaseUrl());
        }

        if (!$this->logged_in && !Utils::endsWith($this->parameters['_controller'], 'AuthenticationController')) {
            $old_request_url = $this->request->getUriForPath($this->request->getPathInfo());
            $this->request = Request::create(
                '/authentication/login',
                'GET',
                ['old' => urlencode($old_request_url)]
            );
            $this->parameters = $this->matcher->matchRequest($this->request);
        }
        elseif ($this->core->getUser() === null) {
            $this->core->loadSubmittyUser();
            if (!Utils::endsWith($this->parameters['_controller'], 'AuthenticationController')) {
                $this->request = Request::create(
                    $this->parameters['_semester'] . '/' . $this->parameters['_course'] . '/no_access',
                    'GET'
                );
                $this->parameters = $this->matcher->matchRequest($this->request);
            }
        }
        elseif ($this->core->getConfig()->isCourseLoaded()
            && !$this->core->getAccess()->canI("course.view", ["semester" => $this->core->getConfig()->getSemester(), "course" => $this->core->getConfig()->getCourse()])
            && !Utils::endsWith($this->parameters['_controller'], 'AuthenticationController')) {
            $this->request = Request::create(
                $this->parameters['_semester'] . '/' . $this->parameters['_course'] . '/no_access',
                'GET'
            );
            $this->parameters = $this->matcher->matchRequest($this->request);
        }

        if(!$this->core->getConfig()->isCourseLoaded()) {
            if ($this->logged_in){
                if (isset($this->parameters['_method']) && $this->parameters['_method'] === 'logout'){
                    $this->request = Request::create(
                        '/authentication/logout',
                        'GET'
                    );
                    $this->parameters = $this->matcher->matchRequest($this->request);
                }
                elseif (!Utils::endsWith($this->parameters['_controller'], 'HomePageController')) {
                    $this->request = Request::create(
                        '/home',
                        'GET'
                    );
                    $this->parameters = $this->matcher->matchRequest($this->request);
                }
            }
            elseif (!Utils::endsWith($this->parameters['_controller'], 'AuthenticationController')) {
                $this->request = Request::create(
                    '/authentication/login',
                    'GET'
                );
                $this->parameters = $this->matcher->matchRequest($this->request);
            }
        }
    }
}