<?php

namespace unitTests\app\exceptions;


use app\exceptions\AuthenticationException;

class AuthenticationExceptionTester extends \PHPUnit_Framework_TestCase {
    public function testAuthenticationException() {
        try {
            throw new AuthenticationException("exception");
        }
        catch (AuthenticationException $exc) {
            $this->assertEquals("exception", $exc->getMessage());
            $this->assertEmpty($exc->getDetails());
        }
    }
}