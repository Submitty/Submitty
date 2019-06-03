<?php


namespace app\libraries;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Doctrine\Common\Annotations\AnnotationReader;


class MainRouter {
    /** @var Core  */
    protected $core;

    /** @var Request  */
    protected $request;

    /** @var bool */
    protected $logged_in;

    /** @var array */
    protected $parameters;

    /** @var UrlMatcher  */
    protected $matcher;

    public function __construct(Core $core, $logged_in) {
        $this->core = $core;
        $this->request = Request::createFromGlobals();
        $this->logged_in = $logged_in;

        $fileLocator = new FileLocator();
        $annotationLoader = new AnnotatedRouteLoader(new AnnotationReader());
        $loader = new AnnotationDirectoryLoader($fileLocator, $annotationLoader);
        $collection = $loader->load(realpath(__DIR__ . "/../controllers"));

        $this->matcher = new UrlMatcher($collection, new RequestContext());
        $this->parameters = $this->matcher->matchRequest($this->request);
    }

    public function run() {
        $this->loginCheck();

        $controllerName = $this->parameters['_controller'];
        $methodName = $this->parameters['_method'];

        if (in_array('semester', $this->parameters) && in_array('course', $this->parameters)) {
            $semester = $this->parameters['semester'];
            $course = $this->parameters['course'];
            $this->core->loadConfig($semester, $course);
        }

        $controller = new $controllerName($this->core);
        return $controller->$methodName();
    }

    private function loginCheck() {
        if (!$this->logged_in) {
            $this->request = Request::create(
                $this->core->buildNewUrl(['authentication', 'login']),
                'GET',
                ['old' => $this->request]
            );
            $this->parameters = $this->matcher->matchRequest($this->request);
        }
        elseif ($this->core->getUser() === null) {
            $this->core->loadSubmittyUser();
            if (!$this->endswith($this->parameters['_controller'], 'AuthenticationController')) {
                $this->request = Request::create(
                    $this->core->buildNewUrl(['navigation', 'no_access']),
                    'GET'
                );
                $this->parameters = $this->matcher->matchRequest($this->request);
            }
        }
        elseif ($this->core->getConfig()->isCourseLoaded()
            && !$this->core->getAccess()->canI("course.view", ["semester" => $this->core->getConfig()->getSemester(), "course" => $this->core->getConfig()->getCourse()])
            && !$this->endswith($this->parameters['_controller'], 'AuthenticationController')) {
            $this->request = Request::create(
                $this->core->buildNewUrl(['navigation', 'no_access']),
                'GET'
            );
            $this->parameters = $this->matcher->matchRequest($this->request);
        }

        // TODO: log

        if(!$this->core->getConfig()->isCourseLoaded()) {
            if ($this->logged_in){
                if (isset($this->parameters['_method']) && $this->parameters['_method'] === 'logout'){
                    $this->request = Request::create(
                        $this->core->buildNewUrl(['authentication', 'logout']),
                        'GET'
                    );
                    $this->parameters = $this->matcher->matchRequest($this->request);
                }
                else {
                    $this->request = Request::create(
                        $this->core->buildNewUrl(['home']),
                        'GET'
                    );
                    $this->parameters = $this->matcher->matchRequest($this->request);
                }
            }
            else {
                $this->request = Request::create(
                    $this->core->buildNewUrl(['authentication']),
                    'GET'
                );
                $this->parameters = $this->matcher->matchRequest($this->request);
            }
        }

        if (empty($this->parameters['_controller']) && $this->core->getUser() !== null) {
            if ($this->core->getConfig()->isCourseLoaded()) {
                $this->request = Request::create(
                    $this->core->buildNewUrl(['navigation']),
                    'GET'
                );
                $this->parameters = $this->matcher->matchRequest($this->request);
            }
            else {
                $this->request = Request::create(
                    $this->core->buildNewUrl(['home']),
                    'GET'
                );
                $this->parameters = $this->matcher->matchRequest($this->request);
            }
        }
    }

    private function endswith($string, $ending) {
        $str_len = strlen($string);
        $ending_len = strlen($ending);
        if ($ending_len > $str_len) return false;
        return substr_compare($string, $ending, $str_len - $ending_len, $ending_len) === 0;
    }
}