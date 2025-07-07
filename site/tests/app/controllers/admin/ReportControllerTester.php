<?php

namespace tests\app\controllers\admin;

use app\controllers\admin\ReportController;
use app\libraries\response\JsonResponse;
use app\models\gradeable\Gradeable;
use tests\BaseUnitTest;
use app\libraries\FileUtils;

class ReportControllerTester extends BaseUnitTest {
    use \phpmock\phpunit\PHPMock;

    private $tmp_dir;
    private $controller;
    private $course_path;
    private $rainbow_dir;
    private $gradeables;
    private $core;

    protected function setUp(): void {
        // Prepare the mock course configurations and directories for Rainbow Grades
        $this->tmp_dir = sys_get_temp_dir() . '/submitty_test_' . uniqid();
        $this->course_path = $this->tmp_dir . '/course';
        $this->rainbow_dir = $this->course_path . '/rainbow_grades';
        FileUtils::createDir($this->rainbow_dir, true);
    }

    protected function tearDown(): void {
        FileUtils::recursiveRmdir($this->tmp_dir);
    }

    private function setupMockConfigs() {
        // Mock the core application properties, user configurations, and database queries for the ReportController
        $config = [
            'course_path' => $this->course_path,
            'semester' => 'f25',
            'course' => 'sample',
            'base_url' => 'http://localhost',
            'use_mock_time' => true,
        ];
        $user_config = [
            'access_admin' => true,
            'user_timezone' => 'America/New_York'
        ];
        $this->gradeables = count($this->gradeables ?? []) > 0 ? $this->gradeables : [
            $this->createMockGradeable('hw1', 'Homework 1', 'homework', 10),
            $this->createMockGradeable('hw2', 'Homework 2', 'homework', 20),
            $this->createMockGradeable('exam1', 'Exam 1', 'exam', 100),
        ];
        $queries = [
            'getGradeableConfigs' => $this->gradeables,
            'getRegistrationSections' => [
                ['sections_registration_id' => '1'],
                ['sections_registration_id' => '2'],
            ],
        ];
        $this->controller = new ReportController($this->createMockCore($config, $user_config, $queries));
    }

    private function getSampleCustomizationJson() {
        return [
            'section' => [
                '1' => '1',
                '2' => '2',
            ],
            'omit_section_from_stats' => ['1'],
            'display_benchmark' => ['average', 'lowest_a-', 'lowest_b-', 'lowest_c-', 'lowest_d'],
            'messages' => ['Instructor Message'],
            'display' => ['grade_summary', 'grade_details', 'final_cutoff'],
            'benchmark_percent' => ['lowest_a-' => 0.9, 'lowest_b-' => 0.8, 'lowest_c-' => 0.7, 'lowest_d' => 0.6, 'average' => 0.5],
            'final_cutoff' => ['A' => 93, 'A-' => 90, 'B+' => 87, 'B' => 83, 'B-' => 80, 'C+' => 77, 'C' => 73, 'C-' => 70, 'D+' => 67, 'D' => 60],
            'gradeables' => [
                [
                    "type" => "homework",
                    "count" => 2,
                    "remove_lowest" => 0,
                    "percent" => 0.25,
                    'ids' => [
                        [
                            'max' => 10,
                            'release_date' => '9998-12-31 23:59:59-0500',
                            'id' => 'hw1',
                            'percent' => 0.50,
                            'curve' => [10, 9, 8, 6]
                        ],
                        [
                            'max' => 20,
                            'release_date' => '9998-12-31 23:59:59-0500',
                            'id' => 'hw2',
                            'percent' => 0.50
                        ],
                    ]
                ],
                [
                    'type' => 'exam',
                    'count' => 3,
                    'remove_lowest' => 0,
                    'percent' => 0.75,
                    'ids' => [
                        [
                            'max' => 100,
                            'release_date' => '9998-12-31 23:59:59-0500',
                            'id' => 'exam1',
                            'curve' => [85, 75, 65, 55]
                        ]
                    ]
                ]
            ],
            'plagiarism' => [
                [
                    'user' => 'aphacker',
                    'gradeable' => 'grades_released_homework_onlyauto',
                    'penalty' => 1
                ]
            ],
            'manual_grade' => [
                [
                    'user' => 'aphacker',
                    'grade' => 'A',
                    'note' => 'Manual Final Grade Override'
                ]
            ],
            'warning' => [
                [
                    'msg' => 'Warning Message',
                    'ids' => ['grades_released_homework_onlyauto'],
                    'value' => 10
                ]
            ],
        ];
    }

