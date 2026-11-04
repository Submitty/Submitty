<?php

declare(strict_types=1);

namespace tests\app\models\gradeable;

use app\models\gradeable\AutogradingTestcase;
use tests\BaseUnitTest;

class AutogradingTestcaseTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    private function createTestcase(array $overrides = []): AutogradingTestcase {
        $details = array_merge([
            'title' => 'Test Case 1',
            'details' => 'some details',
            'points' => 10,
            'extra_credit' => false,
            'hidden' => false,
            'release_hidden_details' => false,
            'view_testcase_message' => true,
            'testcase_label' => 'label1',
            'publish_actions' => false,
            'dispatcher_actions' => ['a'],
            'actions' => ['b']
        ], $overrides);

        return new AutogradingTestcase($this->core, $details, 0);
    }

    public function testBasicGetters(): void {
        $testcase = $this->createTestcase();

        $this->assertEquals(0, $testcase->getIndex());
        $this->assertEquals('Test Case 1', $testcase->getName());
        $this->assertEquals('some details', $testcase->getDetails());
        $this->assertEquals(10, $testcase->getPoints());
        $this->assertFalse($testcase->isExtraCredit());
        $this->assertFalse($testcase->isHidden());
        $this->assertFalse($testcase->isReleaseHiddenDetails());
        $this->assertEquals('label1', $testcase->getTestcaseLabel());
        $this->assertFalse($testcase->isPublishActions());
        $this->assertEquals(['a'], $testcase->getDispatcherActions());
        $this->assertEquals(['b'], $testcase->getGraphicsActions());
    }

    public function testDefaultsWhenDetailsMissing(): void {
        $testcase = new AutogradingTestcase($this->core, [], 5);

        $this->assertEquals(5, $testcase->getIndex());
        $this->assertEquals('', $testcase->getName());
        $this->assertEquals('', $testcase->getDetails());
        $this->assertEquals(0, $testcase->getPoints());
        $this->assertFalse($testcase->isExtraCredit());
        $this->assertFalse($testcase->isHidden());
        $this->assertTrue($testcase->canViewTestcaseMessage());
        $this->assertEquals('', $testcase->getTestcaseLabel());
        $this->assertEquals([], $testcase->getDispatcherActions());
        $this->assertEquals([], $testcase->getGraphicsActions());
    }

    public function testNameIsHtmlEscaped(): void {
        $testcase = $this->createTestcase(['title' => '<script>alert(1)</script>']);
        $this->assertEquals(htmlentities('<script>alert(1)</script>'), $testcase->getName());
    }

    public function testGetNonHiddenPointsWhenVisible(): void {
        $testcase = $this->createTestcase(['points' => 15, 'hidden' => false]);
        $this->assertEquals(15, $testcase->getNonHiddenPoints());
    }

    public function testGetNonHiddenPointsWhenHidden(): void {
        $testcase = $this->createTestcase(['points' => 15, 'hidden' => true]);
        $this->assertEquals(0, $testcase->getNonHiddenPoints());
    }

    public function testGetNonHiddenNonExtraCreditPointsWhenVisibleAndNotExtraCredit(): void {
        $testcase = $this->createTestcase(['points' => 20, 'hidden' => false, 'extra_credit' => false]);
        $this->assertEquals(20, $testcase->getNonHiddenNonExtraCreditPoints());
    }

    public function testGetNonHiddenNonExtraCreditPointsWhenExtraCredit(): void {
        $testcase = $this->createTestcase(['points' => 20, 'hidden' => false, 'extra_credit' => true]);
        $this->assertEquals(0, $testcase->getNonHiddenNonExtraCreditPoints());
    }

    public function testGetNonHiddenNonExtraCreditPointsWhenHidden(): void {
        $testcase = $this->createTestcase(['points' => 20, 'hidden' => true, 'extra_credit' => false]);
        $this->assertEquals(0, $testcase->getNonHiddenNonExtraCreditPoints());
    }

    public function testHasPointsTrue(): void {
        $testcase = $this->createTestcase(['points' => 5]);
        $this->assertTrue($testcase->hasPoints());
    }

    public function testHasPointsFalse(): void {
        $testcase = $this->createTestcase(['points' => 0]);
        $this->assertFalse($testcase->hasPoints());
    }

    public function testCanViewTestcaseMessage(): void {
        $testcase = $this->createTestcase(['view_testcase_message' => false]);
        $this->assertFalse($testcase->canViewTestcaseMessage());
    }

    /**
     * @dataProvider disabledSetterProvider
     */
    public function testDisabledSettersThrow(string $method): void {
        $testcase = $this->createTestcase();
        $this->expectException(\BadFunctionCallException::class);
        $testcase->$method();
    }

    public static function disabledSetterProvider(): array {
        return [
            ['setIndex'],
            ['setName'],
            ['setDetails'],
            ['setPoints'],
            ['setExtraCredit'],
            ['setHidden'],
            ['setViewTestcaseMessage'],
        ];
    }
}
