<?php

namespace e2e;

use tests\e2e\BaseTestCase;

class LoginTester extends BaseTestCase {
    protected $user_id = "student";
    protected $password = "student";
    
    /**
     * Test that when trying to access URL on the site when not logged in, you are taken to the login page, and then
     * after logging in are taken to the original URL you tried to access.
     */
    public function testLoginRedirect() {
        $this->tearDown();
        $url_string = "/index.php?semester=f16&course=csci1000&component=student";
        $this->url($url_string);
        $this->byId("login-guest");
        $this->byName("user_id")->value($this->user_id);
        $this->byName("password")->value($this->password);
        $this->byName("login")->click();
        $this->assertEquals($this->user_id, $this->byId('login-id')->text());
        $this->assertEquals($this->test_url.$url_string, $this->url());
        //$this->assertEquals("No gradeable id specified. Contact your instructor if you think this is an error.", $this->byClassName("content")->text());
    }
}