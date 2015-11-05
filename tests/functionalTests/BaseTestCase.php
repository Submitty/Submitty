<?php

namespace e2e;

/**
 * Gives us a common setup to use for all e2e tests. This specifies an PHP_AUTH_USER to use,
 * what browsers to use and their session strategy.
 *
 * Class BaseTestCase
 * @package e2e
 */
class BaseTestCase extends \PHPUnit_Extensions_Selenium2TestCase {
    // For every browser, always specify sessionStrategy to be shared
    // as this causes one browser window per test class as opposed to
    // one browser window per test function (which is way slower)
    public static $browsers = array(
        array(
            'browserName' => 'firefox',
            'sessionStrategy' => 'shared'
        )
    );

    // Set the base url to be http://localhost/ which allows us to
    // use a shorthand to access the server
    protected function setUp() {
        $this->setBrowserUrl(__TEST_URL__);
    }

    public static function setUpBeforeClass() {
        $_SERVER['PHP_AUTH_USER'] = 'pevelm';
    }
}