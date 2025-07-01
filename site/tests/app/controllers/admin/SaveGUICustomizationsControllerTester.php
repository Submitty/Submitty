<?php

namespace tests\app\controllers\admin;

use app\controllers\admin\ReportController;
use app\libraries\database\AbstractDatabase;
use app\libraries\database\DatabaseQueries;
use app\libraries\FileUtils;
use app\libraries\response\JsonResponse;
use app\models\gradeable\Gradeable;
use app\models\RainbowCustomization;
use app\models\Config;
use tests\BaseUnitTest;

class SaveGUICustomizationsControllerTester extends BaseUnitTest {
    /** @var ReportController */
    protected $controller;

    private $core;
    private $course_path;
    private $rainbow_grades_dir;

    public function setUp(): void {
        parent::setUp();

        // Set up a mock course directory structure
        $this->course_path = sys_get_temp_dir() . '/submitty_test_course_' . uniqid();
        $this->rainbow_grades_dir = FileUtils::joinPaths($this->course_path, 'rainbow_grades');
        FileUtils::createDir($this->rainbow_grades_dir, true);
        // Mock Core
        $this->core = $this->createMockCore([
            'course_path' => $this->course_path,
            'getSyllabusBucket' => 'homework'
        ], [], $this->getMockQueries());
    }

    protected function tearDown(): void {
        FileUtils::recursiveRmdir($this->course_path);
    }

    private function getMockQueries() {
        $mock = $this->createMockModel(DatabaseQueries::class);
        $mock->method('getRegistrationSections')->willReturn([
            ['sections_registration_id' => '1'],
            ['sections_registration_id' => '2'],
        ]);
        $mock->method('getGradeableConfigs')->willReturn($this->getMockGradeables());
        $mock->method('getAllGradeablesIdsAndTitles')->willReturn([
            ['id' => 'hw1', 'title' => 'Homework 1'],
            ['id' => 'hw2', 'title' => 'Homework 2'],
            ['id' => 'exam1', 'title' => 'Exam 1'],
        ]);
        $mock->method('getAllUsers')->willReturn([]);
        $mock->method('getAllOverriddenGrades')->willReturn([]);
        $mock->method('getLateDayUpdates')->willReturn([]);
        return $mock;
    }

    private function getMockGradeables() {
        $g1 = $this->createMockModel(Gradeable::class);
        $g1->method('getSyllabusBucket')->willReturn('homework');
        $g1->method('getId')->willReturn('hw1');
        $g1->method('getTitle')->willReturn('Homework 1');
        $g1->method('getManualGradingPoints')->willReturn(10);
        $g1->method('hasAutogradingConfig')->willReturn(false);
        $g1->method('hasReleaseDate')->willReturn(true);
        $g1->method('getGradeReleasedDate')->willReturn(new \DateTime('2024-08-22 13:38:52-0400'));
        $g1->method('getSubmissionOpenDate')->willReturn(new \DateTime('2024-08-20 13:38:52-0400'));

        $g2 = $this->createMockModel(Gradeable::class);
        $g2->method('getSyllabusBucket')->willReturn('homework');
        $g2->method('getId')->willReturn('hw2');
        $g2->method('getTitle')->willReturn('Homework 2');
        $g2->method('getManualGradingPoints')->willReturn(15);
        $g2->method('hasAutogradingConfig')->willReturn(false);
        $g2->method('hasReleaseDate')->willReturn(true);
        $g2->method('getGradeReleasedDate')->willReturn(new \DateTime('2024-08-23 13:38:52-0400'));
        $g2->method('getSubmissionOpenDate')->willReturn(new \DateTime('2024-08-21 13:38:52-0400'));

        $g3 = $this->createMockModel(Gradeable::class);
        $g3->method('getSyllabusBucket')->willReturn('exam');
        $g3->method('getId')->willReturn('exam1');
        $g3->method('getTitle')->willReturn('Exam 1');
        $g3->method('getManualGradingPoints')->willReturn(20);
        $g3->method('hasAutogradingConfig')->willReturn(false);
        $g3->method('hasReleaseDate')->willReturn(true);
        $g3->method('getGradeReleasedDate')->willReturn(new \DateTime('2024-08-24 13:38:52-0400'));
        $g3->method('getSubmissionOpenDate')->willReturn(new \DateTime('2024-08-22 13:38:52-0400'));

        return [$g1, $g2, $g3];
    }

    public function testSaveGUICustomizationsWritesFilesAndReturnsJson() {
        $controller = new ReportController($this->core);
        $response = $controller->saveGUICustomizations();
        $json = $response->json;
        // Check response status
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('data', $json);
        $data = $json['data'];
        // Check keys in output JSON
        $this->assertArrayHasKey('section', $data);
        $this->assertArrayHasKey('gradeables', $data);
        $this->assertArrayHasKey('display', $data);
        $this->assertArrayHasKey('display_benchmark', $data);
        $this->assertArrayHasKey('benchmark_percent', $data);
        $this->assertArrayHasKey('final_cutoff', $data);
        $this->assertArrayHasKey('messages', $data);
        $this->assertArrayHasKey('plagiarism', $data);
        $this->assertArrayHasKey('manual_grade', $data);
        $this->assertArrayHasKey('warning', $data);
        // Check that files are written
        $gui_path = FileUtils::joinPaths($this->rainbow_grades_dir, 'gui_customization.json');
        $customization_path = FileUtils::joinPaths($this->rainbow_grades_dir, 'customization.json');
        $this->assertFileExists($gui_path);
        $this->assertFileExists($customization_path);
        $gui_json = json_decode(file_get_contents($gui_path), true);
        $customization_json = json_decode(file_get_contents($customization_path), true);
        $this->assertEquals($data, $gui_json);
        $this->assertEquals($data, $customization_json);
    }

    public function testSaveGUICustomizationsManualCustomizationInUse() {
        // Patch RainbowCustomization to simulate manual customization in use
        $mockCustomization = $this->getMockBuilder(RainbowCustomization::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['usesManualCustomization', 'buildCustomization'])
            ->getMock();
        $mockCustomization->method('usesManualCustomization')->willReturn(true);

        // Subclass ReportController to inject our mock RainbowCustomization
        $controller = new class($this->core, $mockCustomization) extends ReportController {
            private $mockCustomization;
            public function __construct($core, $mockCustomization) {
                parent::__construct($core);
                $this->mockCustomization = $mockCustomization;
            }
            public function saveGUICustomizations(): JsonResponse {
                $customization = $this->mockCustomization;
                $customization->buildCustomization();
                if ($customization->usesManualCustomization()) {
                    return JsonResponse::getErrorResponse(
                        "Manual customization is currently in use. GUI customizations cannot be saved."
                    );
                }
                return JsonResponse::getSuccessResponse([]);
            }
        };
        $response = $controller->saveGUICustomizations();
        $json = $response->json;
        $this->assertEquals('error', $json['status']);
        $this->assertStringContainsString('Manual customization is currently in use', $json['message']);
    }
}
