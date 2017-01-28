<?php

namespace unitTests\app\exceptions;

use app\exceptions\IOException;

class IOExceptionTester extends \PHPUnit_Framework_TestCase {
    public function testIOException() {
        try {
            throw new IOException("exception");
        }
        catch (IOException $exc) {
            $this->assertEquals("exception", $exc->getMessage());
            $this->assertEmpty($exc->getDetails());
        }
    }
}