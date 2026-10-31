<?php

declare(strict_types=1);

namespace tests\app\models\gradeable;

use app\models\gradeable\AutogradingConfig;
use app\models\gradeable\AutogradingTestcase;
use app\models\gradeable\LeaderboardConfig;
use tests\BaseUnitTest;

class AutogradingConfigTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    private function createConfig(array $overrides = []): AutogradingConfig {
        return new AutogradingConfig($this->core, array_merge(['id' => 'hw1'], $overrides));
    }

    private function createTestcaseDetails(int $points, bool $hidden = false, bool $extra_credit = false): array {
        return [
            'title' => "Test worth {$points}",
            'points' => $points,
            'hidden' => $hidden,
            'extra_credit' => $extra_credit,
        ];
    }

    public function testEmptyDetailsThrows(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Provided details were blank or null');
        new AutogradingConfig($this->core, []);
    }

    public function testDefaults(): void {
        $config = $this->createConfig();

        $this->assertEquals('hw1', $config->getGradeableId());
        $this->assertEquals(0, $config->getMaxSubmissionSize());
        $this->assertEquals(0, $config->getMaxSubmissions());
        $this->assertNull($config->getGradeableMessage());
        $this->assertFalse($config->getHideTestDetails());
        $this->assertEquals('default', $config->getRequiredCapabilities());
        $this->assertEquals(-1, $config->getMaxPossibleGradingTime());
        $this->assertEquals([], $config->getLeaderboards());
        $this->assertFalse($config->getDisplayTestcaseRuntimeMemory());
        $this->assertFalse($config->hasEarlySubmissionIncentive());
        $this->assertFalse($config->hasLoadGradeableMessageEnabled('student'));
        $this->assertFalse($config->isNotebookGradeable());
        $this->assertEquals([], $config->getNotebookConfig());
        $this->assertEquals([1 => 'Part 1'], $config->getPartNames());
        $this->assertEquals(1, $config->getNumParts());
        $this->assertEquals([], $config->getBaseTestCases());
        $this->assertFalse($config->anyPoints());
    }

    public function testLoadsDetails(): void {
        $config = $this->createConfig([
            'max_submission_size' => '100000',
            'max_submissions' => '20',
            'hide_test_details' => true,
            'required_capabilities' => 'extra_ram',
            'max_possible_grading_time' => 300,
            'display_testcase_runtime_memory' => true,
        ]);

        $this->assertSame(100000.0, $config->getMaxSubmissionSize());
        $this->assertSame(20, $config->getMaxSubmissions());
        $this->assertTrue($config->getHideTestDetails());
        $this->assertEquals('extra_ram', $config->getRequiredCapabilities());
        $this->assertEquals(300, $config->getMaxPossibleGradingTime());
        $this->assertTrue($config->getDisplayTestcaseRuntimeMemory());
    }

    public function testHideTestDetailsFallsBackToLegacyKey(): void {
        $config = $this->createConfig(['hide_version_and_test_details' => true]);
        $this->assertTrue($config->getHideTestDetails());
    }

    /**
     * @dataProvider gradeableMessageProvider
     */
    public function testGradeableMessage(array $details, string $expected): void {
        $config = $this->createConfig($details);
        $this->assertEquals($expected, $config->getGradeableMessage());
    }

    public static function gradeableMessageProvider(): array {
        return [
            'assignment_message' => [['assignment_message' => 'Assignment'], 'Assignment'],
            'gradeable_message' => [['gradeable_message' => 'Gradeable'], 'Gradeable'],
            'assignment_message takes precedence' => [
                ['assignment_message' => 'Assignment', 'gradeable_message' => 'Gradeable'],
                'Assignment',
            ],
        ];
    }

    public function testPartNames(): void {
        $config = $this->createConfig(['part_names' => ['Code', '', '   ', 'Report']]);

        $this->assertEquals([1 => 'Code', 2 => 'Part 2', 3 => 'Part 3', 4 => 'Report'], $config->getPartNames());
        $this->assertEquals(4, $config->getNumParts());
    }

    public function testTestcasePointTotals(): void {
        $config = $this->createConfig([
            'testcases' => [
                $this->createTestcaseDetails(10),
                $this->createTestcaseDetails(5),
                $this->createTestcaseDetails(3, false, true),
                $this->createTestcaseDetails(7, true),
                $this->createTestcaseDetails(2, true, true),
                // Penalty and zero-point testcases do not count towards the totals
                $this->createTestcaseDetails(-4),
                $this->createTestcaseDetails(0),
            ],
        ]);

        $this->assertEquals(15, $config->getTotalNonHiddenNonExtraCredit());
        $this->assertEquals(3, $config->getTotalNonHiddenExtraCredit());
        $this->assertEquals(7, $config->getTotalHiddenNonExtraCredit());
        $this->assertEquals(2, $config->getTotalHiddenExtraCredit());
        $this->assertEquals(18, $config->getTotalNonHidden());
        $this->assertEquals(22, $config->getTotalNonExtraCredit());
        $this->assertTrue($config->anyPoints());
    }

    /**
     * @dataProvider anyPointsProvider
     */
    public function testAnyPointsWithSingleBucket(bool $hidden, bool $extra_credit): void {
        $config = $this->createConfig(['testcases' => [$this->createTestcaseDetails(1, $hidden, $extra_credit)]]);
        $this->assertTrue($config->anyPoints());
    }

    public static function anyPointsProvider(): array {
        return [
            'visible' => [false, false],
            'visible extra credit' => [false, true],
            'hidden' => [true, false],
            'hidden extra credit' => [true, true],
        ];
    }

    public function testAnyPointsFalseWithOnlyPenalties(): void {
        $config = $this->createConfig(['testcases' => [$this->createTestcaseDetails(-5)]]);
        $this->assertFalse($config->anyPoints());
    }

    public function testBaseTestCases(): void {
        $config = $this->createConfig([
            'testcases' => [
                $this->createTestcaseDetails(10),
                $this->createTestcaseDetails(5, true),
            ],
        ]);

        $testcases = $config->getBaseTestCases();
        $this->assertCount(2, $testcases);
        $this->assertContainsOnlyInstancesOf(AutogradingTestcase::class, $testcases);
        $this->assertEquals(0, $testcases[0]->getIndex());
        $this->assertEquals(10, $testcases[0]->getPoints());
        $this->assertEquals(1, $testcases[1]->getIndex());
        $this->assertTrue($testcases[1]->isHidden());
    }

    public function testGetAllTestCasesWithoutNotebook(): void {
        $config = $this->createConfig(['testcases' => [$this->createTestcaseDetails(10)]]);

        $testcases = $config->getAllTestCases();
        $this->assertCount(1, $testcases);
        $this->assertEquals(10, $testcases[0]->getPoints());
    }

    /**
     * @dataProvider anyVisibleTestcasesProvider
     */
    public function testAnyVisibleTestcases(array $testcases, bool $expected): void {
        $config = $this->createConfig(['testcases' => $testcases]);
        $this->assertSame($expected, $config->anyVisibleTestcases(null));
    }

    public static function anyVisibleTestcasesProvider(): array {
        return [
            'no testcases' => [[], false],
            'only hidden' => [[['points' => 5, 'hidden' => true]], false],
            'one visible' => [[['points' => 5, 'hidden' => true], ['points' => 0, 'hidden' => false]], true],
        ];
    }

    public function testToArrayIncludesTestcases(): void {
        $config = $this->createConfig(['testcases' => [$this->createTestcaseDetails(10)]]);

        $arr = $config->toArray();
        $this->assertEquals('hw1', $arr['gradeable_id']);
        $this->assertCount(1, $arr['testcases']);
        $this->assertEquals(10, $arr['testcases'][0]['points']);
        $this->assertCount(1, $arr['all_testcases']);
    }

    public function testLeaderboards(): void {
        $config = $this->createConfig([
            'leaderboards' => [
                ['top_visible_students' => 5, 'title' => 'Runtime', 'tag' => 'runtime'],
                ['top_visible_students' => 0, 'title' => 'Memory', 'tag' => 'memory'],
            ],
        ]);

        $this->assertCount(2, $config->getLeaderboards());
        $this->assertContainsOnlyInstancesOf(LeaderboardConfig::class, $config->getLeaderboards());
        $this->assertEquals('Memory', $config->getLeaderboard('memory')->getTitle());
        $this->assertNull($config->getLeaderboard('missing'));
    }

    public function testGetTestcasesWithTag(): void {
        $config = $this->createConfig([
            'testcases' => [
                ['testcase_id' => 'test01', 'leaderboard_tags' => ['runtime', 'memory']],
                ['testcase_id' => 'test02', 'leaderboard_tags' => ['memory']],
                ['testcase_id' => 'test03'],
            ],
        ]);

        $this->assertEquals(['test01'], $config->getTestcasesWithTag('runtime'));
        $this->assertEquals(['test01', 'test02'], $config->getTestcasesWithTag('memory'));
        $this->assertEquals([], $config->getTestcasesWithTag('missing'));
    }

    public function testEarlySubmissionIncentive(): void {
        $config = $this->createConfig([
            'early_submission_incentive' => [
                'message' => 'Submitted <early>',
                'minimum_days_early' => '2',
                'minimum_points' => '8',
            ],
        ]);

        $this->assertTrue($config->hasEarlySubmissionIncentive());
        $this->assertEquals('Submitted &lt;early&gt;', $config->getEarlySubmissionMessage());
        $this->assertSame(2, $config->getEarlySubmissionMinimumDaysEarly());
        $this->assertSame(8, $config->getEarlySubmissionMinimumPoints());
    }

    public function testNotebookGradeable(): void {
        $notebook = [['type' => 'markdown', 'markdown_string' => 'Answer the questions below']];
        $config = $this->createConfig(['notebook' => $notebook]);

        $this->assertTrue($config->isNotebookGradeable());
        $this->assertEquals($notebook, $config->getNotebookConfig());
    }

    public function testLoadGradeableMessageEnabled(): void {
        $config = $this->createConfig(['load_gradeable_message' => ['message' => 'Read the instructions first']]);

        $this->assertTrue($config->hasLoadGradeableMessageEnabled('student'));
        $this->assertEquals('Read the instructions first', $config->getLoadGradeableMessage());
    }

    public function testEmptyLoadGradeableMessageIsDisabled(): void {
        $config = $this->createConfig(['load_gradeable_message' => ['message' => '']]);
        $this->assertFalse($config->hasLoadGradeableMessageEnabled('student'));
    }

    /**
     * @dataProvider firstTimeOnlyProvider
     */
    public function testLoadGradeableMessageFirstTimeOnly(array $previous_access, bool $expected): void {
        $this->core = $this->createMockCore([], [], ['getGradeableAccessUser' => $previous_access]);
        $config = $this->createConfig([
            'load_gradeable_message' => ['message' => 'Welcome', 'first_time_only' => true],
        ]);

        $this->assertSame($expected, $config->hasLoadGradeableMessageEnabled('student'));
        $this->assertMethodCalled('getGradeableAccessUser');
    }

    public static function firstTimeOnlyProvider(): array {
        return [
            'first visit' => [[], true],
            'already visited' => [[['timestamp' => '2024-01-15 10:00:00']], false],
        ];
    }

    /**
     * @dataProvider disabledSetterProvider
     */
    public function testDisabledSettersThrow(string $method): void {
        $config = $this->createConfig();
        $this->expectException(\BadFunctionCallException::class);
        $config->$method();
    }

    public static function disabledSetterProvider(): array {
        return [
            ['setMaxSubmissionSize'],
            ['setMaxSubmissions'],
            ['setRequiredCapabilities'],
            ['setMaxPossibleGradingTime'],
            ['setPartNames'],
            ['setEarlySubmissionMessage'],
            ['setEarlySubmissionMinimumDaysEarly'],
            ['setEarlySubmissionMinimumPoints'],
            ['setEarlySubmissionTestCases'],
            ['setTotalNonHiddenNonExtraCredit'],
            ['setTotalNonHiddenExtraCredit'],
            ['setTotalHiddenNonExtraCredit'],
            ['setTotalHiddenExtraCredit'],
        ];
    }
}
