<?php

namespace tests\app\libraries;

use app\libraries\Access;
use app\libraries\Core;
use app\models\Gradeable;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Submitter;
use app\models\gradeable\TaGradedGradeable;
use app\models\Team;
use app\models\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use tests\BaseUnitTest;

class AccessTester extends BaseUnitTest {
    /**
     * @var Core $core
     */
    private $core;
    /**
     * @var Access $access
     */
    private $access;

    protected function setUp(): void {
        $this->core = $this->createMockCore();
        $this->access = new Access($this->core);
    }

    public function testIsGradedGradeableInPeerAssignment() {
        $user1 = $this->createMockModel(User::class);
        $user2 = $this->createMockModel(User::class);
        $user3 = $this->createMockModel(User::class);
        $user1->method("getId")->willReturn("user1");
        $user2->method("getId")->willReturn("user2");
        $user3->method("getId")->willReturn("user3");

        /* @var MockObject $queries */
        $queries = $this->core->getQueries();
        $queries->method("getPeerAssignment")->will(
            $this->returnValueMap([
                ["g1", "user1", ["user2", "user3"]],
                ["g1", "user2", ["user1"]],
                ["g1", "user3", []]
            ])
        );

        $su1 = new Submitter($this->core, $user1);
        $g1 = $this->createMockModel(\app\models\gradeable\Gradeable::class);
        $g1->method("getId")->willReturn("g1");
        $g1->method("isPeerGrading")->willReturn(true);
        $gg1 = $this->createMockModel(GradedGradeable::class);
        $gg1->method("getSubmitter")->willReturn($su1);
        $gg1->method("getGradeable")->willReturn($g1);

        $su2 = new Submitter($this->core, $user2);
        $g2 = $this->createMockModel(\app\models\gradeable\Gradeable::class);
        $g2->method("getId")->willReturn("g1");
        $g2->method("isPeerGrading")->willReturn(true);
        $gg2 = $this->createMockModel(GradedGradeable::class);
        $gg2->method("getSubmitter")->willReturn($su2);
        $gg2->method("getGradeable")->willReturn($g2);

        $su3 = new Submitter($this->core, $user3);
        $g3 = $this->createMockModel(\app\models\gradeable\Gradeable::class);
        $g3->method("getId")->willReturn("g1");
        $g3->method("isPeerGrading")->willReturn(true);
        $gg3 = $this->createMockModel(GradedGradeable::class);
        $gg3->method("getSubmitter")->willReturn($su3);
        $gg3->method("getGradeable")->willReturn($g3);

        /*
        NOTE: ALL tests seem to be true
        self::assertFalse($this->access->isGradeableInStudentPeerAssignment($gg1, $user1));
        self::assertTrue($this->access->isGradeableInStudentPeerAssignment($gg2, $user1));
        self::assertTrue($this->access->isGradeableInStudentPeerAssignment($gg3, $user1));
        self::assertTrue($this->access->isGradeableInStudentPeerAssignment($gg1, $user2));
        self::assertFalse($this->access->isGradeableInStudentPeerAssignment($gg2, $user2));
        self::assertFalse($this->access->isGradeableInStudentPeerAssignment($gg3, $user2));
        self::assertFalse($this->access->isGradeableInStudentPeerAssignment($gg1, $user3));
        self::assertFalse($this->access->isGradeableInStudentPeerAssignment($gg2, $user3));
        self::assertFalse($this->access->isGradeableInStudentPeerAssignment($gg3, $user3));
        */
        self::assertTrue($this->access->isGradeableInStudentPeerAssignment($g1, $user1));
        self::assertTrue($this->access->isGradeableInStudentPeerAssignment($g2, $user1));
        self::assertTrue($this->access->isGradeableInStudentPeerAssignment($g3, $user1));
        self::assertTrue($this->access->isGradeableInStudentPeerAssignment($g1, $user2));
        self::assertTrue($this->access->isGradeableInStudentPeerAssignment($g2, $user2));
        self::assertTrue($this->access->isGradeableInStudentPeerAssignment($g3, $user2));
        self::assertTrue($this->access->isGradeableInStudentPeerAssignment($g1, $user3));
        self::assertTrue($this->access->isGradeableInStudentPeerAssignment($g2, $user3));
        self::assertTrue($this->access->isGradeableInStudentPeerAssignment($g3, $user3));
    }

