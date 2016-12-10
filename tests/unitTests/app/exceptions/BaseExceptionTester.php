<?php

namespace unitTests\app\exceptions;


use app\exceptions\BaseException;

class BaseExceptionTester extends \PHPUnit_Framework_TestCase {
    public function testException() {
        try {
            throw new BaseException("Some exception", "Details string");
        }
        catch (BaseException $exc) {
            $this->assertTrue($exc->logException());
            $this->assertFalse($exc->displayMessage());
            $this->assertEquals(array("extra_details" => "Details string"), $exc->getDetails());
        }
    }
}