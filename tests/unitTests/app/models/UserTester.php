<?php

namespace tests\unitTests\app\models;

use app\libraries\Core;
use app\models\User;

class UserTester extends \PHPUnit_Framework_TestCase {
    private $core;
    public function setUp() {
        $this->core = $this->createMock(Core::class);
    }

    public function testUserNoPreferred() {
        $details = array(
            'user_id' => "test",
            'anon_id' => "TestAnon",
            'user_password' => "test",
            'user_firstname' => "User",
            'user_preferred_firstname' => null,
            'user_lastname' => "Tester",
            'user_email' => "test@example.com",
            'user_group' => 1,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => "{1,2}"
        );
        $user = new User($this->core, $details);
        $this->assertEquals($details['user_id'], $user->getId());
        $this->assertEquals($details['anon_id'], $user->getAnonId());
        $this->assertEquals($details['user_firstname'], $user->getFirstName());
        $this->assertEquals($details['user_preferred_firstname'], $user->getPreferredFirstName());
        $this->assertEquals($details['user_firstname'], $user->getDisplayedFirstName());
        $this->assertEquals($details['user_lastname'], $user->getLastName());
        $this->assertEquals($details['user_email'], $user->getEmail());
        $this->assertEquals($details['user_group'], $user->getGroup());
        $this->assertEquals($details['registration_section'], $user->getRegistrationSection());
        $this->assertEquals($details['rotating_section'], $user->getRotatingSection());
        $this->assertEquals($details['manual_registration'], $user->isManualRegistration());
        $this->assertEquals(array(1,2), $user->getGradingRegistrationSections());
        $this->assertTrue($user->accessAdmin());
        $this->assertTrue($user->accessFullGrading());
        $this->assertTrue($user->accessGrading());
        $this->assertTrue($user->isLoaded());
        $this->assertFalse($user->isDeveloper());
    }

    public function testUserPreferred() {
        $details = array(
            'user_id' => "test",
            'anon_id' => "TestAnon",
            'user_firstname' => "User",
            'user_preferred_firstname' => "Paul",
            'user_lastname' => "Tester",
            'user_email' => "test@example.com",
            'user_group' => 1,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => "{1,2}"
        );
        $user = new User($this->core, $details);
        $this->assertEquals($details['user_id'], $user->getId());
        $this->assertEquals($details['anon_id'], $user->getAnonId());
        $this->assertEquals($details['user_firstname'], $user->getFirstName());
        $this->assertEquals($details['user_preferred_firstname'], $user->getPreferredFirstName());
        $this->assertEquals($details['user_preferred_firstname'], $user->getDisplayedFirstName());
        $this->assertEquals($details['user_lastname'], $user->getLastName());
    }

    public function testPassword() {
        $details = array(
            'user_id' => "test",
            'user_password' => "test",
            'user_firstname' => "User",
            'user_preferred_firstname' => null,
            'user_lastname' => "Tester",
            'user_email' => "test@example.com",
            'user_group' => 1,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => "{1,2}"
        );
        $user = new User($this->core, $details);
        $this->assertTrue(password_verify("test", $user->getPassword()));
        $user->setPassword("test");
        $hashed_password = password_hash("test", PASSWORD_DEFAULT);
        password_verify("test", $hashed_password);
        $user->setPassword($hashed_password);
        password_verify("test", $hashed_password);
    }

    public function testToObject() {
        $details = array(
            'user_id' => "test",
            'anon_id' => "TestAnonymous",
            'user_password' => "test",
            'user_firstname' => "User",
            'user_preferred_firstname' => null,
            'user_lastname' => "Tester",
            'user_email' => "test@example.com",
            'user_group' => 1,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => "{1,2}"
        );
        $user = new User($this->core, $details);
        $actual = $user->toArray();
        password_verify("test", $actual['password']);
        unset($actual['password']);
        ksort($actual);
        $expected = array(
            'displayed_first_name' => 'User',
            'email' => 'test@example.com',
            'first_name' => 'User',
            'grading_registration_sections' => array(1,2),
            'group' => 1,
            'id' => 'test',
            'last_name' => 'Tester',
            'loaded' => true,
            'manual_registration' => false,
            'preferred_first_name' => "",
            'registration_section' => 1,
            'rotating_section' => null,
            'modified' => true,
            'anon_id' => "TestAnonymous"
        );
        $this->assertEquals($expected, $actual);
    }

    public function testErrorUser() {
        $user = new User($this->core, array());
        $this->assertFalse($user->isLoaded());
        $this->assertNull($user->getId());
    }
}
