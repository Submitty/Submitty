<?php

namespace tests\app\models;

use app\exceptions\ValidationException;
use app\libraries\Core;
use app\models\User;

class UserTester extends \PHPUnit\Framework\TestCase {
    private $core;
    public function setUp(): void {
        $this->core = $this->createMock(Core::class);
    }

    public function testUserNoPreferred() {
        $details = [
            'user_id' => "test",
            'anon_id' => "TestAnon",
            'user_numeric_id' => '123456789',
            'user_password' => "test",
            'user_firstname' => "User",
            'user_preferred_firstname' => null,
            'user_lastname' => "Tester",
            'user_preferred_lastname' => null,
            'user_email' => "test@example.com",
            'user_group' => User::GROUP_INSTRUCTOR,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];
        $user = new User($this->core, $details);
        $this->assertEquals($details['user_id'], $user->getId());
        $this->assertEquals($details['anon_id'], $user->getAnonId());
        $this->assertEquals($details['user_numeric_id'], $user->getNumericId());
        $this->assertEquals($details['user_firstname'], $user->getLegalFirstName());
        $this->assertEquals($details['user_preferred_firstname'], $user->getPreferredFirstName());
        $this->assertEquals($details['user_firstname'], $user->getDisplayedFirstName());
        $this->assertEquals($details['user_preferred_lastname'], $user->getPreferredLastName());
        $this->assertEquals($details['user_lastname'], $user->getLegalLastName());
        $this->assertEquals($details['user_email'], $user->getEmail());
        $this->assertEquals($details['user_group'], $user->getGroup());
        $this->assertEquals($details['registration_section'], $user->getRegistrationSection());
        $this->assertEquals($details['rotating_section'], $user->getRotatingSection());
        $this->assertEquals($details['manual_registration'], $user->isManualRegistration());
        $this->assertEquals([1,2], $user->getGradingRegistrationSections());
        $this->assertTrue($user->accessAdmin());
        $this->assertTrue($user->accessFullGrading());
        $this->assertTrue($user->accessGrading());
        $this->assertTrue($user->isLoaded());
    }

