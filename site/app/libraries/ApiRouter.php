<?php


namespace app\libraries;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Bundle\FrameworkBundle\Routing\AnnotatedRouteControllerLoader;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\AnnotationException;


class ApiRouter {
    protected $core;

    public function __construct(Core $core) {
        $this->core = $core;

        // TODO: methods deprecated. need to find a workaround.
        $loader = require __DIR__.'/../../vendor/autoload.php';
        AnnotationRegistry::registerLoader([$loader, 'loadClass']);
    }

    public function run() {
        $fileLocator = new FileLocator();
        try {
            $annotationLoader = new AnnotatedRouteControllerLoader(new AnnotationReader());
        } catch (AnnotationException $e) {
            return $this->core->getOutput()->renderJsonError($e->getMessage());
        }

        $loader = new AnnotationDirectoryLoader($fileLocator, $annotationLoader);

        $collection = $loader->load(realpath(__DIR__ . "/../controllers/api"));
        $collection->addPrefix('api');

        $matcher = new UrlMatcher($collection, new RequestContext());

        try {
            $parameters = $matcher->matchRequest(Request::createFromGlobals());

            $methodInfo = explode("::", $parameters['_controller']);
            $controllerName = $methodInfo[0];
            $methodName = $methodInfo[1];

            $controller = new $controllerName($this->core);
            return $controller->$methodName();
        } catch (ResourceNotFoundException $e) {
            return $this->core->getOutput()->renderJsonFail($e->getMessage());
        } catch (\Throwable $e) {
            return $this->core->getOutput()->renderJsonError($e->getMessage());
        }

    }
}