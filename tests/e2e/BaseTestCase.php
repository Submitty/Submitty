<?php

namespace e2e;

/**
 * Gives us a common setup to use for all e2e tests
 *
 * Class BaseTestCase
 * @package e2e
 */
class BaseTestCase extends \PHPUnit_Extensions_Selenium2TestCase {
    protected function setUp() {
        $_SERVER['PHP_AUTH_USER'] = 'pevelm';
        $this->setBrowser('firefox');
        $this->setBrowserUrl("http://localhost/");
    }

    public static function setUpBeforeClass() {
        $_SERVER['PHP_AUTH_USER'] = 'pevelm';
    }
}