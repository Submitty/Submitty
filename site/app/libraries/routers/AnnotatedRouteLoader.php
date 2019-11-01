<?php

namespace app\libraries\routers;

use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Route;

class AnnotatedRouteLoader extends AnnotationClassLoader {


    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, $annot) {
        $route->setDefault('_controller', $class->getName());
        $route->setDefault('_method', $method->getName());
    }
}
