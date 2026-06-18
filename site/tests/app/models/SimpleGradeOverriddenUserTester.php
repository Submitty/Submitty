<?php

declare(strict_types=1);

namespace tests\app\models;

use app\libraries\Core;
use app\models\SimpleGradeOverriddenUser;

class SimpleGradeOverriddenUserTester extends \PHPUnit\Framework\TestCase {
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
        $this->userDetails = [
            'user_id' => 'id',
            'user_givenname' => 'Alexander',
            'user_preferred_givenname' => null,
            'user_familyname' => 'Johnson',
            'marks' => 110,
            'comment' => 'Out of this world!'
        ];
    }

    public function testEmptyGradeOverUser(): void {
        $user = new SimpleGradeOverriddenUser($this->core, []);

        $this->assertFalse($user->getLoaded());
        $this->assertNull($user->getId());
        $this->assertNull($user->getLegalGivenName());
        $this->assertNull($user->getLegalFamilyName());
        $this->assertNull($user->getDisplayedGivenName());
        $this->assertNull($user->getDisplayedFamilyName());
        $this->assertEmpty($user->getPreferredGivenName());
        $this->assertEmpty($user->getPreferredFamilyName());
        $this->assertNull($user->getMarks());
        $this->assertNull($user->getComment());
    }

    public function testNormalGradeOverUser(): void {
        $user = new SimpleGradeOverriddenUser($this->core, $this->userDetails);

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
        $this->assertEquals($this->userDetails['marks'], $user->getMarks());
        $this->assertEquals($this->userDetails['comment'], $user->getComment());
    }

    public function testGradeOverUserWithPrefNames(): void {
        // Add preferred names
        $this->userDetails['user_preferred_givenname'] = 'Alexa';
        $this->userDetails['user_preferred_familyname'] = 'John';
        $user = new SimpleGradeOverriddenUser($this->core, $this->userDetails);

        $this->assertEquals($this->userDetails['user_givenname'], $user->getLegalGivenName());
        $this->assertEquals($this->userDetails['user_preferred_givenname'], $user->getDisplayedGivenName());
        $this->assertEquals($this->userDetails['user_preferred_givenname'], $user->getPreferredGivenName());
        $this->assertEquals($this->userDetails['user_familyname'], $user->getLegalFamilyName());
        $this->assertEquals($this->userDetails['user_preferred_familyname'], $user->getDisplayedFamilyName());
        $this->assertEquals($this->userDetails['user_preferred_familyname'], $user->getPreferredFamilyName());
    }

    public function testGradeOverUserMethods(): void {
        $user = new SimpleGradeOverriddenUser($this->core, $this->userDetails);

        $newUserDetails = [
            'user_id' => 'id_updated',
            'user_givenname' => 'Alexander_updated',
            'user_preferred_givenname' => 'Alexa_updated',
            'user_familyname' => 'Johnson_updated',
            'user_preferred_familyname' => 'John_updated',
            'marks' => 85,
            'comment' => 'Great Work...'
        ];
        // update the properties with the help of provided setters
        $user->setId($newUserDetails['user_id']);
        $user->setLegalGivenName($newUserDetails['user_givenname']);
        $user->setPreferredGivenName($newUserDetails['user_preferred_givenname']);
        $user->setDisplayedGivenName($newUserDetails['user_preferred_givenname']);
        $user->setLegalFamilyName($newUserDetails['user_familyname']);
        $user->setPreferredFamilyName($newUserDetails['user_preferred_familyname']);
        $user->setDisplayedFamilyName($newUserDetails['user_preferred_familyname']);
        $user->setMarks($newUserDetails['marks']);
        $user->setComment($newUserDetails['comment']);

        // Now check if the properties are updated correctly or not
        $this->assertEquals($newUserDetails['user_id'], $user->getId());
        $this->assertEquals($newUserDetails['user_givenname'], $user->getLegalGivenName());
        $this->assertEquals($newUserDetails['user_preferred_givenname'], $user->getDisplayedGivenName());
        $this->assertEquals($newUserDetails['user_preferred_givenname'], $user->getPreferredGivenName());
        $this->assertEquals($newUserDetails['user_familyname'], $user->getLegalFamilyName());
        $this->assertEquals($newUserDetails['user_preferred_familyname'], $user->getDisplayedFamilyName());
        $this->assertEquals($newUserDetails['user_preferred_familyname'], $user->getPreferredFamilyName());
        $this->assertEquals($newUserDetails['marks'], $user->getMarks());
        $this->assertEquals($newUserDetails['comment'], $user->getComment());
    }
}
