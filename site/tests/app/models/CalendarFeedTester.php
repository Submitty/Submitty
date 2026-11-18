<?php

declare(strict_types=1);

namespace tests\app\models;

use app\entities\calendar\CalendarItem;
use app\entities\calendar\GlobalItem;
use app\libraries\Core;
use app\libraries\database\AbstractDatabase;
use app\libraries\GradeableType;
use app\models\CalendarFeed;
use app\models\Course;
use app\models\gradeable\Gradeable;
use app\models\User;
use Doctrine\ORM\EntityRepository;
use tests\BaseUnitTest;

class CalendarFeedTester extends BaseUnitTest {
    /**
     * A core whose user is a student in f26/sample and an instructor in f26/other. Each course has one
     * gradeable that is not open yet, two gradeables that the feed must skip, and one calendar item.
     */
    private function createFeedCore(): Core {
        $not_open = $this->createMockModel(Gradeable::class);
        $not_open->method('getId')->willReturn('hw1');
        $not_open->method('getTitle')->willReturn('Homework 1');
        $not_open->method('getType')->willReturn(GradeableType::ELECTRONIC_FILE);
        $not_open->method('hasDueDate')->willReturn(true);
        $not_open->method('getSubmissionDueDate')->willReturn(new \DateTime('2026-12-01 23:59:59', new \DateTimeZone('UTC')));
        $no_due_date = $this->createMockModel(Gradeable::class);
        $no_due_date->method('getId')->willReturn('no_due_date');
        $no_due_date->method('getType')->willReturn(GradeableType::ELECTRONIC_FILE);
        $numeric = $this->createMockModel(Gradeable::class);
        $numeric->method('getId')->willReturn('numeric');
        $numeric->method('getType')->willReturn(GradeableType::NUMERIC_TEXT);

        $core = $this->createMockCore(['base_url' => 'http://example.com/', 'use_mock_time' => true], [], [
            'getSubmittyUserApiKey' => 'api-key',
            'getGradeableConfigs' => [$not_open, $no_due_date, $numeric],
        ]);
        $core->getQueries()->method('getCourseForUserId')->willReturn([
            new Course($core, ['term' => 'f26', 'term_name' => 'Fall 2026', 'course' => 'sample', 'user_group' => User::GROUP_STUDENT]),
            new Course($core, ['term' => 'f26', 'term_name' => 'Fall 2026', 'course' => 'other', 'user_group' => User::GROUP_INSTRUCTOR]),
        ]);
        $core->getConfig()->method('getSecretSession')->willReturn(str_repeat('s', 64));
        $core->method('buildUrl')->willReturnCallback(fn (array $parts): string => 'http://example.com/' . implode('/', $parts));
        $core->method('buildCourseUrl')->willReturnCallback(fn (array $parts): string => 'http://example.com/course/' . implode('/', $parts));
        $core->method('getCourseDB')->willReturn($this->createMock(AbstractDatabase::class));

        $date = new \DateTime('2026-12-05');
        $item = $this->createMock(CalendarItem::class);
        $item->method('getId')->willReturn(7);
        $item->method('getText')->willReturn('Exam 1');
        $item->method('getDate')->willReturn($date);
        $course_items = $this->createMock(EntityRepository::class);
        $course_items->method('findAll')->willReturn([$item]);
        $core->getCourseEntityManager()->method('getRepository')->willReturn($course_items);

        $global_item = $this->createMock(GlobalItem::class);
        $global_item->method('getId')->willReturn(9);
        $global_item->method('getText')->willReturn('No classes');
        $global_item->method('getDate')->willReturn($date);
        $global_items = $this->createMock(EntityRepository::class);
        $global_items->method('findAll')->willReturn([$global_item]);
        $core->getSubmittyEntityManager()->method('getRepository')->willReturn($global_items);

        return $core;
    }

    public function testSignatureAndUrl(): void {
        $core = $this->createFeedCore();
        $signature = CalendarFeed::getSignature($core, 'testUser');
        $this->assertSame(hash_hmac('sha256', 'calendar-feed|testUser|api-key', str_repeat('s', 64)), $signature);
        $this->assertSame("http://example.com/calendar/feed/testUser/{$signature}.ics", CalendarFeed::getUrl($core, 'testUser'));

        $unknown_user = $this->createMockCore([], [], ['getSubmittyUserApiKey' => null]);
        $this->assertNull(CalendarFeed::getSignature($unknown_user, 'nobody'));
    }

