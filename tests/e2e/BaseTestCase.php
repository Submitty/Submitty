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
    protected $test_url = null;
    
    private $logged_in = false;
    
    // For every browser, always specify sessionStrategy to be shared
    // as this causes one browser window per test class as opposed to
    // one browser window per test function (which is way slower)
    public static $browsers = array(
        array(
            'browserName' => 'phantomjs',
            //'browserName' => 'firefox',
            'sessionStrategy' => 'shared'
        )
    );
    
    public function setUp() {
        $this->setBrowserUrl($this->test_url);
    }
    
    /**
     * Generic set up method for the e2e tests. This function is run once after the session has been created
     */
    public function setUpPage() {
        $this->url("/index.php?semester=f16&course=csci1000");
        try {
            $this->byId("login-guest");
            $this->byName("user_id")->value($this->user_id);
            $this->byName("password")->value($this->password);
            $this->byName("login")->click();
        }
        catch (\PHPUnit_Extensions_Selenium2TestCase_WebDriverException $e) {
            // We're logged in already to the system, just ensure that we're logged in as the right user below
        }
        
        $time = $this->timeouts()->getLastImplicitWaitValue();
        $this->timeouts()->implicitWait(2500);
        $this->byId('login');
        $this->assertEquals($this->user_id, $this->byId('login-id')->text());
        
        $this->timeouts()->implicitWait($time);
        $this->logged_in = true;
    }
    
    /**
     * Log out of the site, making sure there's no open sessions (so to not affect the next test that gets
     * run which might need to be logged in as a different user). However, we need to make sure we're actually
     * logged into the system (from the setup method), else we shouldn't bother trying to logout
     */
    public function tearDown() {
        if ($this->logged_in) {
            $this->byId("logout")->click();
        }
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