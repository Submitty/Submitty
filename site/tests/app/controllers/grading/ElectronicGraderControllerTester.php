<?php

namespace tests\app\controllers\grading;

use app\controllers\grading\ElectronicGraderController;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\AutogradingConfig;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\Submitter;
use app\models\gradeable\TaGradedGradeable;
use tests\BaseUnitTest;

class ElectronicGraderControllerTester extends BaseUnitTest {
    /**
     * @return GradedGradeable&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockSuggestionGrade(
        string $submitter_id,
        string $anon_id,
        bool $has_submission,
        int $active_version,
        bool $is_auto_complete,
        float $autograding_points,
        bool $has_version_conflict,
        bool $has_active_inquiry,
        bool $has_ta_info,
        bool $is_ta_complete
    ) {
        $submitter = $this->createMockModel(Submitter::class);
        $submitter->method('getId')->willReturn($submitter_id);
        $submitter->method('getAnonId')->willReturn($anon_id);

        $auto_graded_gradeable = $this->createMockModel(AutoGradedGradeable::class);
        $auto_graded_gradeable->method('hasSubmission')->willReturn($has_submission);
        $auto_graded_gradeable->method('getActiveVersion')->willReturn($active_version);
        $auto_graded_gradeable->method('isAutoGradingComplete')->willReturn($is_auto_complete);

        $ta_graded_gradeable = $this->createMockModel(TaGradedGradeable::class);
        $ta_graded_gradeable->method('hasVersionConflict')->willReturn($has_version_conflict);

        $graded_gradeable = $this->createMockModel(GradedGradeable::class);
        $graded_gradeable->method('getSubmitter')->willReturn($submitter);
        $graded_gradeable->method('getAutoGradedGradeable')->willReturn($auto_graded_gradeable);
        $graded_gradeable->method('getAutoGradingScore')->willReturn($autograding_points);
        $graded_gradeable->method('hasActiveGradeInquiry')->willReturn($has_active_inquiry);
        $graded_gradeable->method('hasTaGradingInfo')->willReturn($has_ta_info);
        $graded_gradeable->method('isTaGradingComplete')->willReturn($is_ta_complete);
        $graded_gradeable->method('getOrCreateTaGradedGradeable')->willReturn($ta_graded_gradeable);

        return $graded_gradeable;
    }

    /**
     * @return Gradeable&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockGradeableForSuggestions(string $gradeable_id, float $autograding_max) {
        $autograding_config = $this->createMockModel(AutogradingConfig::class);
        $autograding_config->method('getTotalNonExtraCredit')->willReturn($autograding_max);

        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn($gradeable_id);
        $gradeable->method('hasAutogradingConfig')->willReturn(true);
        $gradeable->method('getAutogradingConfig')->willReturn($autograding_config);

        return $gradeable;
    }

    public function testBuildAiGroupingSuggestionsGroupsByStatusAndAutogradingBucket() {
        $core = $this->createMockCore(['semester' => 's26', 'course' => 'sample']);
        $controller = new ElectronicGraderController($core);

        $gradeable = $this->createMockGradeableForSuggestions('grading_homework', 100.0);
        $grades = [
            $this->createMockSuggestionGrade('student_1', 'anon_1', true, 1, true, 84.0, false, false, true, false),
            $this->createMockSuggestionGrade('student_2', 'anon_2', true, 1, true, 81.0, false, false, true, false),
            $this->createMockSuggestionGrade('student_3', 'anon_3', false, 0, false, 0.0, false, false, false, false),
        ];

        $result = $this->invokeMethod($controller, 'buildAiGroupingSuggestions', $gradeable, $grades, 'id', 'ASC');

        $this->assertEquals(2, $result['group_count']);
        $this->assertEquals(3, $result['submitter_count']);

        $largest_group = $result['groups'][0];
        $this->assertEquals('TA_IN_PROGRESS', $largest_group['status']);
        $this->assertEquals(2, $largest_group['size']);
        $this->assertEquals(['anon_1', 'anon_2'], $largest_group['member_anon_ids']);
        $this->assertContains('Autograding 80-99%', $largest_group['top_signals']);
        $this->assertStringContainsString('/courses/s26/sample/gradeable/grading_homework/grading/grade?', $largest_group['members'][0]['jump_url']);
    }

    public function testBuildAiGroupingSuggestionsHandlesAutogradingPendingAndConflicts() {
        $core = $this->createMockCore(['semester' => 's26', 'course' => 'sample']);
        $controller = new ElectronicGraderController($core);

        $gradeable = $this->createMockGradeableForSuggestions('grading_homework', 100.0);
        $grades = [
            $this->createMockSuggestionGrade('student_1', 'anon_1', true, 1, false, 45.0, false, false, false, false),
            $this->createMockSuggestionGrade('student_2', 'anon_2', true, 1, true, 45.0, true, false, true, false),
        ];

        $result = $this->invokeMethod($controller, 'buildAiGroupingSuggestions', $gradeable, $grades, 'id', 'ASC');

        $this->assertEquals(2, $result['group_count']);
        $this->assertContains($result['groups'][0]['status'], ['AUTOGRADING_PENDING', 'VERSION_CONFLICT']);
        $this->assertContains($result['groups'][1]['status'], ['AUTOGRADING_PENDING', 'VERSION_CONFLICT']);
    }
}
