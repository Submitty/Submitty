<?php

namespace tests\app\controllers\student;

use app\controllers\student\GradeInquiryController;
use app\libraries\NotificationFactory;
use app\models\Email;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Submitter;
use app\models\User;
use tests\BaseUnitTest;

class GradeInquiryControllerTester extends BaseUnitTest {
    private const STUDENT_ID = 'student_user';
    private const STUDENT_ANON_ID = 'anon0123abcd';
    private const GRADER_ID = 'ta_user';
    private const GRADEABLE_ID = 'hw1';

    /** @var Email[] */
    private $sent_emails = [];
    /** @var array */
    private $sent_notifications = [];

    /**
     * Builds a course URL the way the real Core does, without needing a configured course.
     */
    private function fakeCourseUrl(array $parts = []): string {
        return '/courses/fall/test' . (count($parts) > 0 ? '/' . implode('/', $parts) : '');
    }

    /**
     * Runs the private notification helper for a grade inquiry event and records what it sends.
     */
    private function runNotifyGradeInquiryEvent(): void {
        $core = $this->createMockCore();
        $core->method('buildCourseUrl')->willReturnCallback(fn (array $parts = []) => $this->fakeCourseUrl($parts));
        $core->method('getUser')->willReturn($this->createUserMock(self::GRADER_ID, true));
        $core->getConfig()->method('isEmailEnabled')->willReturn(true);

        $submitter = $this->createMockModel(Submitter::class);
        $submitter->method('getId')->willReturn(self::STUDENT_ID);
        $submitter->method('getAnonId')->willReturn(self::STUDENT_ANON_ID);
        $submitter->method('isTeam')->willReturn(false);

        $gradeable = $this->createMockModel(\app\models\gradeable\Gradeable::class);
        $gradeable->method('getTitle')->willReturn('Homework 1');
        $gradeable->method('getId')->willReturn(self::GRADEABLE_ID);

        $ta_graded_gradeable = $this->createMockModel(\app\models\gradeable\TaGradedGradeable::class);
        $ta_graded_gradeable->method('getGraders')->willReturn([$this->createUserMock(self::GRADER_ID, true)]);

        $graded_gradeable = $this->createMockModel(GradedGradeable::class);
        $graded_gradeable->method('hasTaGradingInfo')->willReturn(true);
        $graded_gradeable->method('getOrCreateTaGradedGradeable')->willReturn($ta_graded_gradeable);
        $graded_gradeable->method('getSubmitter')->willReturn($submitter);
        $graded_gradeable->method('getGradeable')->willReturn($gradeable);

        $factory = $this->createMock(NotificationFactory::class);
        $factory->method('sendNotifications')->willReturnCallback(function (array $notifications) {
            $this->sent_notifications = array_merge($this->sent_notifications, $notifications);
        });
        $factory->method('sendEmails')->willReturnCallback(function (array $emails) {
            $this->sent_emails = array_merge($this->sent_emails, $emails);
            return count($emails);
        });
        $core->method('getNotificationFactory')->willReturn($factory);

        $controller = new GradeInquiryController($core);
        $method = new \ReflectionMethod(GradeInquiryController::class, 'notifyGradeInquiryEvent');
        $method->setAccessible(true);
        $method->invoke($controller, $graded_gradeable, self::GRADEABLE_ID, 'Please review question 2.', 'new', null);
    }

    private function createUserMock(string $id, bool $full_grading): User {
        $user = $this->createMockModel(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('accessGrading')->willReturn($full_grading);
        $user->method('accessFullGrading')->willReturn($full_grading);
        return $user;
    }

    private function emailTo(string $user_id): Email {
        foreach ($this->sent_emails as $email) {
            if ($email->getUserId() === $user_id) {
                return $email;
            }
        }
        $this->fail("No email was sent to {$user_id}");
    }

    public function testGraderEmailLinksToGradingPageWithAnonymousId(): void {
        $this->runNotifyGradeInquiryEvent();

        $expected = $this->fakeCourseUrl(['gradeable', self::GRADEABLE_ID, 'grading', 'grade?who_id=' . self::STUDENT_ANON_ID]);
        $this->assertStringContainsString('Click here for more info: ' . $expected, $this->emailTo(self::GRADER_ID)->getBody());
    }

    public function testStudentEmailLinksToGradeableSubmissionPage(): void {
        $this->runNotifyGradeInquiryEvent();

        $expected = $this->fakeCourseUrl(['gradeable', self::GRADEABLE_ID]);
        $body = $this->emailTo(self::STUDENT_ID)->getBody();
        $this->assertStringContainsString('Click here for more info: ' . $expected, $body);
        $this->assertStringNotContainsString('grading', $body);
    }

    public function testStudentIdDoesNotAppearInAnyEmail(): void {
        $this->runNotifyGradeInquiryEvent();

        foreach ($this->sent_emails as $email) {
            $this->assertStringNotContainsString(self::STUDENT_ID, $email->getBody());
        }
    }

    public function testNotificationsCarryTheSameLinks(): void {
        $this->runNotifyGradeInquiryEvent();

        $metadata_by_target = [];
        foreach ($this->sent_notifications as $notification) {
            $metadata_by_target[$notification->getNotifyTarget()] = json_decode($notification->getNotifyMetadata(), true)['url'];
        }
        $this->assertSame(
            $this->fakeCourseUrl(['gradeable', self::GRADEABLE_ID, 'grading', 'grade?who_id=' . self::STUDENT_ANON_ID]),
            $metadata_by_target[self::GRADER_ID]
        );
        $this->assertSame($this->fakeCourseUrl(['gradeable', self::GRADEABLE_ID]), $metadata_by_target[self::STUDENT_ID]);
    }
}
