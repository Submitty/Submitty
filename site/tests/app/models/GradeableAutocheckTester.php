<?php

declare(strict_types=1);

namespace tests\app\models;

use app\libraries\DiffViewer;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\GradeableAutocheck;
use tests\BaseUnitTest;

/**
 * Expected files are named with an image extension in these tests, since the DiffViewer only
 * exposes the expected file path through getExpectedImageFilename().
 */
class GradeableAutocheckTester extends BaseUnitTest {
    private $core;
    private $tmp_dir;
    private $course_path;
    private $results_path;
    private $results_public_path;

    public function setUp(): void {
        $this->core = $this->createMockCore();
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $this->course_path = FileUtils::joinPaths($this->tmp_dir, 'course');
        $this->results_path = FileUtils::joinPaths($this->course_path, 'results', 'hw1', 'student', '1');
        $this->results_public_path = FileUtils::joinPaths($this->course_path, 'results_public', 'hw1', 'student', '1');
        FileUtils::createDir(FileUtils::joinPaths($this->results_path, 'details'), true);
        FileUtils::createDir(FileUtils::joinPaths($this->results_public_path, 'details'), true);
    }

    public function tearDown(): void {
        if (file_exists($this->tmp_dir)) {
            FileUtils::recursiveRmdir($this->tmp_dir);
        }
    }

    private function createAutocheck(array $details, string $idx = 'id_0_0'): GradeableAutocheck {
        return new GradeableAutocheck(
            $this->core,
            $details,
            $this->course_path,
            $this->results_path,
            $this->results_public_path,
            $idx
        );
    }

    private function createFile(string $path, string $contents = "output\n"): string {
        FileUtils::createDir(dirname($path), true);
        file_put_contents($path, $contents);
        return $path;
    }

    private function expectNoErrorMessage(): void {
        $this->core->expects($this->never())->method('addErrorMessage');
    }

    private function expectErrorMessage(string $message): void {
        $this->core->expects($this->once())->method('addErrorMessage')->with($message);
    }

    public function testDefaults(): void {
        $this->expectNoErrorMessage();
        $autocheck = $this->createAutocheck([], 'id_2_1');

        $this->assertEquals('id_2_1', $autocheck->getIndex());
        $this->assertEquals('', $autocheck->getDescription());
        $this->assertEquals([], $autocheck->getMessages());
        $this->assertFalse($autocheck->isDisplayAsSequenceDiagram());

        $diff_viewer = $autocheck->getDiffViewer();
        $this->assertInstanceOf(DiffViewer::class, $diff_viewer);
        $this->assertEquals('', $diff_viewer->getActualFilename());
        $this->assertEquals('', $diff_viewer->getExpectedImageFilename());
        $this->assertEquals('', $diff_viewer->getDifferenceFilename());
        $this->assertFalse($diff_viewer->hasDisplayActual());
        $this->assertFalse($diff_viewer->hasDisplayExpected());
        $this->assertFalse($diff_viewer->existsDifference());
    }

    public function testDescriptionAndMessageTypesAreEscaped(): void {
        $autocheck = $this->createAutocheck([
            'description' => '<b>Standard Output</b>',
            'messages' => [
                ['message' => 'Missing trailing newline', 'type' => '<warning>'],
                ['message' => 'Output does not match', 'type' => 'failure'],
            ],
        ]);

        $this->assertEquals('&lt;b&gt;Standard Output&lt;/b&gt;', $autocheck->getDescription());
        $this->assertEquals([
            ['message' => 'Missing trailing newline', 'type' => '&lt;warning&gt;'],
            ['message' => 'Output does not match', 'type' => 'failure'],
        ], $autocheck->getMessages());
    }

    public function testDisplayAsSequenceDiagram(): void {
        $autocheck = $this->createAutocheck(['display_as_sequence_diagram' => true]);
        $this->assertTrue($autocheck->isDisplayAsSequenceDiagram());
    }

    public function testActualFileFromResults(): void {
        $path = $this->createFile(FileUtils::joinPaths($this->results_path, 'details', 'test01', 'STDOUT.txt'));
        $autocheck = $this->createAutocheck(['actual_file' => 'test01/STDOUT.txt']);

        $this->assertFalse($autocheck->getPublic());
        $this->assertEquals($path, $autocheck->getDiffViewer()->getActualFilename());
    }

    public function testActualFileFromResultsPublic(): void {
        $path = $this->createFile(FileUtils::joinPaths($this->results_public_path, 'details', 'test01', 'STDOUT.txt'));
        $autocheck = $this->createAutocheck(['actual_file' => 'test01/STDOUT.txt', 'results_public' => true]);

        $this->assertTrue($autocheck->getPublic());
        $this->assertEquals($path, $autocheck->getDiffViewer()->getActualFilename());
    }

    public function testMissingActualFileIsIgnored(): void {
        $this->expectNoErrorMessage();
        $autocheck = $this->createAutocheck(['actual_file' => 'test01/STDOUT.txt']);

        $this->assertEquals('', $autocheck->getDiffViewer()->getActualFilename());
        $this->assertFalse($autocheck->getDiffViewer()->hasDisplayActual());
    }

