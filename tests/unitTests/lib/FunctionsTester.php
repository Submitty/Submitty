<?php

namespace tests\integrationTests\lib;

use \lib\Functions;
use \lib\FileUtils;

class FunctionsTester extends \PHPUnit_Framework_TestCase {
    public function testPad1() {
        $this->assertEquals("00", Functions::pad("0"));
    }

    public function testPad2() {
        $this->assertEquals("00", Functions::pad("00"));
    }
}