    private function writeCustomization($content, $file = 'gui_customization.json') {
        file_put_contents($this->rainbow_dir . '/' . $file, json_encode($content, JSON_PRETTY_PRINT));
    }

    private function readCustomization($file = 'gui_customization.json') {
        return file_exists($this->rainbow_dir . '/' . $file) ? json_decode(file_get_contents($this->rainbow_dir . '/' . $file), true) : [];
    }

    private function verifyCustomizationUpdates($content) {
        $expected_json = json_encode($content, JSON_PRETTY_PRINT);
        $final_json = json_encode($this->readCustomization('gui_customization.json'), JSON_PRETTY_PRINT);
        $this->assertEquals($expected_json, $final_json);
    }

    private function submitCustomization($content, $expected_status = 'success') {
        $this->setupMockConfigs();
        $response = $this->controller->saveGUICustomizations();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($expected_status, $response->json['status']);
        if ($expected_status == 'success') {
            $this->assertNull($response->json['data']);
            $this->verifyCustomizationUpdates($content);
        }
        else {
            $this->assertSame('Manual customization is currently in use.', $response->json['message']);
        }
        return $response;
    }

    private function createMockGradeable($id = 'test', $title = 'Test Gradeable', $bucket = 'homework', $points = 10, $date = '9998-12-31 23:59:59-0500') {
        // Mock all gradeable methods required to construct the customization data
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn($id);
        $gradeable->method('getTitle')->willReturn($title);
        $gradeable->method('hasReleaseDate')->willReturn(true);
        $gradeable->method('getSyllabusBucket')->willReturn($bucket);
        $gradeable->method('getManualGradingPoints')->willReturn($points);
        $gradeable->method('hasAutogradingConfig')->willReturn(false);
        $gradeable->method('getGradeReleasedDate')->willReturn(new \DateTime($date));
        $gradeable->method('getSubmissionOpenDate')->willReturn(new \DateTime($date));
        return $gradeable;
    }

    public function testExistingManualCustomization() {
        // Ensure the manual and main customization files are the same to imply manual customization applications
        $content = $this->getSampleCustomizationJson();
        $this->writeCustomization($content, 'customization.json');
        $this->writeCustomization($content, 'manual_customization.json');
        $this->submitCustomization($content, 'error');
    }


    public function testGUICustomizationSave() {
        // Set the existing GUI customization file
        $content = $this->getSampleCustomizationJson();
        $this->writeCustomization($content, 'gui_customization.json');
        $this->writeCustomization($content, 'customization.json');

        // Test no modifications in the customization content
        $this->submitCustomization($content);

        // Test the addition of an exam gradeable
        $this->gradeables[] = $this->createMockGradeable('exam2', 'Exam 2', 'exam', 100);
        $content['gradeables'][1]['ids'][] = [
            'max' => 100,
            'release_date' => '9998-12-31 23:59:59-0500',
            'id' => 'exam2',
        ];
        $this->submitCustomization($content);

        // Test the update of the release date of the exam gradeable
        array_pop($this->gradeables); // Replace the mock gradeable
        $this->gradeables[] = $this->createMockGradeable('exam2', 'Exam 2', 'exam', 100, '2025-01-01 23:59:59-0500');
        $content['gradeables'][1]['ids'][1]['release_date'] = '2025-01-01 23:59:59-0500';
        $this->submitCustomization($content);

        // Test removal of the new gradeable
        array_pop($this->gradeables);
        array_pop($content['gradeables'][1]['ids']);
        $this->submitCustomization($content);

        // Test the swapping of a gradeable bucket
        array_pop($this->gradeables); // Remove the original exam gradeable
        $this->gradeables[] = $this->createMockGradeable('exam1', 'Exam 1', 'homework', 100, '2025-01-01 23:59:59-0500');
        $content['gradeables'][0]['count'] = 3; // Increment in count should only be possible to handle "future gradeable" configurations
        $content['gradeables'][1]['ids'] = []; // No existing exam gradeables
        $content['gradeables'][0]['ids'][] = [
            'max' => 100,
            'release_date' => '2025-01-01 23:59:59-0500',
            'id' => 'exam1'
        ];
        $this->submitCustomization($content);

        // Test the addition of a gradeable in an unused bucket, leading to no changes
        $this->gradeables[] = $this->createMockGradeable('lab1', 'Lab 1', 'lab', 100, '2025-01-01 23:59:59-0500');
        $this->submitCustomization($content);
    }
}
