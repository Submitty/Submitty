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
            'user_firstname' => 'Tester',
            'user_lastname' => 'Test',
            'user_email' => null,
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'time_zone' => 'America/New_York'
        ]);
        $this->core->setUser($user);
        $this->core->setConfig($config);
        $sinceDateTime = new \DateTime('now', $this->core->getUser()->getUsableTimeZone());
        $sinceTimestamp = $sinceDateTime->format('m/d/Y h:i:s A T');
        $this->userDetails = [
            'user_id' => 'id',
            'user_firstname' => 'Alexander',
            'user_preferred_first' => '',
            'user_lastname' => 'Johnson',
            'allowed_late_days' => '2',
            'since_timestamp' => $sinceTimestamp,
            'late_day_exceptions' => '5'
        ];
    }

    public function testEmptyLateUser(): void {
        $user = new SimpleLateUser($this->core, []);

        $this->assertFalse($user->getLoaded());
        $this->assertNull($user->getId());
        $this->assertNull($user->getLegalFirstName());
        $this->assertNull($user->getLegalLastName());
        $this->assertNull($user->getDisplayedFirstName());
        $this->assertNull($user->getDisplayedLastName());
        $this->assertEmpty($user->getPreferredFirstName());
        $this->assertEmpty($user->getPreferredLastName());
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
        $this->assertEquals($this->userDetails['user_firstname'], $user->getLegalFirstName());
        $this->assertEquals($this->userDetails['user_lastname'], $user->getLegalLastName());
        // As we have not provided the preferred names, displayed name should be equal to legal name
        $this->assertEquals($this->userDetails['user_firstname'], $user->getDisplayedFirstName());
        $this->assertEquals($this->userDetails['user_lastname'], $user->getDisplayedLastName());
        // Preferred names should be empty...
        $this->assertEmpty($user->getPreferredFirstName());
        $this->assertEmpty($user->getPreferredLastName());
        $this->assertEquals($this->userDetails['allowed_late_days'], $user->getAllowedLateDays());
        $this->assertEquals($this->userDetails['since_timestamp'], $user->getSinceTimestamp());
    }

    public function testLateUserWithPrefNames(): void {
        // Add preferred names
        $this->userDetails['user_preferred_firstname'] = 'Alexa';
        $this->userDetails['user_preferred_lastname'] = 'John';
        $user = new SimpleLateUser($this->core, $this->userDetails);

        $this->assertEquals($this->userDetails['user_firstname'], $user->getLegalFirstName());
        $this->assertEquals($this->userDetails['user_preferred_firstname'], $user->getDisplayedFirstName());
        $this->assertEquals($this->userDetails['user_preferred_firstname'], $user->getPreferredFirstName());
        $this->assertEquals($this->userDetails['user_lastname'], $user->getLegalLastName());
        $this->assertEquals($this->userDetails['user_preferred_lastname'], $user->getDisplayedLastName());
        $this->assertEquals($this->userDetails['user_preferred_lastname'], $user->getPreferredLastName());
    }

    public function testLateUserMethods(): void {
        $user = new SimpleLateUser($this->core, $this->userDetails);
        $sinceDateTime = new \DateTime('now', $this->core->getUser()->getUsableTimeZone());
        $sinceTimestamp = $sinceDateTime->format('m/d/Y h:i:s A T');
        $newUserDetails = [
            'user_id' => 'id_updated',
            'user_firstname' => 'Alexander_updated',
            'user_preferred_firstname' => 'Alexa_updated',
            'user_lastname' => 'Johnson_updated',
            'user_preferred_lastname' => 'John_updated',
            'allowed_late_days' => "22",
            'since_timestamp' => $sinceTimestamp,
            'late_day_exceptions' => '55'
        ];
        // update the properties with the help of provided setters
        $user->setId($newUserDetails['user_id']);
        $user->setLegalFirstName($newUserDetails['user_firstname']);
        $user->setPreferredFirstName($newUserDetails['user_preferred_firstname']);
        $user->setDisplayedFirstName($newUserDetails['user_preferred_firstname']);
        $user->setLegalLastName($newUserDetails['user_lastname']);
        $user->setPreferredLastName($newUserDetails['user_preferred_lastname']);
        $user->setDisplayedLastName($newUserDetails['user_preferred_lastname']);
        $user->setAllowedLateDays($newUserDetails['allowed_late_days']);
        $user->setSinceTimestamp($newUserDetails['since_timestamp']);
        $user->setLateDayExceptions($newUserDetails['late_day_exceptions']);
        // Now check if the properties are updated correctly or not
        $this->assertEquals($newUserDetails['user_id'], $user->getId());
        $this->assertEquals($newUserDetails['user_firstname'], $user->getLegalFirstName());
        $this->assertEquals($newUserDetails['user_preferred_firstname'], $user->getDisplayedFirstName());
        $this->assertEquals($newUserDetails['user_preferred_firstname'], $user->getPreferredFirstName());
        $this->assertEquals($newUserDetails['user_lastname'], $user->getLegalLastName());
        $this->assertEquals($newUserDetails['user_preferred_lastname'], $user->getDisplayedLastName());
        $this->assertEquals($newUserDetails['user_preferred_lastname'], $user->getPreferredLastName());
        $this->assertEquals($newUserDetails['allowed_late_days'], $user->getAllowedLateDays());
        $this->assertEquals($newUserDetails['late_day_exceptions'], $user->getLateDayExceptions());
        // updating 'since_timestamp' only sets it to string and not proper datetime object
        // as in the case of creating a new SimpleLateUser object, which results in exception in getSinceTimestamp
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to a member function format() on string');
        $user->getSinceTimestamp();
    }
}
