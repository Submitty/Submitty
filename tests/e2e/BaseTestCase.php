<?php

namespace tests\e2e;

/**
 * Class BaseTestCase
 *
 * Base TestCase that all e2e tests should extend as it provides a common method for logging into and out of the site,
 * as well as some useful utility functions for running the tests. Any class that extends this class should at a
 * minimum override the class variables $user_id, $password, and $test_url for that particular test case. If a custom
 * setUp or tearDown routine is defined within the child class, it should also still call the parent class's function
 * so as to ensure that logging in and out still happens (unless that's not necessary for a particular test)
 */
class BaseTestCase extends \PHPUnit_Extensions_Selenium2TestCase {
    /* These variables should be overwritten */
    /** @var string user id to use for logging into the site */
    protected $user_id = null;
    /** @var string password to use for logging into the site */
    protected $password = null;
    /** @var string URL to use as the base for the tests */
    protected $test_url = __TEST_URL__;
    
    private $logged_in = false;
    
    // For every browser, always specify sessionStrategy to be shared
    // as this causes one browser window per test class as opposed to
    // one browser window per test function (which is way slower)
    public static $browsers = array(
        array(
            'browserName' => __BROWSER__,
            'sessionStrategy' => 'shared'
        )
    );

    public function logIn($user_id, $password) {
        $this->url("/index.php?semester=f16&course=csci1000");
        try {
            $this->byId("login-guest");
            $this->byName("user_id")->value($user_id);
            $this->byName("password")->value($password);
            $this->byName("login")->click();
        }
        catch (\PHPUnit_Extensions_Selenium2TestCase_WebDriverException $e) {
            // We're logged in already to the system, just ensure that we're logged in as the right user below
        }

        $time = $this->timeouts()->getLastImplicitWaitValue();
        $this->timeouts()->implicitWait(2500);
        try {
            $this->byId('login');
            $this->assertEquals($this->user_id, $this->byId('login-id')->text());
        }
        catch (\PHPUnit_Extensions_Selenium2TestCase_WebDriverException $e) {
            print(exec('whoami'));
            print($this->source());
            print($this->url());
        }
        $this->timeouts()->implicitWait($time);
        $this->logged_in = true;
    }

    /**
     * Log out of the site, making sure there's no open sessions (so to not affect the next test that gets
     * run which might need to be logged in as a different user). However, we only want to run this if we've
     * successfully logged in, else a WebDriverException will end up being thrown as the element won't be found.
     */
    public function logOut() {
        if ($this->logged_in) {
            $this->byId("logout")->click();
        }
    }

    /**
     * Function run before every test function, but we don't have access to session/selenium yet
     */
    public function setUp() {
        $this->setBrowserUrl($this->test_url);
    }

    /**
     * Generic set up method for the e2e tests. This function is run once after the session has been created
     * (so after setUp()) so that we can access the url() function.
     */
    public function setUpPage() {
        $this->logIn($this->user_id, $this->password);
    }

    /**
     * Function run after every test function, and we do have access to session/selenium details still
     */
    public function tearDown() {
        $this->logOut();
    }
    
    /**
     * This causes the selenium test to wait until the user hits enter on the PHP console that's running the test.
     * This shouldn't be used within any any actual tests, but can be useful for creating and debugging tests as it'll
     * cause the selenium window to pause indefinitely allowing for inspection of the HTML elements
     */
    protected function waitForUserInput() {
        if(trim(fgets(fopen("php://stdin","r"))) != chr(13)) {
            return;
        }
    }
}