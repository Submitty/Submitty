<?php

namespace unitTests\app\exceptions;

use app\exceptions\IniException;

class IniExceptionTester extends \PHPUnit_Framework_TestCase {
    public function testIniException() {
        try {
            throw new IniException("exception");
        }
        catch (IniException $exc) {
            $this->assertEquals("exception", $exc->getMessage());
            $this->assertEmpty($exc->getDetails());
        }
    }
}