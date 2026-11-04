<?php

declare(strict_types=1);

namespace tests\app\models\gradeable;

use app\models\gradeable\GradeInquiry;
use tests\BaseUnitTest;

class GradeInquiryTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    private function createGradeInquiry(array $overrides = []): GradeInquiry {
        $details = array_merge([
            'id' => 1,
            'status' => GradeInquiry::STATUS_ACTIVE,
            'timestamp' => '2021-01-01 12:00:00',
            'gc_id' => 2
        ], $overrides);

        return new GradeInquiry($this->core, $details);
    }

    public function testGetters(): void {
        $grade_inquiry = $this->createGradeInquiry();

        $this->assertEquals(1, $grade_inquiry->getId());
        $this->assertEquals(GradeInquiry::STATUS_ACTIVE, $grade_inquiry->getStatus());
        $this->assertEquals(2, $grade_inquiry->getGcId());
        $this->assertEquals('2021-01-01', $grade_inquiry->getTimestamp()->format('Y-m-d'));
    }

    public function testNullGcId(): void {
        $grade_inquiry = $this->createGradeInquiry(['gc_id' => null]);
        $this->assertNull($grade_inquiry->getGcId());
    }

    public function testResolvedStatus(): void {
        $grade_inquiry = $this->createGradeInquiry(['status' => GradeInquiry::STATUS_RESOLVED]);
        $this->assertEquals(GradeInquiry::STATUS_RESOLVED, $grade_inquiry->getStatus());
    }

    public function testInvalidIdThrowsException(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Grade inquiry ids must be > 0');
        $this->createGradeInquiry(['id' => 0]);
    }

    public function testInvalidStatusThrowsException(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid grade inquiry status');
        $this->createGradeInquiry(['status' => 99]);
    }

    public function testSetStatusUpdatesValue(): void {
        $grade_inquiry = $this->createGradeInquiry(['status' => GradeInquiry::STATUS_ACTIVE]);
        $grade_inquiry->setStatus(GradeInquiry::STATUS_RESOLVED);
        $this->assertEquals(GradeInquiry::STATUS_RESOLVED, $grade_inquiry->getStatus());
    }

    public function testSetStatusWithInvalidValueThrows(): void {
        $grade_inquiry = $this->createGradeInquiry();
        $this->expectException(\InvalidArgumentException::class);
        $grade_inquiry->setStatus(42);
    }
}
