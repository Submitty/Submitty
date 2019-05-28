<?php


namespace app\libraries;

use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;


class ApiRouter {
    protected $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function run() {
        $fileLocator = new FileLocator([dirname(__DIR__) . '/config']);

        $router = new Router(
            new YamlFileLoader($fileLocator),
            'api_routes.yaml'
        );

        $router->getRouteCollection()->addPrefix('api');

        try {
            $parameters = $router->matchRequest(Request::createFromGlobals());

            $controllerName = 'app\\controllers\\api\\' . $parameters['_controller'];
            $controller = new $controllerName($this->core);

            $controller->run();
        } catch (ResourceNotFoundException $e) {
            $this->core->getOutput()->renderJsonFail($e->getMessage());
        } catch (\Throwable $e) {
            $this->core->getOutput()->renderJsonError($e->getMessage());
        }

    }
}