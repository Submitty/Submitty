<?php

namespace tests\app\libraries;

use app\libraries\Router;

class RouterTester {
    public function testRouter() {
        $router = new Router('part/part2/part3');
        $expected = ['part', 'part2', 'part3'];
        $actual = [];
        while ($router->hasNext()) {
            $actual[] = $router->getNext();
        }

        $this->assertEquals($expected, $actual);
        $this->assertNull($router->getNext());
        $this->assertFalse($router->hasNext());
    }

    public function testEmptyRouter() {
        $router = new Router();
        $this->assertFalse($router->hasNext());
        $this->assertNull($router->getNext());
    }
}