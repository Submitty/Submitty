<?php

namespace app\libraries\routers;

use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;

class AttributeRouteLoader extends AttributeClassLoader {
    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $annot) {
        $route->setDefault('_controller', $class->getName());
        $route->setDefault('_method', $method->getName());
    }
}
