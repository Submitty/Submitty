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

class ReportControllerTester extends BaseUnitTest {
    private $tmp_dir;
    private $course_path;
    private $rainbow_dir;
    private $core;

    protected function setUp(): void {
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
            'getRegistrationSections' => [
                ['sections_registration_id' => '1'],
                ['sections_registration_id' => '2'],
            ]
        ];
        $this->core = $this->createMockCore($config, $user_config, $queries);

        // Mock the course database connection to avoid null query errors
        $mock_db = $this->createMock(AbstractDatabase::class);
        $mock_db->method('query');
        $this->core->setCourseDatabase($mock_db);

        $mock_queries = $this->createMockModel(DatabaseQueries::class);
        $mock_queries->method('getGradeableConfigs')->willReturn([
            $this->createMockGradeable($this->getGradeableDetails('hw1', 'Homework 1')['id'], $this->getGradeableDetails('hw1', 'Homework 1')['title'])
        ]);
        $mock_queries->method('getRegistrationSections')->willReturn([
            ['sections_registration_id' => '1'],
            ['sections_registration_id' => '2'],
        ]);
        $this->core->setQueries($mock_queries);
    }

    protected function tearDown(): void {
        FileUtils::recursiveRmdir($this->tmp_dir);
        parent::tearDown();
    }

    private function writeManualCustomization($content) {
        file_put_contents($this->rainbow_dir . '/manual_customization.json', $content);
    }

    private function writeCustomization($content) {
        file_put_contents($this->rainbow_dir . '/customization.json', $content);
    }

    private function readGuiCustomization() {
        $file = $this->rainbow_dir . '/gui_customization.json';
        return file_exists($file) ? file_get_contents($file) : null;
    }

    private function readCustomization() {
        $file = $this->rainbow_dir . '/customization.json';
        return file_exists($file) ? file_get_contents($file) : null;
    }

    private function getSampleCustomizationJson($gradeables = null) {
        $json = [
            'section' => [ '1' => '1', '2' => '2' ],
            'omit_section_from_stats' => ['1'],
            'display_benchmark' => ['average', 'lowest_a-'],
            'messages' => ['Hello!'],
            'display' => ['grade_summary', 'grade_details'],
            'benchmark_percent' => [ 'lowest_a-' => 0.9 ],
            'final_cutoff' => [ 'A' => 93, 'A-' => 90 ],
            'gradeables' => $gradeables ?? [
                [
                    'type' => 'homework',
                    'count' => 1,
                    'remove_lowest' => 0,
                    'percent' => 1,
                    'ids' => [
                        [
                            'max' => 10,
                            'release_date' => '2024-08-22 13:38:52-0400',
                            'id' => 'hw1'
                        ]
                    ]
                ]
            ],
            'plagiarism' => [],
            'manual_grade' => [],
            'warning' => []
        ];
        return json_encode($json, JSON_PRETTY_PRINT);
    }

    private function getGradeableDetails($id, $title = null) {
        return [
            'id' => $id,
            'title' => $title ?? $id,
            'instructions_url' => '',
            'type' => 0,
            'grader_assignment_method' => 1,
            'min_grading_group' => 1,
            'syllabus_bucket' => 'homework',
            'ta_instructions' => '',
            'any_manual_grades' => false,
            'autograding_config_path' => '/tmp/autograding_config.json',
            'vcs' => false,
            'vcs_subdirectory' => '',
            'using_subdirectory' => false,
            'vcs_partial_path' => '',
            'vcs_host_type' => 0,
            'team_assignment' => false,
            'team_size_max' => 1,
            'ta_grading' => false,
            'student_view' => false,
            'student_view_after_grades' => false,
            'student_download' => false,
            'student_submit' => false,
            'has_due_date' => false,
            'has_release_date' => true,
            'late_submission_allowed' => false,
            'precision' => 1.0,
            'grade_inquiry_allowed' => false,
            'grade_inquiry_per_component_allowed' => false,
            'discussion_based' => false,
            'discussion_thread_ids' => '',
            'allow_custom_marks' => false,
            'depends_on' => '',
            'depends_on_points' => 0,
            'notifications_sent' => 0,
            'active_grade_inquiries_count' => 0,
            // Dates
            'ta_view_start_date' => '2024-08-01 00:00:00-0400',
            'team_lock_date' => '2024-08-01 00:00:00-0400',
            'submission_open_date' => '2024-08-01 00:00:00-0400',
            'submission_due_date' => '2024-08-10 00:00:00-0400',
            'grade_start_date' => '2024-08-11 00:00:00-0400',
            'grade_due_date' => '2024-08-15 00:00:00-0400',
            'grade_released_date' => '2024-08-20 00:00:00-0400',
            'grade_inquiry_start_date' => '2024-08-21 00:00:00-0400',
            'grade_inquiry_due_date' => '2024-08-22 00:00:00-0400',
        ];
    }

    /**
     * Helper to create a mock Gradeable object for testing.
     */
    private function createMockGradeable($id = 'test', $title = 'Test Gradeable') {
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn($id);
        $gradeable->method('getTitle')->willReturn($title);
        $gradeable->method('hasReleaseDate')->willReturn(true);
        $gradeable->method('getGradeReleasedDate')->willReturn(new \DateTime('2024-08-20 00:00:00-0400'));
        $gradeable->method('getSubmissionOpenDate')->willReturn(new \DateTime('2024-08-01 00:00:00-0400'));
        return $gradeable;
    }

    /**
     * @runInSeparateProcess
     */
    public function testSaveGUICustomizationsGuiMode() {
        // No manual customization exists
        $mock_queries = $this->core->getQueries();
        $mock_queries->method('getGradeableConfigs')->willReturn([
            $this->createMockGradeable($this->getGradeableDetails('hw1', 'Homework 1')['id'], $this->getGradeableDetails('hw1', 'Homework 1')['title'])
        ]);
        $controller = new ReportController($this->core);
        $response = $controller->saveGUICustomizations();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('success', $response->json['status']);
        $gui_json = $this->readGuiCustomization();
        $this->assertNotNull($gui_json);
        $decoded = json_decode($gui_json, true);
        $this->assertArrayHasKey('gradeables', $decoded);
        $this->assertNotEmpty($decoded['gradeables']);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSaveGUICustomizationsManualMode() {
        // Write manual customization and customization.json to match
        $content = $this->getSampleCustomizationJson();
        $this->writeManualCustomization($content);
        $this->writeCustomization($content);
        $mock_queries = $this->core->getQueries();
        $mock_queries->method('getGradeableConfigs')->willReturn([
            $this->createMockGradeable($this->getGradeableDetails('hw1', 'Homework 1')['id'], $this->getGradeableDetails('hw1', 'Homework 1')['title'])
        ]);
        $controller = new ReportController($this->core);
        $response = $controller->saveGUICustomizations();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('fail', $response->json['status']);
        $this->assertStringContainsString('Manual customization', $response->json['message']);
    }

    /**
     * @runInSeparateProcess
     */
    public function testSaveGUICustomizationsAddGradeable() {
        // Start with one gradeable, then add another
        $mock_queries = $this->core->getQueries();
        $mock_queries->method('getGradeableConfigs')->willReturn([
            $this->createMockGradeable($this->getGradeableDetails('hw1', 'Homework 1')['id'], $this->getGradeableDetails('hw1', 'Homework 1')['title'])
        ]);
        $controller = new ReportController($this->core);
        $controller->saveGUICustomizations();
        // Now add a new gradeable
        $mock_queries->method('getGradeableConfigs')->willReturn([
            $this->createMockGradeable($this->getGradeableDetails('hw1', 'Homework 1')['id'], $this->getGradeableDetails('hw1', 'Homework 1')['title']),
            $this->createMockGradeable($this->getGradeableDetails('hw2', 'Homework 2')['id'], $this->getGradeableDetails('hw2', 'Homework 2')['title'])
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

    public function testManualCustomizationDetection() {
        $content = $this->getSampleCustomizationJson();
        $this->writeManualCustomization($content);
        $this->writeCustomization($content);
        $customization = new RainbowCustomization($this->core);
        $this->assertTrue($customization->usesManualCustomization());
    }
}
