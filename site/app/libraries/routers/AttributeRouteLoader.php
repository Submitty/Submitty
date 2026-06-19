<?php

namespace app\libraries\routers;

use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;

class AttributeRouteLoader extends AttributeClassLoader {
    /**
     * @param Route $route
     * @param \ReflectionClass $class
     * @param \ReflectionMethod $method
     * @param mixed $annot
     * * @phpstan-ignore-next-line
     */
    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, $annot) {
        $route->setDefault('_controller', $class->getName());
        $route->setDefault('_method', $method->getName());
    }
}
