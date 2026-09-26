<?php

namespace tests\app\models\gradeable;

use app\libraries\Core;
use app\libraries\GradeableType;
use app\libraries\database\DatabaseQueries;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradeableUtils;
use app\models\Config;
use app\models\User;
use tests\BaseUnitTest;

class GradeableTester extends BaseUnitTest {
    public function testWithdrawnStudentExcludedFromAllGradingSections() {
        $core = $this->getCore();

        $active_user = new User($core, [
            'user_id' => 'active_student',
            'user_givenname' => 'Active',
            'user_familyname' => 'Student',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => '',
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'user_group' => 4,
            'registration_type' => 'active',
            'registration_section' => '1'
        ]);

        $withdrawn_user = new User($core, [
            'user_id' => 'withdrawn_student',
            'user_givenname' => 'Withdrawn',
            'user_familyname' => 'Student',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => '',
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'user_group' => 4,
            'registration_type' => 'withdrawn',
            'registration_section' => '1'
        ]);

        $mock_queries = $this->createMock(DatabaseQueries::class);
        $mock_queries->method('getAllUsers')->willReturn([$active_user, $withdrawn_user]);
        $mock_queries->method('getGradersForRegistrationSections')->willReturn(['1' => []]);
        $core->setQueries($mock_queries);

        $gradeable = $this->mockGradeable(
            $core,
            "test_gradeable",
            GradeableType::ELECTRONIC_FILE,
            '1000-01-01',
            '1001-01-01',
            '1002-01-01',
            '1003-01-01',
            '9999-01-01',
            0
        );

        $sections = $gradeable->getAllGradingSections();

        $all_user_ids = [];
        foreach ($sections as $section) {
            foreach ($section->getSubmitters() as $submitter) {
                $all_user_ids[] = $submitter->getId();
            }
        }

        $this->assertContains('active_student', $all_user_ids);
        $this->assertNotContains('withdrawn_student', $all_user_ids);
    }

    public function testWithdrawnStudentExcludedFromGradingSectionsForUser() {
        $core = $this->getCore();

        $active_user = new User($core, [
            'user_id' => 'active_student',
            'user_givenname' => 'Active',
            'user_familyname' => 'Student',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => '',
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'user_group' => 4,
            'registration_type' => 'active',
            'registration_section' => '1'
        ]);

        $withdrawn_user = new User($core, [
            'user_id' => 'withdrawn_student',
            'user_givenname' => 'Withdrawn',
            'user_familyname' => 'Student',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => '',
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'user_group' => 4,
            'registration_type' => 'withdrawn',
            'registration_section' => '1'
        ]);

        $grader = new User($core, [
            'user_id' => 'ta1',
            'user_givenname' => 'TA',
            'user_familyname' => 'Grader',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => '',
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'user_group' => 2,
            'grading_registration_sections' => ['1']
        ]);

        $mock_queries = $this->createMock(DatabaseQueries::class);
        $mock_queries->method('getUsersByRegistrationSections')->willReturn([$active_user, $withdrawn_user]);
        $mock_queries->method('getGradersForRegistrationSections')->willReturn(['1' => []]);
        $core->setQueries($mock_queries);

        $gradeable = $this->mockGradeable(
            $core,
            "test_gradeable_2",
            GradeableType::ELECTRONIC_FILE,
            '1000-01-01',
            '1001-01-01',
            '1002-01-01',
            '1003-01-01',
            '9999-01-01',
            1
        );

        $sections = $gradeable->getGradingSectionsForUser($grader);

        $all_user_ids = [];
        foreach ($sections as $section) {
            foreach ($section->getSubmitters() as $submitter) {
                $all_user_ids[] = $submitter->getId();
            }
        }

        $this->assertContains('active_student', $all_user_ids);
        $this->assertNotContains('withdrawn_student', $all_user_ids);
    }

    private function getCore() {
        $core = new Core();
        $user = new User($core, [
            'user_id' => 'test',
            'user_givenname' => 'Test',
            'user_familyname' => 'Person',
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => '',
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'user_group' => 1
        ]);
        $core->setUser($user);
        $core->setConfig(new Config($core));
        $core->setTesting(true);
        return $core;
    }

    private function mockGradeable(
        Core $core,
        $id,
        $type,
        $ta_view_start_date,
        $submission_open_date,
        $submission_due_date,
        $grade_start_date,
        $grade_released_date,
        $grader_assignment_method = 0
    ) {
        $timezone = new \DateTimeZone('America/New_York');
        $details = [
            'id' => $id,
            'title' => $id,
            'instructions_url' => '',
            'ta_instructions' => '',
            'type' => $type,
            'grader_assignment_method' => $grader_assignment_method,
            'min_grading_group' => 3,
            'syllabus_bucket' => 'homework',
            'autograding_config_path' => '/path/to/autograding',
            'vcs' => false,
            'using_subdirectory' => false,
            'vcs_subdirectory' => '',
            'vcs_partial_path' => '',
            'vcs_host_type' => GradeableUtils::VCS_TYPE_NONE,
            'team_assignment' => false,
            'team_size_max' => 1,
            'ta_grading' => true,
            'student_view' => true,
            'student_view_after_grades' => false,
            'student_download' => true,
            'student_submit' => true,
            'has_due_date' => true,
            'has_release_date' => true,
            'peer_grading' => false,
            'peer_grade_set' => false,
            'late_submission_allowed' => true,
            'precision' => 0.5,
            'grade_inquiry_allowed' => true,
            'grade_inquiry_per_component_allowed' => true,
            'discussion_based' => false,
            'discussion_thread_ids' => '',
            'ta_view_start_date' => new \DateTime($ta_view_start_date, $timezone),
            'grade_start_date' => new \DateTime($grade_start_date, $timezone),
            'grade_due_date' => new \DateTime($grade_start_date, $timezone),
            'grade_released_date' => new \DateTime($grade_released_date, $timezone),
            'team_lock_date' => new \DateTime($submission_due_date, $timezone),
            'submission_open_date' => new \DateTime($submission_open_date, $timezone),
            'submission_due_date' => $submission_due_date === null ? null : new \DateTime($submission_due_date, $timezone),
            'late_days' => 2,
            'grade_inquiry_start_date' => new \DateTime($grade_released_date, $timezone),
            'grade_inquiry_due_date' => new \DateTime($grade_released_date, $timezone),
            'allowed_minutes' => null,
            'depends_on' => null,
            'depends_on_points' => null,
            'allow_custom_marks' => true,
            'any_manual_grades' => false,
            'score_notifications_sent' => 0,
            'release_notifications_sent' => false
        ];

        return new Gradeable($core, $details);
    }
}