    public function testUserPreferred() {
        $details = [
            'user_id' => "test",
            'anon_id' => "TestAnon",
            'user_numeric_id' => '123456789',
            'user_firstname' => "User",
            'user_preferred_firstname' => "Paul",
            'user_lastname' => "Tester",
            'user_preferred_lastname' => "Bunyan",
            'user_email' => "test@example.com",
            'user_group' => User::GROUP_INSTRUCTOR,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1,2]
        ];
        $user = new User($this->core, $details);
        $this->assertEquals($details['user_id'], $user->getId());
        $this->assertEquals($details['anon_id'], $user->getAnonId());
        $this->assertEquals($details['user_numeric_id'], $user->getNumericId());
        $this->assertEquals($details['user_firstname'], $user->getLegalFirstName());
        $this->assertEquals($details['user_preferred_firstname'], $user->getPreferredFirstName());
        $this->assertEquals($details['user_preferred_firstname'], $user->getDisplayedFirstName());
        $this->assertEquals($details['user_lastname'], $user->getLegalLastName());
        $this->assertEquals($details['user_preferred_lastname'], $user->getPreferredLastName());
        $this->assertEquals($details['user_preferred_lastname'], $user->getDisplayedLastName());
    }

    public function testPassword() {
        $details = [
            'user_id' => "test",
            'user_numeric_id' => "123456789",
            'user_password' => "test",
            'user_firstname' => "User",
            'user_preferred_firstname' => null,
            'user_lastname' => "Tester",
            'user_preferred_lastname' => null,
            'user_email' => "test@example.com",
            'user_group' => User::GROUP_INSTRUCTOR,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1,2]
        ];
        $user = new User($this->core, $details);
        $this->assertTrue(password_verify("test", $user->getPassword()));
        $user->setPassword("test1");
        $this->assertTrue(password_verify("test1", $user->getPassword()));
        $user->setPassword(password_hash("test2", PASSWORD_DEFAULT));
        $this->assertTrue(password_verify("test2", $user->getPassword()));
    }

    public function testToObject() {
        $details = [
            'user_id' => "test",
            'anon_id' => "TestAnonymous",
            'user_numeric_id' => '123456789',
            'user_password' => "test",
            'user_firstname' => "User",
            'user_preferred_firstname' => null,
            'user_lastname' => "Tester",
            'user_preferred_lastname' => null,
            'user_email' => "test@example.com",
            'user_group' => User::GROUP_INSTRUCTOR,
            'user_access_level' => User::LEVEL_FACULTY,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1,2]
        ];
        $user = new User($this->core, $details);
        $actual = $user->toArray();
        password_verify("test", $actual['password']);
        unset($actual['password']);
        ksort($actual);
        $expected = [
            'displayed_first_name' => 'User',
            'displayed_last_name' => 'Tester',
            'email' => 'test@example.com',
            'legal_first_name' => 'User',
            'grading_registration_sections' => [1,2],
            'group' => User::GROUP_INSTRUCTOR,
            'access_level' => User::LEVEL_FACULTY,
            'id' => 'test',
            'legal_last_name' => 'Tester',
            'loaded' => true,
            'manual_registration' => false,
            'preferred_first_name' => "",
            'preferred_last_name' => "",
            'numeric_id' => '123456789',
            'registration_section' => 1,
            'rotating_section' => null,
            'modified' => true,
            'anon_id' => "TestAnonymous",
            'user_updated' => false,
            'instructor_updated' => false,
            'display_image_state' => null,
            'notification_settings' => [
                'reply_in_post_thread' => false,
                'merge_threads' => false,
                'all_new_threads' => false,
                'all_new_posts' => false,
                'all_modifications_forum' => false,
                'team_invite' => true,
                'team_joined' => true,
                'team_member_submission' => true,
                'self_notification' => false,
                'reply_in_post_thread_email' => false,
                'merge_threads_email' => false,
                'all_new_threads_email' => false,
                'all_new_posts_email' => false,
                'all_modifications_forum_email' => false,
                'team_invite_email' => true,
                'team_joined_email' => true,
                'team_member_submission_email' => true,
                'self_notification_email' => false
            ],
            'registration_subsection' => null
        ];
        $this->assertEquals($expected, $actual);
    }

    public function testErrorUser() {
        $user = new User($this->core, []);
        $this->assertFalse($user->isLoaded());
        $this->assertNull($user->getId());
    }

    public function testGetNiceFormatTimeZoneExplicitlySet() {
        $user = new User($this->core, [
            'user_id' => 'test',
            'user_firstname' => 'test',
            'user_lastname' => 'test',
            'user_email' => 'user@email.com',
            'time_zone' => 'NOT_SET/NOT_SET'
        ]);
        $this->assertEquals('NOT SET', $user->getNiceFormatTimeZone());
    }

    public function testGetUTCOffsetExplicitlySet() {
        $user = new User($this->core, [
            'user_id' => 'test',
            'user_firstname' => 'test',
            'user_lastname' => 'test',
            'user_email' => 'user@email.com',
            'time_zone' => 'NOT_SET/NOT_SET'
        ]);
        $this->assertEquals('NOT SET', $user->getUTCOffset());
    }


    public function validateUserDataProvider(): array {
        $return = [
            ['user_id', 'test', true],
            ['user_id', 'system_user-1', true],
            ['user_id', 'te#t', false],
            ['user_email', '', true],
            ['user_email', 'pevelm@rpi.edu', true],
            ['user_email', 'student@faculty.university-of-xy.edu', true],
            ['user_email', '_______@example.com', true],
            ['user_email', 'firstname-lastname@example.com', true],
            ['user_email', 'invalid', false],
            ['user_email', '@example.com', false],
            ['user_email', 'Abc..123@example.com', false],
            ['user_group', '0', false],
            ['user_group', '1', true],
            ['user_group', '2', true],
            ['user_group', '3', true],
            ['user_group', '4', true],
            ['user_group', '5', false],
            ['registration_section', null, true],
            ['registration_section', 'test', true],
            ['registration_section', '1', true],
            ['registration_section', 'section-1', true],
            ['registration_section', 'section 1', false],
            ['registration_section', 'Section_1-2', true],
            ['user_password', '', false],
            ['user_password', 'test', true],
        ];

        foreach (['firstname', 'lastname'] as $key) {
            $return[] = ["user_legal_{$key}", '', false];
            $return[] = ["user_legal_{$key}", 'Test', true];
            $return[] = ["user_legal_{$key}", "Test-Phil Mc'Duffy Sr.", true];
            $return[] = ["user_legal_{$key}", 'Test!!', false];
            $return[] = ["user_legal_{$key}", "A very long name that goes on for a long time and uses a lot of characters and holy smokes what a name it just keeps going", true];
            $return[] = ["user_preferred_{$key}", '', true];
            $return[] = ["user_preferred_{$key}", 'Test', true];
            $return[] = ["user_preferred_{$key}", "Test-Phil Mc'Duffy Sr.", true];
            $return[] = ["user_preferred_{$key}", 'Test!!', false];
            $return[] = ["user_preferred_{$key}", "A very long name that goes on for a long time and uses a lot of characters and holy smokes what a name it just keeps going", false];
        }

        return $return;
    }

    /**
     * @dataProvider validateUserDataProvider
     */
    public function testValidateUserData(string $field, ?string $value, bool $expected): void {
        $this->assertSame($expected, User::validateUserData($field, $value));
    }

    public function testInvalidFieldForValidate(): void {
        try {
            User::validateUserData('invalid_field', 'blah');
            $this->fail('ValidationException should have been thrown');
        }
        catch (ValidationException $exc) {
            $this->assertSame('User::validateUserData() called with unknown $field.  See extra details, below.', $exc->getMessage());
            $this->assertSame(['$field: \'invalid_field\'', '$data: \'blah\''], $exc->getDetails());
        }
    }
}
