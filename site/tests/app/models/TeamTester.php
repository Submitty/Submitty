<?php

namespace tests\app\models;

use app\models\Team;
use tests\BaseUnitTest;

class TeamTester extends BaseUnitTest {
    private $core;
    private $member_users = [
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
                ],
                [
                    'state' => 0,
                    'user_id' => 'user2',
                    'user_givenname' => 'User',
                    'user_familyname' => 'Two',
                    'user_pronouns' => '',
                    'display_pronouns' => false,
                    'user_email' => 'user2@example.com',
                    'user_email_secondary' => null,
                    'user_email_secondary_notify' => false
                ]
    ];

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    public function testTeamCreation() {
        $details = [
            'team_id' => 'test',
            'team_name' => 'TEST NAME',
            'registration_section' => 'test',
            'rotating_section' => 0,
            'users' => $this->member_users
        ];
        $team = new Team($this->core, $details);
        $this->assertEquals($details['team_id'], $team->getId());
        $this->assertEquals($details['team_name'], $team->getTeamName());
        $this->assertEquals($details['registration_section'], $team->getRegistrationSection());
        $this->assertEquals($details['rotating_section'], $team->getRotatingSection());
        $this->assertEquals($details['users'][0]['user_id'], $team->getMemberUsers()[0]->getId());
        $this->assertEquals($details['users'][0]['user_id'], $team->getMemberUserIds()[0]);
        $this->assertEquals($details['users'][1]['user_id'], $team->getInvitedUsers()[0]->getId());
        $this->assertEquals($details['users'][1]['user_id'], $team->getInvitedUserIds()[0]);
        $this->assertEquals($details['users'][0]['user_id'], $team->getLeaderId());
    }

    public function testAnonID() {
        $details = [
            'team_id' => 'test',
            'team_name' => null,
            'registration_section' => 'test',
            'rotating_section' => 0,
            'users' => $this->member_users
        ];
        $team = new Team($this->core, $details);
        $anon_id = "anon_id";
        $this->core->getQueries()
            ->expects($this->exactly(2))
            ->method('getTeamAnonId')
            ->with($team->getId())
            ->willReturnOnConsecutiveCalls([], [$team->getId() => $anon_id]);
        $this->core->getQueries()
            ->expects($this->once())
            ->method('getAllAnonIds')
            ->willReturn([]);
        $this->core->getQueries()
            ->expects($this->once())
            ->method('updateTeamAnonId')
            ->will($this->returnCallback(function ($team_id, $anon_id) use ($team) {
                $this->assertEquals($team->getId(), $team_id);
                $this->assertEquals(15, strlen($anon_id));
            }));
        $this->assertEquals($anon_id, $team->getAnonId());
    }
}
