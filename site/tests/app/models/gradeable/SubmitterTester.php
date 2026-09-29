<?php

declare(strict_types=1);

namespace tests\app\models\gradeable;

use app\models\gradeable\Submitter;
use app\models\Team;
use app\models\User;
use tests\BaseUnitTest;

class SubmitterTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    private function createUser(): User {
        return new User($this->core, [
            'user_id' => 'user1',
            'user_givenname' => 'User',
            'user_familyname' => 'One',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => 'user1@example.com',
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'registration_section' => 'reg1',
            'rotating_section' => 2
        ]);
    }

    private function createTeam(): Team {
        return new Team($this->core, [
            'team_id' => 'team1',
            'team_name' => 'Team One',
            'registration_section' => 'reg2',
            'rotating_section' => 3,
            'users' => [
                [
                    'state' => 1,
                    'user_id' => 'user1',
                    'user_givenname' => 'User',
                    'user_familyname' => 'One',
                    'user_pronouns' => '',
                    'display_pronouns' => false,
                    'user_email' => 'user1@example.com',
                    'user_email_secondary' => null,
                    'user_email_secondary_notify' => false
                ]
            ]
        ]);
    }

    public function testUserSubmitter(): void {
        $user = $this->createUser();
        $submitter = new Submitter($this->core, $user);

        $this->assertFalse($submitter->isTeam());
        $this->assertSame($user, $submitter->getObject());
        $this->assertSame($user, $submitter->getUser());
        $this->assertNull($submitter->getTeam());
        $this->assertEquals('user1', $submitter->getId());
        $this->assertEquals('reg1', $submitter->getRegistrationSection());
        $this->assertEquals(2, $submitter->getRotatingSection());
        $this->assertTrue($submitter->hasUser($user));
        $this->assertEquals('', $submitter->getAnonId(''));
        $this->assertEquals($user->toArray(), $submitter->toArray());
    }

    public function testUserSubmitterHasUserFalseForOtherUser(): void {
        $user = $this->createUser();
        $other = new User($this->core, [
            'user_id' => 'other_user',
            'user_givenname' => 'Other',
            'user_familyname' => 'Person',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => 'other@example.com',
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false
        ]);
        $submitter = new Submitter($this->core, $user);

        $this->assertFalse($submitter->hasUser($other));
    }

    public function testTeamSubmitter(): void {
        $team = $this->createTeam();
        $submitter = new Submitter($this->core, $team);

        $this->assertTrue($submitter->isTeam());
        $this->assertSame($team, $submitter->getObject());
        $this->assertSame($team, $submitter->getTeam());
        $this->assertNull($submitter->getUser());
        $this->assertEquals('team1', $submitter->getId());
        $this->assertEquals('reg2', $submitter->getRegistrationSection());
        $this->assertEquals(3, $submitter->getRotatingSection());
        $this->assertTrue($submitter->hasUser($this->createUser()));
        $this->assertEquals($team->toArray(), $submitter->toArray());
    }

    public function testJsonSerializeUser(): void {
        $user = $this->createUser();
        $submitter = new Submitter($this->core, $user);

        $json = $submitter->jsonSerialize();
        $this->assertEquals('user1', $json['id']);
        $this->assertSame($user, $json['user']);
        $this->assertNull($json['team']);
    }

    public function testJsonSerializeTeam(): void {
        $team = $this->createTeam();
        $submitter = new Submitter($this->core, $team);

        $json = $submitter->jsonSerialize();
        $this->assertEquals('team1', $json['id']);
        $this->assertNull($json['user']);
        $this->assertSame($team, $json['team']);
    }

    public function testNullThrowsException(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Team or user must not be null');
        new Submitter($this->core, null);
    }

    public function testInvalidTypeThrowsException(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Team or user must be a Team or a User');
        new Submitter($this->core, 'not_a_user_or_team');
    }
}
