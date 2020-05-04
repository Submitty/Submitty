<?php

declare(strict_types=1);

namespace app\libraries\routers;

class RouteCollection extends \Symfony\Component\Routing\RouteCollection {
    public function addCollection(\Symfony\Component\Routing\RouteCollection $collection) {
        foreach (array_keys($collection->all()) as $name) {
            if ($this->get($name) !== null) {
                $collection->remove($name);
            }
        }
        parent::addCollection($collection);
    }
}
