<?php

declare(strict_types=1);

namespace tests\app\models;

use app\libraries\Core;
use app\models\Config;
use app\models\SimpleLateUser;
use app\models\User;

class SimpleLateUserTester extends \PHPUnit\Framework\TestCase {
    /*
     * @var Core
     */
    private $core;
    /*
    * @var array
    */
    private $userDetails;

    public function setUp(): void {
        $this->core = new Core();
        $config = new Config($this->core);
        $user = new User($this->core, [
            'user_id' => 'test_user',
            'user_givenname' => 'Tester',
            'user_familyname' => 'Test',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => null,
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'time_zone' => 'America/New_York'
        ]);
        $this->core->setUser($user);
        $this->core->setConfig($config);
        $sinceDateTime = new \DateTime('now', $this->core->getUser()->getUsableTimeZone());
        $sinceTimestamp = $sinceDateTime->format('m/d/Y');
        $this->userDetails = [
            'user_id' => 'id',
            'user_givenname' => 'Alexander',
            'user_preferred_given' => '',
            'user_familyname' => 'Johnson',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'allowed_late_days' => '2',
            'since_timestamp' => $sinceTimestamp,
            'late_day_exceptions' => '5'
        ];
    }

    public function testEmptyLateUser(): void {
        $user = new SimpleLateUser($this->core, []);

        $this->assertFalse($user->getLoaded());
        $this->assertNull($user->getId());
        $this->assertNull($user->getLegalGivenName());
        $this->assertNull($user->getLegalFamilyName());
        $this->assertNull($user->getDisplayedGivenName());
        $this->assertNull($user->getDisplayedFamilyName());
        $this->assertEmpty($user->getPreferredGivenName());
        $this->assertEmpty($user->getPreferredFamilyName());
        $this->assertNull($user->getAllowedLateDays());
        $this->assertNull($user->getLateDayExceptions());
        // As 'since_timestamp' is null here, it results in exception in getSinceTimestamp
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to a member function format() on null');
        $user->getSinceTimestamp();
    }

    public function testNormalLateUser(): void {
        $user = new SimpleLateUser($this->core, $this->userDetails);

        $this->assertTrue($user->getLoaded());
        $this->assertEquals($this->userDetails['user_id'], $user->getId());
        $this->assertEquals($this->userDetails['user_givenname'], $user->getLegalGivenName());
        $this->assertEquals($this->userDetails['user_familyname'], $user->getLegalFamilyName());
        // As we have not provided the preferred names, displayed name should be equal to legal name
        $this->assertEquals($this->userDetails['user_givenname'], $user->getDisplayedGivenName());
        $this->assertEquals($this->userDetails['user_familyname'], $user->getDisplayedFamilyName());
        // Preferred names should be empty...
        $this->assertEmpty($user->getPreferredGivenName());
        $this->assertEmpty($user->getPreferredFamilyName());
        $this->assertEquals($this->userDetails['allowed_late_days'], $user->getAllowedLateDays());
        $this->assertEquals($this->userDetails['since_timestamp'], $user->getSinceTimestamp());
    }

    public function testLateUserWithPrefNames(): void {
        // Add preferred names
        $this->userDetails['user_preferred_givenname'] = 'Alexa';
        $this->userDetails['user_preferred_familyname'] = 'John';
        $user = new SimpleLateUser($this->core, $this->userDetails);

        $this->assertEquals($this->userDetails['user_givenname'], $user->getLegalGivenName());
        $this->assertEquals($this->userDetails['user_preferred_givenname'], $user->getDisplayedGivenName());
        $this->assertEquals($this->userDetails['user_preferred_givenname'], $user->getPreferredGivenName());
        $this->assertEquals($this->userDetails['user_familyname'], $user->getLegalFamilyName());
        $this->assertEquals($this->userDetails['user_preferred_familyname'], $user->getDisplayedFamilyName());
        $this->assertEquals($this->userDetails['user_preferred_familyname'], $user->getPreferredFamilyName());
    }

    public function testLateUserMethods(): void {
        $user = new SimpleLateUser($this->core, $this->userDetails);
        $sinceDateTime = new \DateTime('now', $this->core->getUser()->getUsableTimeZone());
        $sinceTimestamp = $sinceDateTime->format('m/d/Y');
        $newUserDetails = [
            'user_id' => 'id_updated',
            'user_givenname' => 'Alexander_updated',
            'user_preferred_givenname' => 'Alexa_updated',
            'user_familyname' => 'Johnson_updated',
            'user_preferred_familyname' => 'John_updated',
            'allowed_late_days' => "22",
            'since_timestamp' => $sinceTimestamp,
            'late_day_exceptions' => '55'
        ];
        // update the properties with the help of provided setters
        $user->setId($newUserDetails['user_id']);
        $user->setLegalGivenName($newUserDetails['user_givenname']);
        $user->setPreferredGivenName($newUserDetails['user_preferred_givenname']);
        $user->setDisplayedGivenName($newUserDetails['user_preferred_givenname']);
        $user->setLegalFamilyName($newUserDetails['user_familyname']);
        $user->setPreferredFamilyName($newUserDetails['user_preferred_familyname']);
        $user->setDisplayedFamilyName($newUserDetails['user_preferred_familyname']);
        $user->setAllowedLateDays($newUserDetails['allowed_late_days']);
        $user->setSinceTimestamp($newUserDetails['since_timestamp']);
        $user->setLateDayExceptions($newUserDetails['late_day_exceptions']);
        // Now check if the properties are updated correctly or not
        $this->assertEquals($newUserDetails['user_id'], $user->getId());
        $this->assertEquals($newUserDetails['user_givenname'], $user->getLegalGivenName());
        $this->assertEquals($newUserDetails['user_preferred_givenname'], $user->getDisplayedGivenName());
        $this->assertEquals($newUserDetails['user_preferred_givenname'], $user->getPreferredGivenName());
        $this->assertEquals($newUserDetails['user_familyname'], $user->getLegalFamilyName());
        $this->assertEquals($newUserDetails['user_preferred_familyname'], $user->getDisplayedFamilyName());
        $this->assertEquals($newUserDetails['user_preferred_familyname'], $user->getPreferredFamilyName());
        $this->assertEquals($newUserDetails['allowed_late_days'], $user->getAllowedLateDays());
        $this->assertEquals($newUserDetails['late_day_exceptions'], $user->getLateDayExceptions());
        // updating 'since_timestamp' only sets it to string and not proper datetime object
        // as in the case of creating a new SimpleLateUser object, which results in exception in getSinceTimestamp
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to a member function format() on string');
        $user->getSinceTimestamp();
    }
}
