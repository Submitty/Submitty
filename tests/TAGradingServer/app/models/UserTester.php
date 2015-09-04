<?php

namespace tests\app\models;

use app\models\User;

class UserTester extends \PHPUnit_Framework_TestCase {
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidUser() {
        User::loadUser('fake user');
    }
    
    public function testBasicUser() {
        User::loadUser('user');
        $this->assertNotEmpty(User::$user_details);
        $this->assertFalse(User::$is_developer);
        $this->assertFalse(User::$is_administrator);
    }
    
    public function testAdministratorUser() {
        User::loadUser('admin');
        $this->assertNotEmpty(User::$user_details);
        $this->assertFalse(User::$is_developer);
        $this->assertTrue(User::$is_administrator);
    }
    
    public function testDeveloperUser() {
        User::loadUser('developer');
        $this->assertNotEmpty(User::$user_details);
        $this->assertTrue(User::$is_developer);
        $this->assertTrue(User::$is_administrator);
    }
}