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

    protected function setUp() {
        $this->core = $this->createMockCore();
        $this->access = new Access($this->core);
    }

    public function testIsGradedGradeableInGradingSections() {

    }

    public function testIsSectionInGradingSections() {

    }

    public function testIsGradedGradeableInPeerAssignment() {

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
        $user1->method('getId')->willReturn("user1");
        $user2->method('getId')->willReturn("user2");
        $user3->method('getId')->willReturn("user3");

        $team1 = $this->createMockModel(Team::class);
        $team1->method('getId')->willReturn("team1");
        $team1->method('hasMember')->willReturnCallback(function($test) {
            return in_array($test, ["user1", "user2"]);
        });

        //Old model
        $g1 = $this->createMockModel(Gradeable::class);
        $g1->method("getUser")->willReturn($user1);
        $g1->method("getTeam")->willReturn(null);
        $g1->method('beenTAGraded')->willReturn(true);
        $g1->method('beenAutograded')->willReturn(true);
        self::assertTrue($this->access->isGradedGradeableByUser($g1, $user1));
        self::assertFalse($this->access->isGradedGradeableByUser($g1, $user2));
        self::assertFalse($this->access->isGradedGradeableByUser($g1, $user3));

        $g2 = $this->createMockModel(Gradeable::class);
        $g2->method("getUser")->willReturn($user1);
        $g2->method("getTeam")->willReturn($team1);
        $g2->method('beenTAGraded')->willReturn(true);
        $g2->method('beenAutograded')->willReturn(true);
        self::assertTrue($this->access->isGradedGradeableByUser($g2, $user1));
        self::assertTrue($this->access->isGradedGradeableByUser($g2, $user2));
        self::assertFalse($this->access->isGradedGradeableByUser($g2, $user3));

        //New model
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
        $user1->method('getId')->willReturn("user1");
        $user2->method('getId')->willReturn("user2");
        $user3->method('getId')->willReturn("user3");

        $team1 = $this->createMockModel(Team::class);
        $team1->method('getId')->willReturn("team1");
        $team1->method('hasMember')->willReturnCallback(function($test) {
            return in_array($test, ["user1", "user2"]);
        });

        $su1 = new Submitter($this->core, $user1);
        $su2 = new Submitter($this->core, $user2);
        $su3 = new Submitter($this->core, $user3);
        $st1 = new Submitter($this->core, $team1);

        //Old model
        $g1 = $this->createMockModel(Gradeable::class);
        $g1->method("getUser")->willReturn($user1);
        $g1->method("getTeam")->willReturn(null);
        $g1->method('beenTAGraded')->willReturn(true);
        $g1->method('beenAutograded')->willReturn(true);
        self::assertTrue($this->access->isGradedGradeableBySubmitter($g1, $su1));
        self::assertFalse($this->access->isGradedGradeableBySubmitter($g1, $su2));
        self::assertFalse($this->access->isGradedGradeableBySubmitter($g1, $su3));
        self::assertFalse($this->access->isGradedGradeableBySubmitter($g1, $st1));

        $g2 = $this->createMockModel(Gradeable::class);
        $g2->method("getUser")->willReturn($user1);
        $g2->method("getTeam")->willReturn($team1);
        $g2->method('beenTAGraded')->willReturn(true);
        $g2->method('beenAutograded')->willReturn(true);
        self::assertTrue($this->access->isGradedGradeableBySubmitter($g2, $su1));
        self::assertTrue($this->access->isGradedGradeableBySubmitter($g2, $su2));
        self::assertFalse($this->access->isGradedGradeableBySubmitter($g2, $su3));
        self::assertTrue($this->access->isGradedGradeableBySubmitter($g2, $st1));

        //New model
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

    public function testResolveNewGradeable() {
        //Ungraded old model Gradeable
        $g1 = $this->createMock(Gradeable::class);
        $g1->method('beenTAGraded')->willReturn(false);
        $g1->method('beenAutograded')->willReturn(false);
        //Graded old model Gradeable
        $g2 = $this->createMock(Gradeable::class);
        $g2->method('beenTAGraded')->willReturn(true);
        $g2->method('beenAutograded')->willReturn(true);

        $this->assertEquals([$g1, null, null, null], $this->invokeMethod($this->access, 'resolveNewGradeable', $g1));
        $this->assertEquals([$g2, $g2, null, null], $this->invokeMethod($this->access, 'resolveNewGradeable', $g2));

        //New model Gradeable and *GradedGradeable
        $ng = $this->createMock(\app\models\gradeable\Gradeable::class);
        $ngg = $this->createMock(GradedGradeable::class);
        $tgg = $this->createMock(TaGradedGradeable::class);
        $agg = $this->createMock(AutoGradedGradeable::class);
        $ngg->method('getGradeable')->willReturn($ng);
        $tgg->method('getGradedGradeable')->willReturn($ngg);
        $agg->method('getGradedGradeable')->willReturn($ngg);

        $this->assertEquals([null, null, $ng, null], $this->invokeMethod($this->access, 'resolveNewGradeable', $ng));
        $this->assertEquals([null, null, $ng, $ngg], $this->invokeMethod($this->access, 'resolveNewGradeable', $ngg));
        $this->assertEquals([null, null, $ng, $ngg], $this->invokeMethod($this->access, 'resolveNewGradeable', $tgg));
        $this->assertEquals([null, null, $ng, $ngg], $this->invokeMethod($this->access, 'resolveNewGradeable', $agg));
    }
}
