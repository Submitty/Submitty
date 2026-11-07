<?php

declare(strict_types=1);

namespace tests\app\models\gradeable;

use app\models\GradeableAutocheck;
use app\models\gradeable\AutoGradedTestcase;
use app\models\gradeable\AutogradingTestcase;
use tests\BaseUnitTest;

class AutoGradedTestcaseTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    private function createTestcase(int $points = 5, int $index = 3): AutogradingTestcase {
        return new AutogradingTestcase($this->core, ['title' => 'Test', 'points' => $points], $index);
    }

    private function createGradedTestcase(array $details, ?AutogradingTestcase $testcase = null): AutoGradedTestcase {
        return new AutoGradedTestcase(
            $this->core,
            $testcase ?? $this->createTestcase(),
            '/path/to/results',
            '/path/to/results_public',
            $details
        );
    }

    public function testDefaultsWithEmptyDetails(): void {
        $testcase = $this->createTestcase();
        $graded = $this->createGradedTestcase([], $testcase);

        $this->assertSame($testcase, $graded->getTestcase());
        $this->assertEquals(0.0, $graded->getPoints());
        $this->assertEquals('', $graded->getMessage());
        $this->assertTrue($graded->canView());
        $this->assertFalse($graded->hasAutochecks());
        $this->assertEquals([], $graded->getAutochecks());
    }

    public function testLoadsDetails(): void {
        $graded = $this->createGradedTestcase([
            'view_testcase' => false,
            'testcase_message' => 'Program crashed',
            'points_awarded' => '4.5',
        ]);

        $this->assertFalse($graded->canView());
        $this->assertEquals('Program crashed', $graded->getMessage());
        $this->assertEquals(4.5, $graded->getPoints());
    }

    public function testMessageIsHtmlEscaped(): void {
        $graded = $this->createGradedTestcase(['testcase_message' => '<b>bold</b>']);
        $this->assertEquals('&lt;b&gt;bold&lt;/b&gt;', $graded->getMessage());
    }

    public function testPositiveTestcaseKeepsAwardedPoints(): void {
        $graded = $this->createGradedTestcase(['points_awarded' => 4], $this->createTestcase(5));
        $this->assertEquals(4.0, $graded->getPoints());
    }

    /**
     * @dataProvider penaltyPointsProvider
     */
    public function testPenaltyTestcaseClampsPoints(?float $awarded, float $expected): void {
        $details = $awarded === null ? [] : ['points_awarded' => $awarded];
        $graded = $this->createGradedTestcase($details, $this->createTestcase(-5));
        $this->assertEquals($expected, $graded->getPoints());
    }

    public static function penaltyPointsProvider(): array {
        return [
            'within range' => [-3.0, -3.0],
            'below penalty is clamped to penalty' => [-10.0, -5.0],
            'positive is clamped to zero' => [2.0, 0.0],
            'missing defaults to zero' => [null, 0.0],
        ];
    }

    public function testLoadsAutochecks(): void {
        $graded = $this->createGradedTestcase([
            'autochecks' => [
                ['description' => 'First check'],
                ['description' => 'Second check'],
            ],
        ], $this->createTestcase(5, 3));

        $this->assertTrue($graded->hasAutochecks());
        $this->assertCount(2, $graded->getAutochecks());

        $autocheck = $graded->getAutocheck(1);
        $this->assertInstanceOf(GradeableAutocheck::class, $autocheck);
        $this->assertEquals('id_3_1', $autocheck->getIndex());
        $this->assertEquals('Second check', $autocheck->getDescription());
    }

    public function testGetAutocheckWithInvalidIndexThrows(): void {
        $graded = $this->createGradedTestcase([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Testcase did not contain autocheck at provided index');
        $graded->getAutocheck(0);
    }

    /**
     * @dataProvider disabledSetterProvider
     */
    public function testDisabledSettersThrow(string $method): void {
        $graded = $this->createGradedTestcase([]);
        $this->expectException(\BadFunctionCallException::class);
        $graded->$method();
    }

    public static function disabledSetterProvider(): array {
        return [
            ['setPoints'],
            ['setAutochecks'],
            ['setView'],
            ['setMessage'],
        ];
    }
}
