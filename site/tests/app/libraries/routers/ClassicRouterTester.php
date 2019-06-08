<?php

namespace tests\app\libraries\routers;

use app\libraries\routers\ClassicRouter;

class ClassicRouterTester extends \PHPUnit\Framework\TestCase {
    public function testRouter() {
        $router = new ClassicRouter('part/part2/part3');
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
        $router = new ClassicRouter('');
        $this->assertFalse($router->hasNext());
        $this->assertNull($router->getNext());
    }
}