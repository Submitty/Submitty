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
            'user_numeric_id' => '123456789',
            'user_password' => "test",
            'user_givenname' => "User",
            'user_preferred_givenname' => null,
            'user_familyname' => "Tester",
            'user_preferred_familyname' => null,
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "test@example.com",
            'user_email_secondary' => "test@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_INSTRUCTOR,
            'registration_section' => 1,
            'course_section_id' => null,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];
        $user = new User($this->core, $details);
        $this->assertEquals($details['user_id'], $user->getId());
        $this->assertEquals($details['user_numeric_id'], $user->getNumericId());
        $this->assertEquals($details['user_givenname'], $user->getLegalGivenName());
        $this->assertEquals($details['user_preferred_givenname'], $user->getPreferredGivenName());
        $this->assertEquals($details['user_givenname'], $user->getDisplayedGivenName());
        $this->assertEquals($details['user_preferred_familyname'], $user->getPreferredFamilyName());
        $this->assertEquals($details['user_familyname'], $user->getLegalFamilyName());
        $this->assertEquals($details['user_email'], $user->getEmail());
        $this->assertEquals($details['user_group'], $user->getGroup());
        $this->assertEquals($details['registration_section'], $user->getRegistrationSection());
        $this->assertEquals($details['course_section_id'], $user->getCourseSectionId());
        $this->assertEquals($details['rotating_section'], $user->getRotatingSection());
        $this->assertEquals($details['manual_registration'], $user->isManualRegistration());
        $this->assertEquals([1,2], $user->getGradingRegistrationSections());
        $this->assertEquals('staff', $user->getRegistrationType());
        $this->assertTrue($user->accessAdmin());
        $this->assertTrue($user->accessFullGrading());
        $this->assertTrue($user->accessGrading());
        $this->assertTrue($user->isLoaded());
    }

    public function testUserPreferred() {
        $details = [
            'user_id' => "test",
            'user_numeric_id' => '123456789',
            'user_givenname' => "User",
            'user_preferred_givenname' => "Paul",
            'user_familyname' => "Tester",
            'user_preferred_familyname' => "Bunyan",
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "test@example.com",
            'user_email_secondary' => "test@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_INSTRUCTOR,
            'registration_section' => 1,
            'course_section_id' => null,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1,2],
            'registration_type' => 'audit',
        ];
        $user = new User($this->core, $details);
        $this->assertEquals($details['user_id'], $user->getId());
        $this->assertEquals($details['user_numeric_id'], $user->getNumericId());
        $this->assertEquals($details['user_givenname'], $user->getLegalGivenName());
        $this->assertEquals($details['user_preferred_givenname'], $user->getPreferredGivenName());
        $this->assertEquals($details['user_preferred_givenname'], $user->getDisplayedGivenName());
        $this->assertEquals($details['user_familyname'], $user->getLegalFamilyName());
        $this->assertEquals($details['user_preferred_familyname'], $user->getPreferredFamilyName());
        $this->assertEquals($details['user_preferred_familyname'], $user->getDisplayedFamilyName());
        $this->assertEquals($details['registration_type'], $user->getRegistrationType());
    }

    public function testPassword() {
        $details = [
            'user_id' => "test",
            'user_numeric_id' => "123456789",
            'user_password' => "test",
            'user_givenname' => "User",
            'user_preferred_givenname' => null,
            'user_familyname' => "Tester",
            'user_preferred_familyname' => null,
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "test@example.com",
            'user_email_secondary' => "test@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_INSTRUCTOR,
            'registration_section' => 1,
            'course_section_id' => null,
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
            'user_numeric_id' => '123456789',
            'user_password' => "test",
            'user_givenname' => "User",
            'user_preferred_givenname' => null,
            'user_familyname' => "Tester",
            'user_preferred_familyname' => null,
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "test@example.com",
            'user_email_secondary' => "test@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_INSTRUCTOR,
            'user_access_level' => User::LEVEL_FACULTY,
            'registration_section' => 1,
            'course_section_id' => null,
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
            'displayed_given_name' => 'User',
            'displayed_family_name' => 'Tester',
            'pronouns' => '',
            'display_pronouns' => false,
            'email' => 'test@example.com',
            'secondary_email' => "test@exampletwo.com",
            'email_both' => false,
            'legal_given_name' => 'User',
            'last_initial_format' => 0,
            'display_name_order' => 'GIVEN_F',
            'grading_registration_sections' => [1,2],
            'group' => User::GROUP_INSTRUCTOR,
            'access_level' => User::LEVEL_FACULTY,
            'id' => 'test',
            'legal_family_name' => 'Tester',
            'loaded' => true,
            'manual_registration' => false,
            'preferred_given_name' => "",
            'preferred_family_name' => "",
            'numeric_id' => '123456789',
            'preferred_locale' => null,
            'registration_section' => 1,
            'registration_type' => 'staff',
            'course_section_id' => null,
            'rotating_section' => null,
            'modified' => true,
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
                'self_notification_email' => false,
                'self_registration_email' => true,
            ],
            'registration_subsection' => '',
            'enforce_single_session' => false
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
            'user_givenname' => 'test',
            'user_familyname' => 'test',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => 'user@email.com',
            'user_email_secondary' => "test@exampletwo.com",
            'user_email_secondary_notify' => false,
            'time_zone' => 'NOT_SET/NOT_SET'
        ]);
        $this->assertEquals('NOT SET', $user->getNiceFormatTimeZone());
    }

    public function testGetUTCOffsetExplicitlySet() {
        $user = new User($this->core, [
            'user_id' => 'test',
            'user_givenname' => 'test',
            'user_familyname' => 'test',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => 'user@email.com',
            'user_email_secondary' => "test@exampletwo.com",
            'user_email_secondary_notify' => false,
            'time_zone' => 'NOT_SET/NOT_SET'
        ]);
        $this->assertEquals('NOT SET', $user->getUTCOffset());
    }

    public function testLastInitialFormat() {
        $formats = [ 'John S.', 'John S.W.', 'John S-J.W.', 'John' ];
        foreach ($formats as $format => $expected) {
            $user = new User($this->core, [
                'user_id' => 'test',
                'user_givenname' => 'John',
                'user_familyname' => 'Smith-Jones Warren',
                'user_pronouns' => '',
                'display_pronouns' => false,
                'user_email' => 'user@email.com',
                'user_email_secondary' => 'test@exampletwo.com',
                'user_email_secondary_notify' => false,
                'user_last_initial_format' => $format
            ]);
            $this->assertEquals($expected, $user->getDisplayAbbreviatedName());
        }
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
            ['user_email', 'givenname-familyname@example.com', true],
            ['user_email', 'invalid', false],
            ['user_email', '@example.com', false],
            ['user_email', 'Abc..123@example.com', false],
            ['user_email_secondary', '', true],
            ['user_email_secondary', 'pevelm@rpi.edu', true],
            ['user_email_secondary', 'student@faculty.university-of-xy.edu', true],
            ['user_email_secondary', '_______@example.com', true],
            ['user_email_secondary', 'givenname-familyname@example.com', true],
            ['user_email_secondary', 'invalid', false],
            ['user_email_secondary', '@example.com', false],
            ['user_email_secondary', 'Abc..123@example.com', false],
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

        foreach (['givenname', 'familyname'] as $key) {
            $return[] = ["user_legal_{$key}", '', false];
            $return[] = ["user_legal_{$key}", 'Test', true];
            $return[] = ["user_legal_{$key}", "Test-Phil Mc'Duffy Sr.", true];
            $return[] = ["user_legal_{$key}", 'Báiñø', true];
            $return[] = ["user_legal_{$key}", 'Test!!', false];
            $return[] = ["user_legal_{$key}", "A very long name that goes on for a long time and uses a lot of characters and holy smokes what a name it just keeps going", true];
            $return[] = ["user_preferred_{$key}", '', true];
            $return[] = ["user_preferred_{$key}", 'Test', true];
            $return[] = ["user_preferred_{$key}", "Test-Phil Mc'Duffy Sr.", true];
            $return[] = ["user_preferred_{$key}", 'Báiñø', true];
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
