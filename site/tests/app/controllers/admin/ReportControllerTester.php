<?php

namespace tests\app\controllers\admin;

use app\controllers\admin\ReportController;
use app\libraries\response\JsonResponse;
use app\models\gradeable\Gradeable;
use tests\BaseUnitTest;
use app\libraries\FileUtils;

class ReportControllerTester extends BaseUnitTest
{
    use \phpmock\phpunit\PHPMock;
    private $controller;
    private $tmp_dir;
    private $course_path;
    private $rainbow_dir;
    private $gradeables;
    private $core;

    protected function setUp(): void
    {
        parent::setUp();

        // Prepare the mock course configurations and directories
        $this->tmp_dir = sys_get_temp_dir() . '/submitty_test_' . uniqid();
        $this->course_path = $this->tmp_dir . '/course';
        $this->rainbow_dir = $this->course_path . '/rainbow_grades';
        FileUtils::createDir($this->rainbow_dir, true);
        $this->mockCoreApplication();
    }

    private function mockCoreApplication($config = null, $user_config = null, $queries = null)
    {
        // Mock the core application properties, user configurations, and database queries for the ReportController
        $config = $config ?? [
            'course_path' => $this->course_path,
            'semester' => 'f25',
            'course' => 'sample',
            'base_url' => 'http://localhost',
            'use_mock_time' => true,
        ];
        $user_config = $user_config ?? [
            'access_admin' => true,
            'user_timezone' => 'America/New_York'
        ];
        $this->gradeables = $queries['getGradeableConfigs'] ?? [
            $this->createMockGradeable('hw1', 'Homework 1', 'homework', 10),
            $this->createMockGradeable('hw2', 'Homework 2', 'homework', 20),
            $this->createMockGradeable('exam1', 'Exam 1', 'exam', 100),
        ];
        $queries = $queries ?? [
            'getGradeableConfigs' => $this->gradeables,
            'getRegistrationSections' => [
                ['sections_registration_id' => '1'],
                ['sections_registration_id' => '2'],
            ],
        ];
        $this->core = $this->createMockCore($config, $user_config, $queries);
        $this->controller = new ReportController($this->core);
    }

    private function getSampleCustomizationJson()
    {
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
                    'count' => 1,
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
                    'ids' => ['grades_released_homework_onlyauto', 'grades_released_homework_autohiddenEC', 'bulk_upload_test'],
                    'value' => 10
                ]
            ],
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        FileUtils::recursiveRmdir($this->tmp_dir);
    }

    private function writeCustomization($content, $file = 'gui_customization.json')
    {
        file_put_contents($this->rainbow_dir . '/' . $file, json_encode($content, JSON_PRETTY_PRINT));
    }

    private function fetchCustomization($file = 'gui_customization.json')
    {
        return file_exists($this->rainbow_dir . '/' . $file) ? json_encode(json_decode(file_get_contents($this->rainbow_dir . '/' . $file), true), JSON_PRETTY_PRINT) : [];
    }

    private function clearCustomization($file = 'gui_customization.json')
    {
        $file = $this->rainbow_dir . '/' . $file;
        if (file_exists($file)) {
            unlink($file);
        }
    }

    private function verifyGUICustomizationUpdates($content)
    {
        $expected_json = json_encode($content, JSON_PRETTY_PRINT);
        $final_json = $this->fetchCustomization('gui_customization.json');
        $this->assertEquals($expected_json, $final_json);
    }

    /**
     * Helper to create a mock Gradeable object for testing.
     */
    private function createMockGradeable($id = 'test', $title = 'Test Gradeable', $bucket = 'homework', $points = 10)
    {
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn($id);
        $gradeable->method('getTitle')->willReturn($title);
        $gradeable->method('hasReleaseDate')->willReturn(true);
        $gradeable->method('getSyllabusBucket')->willReturn($bucket);
        $gradeable->method('getManualGradingPoints')->willReturn($points);
        $gradeable->method('hasAutogradingConfig')->willReturn(false);
        $gradeable->method('getGradeReleasedDate')->willReturn(new \DateTime('9998-12-31 23:59:59-0500'));
        $gradeable->method('getSubmissionOpenDate')->willReturn(new \DateTime('9998-12-31 23:59:59-0500'));

        return $gradeable;
    }

    public function testExistingManualCustomization()
    {
        // Ensure the manual and main customization files are the same
        $content = $this->getSampleCustomizationJson();
        $this->writeCustomization($content, 'customization.json');
        $this->writeCustomization($content, 'manual_customization.json');;

        // No GUI customization file modifications due to manual customization applications
        $response = $this->controller->saveGUICustomizations();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('error', $response->json['status']);
        $this->assertSame('Manual customization is currently in use.', $response->json['message']);
    }


    public function testGUICustomizationSave()
    {
        // Set the existing GUI customization file
        $content = $this->getSampleCustomizationJson();
        $this->writeCustomization($content, 'gui_customization.json');
        $this->writeCustomization($content, 'customization.json');

        // Test no modifications in the customization content
        $content = $this->getSampleCustomizationJson();

        $response = $this->controller->saveGUICustomizations();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('success', $response->json['status']);
        $this->assertNull($response->json['data']);
        $this->verifyGUICustomizationUpdates($content);

        // Test the addition of an exam gradeable
        $content = $this->getSampleCustomizationJson();

        // Mock the gradable addition for the database query
        $this->gradeables[] = $this->createMockGradeable('exam2', 'Exam 2', 'exam', 100);
        $this->mockCoreApplication([], [], ['getGradeableConfigs' => $this->gradeables]);

        // Update the customization content to include the new exam gradeable
        $content['gradeables'][1]['count'] = 2;
        $content['gradeables'][1]['ids'][] = [
            'max' => 100,
            'release_date' => '9998-12-31 23:59:59-0500',
            'id' => 'exam2',
        ];

        $response = $this->controller->saveGUICustomizations();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('success', $response->json['status']);
        $this->assertNull($response->json['data']);
        $this->verifyGUICustomizationUpdates($content);
    }
}
