<?php

declare(strict_types=1);

namespace tests\app\models\gradeable;

use app\models\gradeable\AutoGradedVersionHistory;
use tests\BaseUnitTest;

class AutoGradedVersionHistoryTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    private function createHistory(array $overrides = []): AutoGradedVersionHistory {
        $details = array_merge([
            'queue_time' => '2024-01-15 10:00:00-0500',
            'batch_regrade' => false,
            'grading_began' => '2024-01-15 10:00:05-0500',
            'grading_finished' => '2024-01-15 10:00:35-0500',
            'wait_time' => 5,
            'grade_time' => 30,
            'submission_time' => '2024-01-15 09:59:58-0500',
            'first_access_time' => '2024-01-15 09:30:00-0500',
            'access_duration' => 1798,
            'revision' => 'abc123',
        ], $overrides);

        return new AutoGradedVersionHistory($this->core, $details);
    }

    public function testGetters(): void {
        $history = $this->createHistory();

        $this->assertEquals(new \DateTime('2024-01-15 10:00:00-0500'), $history->getQueueTime());
        $this->assertEquals(new \DateTime('2024-01-15 10:00:05-0500'), $history->getGradingBegan());
        $this->assertEquals(new \DateTime('2024-01-15 10:00:35-0500'), $history->getGradingFinished());
        $this->assertEquals(new \DateTime('2024-01-15 09:59:58-0500'), $history->getSubmissionTime());
        $this->assertEquals(new \DateTime('2024-01-15 09:30:00-0500'), $history->getFirstAccessTime());
        $this->assertFalse($history->isBatchRegrade());
        $this->assertEquals(5, $history->getWaitTime());
        $this->assertEquals(30, $history->getGradeTime());
        $this->assertEquals(1798, $history->getAccessDuration());
        $this->assertEquals('abc123', $history->getVcsRevision());
    }

    public function testDatesUseConfigTimezone(): void {
        $history = $this->createHistory([
            'queue_time' => new \DateTime('2024-01-15 15:00:00', new \DateTimeZone('UTC')),
        ]);

        $this->assertEquals('America/New_York', $history->getQueueTime()->getTimezone()->getName());
        $this->assertEquals('2024-01-15 10:00:00', $history->getQueueTime()->format('Y-m-d H:i:s'));
        $this->assertEquals('America/New_York', $history->getGradingFinished()->getTimezone()->getName());
    }

    public function testBatchRegrade(): void {
        $history = $this->createHistory(['batch_regrade' => true]);
        $this->assertTrue($history->isBatchRegrade());
    }

    public function testFirstAccessTimeIsNullWhenMissing(): void {
        $details = [
            'queue_time' => '2024-01-15 10:00:00-0500',
            'batch_regrade' => false,
            'grading_began' => '2024-01-15 10:00:05-0500',
            'grading_finished' => '2024-01-15 10:00:35-0500',
            'wait_time' => 5,
            'grade_time' => 30,
            'submission_time' => '2024-01-15 09:59:58-0500',
        ];
        $history = new AutoGradedVersionHistory($this->core, $details);

        $this->assertNull($history->getFirstAccessTime());
        $this->assertEquals(-1, $history->getAccessDuration());
        $this->assertEquals('', $history->getVcsRevision());
    }

    public function testInvalidDateStringThrows(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DateTime Format');
        $this->createHistory(['grading_began' => 'not a date']);
    }

    public function testNullDateThrows(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->createHistory(['queue_time' => null]);
    }
}