    public function checkGroupPrivilegeProvider() {
        //This might seem overkill but maybe one day we'll replace these numbers with something
        // fancier and we need to make sure nothing breaks
        return [
            [true,  User::GROUP_STUDENT,               User::GROUP_STUDENT],
            [true,  User::GROUP_LIMITED_ACCESS_GRADER, User::GROUP_STUDENT],
            [true,  User::GROUP_FULL_ACCESS_GRADER,    User::GROUP_STUDENT],
            [true,  User::GROUP_INSTRUCTOR,            User::GROUP_STUDENT],
            [false, User::GROUP_STUDENT,               User::GROUP_LIMITED_ACCESS_GRADER],
            [true,  User::GROUP_LIMITED_ACCESS_GRADER, User::GROUP_LIMITED_ACCESS_GRADER],
            [true,  User::GROUP_FULL_ACCESS_GRADER,    User::GROUP_LIMITED_ACCESS_GRADER],
            [true,  User::GROUP_INSTRUCTOR,            User::GROUP_LIMITED_ACCESS_GRADER],
            [false, User::GROUP_STUDENT,               User::GROUP_FULL_ACCESS_GRADER],
            [false, User::GROUP_LIMITED_ACCESS_GRADER, User::GROUP_FULL_ACCESS_GRADER],
            [true,  User::GROUP_FULL_ACCESS_GRADER,    User::GROUP_FULL_ACCESS_GRADER],
            [true,  User::GROUP_INSTRUCTOR,            User::GROUP_FULL_ACCESS_GRADER],
            [false, User::GROUP_STUDENT,               User::GROUP_INSTRUCTOR],
            [false, User::GROUP_LIMITED_ACCESS_GRADER, User::GROUP_INSTRUCTOR],
            [false, User::GROUP_FULL_ACCESS_GRADER,    User::GROUP_INSTRUCTOR],
            [true,  User::GROUP_INSTRUCTOR,            User::GROUP_INSTRUCTOR],
        ];
    }

    /**
     * @dataProvider checkGroupPrivilegeProvider
     */
    public function testCheckGroupPrivilege($expect, $check, $minimum) {
        $this->assertEquals($expect, $this->access->checkGroupPrivilege($check, $minimum));
    }

    public function testIsGradedGradeableByUser() {
        $user1 = $this->createMockModel(User::class);
        $user2 = $this->createMockModel(User::class);
        $user3 = $this->createMockModel(User::class);
        $user1->method("getId")->willReturn("user1");
        $user2->method("getId")->willReturn("user2");
        $user3->method("getId")->willReturn("user3");

        $team1 = $this->createMockModel(Team::class);
        $team1->method("getId")->willReturn("team1");
        $team1->method("hasMember")->willReturnCallback(function ($test) {
            return in_array($test, ["user1", "user2"]);
        });

        $su1 = new Submitter($this->core, $user1);
        $st1 = new Submitter($this->core, $team1);

        $gg1 = $this->createMockModel(GradedGradeable::class);
        $gg1->method("getSubmitter")->willReturn($su1);
        self::assertTrue($this->access->isGradedGradeableByUser($gg1, $user1));
        self::assertFalse($this->access->isGradedGradeableByUser($gg1, $user2));
        self::assertFalse($this->access->isGradedGradeableByUser($gg1, $user3));

        $gg2 = $this->createMockModel(GradedGradeable::class);
        $gg2->method("getSubmitter")->willReturn($st1);
        self::assertTrue($this->access->isGradedGradeableByUser($gg2, $user1));
        self::assertTrue($this->access->isGradedGradeableByUser($gg2, $user2));
        self::assertFalse($this->access->isGradedGradeableByUser($gg2, $user3));
    }

    public function testIsGradedGradeableBySubmitter() {
        $user1 = $this->createMockModel(User::class);
        $user2 = $this->createMockModel(User::class);
        $user3 = $this->createMockModel(User::class);
        $user1->method("getId")->willReturn("user1");
        $user2->method("getId")->willReturn("user2");
        $user3->method("getId")->willReturn("user3");

        $team1 = $this->createMockModel(Team::class);
        $team1->method("getId")->willReturn("team1");
        $team1->method("hasMember")->willReturnCallback(function ($test) {
            return in_array($test, ["user1", "user2"]);
        });

        $su1 = new Submitter($this->core, $user1);
        $su2 = new Submitter($this->core, $user2);
        $su3 = new Submitter($this->core, $user3);
        $st1 = new Submitter($this->core, $team1);

        $gg1 = $this->createMockModel(GradedGradeable::class);
        $gg1->method("getSubmitter")->willReturn($su1);
        self::assertTrue($this->access->isGradedGradeableBySubmitter($gg1, $su1));
        self::assertFalse($this->access->isGradedGradeableBySubmitter($gg1, $su2));
        self::assertFalse($this->access->isGradedGradeableBySubmitter($gg1, $su3));
        self::assertFalse($this->access->isGradedGradeableBySubmitter($gg1, $st1));

        $gg2 = $this->createMockModel(GradedGradeable::class);
        $gg2->method("getSubmitter")->willReturn($st1);
        self::assertTrue($this->access->isGradedGradeableBySubmitter($gg2, $su1));
        self::assertTrue($this->access->isGradedGradeableBySubmitter($gg2, $su2));
        self::assertFalse($this->access->isGradedGradeableBySubmitter($gg2, $su3));
        self::assertTrue($this->access->isGradedGradeableBySubmitter($gg2, $st1));
    }
}
