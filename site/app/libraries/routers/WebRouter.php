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

    /** @var array */
    public $parameters;

    /** @var string the controller to call */
    public $controller_name;

    /** @var string the method to call */
    public $method_name;

    /**
     * WebRouter constructor.
     *
     * The constructor parses the request and obtains raw parameters.
     *
     * @param Request $request
     * @param Core $core
     * @param $logged_in
     * @throws \Doctrine\Common\Annotations\AnnotationException
     */
    public function __construct(Request $request, Core $core, $logged_in) {
        $this->core = $core;
        $this->request = $request;
        $this->logged_in = $logged_in;

        $fileLocator = new FileLocator();
        /** @noinspection PhpUnhandledExceptionInspection */
        $annotationLoader = new AnnotatedRouteLoader(new AnnotationReader());
        $loader = new AnnotationDirectoryLoader($fileLocator, $annotationLoader);
        $collection = $loader->load(realpath(__DIR__ . "/../../controllers"));

        $this->matcher = new UrlMatcher($collection, new RequestContext());

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

    /**
     * Runs the corresponding controller with parameters needed.
     *
     * @return mixed
     */
    public function run() {
        $this->controller_name = $this->parameters['_controller'];
        $this->method_name = $this->parameters['_method'];

        // Check CSRF token for POST requests
        if ($this->request->isMethod("POST") &&
            !$this->core->checkCsrfToken() &&
            !Utils::endsWith($this->controller_name, 'AuthenticationController')) {
            $msg = "Invalid CSRF token.";
            $this->core->addErrorMessage($msg);
            return $this->core->getOutput()->renderJsonFail($msg);
        }

        $this->processParameters();

        $controller = new $this->controller_name($this->core);
        return call_user_func_array([$controller, $this->method_name], $this->parameters);
    }

    /**
     * Prepare the parameters for controllers
     */
    private function processParameters() {
        foreach ($this->parameters as $key => $value) {
            if (Utils::startsWith($key, "_")) {
                unset($this->parameters[$key]);
            }
        }

        // pass $_GET to controllers
        // the user-specified $_GET should NOT override the controller name and method name matched
        $this->request->query->remove('url');
        $this->parameters = array_merge($this->parameters, $this->request->query->all());
    }

    private function loadCourses() {
        if (array_key_exists('_semester', $this->parameters) &&
            array_key_exists('_course', $this->parameters)) {
            $semester = $this->parameters['_semester'];
            $course = $this->parameters['_course'];
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->core->loadConfig($semester, $course);
        }
    }

    private function loginCheck() {
        if (!$this->logged_in && !Utils::endsWith($this->parameters['_controller'], 'AuthenticationController')) {
            $old_request_url = $this->request->getUriForPath($this->request->getPathInfo());
            $this->request = Request::create(
                '/authentication/login',
                'GET'
            );
            $this->parameters = $this->matcher->matchRequest($this->request);
            $this->parameters['old'] = base64_encode($old_request_url);
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

        // TODO: log

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