    public function testBuild(): void {
        $feed = CalendarFeed::build($this->createFeedCore(), 'testUser', null, true, true);

        // The student cannot open the gradeable yet, so it is tentative and has no link.
        $this->assertStringContainsString(
            "UID:gradeable/f26/sample/hw1@example.com\r\nDTSTAMP:20010101T050000Z\r\nDTSTART:20261201T235959Z\r\n"
            . "SUMMARY:Homework 1 (sample) (not open yet)\r\nSTATUS:TENTATIVE\r\n",
            $feed
        );
        // The instructor can always open it.
        $this->assertStringContainsString(
            "UID:gradeable/f26/other/hw1@example.com\r\nDTSTAMP:20010101T050000Z\r\nDTSTART:20261201T235959Z\r\n"
            . "SUMMARY:Homework 1 (other)\r\nURL:http://example.com/course/gradeable/hw1\r\n",
            $feed
        );
        $this->assertSame(1, substr_count($feed, "\r\nSTATUS:TENTATIVE\r\n"));
        $this->assertStringNotContainsString('no_due_date', $feed);
        $this->assertStringNotContainsString('numeric', $feed);
        $this->assertStringContainsString("UID:item/f26/sample/7@example.com\r\n", $feed);
        $this->assertStringContainsString("DTSTART;VALUE=DATE:20261205\r\nSUMMARY:Exam 1 (other)\r\n", $feed);
        $this->assertStringContainsString("UID:global-item/9@example.com\r\n", $feed);
        $this->assertSame(5, substr_count($feed, 'BEGIN:VEVENT'));
    }

    public function testBuildOptionsOnlyRemoveEvents(): void {
        $one_course = CalendarFeed::build($this->createFeedCore(), 'testUser', ['f26.sample', 'f26.not_enrolled'], true, true);
        $this->assertStringNotContainsString('/f26/other/', $one_course);
        $this->assertStringNotContainsString('not_enrolled', $one_course);
        $this->assertSame(3, substr_count($one_course, 'BEGIN:VEVENT'));

        $no_gradeables = CalendarFeed::build($this->createFeedCore(), 'testUser', null, false, true);
        $this->assertStringNotContainsString('UID:gradeable/', $no_gradeables);
        $this->assertSame(3, substr_count($no_gradeables, 'BEGIN:VEVENT'));

        $no_items = CalendarFeed::build($this->createFeedCore(), 'testUser', null, true, false);
        $this->assertStringNotContainsString('item/', $no_items);
        $this->assertSame(2, substr_count($no_items, 'BEGIN:VEVENT'));

        $core = $this->createFeedCore();
        $core->expects($this->never())->method('loadCourseConfig');
        $this->assertStringNotContainsString('BEGIN:VEVENT', CalendarFeed::build($core, 'testUser', null, false, false));
    }

    public function testRender(): void {
        $now = new \DateTime('2026-11-18 12:00:00', new \DateTimeZone('UTC'));
        $long_title = str_repeat('é', 60);
        $feed = CalendarFeed::render([
            [
                'uid' => 'f26-sample-hw1@example.com',
                'summary' => 'HW 1; part a, b\\c',
                'start' => new \DateTime('2026-12-01 23:59:59', new \DateTimeZone('America/New_York')),
                'all_day' => false,
                'url' => 'http://example.com/courses/f26/sample/gradeable/hw1',
                'tentative' => false,
            ],
            [
                'uid' => 'f26-sample-item-1@example.com',
                'summary' => $long_title,
                'start' => new \DateTime('2026-12-02 00:00:00', new \DateTimeZone('America/New_York')),
                'all_day' => true,
                'url' => null,
                'tentative' => true,
            ],
        ], $now);

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $feed);
        $this->assertStringEndsWith("END:VEVENT\r\nEND:VCALENDAR\r\n", $feed);
        // The due date is converted to UTC, and an item is an all-day event on its own date.
        $this->assertStringContainsString("\r\nDTSTART:20261202T045959Z\r\n", $feed);
        $this->assertStringContainsString("\r\nDTSTART;VALUE=DATE:20261202\r\n", $feed);
        $this->assertStringContainsString("\r\nSUMMARY:HW 1\\; part a\\, b\\\\c\r\n", $feed);
        $this->assertStringContainsString("\r\nURL:http://example.com/courses/f26/sample/gradeable/hw1\r\n", $feed);
        $this->assertSame(2, substr_count($feed, "DTSTAMP:20261118T120000Z\r\n"));
        // Only the event that is not open yet is marked tentative.
        $this->assertSame(1, substr_count($feed, "\r\nSTATUS:TENTATIVE\r\nX-MICROSOFT-CDO-BUSYSTATUS:TENTATIVE\r\nEND:VEVENT\r\n"));

        foreach (explode("\r\n", $feed) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'));
        }
        // Unfolding the lines gives the long title back.
        $this->assertStringContainsString('SUMMARY:' . $long_title, str_replace("\r\n ", '', $feed));
    }

    public function testCanOpen(): void {
        $cases = [
            // [TA view open, submission open, instructor, grader, student]
            [false, false, true, false, false],
            [true, false, true, true, false],
            [true, true, true, true, true],
        ];
        foreach ($cases as [$ta_view, $open, $instructor, $grader, $student]) {
            $gradeable = $this->createMockModel(Gradeable::class);
            $gradeable->method('isTaViewOpen')->willReturn($ta_view);
            $gradeable->method('isSubmissionOpen')->willReturn($open);
            $this->assertSame($instructor, CalendarFeed::canOpen($gradeable, User::GROUP_INSTRUCTOR));
            $this->assertSame($grader, CalendarFeed::canOpen($gradeable, User::GROUP_LIMITED_ACCESS_GRADER));
            $this->assertSame($student, CalendarFeed::canOpen($gradeable, User::GROUP_STUDENT));
        }
    }
}
