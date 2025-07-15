<?php

namespace tests\app\libraries;

use app\libraries\Access;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Submitter;
use app\models\Team;
use app\models\User;
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

    /**
     * @var string $course_path
     */
    private $course_path;

    protected function setUp(): void {
        $this->course_path = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $this->core = $this->createMockCore(['course_path' => $this->course_path]);
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
        $g1->method("hasPeerComponent")->willReturn(true);
        $gg1 = $this->createMockModel(GradedGradeable::class);
        $gg1->method("getSubmitter")->willReturn($su1);
        $gg1->method("getGradeable")->willReturn($g1);

        $su2 = new Submitter($this->core, $user2);
        $g2 = $this->createMockModel(\app\models\gradeable\Gradeable::class);
        $g2->method("getId")->willReturn("g1");
        $g2->method("hasPeerComponent")->willReturn(true);
        $gg2 = $this->createMockModel(GradedGradeable::class);
        $gg2->method("getSubmitter")->willReturn($su2);
        $gg2->method("getGradeable")->willReturn($g2);

        $su3 = new Submitter($this->core, $user3);
        $g3 = $this->createMockModel(\app\models\gradeable\Gradeable::class);
        $g3->method("getId")->willReturn("g1");
        $g3->method("hasPeerComponent")->willReturn(true);
        $gg3 = $this->createMockModel(GradedGradeable::class);
        $gg3->method("getSubmitter")->willReturn($su3);
        $gg3->method("getGradeable")->willReturn($g3);

        self::assertFalse($this->access->isGradedGradeableInPeerAssignment($g1, $gg1, $user1));
        self::assertTrue($this->access->isGradedGradeableInPeerAssignment($g2, $gg2, $user1));
        self::assertTrue($this->access->isGradedGradeableInPeerAssignment($g3, $gg3, $user1));
        self::assertTrue($this->access->isGradedGradeableInPeerAssignment($g1, $gg1, $user2));
        self::assertFalse($this->access->isGradedGradeableInPeerAssignment($g2, $gg2, $user2));
        self::assertFalse($this->access->isGradedGradeableInPeerAssignment($g3, $gg3, $user2));
        self::assertFalse($this->access->isGradedGradeableInPeerAssignment($g1, $gg1, $user3));
        self::assertFalse($this->access->isGradedGradeableInPeerAssignment($g2, $gg2, $user3));
        self::assertFalse($this->access->isGradedGradeableInPeerAssignment($g3, $gg3, $user3));
    }

    public static function checkGroupPrivilegeProvider() {
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

    public function testCanUserPathWrite() {
        $user1 = $this->createMockModel(User::class);
        $user1->method("getId")->willReturn("user1");

        self::assertFalse($this->access->canUser($user1, "path.write", [
            "path" => "",
            "dir" => "course_materials"
        ]));

        FileUtils::createDir(FileUtils::joinPaths($this->course_path, "course_materials", ".access_tester"), true);
        self::assertTrue(FileUtils::writeFile(FileUtils::joinPaths($this->course_path, "course_materials", ".access_tester/test.txt"), "data"));
        self::assertTrue($this->access->canUser($user1, "path.write", [
            "path" => FileUtils::joinPaths($this->course_path, "course_materials", ".access_tester/test.txt"),
            "dir" => "course_materials"
        ]));

        self::assertFalse($this->access->canUser($user1, "path.write", [
            "path" => FileUtils::joinPaths($this->course_path, "course_materials", ".access_tester/../.access_tester/test.txt"),
            "dir" => "course_materials"
        ]));
        FileUtils::recursiveRmdir($this->course_path);
    }

    public function testPollViewPermissions() {
        $student = $this->createMockModel(User::class);
        $student->method('getGroup')->willReturn(User::GROUP_STUDENT);
        $instructor = $this->createMockModel(User::class);
        $instructor->method('getGroup')->willReturn(User::GROUP_INSTRUCTOR);

        $poll = $this->getMockBuilder(\app\entities\poll\Poll::class)
            ->disableOriginalConstructor()
            ->getMock();
        $poll->method('isVisible')->willReturn(true);

        // poll.view should be accessible to all groups
        $this->assertTrue($this->access->canUser($student, 'poll.view', ['poll' => $poll]));
        $this->assertTrue($this->access->canUser($instructor, 'poll.view', ['poll' => $poll]));

        // poll.view.histogram should be accessible to instructors only
        $this->assertFalse($this->access->canUser($student, 'poll.view.histogram', ['poll' => $poll]));
        $this->assertTrue($this->access->canUser($instructor, 'poll.view.histogram', ['poll' => $poll]));

        $poll->method('isHistogramAvailable')->willReturn(true);

        // poll.view.histogram should be accessible to all groups if histogram is available
        $this->assertTrue($this->access->canUser($student, 'poll.view.histogram', ['poll' => $poll]));
        $this->assertTrue($this->access->canUser($instructor, 'poll.view.histogram', ['poll' => $poll]));
    }
}
