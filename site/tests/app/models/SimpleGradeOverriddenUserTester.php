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
            'user_firstname' => 'Alexander',
            'user_preferred_firstname' => '',
            'user_lastname' => 'Johnson',
            'marks' => 110,
            'comment' => 'Out of this world!'
        ];
    }

    public function testEmptyGradeOverUser(): void {
        $user = new SimpleGradeOverriddenUser($this->core, []);

        $this->assertFalse($user->getLoaded());
        $this->assertNull($user->getId());
        $this->assertNull($user->getLegalFirstName());
        $this->assertNull($user->getLegalLastName());
        $this->assertNull($user->getDisplayedFirstName());
        $this->assertNull($user->getDisplayedLastName());
        $this->assertEmpty($user->getPreferredFirstName());
        $this->assertEmpty($user->getPreferredLastName());
        $this->assertNull($user->getMarks());
        $this->assertNull($user->getComment());
    }

    public function testNormalGradeOverUser(): void {
        $user = new SimpleGradeOverriddenUser($this->core, $this->userDetails);

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
        $this->assertEquals($this->userDetails['marks'], $user->getMarks());
        $this->assertEquals($this->userDetails['comment'], $user->getComment());
    }

    public function testGradeOverUserWithPrefNames(): void {
        // Add preferred names
        $this->userDetails['user_preferred_firstname'] = 'Alexa';
        $this->userDetails['user_preferred_lastname'] = 'John';
        $user = new SimpleGradeOverriddenUser($this->core, $this->userDetails);

        $this->assertEquals($this->userDetails['user_firstname'], $user->getLegalFirstName());
        $this->assertEquals($this->userDetails['user_preferred_firstname'], $user->getDisplayedFirstName());
        $this->assertEquals($this->userDetails['user_preferred_firstname'], $user->getPreferredFirstName());
        $this->assertEquals($this->userDetails['user_lastname'], $user->getLegalLastName());
        $this->assertEquals($this->userDetails['user_preferred_lastname'], $user->getDisplayedLastName());
        $this->assertEquals($this->userDetails['user_preferred_lastname'], $user->getPreferredLastName());
    }

    public function testGradeOverUserMethods(): void {
        $user = new SimpleGradeOverriddenUser($this->core, $this->userDetails);

        $newUserDetails = [
            'user_id' => 'id_updated',
            'user_firstname' => 'Alexander_updated',
            'user_preferred_firstname' => 'Alexa_updated',
            'user_lastname' => 'Johnson_updated',
            'user_preferred_lastname' => 'John_updated',
            'marks' => 85,
            'comment' => 'Great Work...'
        ];
        // update the properties with the help of provided setters
        $user->setId($newUserDetails['user_id']);
        $user->setLegalFirstName($newUserDetails['user_firstname']);
        $user->setPreferredFirstName($newUserDetails['user_preferred_firstname']);
        $user->setDisplayedFirstName($newUserDetails['user_preferred_firstname']);
        $user->setLegalLastName($newUserDetails['user_lastname']);
        $user->setPreferredLastName($newUserDetails['user_preferred_lastname']);
        $user->setDisplayedLastName($newUserDetails['user_preferred_lastname']);
        $user->setMarks($newUserDetails['marks']);
        $user->setComment($newUserDetails['comment']);

        // Now check if the properties are updated correctly or not
        $this->assertEquals($newUserDetails['user_id'], $user->getId());
        $this->assertEquals($newUserDetails['user_firstname'], $user->getLegalFirstName());
        $this->assertEquals($newUserDetails['user_preferred_firstname'], $user->getDisplayedFirstName());
        $this->assertEquals($newUserDetails['user_preferred_firstname'], $user->getPreferredFirstName());
        $this->assertEquals($newUserDetails['user_lastname'], $user->getLegalLastName());
        $this->assertEquals($newUserDetails['user_preferred_lastname'], $user->getDisplayedLastName());
        $this->assertEquals($newUserDetails['user_preferred_lastname'], $user->getPreferredLastName());
        $this->assertEquals($newUserDetails['marks'], $user->getMarks());
        $this->assertEquals($newUserDetails['comment'], $user->getComment());
    }
}
