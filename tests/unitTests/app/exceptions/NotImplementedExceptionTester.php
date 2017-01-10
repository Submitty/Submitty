<?php

namespace unitTests\app\exceptions;

use app\exceptions\NotImplementedException;

class NotImplementedExceptionTester extends \PHPUnit_Framework_TestCase {
    public function testNotImplementedExceptionMessage() {
        try {
            throw new NotImplementedException("exception");
        }
        catch (NotImplementedException $exc) {
            $this->assertEquals("exception", $exc->getMessage());
        }
    }

    public function testNotImplementedExceptionNoMessage() {
        try {
            throw new NotImplementedException();
        }
        catch (NotImplementedException $exc) {
            $this->assertEquals("This is not implemented yet.", $exc->getMessage());
        }
    }
}