<?php

namespace tests\lib;

use \lib\ServerException;

class ServerExceptionTester extends \PHPUnit_Framework_TestCase {
    public function testException() {
        $se = new ServerException("test");
        $this->assertEquals("ServerException: test\n", $se->__toString());
    }
}