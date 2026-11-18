<?php

declare(strict_types=1);

namespace tests\app\models;

use app\models\CalendarFeed;
use app\models\gradeable\Gradeable;
use app\models\User;
use tests\BaseUnitTest;

class CalendarFeedTester extends BaseUnitTest {
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
