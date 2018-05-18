<?php

namespace tests\app\exceptions;


use app\exceptions\AuthenticationException;

class AuthenticationExceptionTester extends \PHPUnit\Framework\TestCase {
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
