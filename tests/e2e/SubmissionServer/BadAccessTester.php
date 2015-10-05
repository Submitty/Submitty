<?php

namespace e2e\SubmissionServer;
use e2e\BaseTestCase;

/**
 * Test error screens when accessing the TAGradingServer
 *
 * Class BadAccessTester
 * @package e2e\SubmissionServer
 */
class BadAccessTester extends BaseTestCase {

    public static function setUpBeforeClass() {
        $_SERVER['PHP_AUTH_USER'] = 'invalid_user';
    }

    public function testNoCourse() {
        $this->url('TAGradingServer/account/index.php');
        $this->assertEquals("Error", $this->title());
        $this->assertEquals("Fatal Error: You must have course=#### in the URL bar",
                            $this->byId('message')->text());
    }

    public function testInvalidCourse() {
        $this->url('TAGradingServer/account/index.php?course=invalid');
        $this->assertEquals("Error", $this->title());
        $this->assertEquals("Fatal Error: The config for the specified course 'invalid' does not exist",
                            $this->byId('message')->text());
    }

    public function testInvalidUser() {
        $this->url('TAGradingServer/account/index.php?course=test_course');
        $this->assertEquals("Error", $this->title());
        $this->assertEquals("Unrecognized user: pevelm. Please contact an administrator to get an account.",
                            $this->byId('message')->text());
    }

}