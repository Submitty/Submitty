<?php

namespace tests\app\models\gradeable;

use app\models\gradeable\AutogradingConfig;
use app\models\gradeable\Gradeable;
use app\libraries\GradeableType;
use app\models\gradeable\GradeableUtils;
use tests\BaseUnitTest;

class GradeableTester extends BaseUnitTest {
    private function buildGradeable($core, ?string $depends_on, ?int $depends_on_points): Gradeable {
        $timezone = new \DateTimeZone('America/New_York');
        $details = [
            'id' => 'lesson_02',
            'title' => 'lesson_02',
            'instructions_url' => '',
            'ta_instructions' => '',
            'type' => GradeableType::ELECTRONIC_FILE,
            'grader_assignment_method' => 0,
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
            'ta_view_start_date' => new \DateTime('1000-01-01', $timezone),
            'grade_start_date' => new \DateTime('9997-01-01', $timezone),
            'grade_due_date' => new \DateTime('9997-01-01', $timezone),
            'grade_released_date' => new \DateTime('9998-01-01', $timezone),
            'team_lock_date' => new \DateTime('1001-01-01', $timezone),
            'submission_open_date' => new \DateTime('1000-01-01', $timezone),
            'submission_due_date' => new \DateTime('1001-01-01', $timezone),
            'late_days' => 2,
            'grade_inquiry_start_date' => new \DateTime('9998-01-01', $timezone),
            'grade_inquiry_due_date' => new \DateTime('9998-01-01', $timezone),
            'allowed_minutes' => null,
            'depends_on' => $depends_on,
            'depends_on_points' => $depends_on_points,
            'allow_custom_marks' => true,
            'any_manual_grades' => false,
            'score_notifications_sent' => 0,
            'release_notifications_sent' => false
        ];
        return new Gradeable($core, $details);
    }

    private function mockDependentGradeable(string $title, int $max_points) {
        // getTotalNonHiddenNonExtraCredit() is only declared via a @method docblock
        // (dispatched through AbstractModel::__call), so createMock() can't see it as
        // a mockable method. addMethods() explicitly adds it to the generated mock.
        $autograding_config = $this->getMockBuilder(AutogradingConfig::class)
            ->disableOriginalConstructor()
            ->addMethods(['getTotalNonHiddenNonExtraCredit'])
            ->getMock();
        $autograding_config->method('getTotalNonHiddenNonExtraCredit')->willReturn($max_points);

        $dependent = $this->createMock(Gradeable::class);
        $dependent->method('getTitle')->willReturn($title);
        $dependent->method('getAutogradingConfig')->willReturn($autograding_config);

        return $dependent;
    }

    public function testGetPrerequisiteBelowMaxPointsShowsOrMore() {
        $dependent = $this->mockDependentGradeable('Lesson 01: Types Variables', 10);
        $core = $this->createMockCore([], [], ['getGradeableConfig' => $dependent]);

        $gradeable = $this->buildGradeable($core, 'lesson_01', 6);

        $this->assertEquals(
            'Lesson 01: Types Variables first with a score of 6 or more point(s)',
            $gradeable->getPrerequisite()
        );
    }

    public function testGetPrerequisiteAtMaxPointsShowsExactScore() {
        $dependent = $this->mockDependentGradeable('Lesson 01: Types Variables', 6);
        $core = $this->createMockCore([], [], ['getGradeableConfig' => $dependent]);

        $gradeable = $this->buildGradeable($core, 'lesson_01', 6);

        $this->assertEquals(
            'Lesson 01: Types Variables first with a score of 6 point(s)',
            $gradeable->getPrerequisite()
        );
    }

    public function testGetPrerequisiteNoAutogradingConfigFallsBackToExactScore() {
        $dependent = $this->createMock(Gradeable::class);
        $dependent->method('getTitle')->willReturn('Lesson 01: Types Variables');
        $dependent->method('getAutogradingConfig')->willReturn(null);
        $core = $this->createMockCore([], [], ['getGradeableConfig' => $dependent]);

        $gradeable = $this->buildGradeable($core, 'lesson_01', 6);

        $this->assertEquals(
            'Lesson 01: Types Variables first with a score of 6 point(s)',
            $gradeable->getPrerequisite()
        );
    }

    public function testGetPrerequisiteReturnsEmptyStringWhenNoDependency() {
        $core = $this->createMockCore();
        $gradeable = $this->buildGradeable($core, null, null);

        $this->assertEquals('', $gradeable->getPrerequisite());
    }
}
