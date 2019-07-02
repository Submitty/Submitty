<?php


namespace app\libraries\routers;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
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

        $fileLocator = new FileLocator();
        /** @noinspection PhpUnhandledExceptionInspection */
        $annotationLoader = new AnnotatedRouteLoader(new AnnotationReader());
        $loader = new AnnotationDirectoryLoader($fileLocator, $annotationLoader);
        $collection = $loader->load(realpath(__DIR__ . "/../../controllers"));
        $context = new RequestContext();

        $this->matcher = new UrlMatcher($collection, $context->fromRequest($this->request));
        if ($is_api) {
            try {
                $this->parameters = $this->matcher->matchRequest($this->request);
                // prevent /api/something from being matched to /{_semester}/{_course}
                if ($this->parameters['_method'] === 'navigationPage') {
                    throw new ResourceNotFoundException;
                }
                // prevent user that is not logged in from going anywhere except AuthenticationController
                if (!$this->logged_in &&
                    !Utils::endsWith($this->parameters['_controller'], 'AuthenticationController')) {
                    $this->core->getOutput()->renderJsonFail("Unauthorized access. Please log in.");
                    die($this->core->getOutput()->getOutput());
                }
            }
            catch (ResourceNotFoundException $e) {
                $this->core->getOutput()->renderJsonFail("Endpoint not found.");
                die($this->core->getOutput()->getOutput());
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

    public function run() {
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