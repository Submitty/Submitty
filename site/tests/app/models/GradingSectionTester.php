<?php

declare(strict_types=1);

namespace tests\app\models;

use app\models\GradingSection;
use app\models\Team;
use app\models\User;
use tests\BaseUnitTest;

class GradingSectionTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    private function createUser(string $id): User {
        return new User($this->core, [
            'user_id' => $id,
            'user_givenname' => 'User',
            'user_familyname' => $id,
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "$id@example.com",
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false
        ]);
    }

    private function createTeam(string $id): Team {
        return new Team($this->core, [
            'team_id' => $id,
            'team_name' => 'Team ' . $id,
            'registration_section' => 'reg1',
            'rotating_section' => 0,
            'users' => []
        ]);
    }

    public function testBasicGetters(): void {
        $graders = [$this->createUser('grader1')];
        $users = [$this->createUser('user1')];
        $teams = [];

        $section = new GradingSection($this->core, true, 'Section 1', $graders, $users, $teams);

        $this->assertTrue($section->isRegistration());
        $this->assertEquals('Section 1', $section->getName());
        $this->assertSame($graders, $section->getGraders());
        $this->assertSame($users, $section->getUsers());
        $this->assertSame($teams, $section->getTeams());
    }

    public function testRotatingSection(): void {
        $section = new GradingSection($this->core, false, 5, [], [], []);
        $this->assertFalse($section->isRegistration());
        $this->assertEquals('5', $section->getName());
    }

    public function testNullNameStaysNull(): void {
        $section = new GradingSection($this->core, true, null, [], [], []);
        $this->assertNull($section->getName());
    }

    public function testContainsUserTrue(): void {
        $user = $this->createUser('user1');
        $section = new GradingSection($this->core, true, 'Section 1', [], [$user], []);
        $this->assertTrue($section->containsUser($user));
    }

    public function testContainsUserFalse(): void {
        $user = $this->createUser('user1');
        $other = $this->createUser('user2');
        $section = new GradingSection($this->core, true, 'Section 1', [], [$user], []);
        $this->assertFalse($section->containsUser($other));
    }

    public function testContainsTeamTrue(): void {
        $team = $this->createTeam('team1');
        $section = new GradingSection($this->core, true, 'Section 1', [], [], [$team]);
        $this->assertTrue($section->containsTeam($team));
    }

    public function testContainsTeamFalse(): void {
        $team = $this->createTeam('team1');
        $other = $this->createTeam('team2');
        $section = new GradingSection($this->core, true, 'Section 1', [], [], [$team]);
        $this->assertFalse($section->containsTeam($other));
    }

    public function testGetSubmittersWithUsers(): void {
        $user = $this->createUser('user1');
        $section = new GradingSection($this->core, true, 'Section 1', [], [$user], []);

        $submitters = $section->getSubmitters();
        $this->assertCount(1, $submitters);
        $this->assertFalse($submitters[0]->isTeam());
        $this->assertSame($user, $submitters[0]->getUser());
    }

    public function testGetSubmittersWithTeams(): void {
        $team = $this->createTeam('team1');
        $section = new GradingSection($this->core, true, 'Section 1', [], [], [$team]);

        $submitters = $section->getSubmitters();
        $this->assertCount(1, $submitters);
        $this->assertTrue($submitters[0]->isTeam());
        $this->assertSame($team, $submitters[0]->getTeam());
    }

    public function testGetSubmittersEmpty(): void {
        $section = new GradingSection($this->core, true, 'Section 1', [], [], []);
        $this->assertEquals([], $section->getSubmitters());
    }
}
