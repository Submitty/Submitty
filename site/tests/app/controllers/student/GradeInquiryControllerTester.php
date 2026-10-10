<?php

namespace tests\app\controllers\student;

use app\controllers\student\GradeInquiryController;
use app\libraries\FileUtils;
use app\libraries\NotificationFactory;
use app\libraries\Utils;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Submitter;
use app\models\gradeable\TaGradedGradeable;
use app\models\Notification;
use app\models\Team;
use app\models\User;
use ReflectionObject;
use tests\BaseUnitTest;

class GradeInquiryControllerTester extends BaseUnitTest {
    private $core;
    private $config = [];

    public function setUp(): void {
        $config['gradeable_id'] = "test_gradeable";
        $config['semester'] = "test";
        $config['course'] = "test";
        $config['course_path'] = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $config['gradeable_path'] = FileUtils::joinPaths($config['course_path'], "submissions", $config['gradeable_id']);

        $this->config = $config;
        $this->core = $this->createMockCore($this->config);
    }

    public function tearDown(): void {
        if (file_exists($this->config['course_path'])) {
            FileUtils::recursiveRmdir($this->config['course_path']);
        }
    }

    public function testNotifyGradeInquiryEventPreservesGraderAndStudentNotifications(): void {
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn("test_gradeable");
        $gradeable->method('getTitle')->willReturn("Test Gradeable");

        $submitter = $this->createMockModel(Submitter::class);
        $submitter->method('getId')->willReturn("student_user");
        $submitter->method('isTeam')->willReturn(false);

        $grader = $this->createMockModel(User::class);
        $grader->method('getId')->willReturn("grader_user");
        $grader->method('accessFullGrading')->willReturn(true);

        $ta_graded_gradeable = $this->createMockModel(TaGradedGradeable::class);
        $ta_graded_gradeable->method('getGraders')->willReturn([$grader]);
        $ta_graded_gradeable->method('getVisibleGraders')->willReturn([$grader]);

        $graded_gradeable = $this->createMockModel(GradedGradeable::class);
        $graded_gradeable->method('hasTaGradingInfo')->willReturn(true);
        $graded_gradeable->method('getOrCreateTaGradedGradeable')->willReturn($ta_graded_gradeable);
        $graded_gradeable->method('getGradeable')->willReturn($gradeable);
        $graded_gradeable->method('getSubmitter')->willReturn($submitter);

        $this->core->method('buildCourseUrl')->willReturn("http://example.com/test");

        $mock_notification_factory = $this->createMock(NotificationFactory::class);
        $mock_notification_factory->expects($this->once())
            ->method('sendNotifications')
            ->with($this->callback(function (array $notifications) {
                $this->assertCount(2, $notifications, "Expected both grader and student notifications to be sent");

                $grader_notifications = array_values(array_filter($notifications, function (Notification $n) {
                    return $n->getComponent() === 'grading' && $n->getNotifyTarget() === 'grader_user';
                }));

                $student_notifications = array_values(array_filter($notifications, function (Notification $n) {
                    return $n->getComponent() === 'student' && $n->getNotifyTarget() === 'student_user';
                }));

                $this->assertCount(1, $grader_notifications, "Grader notification must be present in sendNotifications payload");
                $this->assertCount(1, $student_notifications, "Student notification must be present in sendNotifications payload");

                return true;
            }));

        $this->core->method('getNotificationFactory')->willReturn($mock_notification_factory);

        $controller = new GradeInquiryController($this->core);
        $reflector = new ReflectionObject($controller);
        $method = $reflector->getMethod('notifyGradeInquiryEvent');
        $method->setAccessible(true);

        $method->invoke($controller, $graded_gradeable, "test_gradeable", "I have a question about my grade", "new", null);
    }

    public function testNotifyGradeInquiryEventWithTeamSubmitterPreservesAllNotifications(): void {
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn("test_gradeable");
        $gradeable->method('getTitle')->willReturn("Test Gradeable");

        $student1 = $this->createMockModel(User::class);
        $student1->method('getId')->willReturn("student_1");

        $student2 = $this->createMockModel(User::class);
        $student2->method('getId')->willReturn("student_2");

        $team = $this->createMockModel(Team::class);
        $team->method('getMemberUsers')->willReturn([$student1, $student2]);

        $submitter = $this->createMockModel(Submitter::class);
        $submitter->method('getId')->willReturn("team_1");
        $submitter->method('isTeam')->willReturn(true);
        $submitter->method('getTeam')->willReturn($team);

        $grader1 = $this->createMockModel(User::class);
        $grader1->method('getId')->willReturn("grader_1");
        $grader1->method('accessFullGrading')->willReturn(true);

        $grader2 = $this->createMockModel(User::class);
        $grader2->method('getId')->willReturn("grader_2");
        $grader2->method('accessFullGrading')->willReturn(true);

        $ta_graded_gradeable = $this->createMockModel(TaGradedGradeable::class);
        $ta_graded_gradeable->method('getGraders')->willReturn([$grader1, $grader2]);
        $ta_graded_gradeable->method('getVisibleGraders')->willReturn([$grader1, $grader2]);

        $graded_gradeable = $this->createMockModel(GradedGradeable::class);
        $graded_gradeable->method('hasTaGradingInfo')->willReturn(true);
        $graded_gradeable->method('getOrCreateTaGradedGradeable')->willReturn($ta_graded_gradeable);
        $graded_gradeable->method('getGradeable')->willReturn($gradeable);
        $graded_gradeable->method('getSubmitter')->willReturn($submitter);

        $this->core->method('buildCourseUrl')->willReturn("http://example.com/test");

        $mock_notification_factory = $this->createMock(NotificationFactory::class);
        $mock_notification_factory->expects($this->once())
            ->method('sendNotifications')
            ->with($this->callback(function (array $notifications) {
                // 2 graders + 2 team students = 4 total notifications
                $this->assertCount(4, $notifications, "Expected 4 total notifications (2 graders + 2 students)");

                $grader_targets = array_map(function (Notification $n) {
                    return $n->getNotifyTarget();
                }, array_filter($notifications, function (Notification $n) {
                    return $n->getComponent() === 'grading';
                }));

                $student_targets = array_map(function (Notification $n) {
                    return $n->getNotifyTarget();
                }, array_filter($notifications, function (Notification $n) {
                    return $n->getComponent() === 'student';
                }));

                $this->assertEqualsCanonicalizing(['grader_1', 'grader_2'], $grader_targets);
                $this->assertEqualsCanonicalizing(['student_1', 'student_2'], $student_targets);

                return true;
            }));

        $this->core->method('getNotificationFactory')->willReturn($mock_notification_factory);

        $controller = new GradeInquiryController($this->core);
        $reflector = new ReflectionObject($controller);
        $method = $reflector->getMethod('notifyGradeInquiryEvent');
        $method->setAccessible(true);

        $method->invoke($controller, $graded_gradeable, "test_gradeable", "Team question", "reply", null);
    }
}
