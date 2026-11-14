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

    protected function tearDown(): void {
        parent::tearDown();
        if (is_dir($this->course_path)) {
            FileUtils::recursiveRmdir($this->course_path);
        }
    }

    private function createStudent(string $id): User {
        $student = $this->createMockModel(User::class);
        $student->method("getId")->willReturn($id);
        $student->method("getGroup")->willReturn(User::GROUP_STUDENT);
        return $student;
    }

    private function createCourseFile(string $relative_path): string {
        $path = FileUtils::joinPaths($this->course_path, $relative_path);
        FileUtils::createDir(dirname($path), true);
        FileUtils::writeFile($path, "data");
        return $path;
    }

    /**
     * Registers submitters with the mocked queries so that file paths resolve back to them.
     *
     * @param array<string, User|Team> $owners Submitter objects keyed by submitter id
     * @return array<string, GradedGradeable> Graded gradeables for "hw1" keyed by submitter id
     */
    private function mockSubmitters(array $owners): array {
        $gradeable = $this->createMockModel(\app\models\gradeable\Gradeable::class);
        $gradeable->method("getId")->willReturn("hw1");
        $gradeable->method("getHiddenFiles")->willReturn([]);
        $gradeable->method("getMinGradingGroup")->willReturn(User::GROUP_INSTRUCTOR);
        $gradeable->method("isTaGrading")->willReturn(true);
        $gradeable->method("isStudentView")->willReturn(true);
        $gradeable->method("isStudentSubmit")->willReturn(true);
        $gradeable->method("canStudentDownload")->willReturn(true);

        $submitters = [];
        $graded_gradeables = [];
        foreach ($owners as $id => $owner) {
            $submitters[$id] = new Submitter($this->core, $owner);
            $auto_graded_gradeable = $this->createMockModel(\app\models\gradeable\AutoGradedGradeable::class);
            $auto_graded_gradeable->method("getHighestVersion")->willReturn(1);
            $auto_graded_gradeable->method("getActiveVersion")->willReturn(1);
            $graded_gradeable = $this->createMockModel(GradedGradeable::class);
            $graded_gradeable->method("getGradeable")->willReturn($gradeable);
            $graded_gradeable->method("getSubmitter")->willReturn($submitters[$id]);
            $graded_gradeable->method("getAutoGradedGradeable")->willReturn($auto_graded_gradeable);
            $graded_gradeables[$id] = $graded_gradeable;
        }

        $queries = $this->core->getQueries();
        $queries->method("getGradeableConfig")->willReturn($gradeable);
        $queries->method("getSubmitterById")->willReturnCallback(fn (string $id) => $submitters[$id] ?? null);
        $queries->method("getGradedGradeableForSubmitter")->willReturnCallback(
            fn ($unused, Submitter $submitter) => $graded_gradeables[$submitter->getId()]
        );

        return $graded_gradeables;
    }

    private function canRead(User $user, string $dir, string $path, ?GradedGradeable $graded_gradeable = null): bool {
        $args = ["dir" => $dir, "path" => $path];
        if ($graded_gradeable !== null) {
            $args["gradeable"] = $graded_gradeable->getGradeable();
            $args["graded_gradeable"] = $graded_gradeable;
        }
        return $this->access->canUser($user, "path.read", $args);
    }

    public function testResolveDirPathRejectsEmptySegments() {
        $base = FileUtils::joinPaths($this->course_path, "submissions");
        self::assertFalse($this->access->resolveDirPath("submissions", "hw1//user/1/file.c"));
        self::assertFalse($this->access->resolveDirPath("submissions", "{$base}/hw1//user/1/file.c"));

        $expected = "{$base}/hw1/user/1/file.c";
        self::assertSame($expected, $this->access->resolveDirPath("submissions", "hw1/user/1/file.c"));
        self::assertSame($expected, $this->access->resolveDirPath("submissions", "/hw1/user/1/file.c"));
        self::assertSame($expected, $this->access->resolveDirPath("submissions", $expected));
        self::assertSame("{$expected}/", $this->access->resolveDirPath("submissions", "{$expected}/"));
    }

    public function testStudentCanOnlyReadOwnSubmittedFiles() {
        $student = $this->createStudent("student");
        $graded_gradeables = $this->mockSubmitters(["student" => $student, "other" => $this->createStudent("other")]);
        $own_file = $this->createCourseFile("submissions/hw1/student/1/file.c");
        $other_file = $this->createCourseFile("submissions/hw1/other/1/file.c");

        self::assertTrue($this->canRead($student, "submissions", $own_file, $graded_gradeables["student"]));
        self::assertFalse($this->canRead($student, "submissions", $other_file, $graded_gradeables["student"]));
        self::assertFalse($this->canRead($student, "submissions", $other_file));
    }

    public function testEmptyPathSegmentCannotReachAnotherSubmitter() {
        $student = $this->createStudent("student");
        $graded_gradeables = $this->mockSubmitters(["student" => $student, "other" => $this->createStudent("other")]);

        $files = [
            "submissions" => "other/1/file.c",
            "results_public" => "other/1/file.c",
            "attachments" => "other/grader/file.c",
        ];
        foreach ($files as $dir => $relative_path) {
            $this->createCourseFile("{$dir}/hw1/{$relative_path}");
            $path = FileUtils::joinPaths($this->course_path, $dir, "hw1") . "//" . $relative_path;
            self::assertFalse($this->canRead($student, $dir, $path, $graded_gradeables["student"]), $dir);
        }
    }

    public function testFileWithUnknownSubmitterIsDenied() {
        $student = $this->createStudent("student");
        $graded_gradeables = $this->mockSubmitters(["student" => $student]);
        $file = $this->createCourseFile("submissions/hw1/removed_user/1/file.c");

        self::assertFalse($this->canRead($student, "submissions", $file, $graded_gradeables["student"]));
    }

    public function testGradedGradeableMustOwnTheFile() {
        $instructor = $this->createMockModel(User::class);
        $instructor->method("getGroup")->willReturn(User::GROUP_INSTRUCTOR);
        $graded_gradeables = $this->mockSubmitters([
            "student" => $this->createStudent("student"),
            "other" => $this->createStudent("other"),
        ]);
        $file = $this->createCourseFile("submissions/hw1/other/1/file.c");

        self::assertTrue($this->canRead($instructor, "submissions", $file, $graded_gradeables["other"]));
        self::assertFalse($this->canRead($instructor, "submissions", $file, $graded_gradeables["student"]));
    }

    public function testOnlyTeamMembersCanReadTeamFiles() {
        $member = $this->createStudent("member");
        $outsider = $this->createStudent("outsider");
        $team = $this->createMockModel(Team::class);
        $team->method("getId")->willReturn("team1");
        $team->method("hasMember")->willReturnCallback(fn (string $id) => $id === "member");
        $this->mockSubmitters(["team1" => $team, "member" => $member, "outsider" => $outsider]);
        $file = $this->createCourseFile("submissions/hw1/team1/1/file.c");

        self::assertTrue($this->canRead($member, "submissions", $file));
        self::assertFalse($this->canRead($outsider, "submissions", $file));
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
