<?php


namespace app\libraries;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationException;


class MainRouter {
    /** @var Core  */
    protected $core;

    /** @var Request  */
    protected $request;

    public function __construct(Core $core) {
        $this->core = $core;
        $this->request = Request::createFromGlobals();
    }

    public function run() {
        $fileLocator = new FileLocator();
//        try {
            $annotationLoader = new AnnotatedRouteLoader(new AnnotationReader());
//        } catch (AnnotationException $e) {
//            return $this->core->getOutput()->showError($e->getMessage());
//        }

        $loader = new AnnotationDirectoryLoader($fileLocator, $annotationLoader);

        $collection = $loader->load(realpath(__DIR__ . "/../controllers"));

        $matcher = new UrlMatcher($collection, new RequestContext());

//        try {
            $parameters = $matcher->matchRequest($this->request);
            $controllerName = $parameters['_controller'];
            $methodName = $parameters['_method'];

            $controller = new $controllerName($this->core);
            return $controller->$methodName();
//        } catch (ResourceNotFoundException $e) {
//            return $this->core->getOutput()->showError($e->getMessage());
//        } catch (\Throwable $e) {
//            return $this->core->getOutput()->showException($e->getMessage());
//        }

    }
}