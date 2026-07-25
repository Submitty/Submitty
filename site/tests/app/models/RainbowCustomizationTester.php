<?php

namespace tests\app\models;

use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\gradeable\Gradeable;
use app\models\RainbowCustomization;
use tests\BaseUnitTest;

class RainbowCustomizationTester extends BaseUnitTest {
    private string $tmp_dir;
    private string $course_path;
    private string $rainbow_dir;

    protected function setUp(): void {
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $this->course_path = FileUtils::joinPaths($this->tmp_dir, 'course');
        $this->rainbow_dir = FileUtils::joinPaths($this->course_path, 'rainbow_grades');
        FileUtils::createDir($this->rainbow_dir, true);
    }

    protected function tearDown(): void {
        FileUtils::recursiveRmdir($this->tmp_dir);
    }

    public function testBuildCustomizationHandlesLegacyNullBuckets(): void {
        FileUtils::writeJsonFile(FileUtils::joinPaths($this->rainbow_dir, 'gui_customization.json'), [
            'gradeables' => [
                [
                    'type' => 'Tests',
                    'count' => 1,
                    'remove_lowest' => 0,
                    'percent' => 0.25,
                    'ids' => null,
                ],
                [
                    'type' => 'homework',
                    'count' => 1,
                    'remove_lowest' => 0,
                    'percent' => 0.75,
                    'ids' => null,
                ],
            ],
        ]);

        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn('hw1');
        $gradeable->method('getTitle')->willReturn('Homework 1');
        $gradeable->method('hasReleaseDate')->willReturn(true);
        $gradeable->method('getSyllabusBucket')->willReturn('homework');
        $gradeable->method('getManualGradingPoints')->willReturn(10);
        $gradeable->method('hasAutogradingConfig')->willReturn(false);
        $gradeable->method('getGradeReleasedDate')->willReturn(new \DateTime('2025-01-01 23:59:59-0500'));
        $gradeable->method('getSubmissionOpenDate')->willReturn(new \DateTime('2025-01-01 23:59:59-0500'));

        $core = $this->createMockCore(
            ['course_path' => $this->course_path],
            [],
            ['getGradeableConfigs' => [$gradeable]]
        );

        $customization = new RainbowCustomization($core);
        $customization->buildCustomization();

        $customization_data = $customization->getCustomizationData();
        $this->assertSame([], $customization_data['Tests']);
        $this->assertSame('hw1', $customization_data['homework'][0]['id']);
        $this->assertSame([
            'Some Rainbow Grades customization buckets contained malformed legacy data (for example a null ids value or unknown bucket type) and were loaded as empty. Please review and resave your customization.'
        ], $customization->getNormalizationWarnings());
        $this->assertSame([], $customization->getPerGradeableCurves()['Tests']);
        $this->assertSame([], $customization->getPerGradeableCurves()['homework']);
        $this->assertSame(25, $customization->getBucketPercentages()['Tests']);
        $this->assertSame(75, $customization->getBucketPercentages()['homework']);
    }
}