    public function testExpectedString(): void {
        // The expected file is not checked when an expected string is used, so its absence is not an error
        $this->expectNoErrorMessage();
        $autocheck = $this->createAutocheck([
            'use_expected_string' => true,
            'expected_string' => 'Hello World',
            'expected_file' => 'test_output/hw1/expected.png',
        ]);

        $this->assertTrue($autocheck->getDiffViewer()->hasExpectedOutput());
        $this->assertStringContainsString('Hello World', $autocheck->getDiffViewer()->getDisplayExpected());
        $this->assertEquals('', $autocheck->getDiffViewer()->getExpectedImageFilename());
    }

    public function testMissingExpectedStringAddsError(): void {
        $this->expectErrorMessage('Expected string not specified.');
        $autocheck = $this->createAutocheck(['use_expected_string' => true]);

        $this->assertFalse($autocheck->getDiffViewer()->hasDisplayExpected());
    }

    public function testExpectedFileFromTestOutput(): void {
        $this->expectNoErrorMessage();
        $path = $this->createFile(FileUtils::joinPaths($this->course_path, 'test_output', 'hw1', 'expected.png'));
        $autocheck = $this->createAutocheck(['expected_file' => 'test_output/hw1/expected.png']);

        $this->assertEquals($path, $autocheck->getDiffViewer()->getExpectedImageFilename());
    }

    public function testMissingExpectedFileFromTestOutputAddsError(): void {
        $this->expectErrorMessage('Expected file not found.');
        $autocheck = $this->createAutocheck(['expected_file' => 'test_output/hw1/expected.png']);

        $this->assertEquals('', $autocheck->getDiffViewer()->getExpectedImageFilename());
    }

    public function testExpectedFileFromGeneratedOutput(): void {
        $this->expectNoErrorMessage();
        // The gradeable id and "random_output" are inserted after the first path segment
        $path = $this->createFile(
            FileUtils::joinPaths($this->course_path, 'generated_output', 'hw1', 'random_output', 'test01', 'expected.png')
        );
        $autocheck = $this->createAutocheck(['expected_file' => 'generated_output/test01/expected.png']);

        $this->assertEquals($path, $autocheck->getDiffViewer()->getExpectedImageFilename());
    }

    public function testMissingExpectedFileFromGeneratedOutputAddsError(): void {
        $path = FileUtils::joinPaths($this->course_path, 'generated_output', 'hw1', 'random_output', 'test01', 'expected.png');
        $this->expectErrorMessage($path);
        $this->createAutocheck(['expected_file' => 'generated_output/test01/expected.png']);
    }

    public function testExpectedFileFromRandomOutput(): void {
        $this->expectNoErrorMessage();
        $path = $this->createFile(FileUtils::joinPaths($this->results_path, 'random_output', 'test01', 'expected.png'));
        $autocheck = $this->createAutocheck(['expected_file' => 'random_output/test01/expected.png']);

        $this->assertEquals($path, $autocheck->getDiffViewer()->getExpectedImageFilename());
    }

    public function testMissingExpectedFileFromRandomOutputAddsError(): void {
        $this->expectErrorMessage('Expected file not found.');
        $autocheck = $this->createAutocheck(['expected_file' => 'random_output/test01/expected.png']);

        $this->assertEquals('', $autocheck->getDiffViewer()->getExpectedImageFilename());
    }

    public function testExpectedFileFromResultsDetails(): void {
        $this->expectNoErrorMessage();
        $path = $this->createFile(FileUtils::joinPaths($this->results_path, 'details', 'test01', 'expected.png'));
        $autocheck = $this->createAutocheck(['expected_file' => 'test01/expected.png']);

        $this->assertEquals($path, $autocheck->getDiffViewer()->getExpectedImageFilename());
    }

    public function testMissingExpectedFileFromResultsDetailsIsIgnored(): void {
        $this->expectNoErrorMessage();
        $autocheck = $this->createAutocheck(['expected_file' => 'test01/expected.png']);

        $this->assertEquals('', $autocheck->getDiffViewer()->getExpectedImageFilename());
    }

    public function testDifferenceFile(): void {
        $this->createFile(
            FileUtils::joinPaths($this->results_path, 'details', 'test01', 'diff.json'),
            json_encode(['differences' => [[
                'actual' => ['start' => 0, 'line' => [['line_number' => 0]]],
                'expected' => ['start' => 0, 'line' => [['line_number' => 0]]],
            ]]])
        );
        $autocheck = $this->createAutocheck(['difference_file' => 'test01/diff.json']);

        $this->assertTrue($autocheck->getDiffViewer()->existsDifference());
    }

    public function testImageDifferenceFile(): void {
        $path = $this->createFile(FileUtils::joinPaths($this->results_path, 'details', 'test01', 'diff.png'));
        $autocheck = $this->createAutocheck(['image_difference_file' => 'test01/diff.png']);

        $this->assertEquals($path, $autocheck->getDiffViewer()->getDifferenceFilename());
    }

    public function testMissingDifferenceFilesAreIgnored(): void {
        $this->expectNoErrorMessage();
        $autocheck = $this->createAutocheck([
            'difference_file' => 'test01/diff.json',
            'image_difference_file' => 'test01/diff.png',
        ]);

        $this->assertFalse($autocheck->getDiffViewer()->existsDifference());
        $this->assertEquals('', $autocheck->getDiffViewer()->getDifferenceFilename());
    }
}
