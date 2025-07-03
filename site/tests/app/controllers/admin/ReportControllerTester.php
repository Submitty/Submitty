<?php

namespace tests\app\controllers\admin;

use app\controllers\admin\ReportController;
use app\models\RainbowCustomization;
use app\libraries\response\JsonResponse;
use app\models\gradeable\Gradeable;
use tests\BaseUnitTest;
use app\libraries\FileUtils;
use app\libraries\database\DatabaseQueries;
use app\libraries\database\AbstractDatabase;

class ReportControllerTester extends BaseUnitTest
{
    use \phpmock\phpunit\PHPMock;
    private $controller;
    private $tmp_dir;
    private $course_path;
    private $rainbow_dir;
    private $core;

    protected function setUp(): void
    {
        $this->tmp_dir = sys_get_temp_dir() . '/submitty_test_' . uniqid();
        $this->course_path = $this->tmp_dir . '/course';
        $this->rainbow_dir = $this->course_path . '/rainbow_grades';
        FileUtils::createDir($this->rainbow_dir, true);
        $config = [
            'course_path' => $this->course_path,
            'semester' => 'f24',
            'course' => 'sample',
            'base_url' => 'http://localhost',
            'use_mock_time' => true,
        ];
        $user_config = [
            'access_admin' => true,
            'user_timezone' => 'America/New_York'
        ];
        $queries = [
            'getGradeableConfig' => $this->createMockGradeable('hw1', 'Homework 1'),
            'getRegistrationSections' => [
                ['sections_registration_id' => '1'],
                ['sections_registration_id' => '2'],
            ],
            'getAllSectionsForGradeable' => [
                '1',
                '2'
            ]
        ];
        $this->core = $this->createMockCore($config, $user_config, $queries);
        $this->controller = new ReportController($this->core);

        // Write to the sample gui_customization.json
        $gui_customization_file = FileUtils::joinPaths($this->course_path, 'rainbow_grades', 'gui_customization.json');
        file_put_contents($gui_customization_file, json_encode($this->getSampleCustomizationJson(), JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        FileUtils::recursiveRmdir($this->tmp_dir);
        parent::tearDown();
    }

    private function writeManualCustomization($content)
    {
        file_put_contents($this->rainbow_dir . '/manual_customization.json', $content);
    }

    private function writeCustomization($content)
    {
        file_put_contents($this->rainbow_dir . '/customization.json', $content);
    }

    private function readGuiCustomization()
    {
        $file = $this->rainbow_dir . '/gui_customization.json';
        return file_exists($file) ? file_get_contents($file) : null;
    }

    private function readCustomization()
    {
        $file = $this->rainbow_dir . '/customization.json';
        return file_exists($file) ? file_get_contents($file) : null;
    }

    private function getSampleCustomizationJson() {
        return [
            'section' => [
                '1' => '1',
                '2' => '2',
                '3' => '3',
                '4' => '4',
                '5' => '5',
            ],
            'omit_section_from_stats' => ['1', '2', '3', '6', '9'],
            'display_benchmark' => ['average', 'lowest_a-', 'lowest_b-', 'lowest_c-', 'lowest_d'],
            'messages' => ['Hello!'],
            'display' => ['grade_summary', 'grade_details'],
            'benchmark_percent' => ['lowest_a-' => 0.9, 'lowest_b-' => 0.8, 'lowest_c-' => 0.7, 'lowest_d' => 0.6],
            'final_cutoff' => ['A' => 93, 'A-' => 90, 'B+' => 87, 'B' => 83, 'B-' => 80, 'C+' => 77, 'C' => 73, 'C-' => 70, 'D+' => 67, 'D' => 60],
            'plagiarism' => [
                [
                    'user' => 'aphacker',
                    'gradeable' => 'grades_released_homework_onlyauto',
                    'penalty' => 1
                ]
            ],
            'manual_grade' => [
                [
                    'user' => 'student',
                    'grade' => 'A',
                    'note' => 'Manual Grade'
                ]
            ],
            'warning' => [
                [
                    'msg' => 'Message',
                    'ids' => ['grades_released_homework_onlyauto', 'grades_released_homework_autohiddenEC', 'bulk_upload_test'],
                    'value' => 13
                ]
            ],

            'gradeables' => [
                [
                    "type" => "homework",
                    "count" => 30,
                    "remove_lowest" => 0,
                    "percent" => 0.25,
                    'ids' => [
                        [
                            'max' => 12,
                            'release_date' => '9998-12-31 23:59:59-0500',
                            'id' => 'open_team_homework',
                            'curve' => [10, 9, 8, 6],
                            'percent' => 0.50
                        ],
                        [
                            'max' => 19,
                            'release_date' => '9998-12-31 23:59:59-0500',
                            'id' => 'open_vcs_homework',
                            'percent' => 0.50
                        ],
                    ]
                ]
            ]
        ];
    }

    /**
     * Helper to create a mock Gradeable object for testing.
     */
    private function createMockGradeable($id = 'test', $title = 'Test Gradeable')
    {
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn($id);
        $gradeable->method('getTitle')->willReturn($title);
        $gradeable->method('hasReleaseDate')->willReturn(true);
        $gradeable->method('getGradeReleasedDate')->willReturn(new \DateTime('2024-08-20 00:00:00-0400'));
        $gradeable->method('getSubmissionOpenDate')->willReturn(new \DateTime('2024-08-01 00:00:00-0400'));
        return $gradeable;
    }


    public function testSaveGUICustomizationsGuiMode()
    {
        // No manual customization exists
        $response = $this->controller->saveGUICustomizations();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('success', $response->json['status']);
        $gui_json = $this->readGuiCustomization();
        $this->assertNotNull($gui_json);
        $decoded = json_decode($gui_json, true);
        $this->assertArrayHasKey('gradeables', $decoded);
        $this->assertNotEmpty($decoded['gradeables']);
        var_dump($decoded);
        // $mock_queries = $this->core->getQueries();
        // $mock_queries->method('getGradeableConfigs')->willReturn([
        //     $this->createMockGradeable($this->getGradeableDetails('hw1', 'Homework 1')['id'], $this->getGradeableDetails('hw1', 'Homework 1')['title'])
        // ]);
        // $controller = new ReportController($this->core);
        // $response = $controller->saveGUICustomizations();
        // $this->assertInstanceOf(JsonResponse::class, $response);
        // $this->assertEquals('success', $response->json['status']);
        // $gui_json = $this->readGuiCustomization();
        // $this->assertNotNull($gui_json);
        // $decoded = json_decode($gui_json, true);
        // $this->assertArrayHasKey('gradeables', $decoded);
        // $this->assertNotEmpty($decoded['gradeables']);
    }

    public function testSaveGUICustomizationsManualMode()
    {
        return;
        // Write manual customization and customization.json to match
        $content = $this->getSampleCustomizationJson();
        $this->writeManualCustomization($content);
        $this->writeCustomization($content);
        $mock_queries = $this->core->getQueries();
        $mock_queries->method('getGradeableConfigs')->willReturn([
            $this->createMockGradeable('hw1', 'Homework 1')
        ]);
        $controller = new ReportController($this->core);
        $response = $controller->saveGUICustomizations();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('fail', $response->json['status']);
        $this->assertStringContainsString('Manual customization', $response->json['message']);
    }


    public function testSaveGUICustomizationsAddGradeable()
    {
        return;
        // Start with one gradeable, then add another
        $mock_queries = $this->core->getQueries();
        $mock_queries->method('getGradeableConfigs')->willReturn([
            $this->createMockGradeable('hw1', 'Homework 1')
        ]);
        $controller = new ReportController($this->core);
        $controller->saveGUICustomizations();
        // Now add a new gradeable
        $mock_queries->method('getGradeableConfigs')->willReturn([
            $this->createMockGradeable('hw1', 'Homework 1'),
            $this->createMockGradeable('hw2', 'Homework 2')
        ]);
        $controller = new ReportController($this->core);
        $controller->saveGUICustomizations();
        $gui_json = $this->readGuiCustomization();
        $this->assertNotNull($gui_json);
        $decoded = json_decode($gui_json, true);
        $found = false;
        foreach ($decoded['gradeables'] as $bucket) {
            foreach ($bucket['ids'] as $g) {
                if ($g['id'] === 'hw2') {
                    $found = true;
                }
            }
        }
        $this->assertTrue($found, 'New gradeable hw2 should be present in gui_customization.json');
    }

    public function testManualCustomizationDetection()
    {
        $content = $this->getSampleCustomizationJson();
        $this->writeManualCustomization($content);
        $this->writeCustomization($content);
        $customization = new RainbowCustomization($this->core);
        $this->assertTrue($customization->usesManualCustomization());
    }
}
