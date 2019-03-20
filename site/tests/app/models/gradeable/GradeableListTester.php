<?php

namespace tests\app\models\gradeable;

use app\libraries\GradeableType;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradeableList;
use tests\BaseUnitTest;

class GradeableListTester extends BaseUnitTest {

    public function testFullList() {
        $gradeables = array();
        $gradeables['01_future_homework_no_ta'] = $this->mockGradeable("01_future_homework_no_ta",
            GradeableType::ELECTRONIC_FILE, '9995-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['02_future_homework'] = $this->mockGradeable("02_future_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['03_open_homework'] = $this->mockGradeable("03_open_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['04_closed_homework'] = $this->mockGradeable("04_closed_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '1002-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['05_grading_homework'] = $this->mockGradeable("05_grading_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '1002-01-01', '1003-01-01',
            '9999-01-01');
        $gradeables['06_graded_homework'] = $this->mockGradeable("06_graded_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '1002-01-01', '1003-01-01',
            '1004-01-01');

        $gradeables['11_future_numeric_no_ta'] = $this->mockGradeable("11_future_numeric_no_ta",
            GradeableType::NUMERIC_TEXT, '9995-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['12_future_numeric'] = $this->mockGradeable("12_future_numeric",
            GradeableType::NUMERIC_TEXT, '1000-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['13_grading_numeric'] = $this->mockGradeable("13_grading_numeric",
            GradeableType::NUMERIC_TEXT, '1000-01-01', '1001-01-01', '9997-01-01', '1003-01-01',
            '9999-01-01');
        $gradeables['14_graded_numeric'] = $this->mockGradeable("14_graded_numeric",
            GradeableType::NUMERIC_TEXT, '1000-01-01', '1001-01-01', '1002-01-01', '1003-03-01',
            '1004-02-01');

        $gradeables['07_future_checkpoint_no_ta'] = $this->mockGradeable("07_future_checkpoint_no_ta",
            GradeableType::CHECKPOINTS, '9995-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['08_future_checkpoint'] = $this->mockGradeable("08_future_checkpoint",
            GradeableType::CHECKPOINTS, '1000-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['09_grading_lab'] = $this->mockGradeable("09_grading_lab",
            GradeableType::CHECKPOINTS, '1000-01-01', '1001-01-01', '9997-01-01', '1003-02-01',
            '9999-01-01');
        $gradeables['10_graded_lab'] = $this->mockGradeable("10_graded_lab",
            GradeableType::CHECKPOINTS, '1000-01-01', '1001-01-01', '1002-01-01', '1003-01-01',
            '1004-03-01');

        $core = $this->mockCore($gradeables);

        $list = new GradeableList($core);
        $this->assertCount(count($gradeables), $list->getGradeables());
        $this->assertEquals(count($gradeables), $list->getGradeableCount());
        $this->assertEquals(6, $list->getGradeableCount(GradeableType::ELECTRONIC_FILE));
        $this->assertEquals(4, $list->getGradeableCount(GradeableType::CHECKPOINTS));
        $this->assertEquals(4, $list->getGradeableCount(GradeableType::NUMERIC_TEXT));

        $expected = array('01_future_homework_no_ta', '07_future_checkpoint_no_ta', '11_future_numeric_no_ta');
        $actual = $list->getFutureGradeables();
        $this->assertCount(count($expected), $actual);
        $this->assertEquals($expected, array_keys($actual));
        foreach ($expected as $key) {
            $this->assertEquals($gradeables[$key], $actual[$key]);
        }

        $expected = array('02_future_homework', '08_future_checkpoint', '12_future_numeric');
        $actual = $list->getBetaGradeables();
        $this->assertCount(count($expected), $actual);
        $this->assertEquals($expected, array_keys($actual));
        foreach ($expected as $key) {
            $this->assertEquals($gradeables[$key], $actual[$key]);
        }

        $actual = $list->getOpenGradeables();
        $this->assertCount(1, $actual);
        $this->assertArrayHasKey('03_open_homework', $actual);
        $this->assertEquals($gradeables['03_open_homework'], $actual['03_open_homework']);

        $actual = $list->getClosedGradeables();
        $this->assertCount(1, $actual);
        $this->assertArrayHasKey('04_closed_homework', $actual);
        $this->assertEquals($gradeables['04_closed_homework'], $actual['04_closed_homework']);

        $expected = array('09_grading_lab', '05_grading_homework', '13_grading_numeric');
        $actual = $list->getGradingGradeables();
        $this->assertCount(count($expected), $actual);
        $this->assertEquals($expected, array_keys($actual));
        foreach ($expected as $key) {
            $this->assertEquals($gradeables[$key], $actual[$key]);
        }

        $expected = array('10_graded_lab', '14_graded_numeric', '06_graded_homework');
        $actual = $list->getGradedGradeables();
        $this->assertCount(count($expected), $actual);
        $this->assertEquals($expected, array_keys($actual));
        foreach ($expected as $key) {
            $this->assertEquals($gradeables[$key], $actual[$key]);
        }
    }

    public function testSubmittableHasDueAdmin() {
        $gradeables = array();
        $gradeables['01_future_homework_no_ta'] = $this->mockGradeable("01_future_homework_no_ta",
            GradeableType::ELECTRONIC_FILE, '9995-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['02_future_homework'] = $this->mockGradeable("02_future_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['03_open_homework'] = $this->mockGradeable("03_open_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['04_closed_homework'] = $this->mockGradeable("04_closed_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '1002-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['05_grading_homework'] = $this->mockGradeable("05_grading_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '1002-01-01', '1003-01-01',
            '9999-01-01');
        $gradeables['06_graded_homework'] = $this->mockGradeable("06_graded_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '1002-01-01', '1003-01-01',
            '1004-01-01');
        $gradeables['07_future_checkpoint_no_ta'] = $this->mockGradeable("07_future_checkpoint_no_ta",
            GradeableType::CHECKPOINTS, '9995-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');

        $core = $this->mockCore($gradeables);
        $list = new GradeableList($core);
        $this->assertCount(6, $list->getSubmittableElectronicGradeables());
    }

    public function testSubmittableHasDueGrader() {
        $gradeables = array();
        $gradeables['01_future_homework_no_ta'] = $this->mockGradeable("01_future_homework_no_ta",
            GradeableType::ELECTRONIC_FILE, '9995-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['02_future_homework'] = $this->mockGradeable("02_future_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['03_open_homework'] = $this->mockGradeable("03_open_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['04_closed_homework'] = $this->mockGradeable("04_closed_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '1002-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['05_grading_homework'] = $this->mockGradeable("05_grading_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '1002-01-01', '1003-01-01',
            '9999-01-01');
        $gradeables['06_graded_homework'] = $this->mockGradeable("06_graded_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '1002-01-01', '1003-01-01',
            '1004-01-01');
        $gradeables['07_future_checkpoint_no_ta'] = $this->mockGradeable("07_future_checkpoint_no_ta",
            GradeableType::CHECKPOINTS, '9995-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');

        $core = $this->mockCore($gradeables, false);
        $list = new GradeableList($core);
        $this->assertCount(5, $list->getSubmittableElectronicGradeables());
    }

    public function testSubmittableHasDueStudent() {
        $gradeables = array();
        $gradeables['01_future_homework_no_ta'] = $this->mockGradeable("01_future_homework_no_ta",
            GradeableType::ELECTRONIC_FILE, '9995-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['02_future_homework'] = $this->mockGradeable("02_future_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['03_open_homework'] = $this->mockGradeable("03_open_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['04_closed_homework'] = $this->mockGradeable("04_closed_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '1002-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['05_grading_homework'] = $this->mockGradeable("05_grading_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '1002-01-01', '1003-01-01',
            '9999-01-01');
        $gradeables['06_graded_homework'] = $this->mockGradeable("06_graded_homework",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '1002-01-01', '1003-01-01',
            '1004-01-01');
        $gradeables['07_future_checkpoint_no_ta'] = $this->mockGradeable("07_future_checkpoint_no_ta",
            GradeableType::CHECKPOINTS, '9995-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');

        $core = $this->mockCore($gradeables, false, false);
        $list = new GradeableList($core);
        $this->assertCount(4, $list->getSubmittableElectronicGradeables());
    }

    public function testSubmittableNoDueGrader() {
        $gradeables = array();
        $gradeables['01_future_no_due'] = $this->mockGradeable("01_future_no_due",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01', true, true, false, false);
        $gradeables['02_grading_no_due'] = $this->mockGradeable("02_grading_no_due",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '9997-01-01', '1003-02-01',
            '9999-01-01', true, true, false, false);
        $gradeables['03_ta_submit_no_due'] = $this->mockGradeable("03_ta_submit_no_due",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '9997-01-01', '1003-02-01',
            '9999-01-01', true, false, false, false);

        $core = $this->mockCore($gradeables, false, true);
        $list = new GradeableList($core);
        $this->assertCount(3, $list->getSubmittableElectronicGradeables());

        $actual = $list->getFutureGradeables();
        $this->assertCount(0, $actual);

        $actual = $list->getBetaGradeables();
        $this->assertCount(0, $actual);

        $actual = $list->getOpenGradeables();
        $this->assertCount(1, $actual);
        $this->assertArrayHasKey('01_future_no_due', $actual);
        $this->assertEquals($gradeables['01_future_no_due'], $actual['01_future_no_due']);

        $actual = $list->getClosedGradeables();
        $this->assertCount(0, $actual);

        $expected = array('02_grading_no_due', '03_ta_submit_no_due');
        $actual = $list->getGradingGradeables();
        $this->assertCount(count($expected), $actual);
        $this->assertEquals($expected, array_keys($actual));
        foreach ($expected as $key) {
            $this->assertEquals($gradeables[$key], $actual[$key]);
        }

        $actual = $list->getGradedGradeables();
        $this->assertCount(0, $actual);
    }

    public function testSubmittableNoDueStudent() {
        $gradeables = array();
        $gradeables['01_no_submit_no_due'] = $this->mockGradeable("01_no_submit_no_due",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '9997-01-01', '1003-02-01',
            '9999-01-01', true, true, false, false);
        $gradeables['02_submitted_no_due'] = $this->mockGradeable("02_submitted_no_due",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '9997-01-01', '1003-02-01',
            '9999-01-01', true, true, false, true);
        $gradeables['03_ta_submit_no_due'] = $this->mockGradeable("03_ta_submit_no_due",
            GradeableType::ELECTRONIC_FILE, '1000-01-01', '1001-01-01', '9997-01-01', '1003-02-01',
            '9999-01-01', true, false, false, false);

        $core = $this->mockCore($gradeables, false, false);
        $list = new GradeableList($core);
        $this->assertCount(3, $list->getSubmittableElectronicGradeables());

        $actual = $list->getFutureGradeables();
        $this->assertCount(0, $actual);

        $actual = $list->getBetaGradeables();
        $this->assertCount(0, $actual);

        $actual = $list->getOpenGradeables();
        $this->assertCount(1, $actual);
        $this->assertArrayHasKey('01_no_submit_no_due', $actual);
        $this->assertEquals($gradeables['01_no_submit_no_due'], $actual['01_no_submit_no_due']);

        $actual = $list->getClosedGradeables();
        $this->assertCount(1, $actual);
        $this->assertArrayHasKey('02_submitted_no_due', $actual);
        $this->assertEquals($gradeables['02_submitted_no_due'], $actual['02_submitted_no_due']);

        $actual = $list->getGradingGradeables();
        $this->assertCount(1, $actual);
        $this->assertArrayHasKey('03_ta_submit_no_due', $actual);
        $this->assertEquals($gradeables['03_ta_submit_no_due'], $actual['03_ta_submit_no_due']);

        $actual = $list->getGradedGradeables();
        $this->assertCount(0, $actual);
    }

    public function testNoSubmittableGradeables() {
        $gradeables = array();
        $gradeables['07_future_checkpoint_no_ta'] = $this->mockGradeable("07_future_checkpoint_no_ta",
            GradeableType::CHECKPOINTS, '9995-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');

        $core = $this->mockCore($gradeables);
        $list = new GradeableList($core);
        $this->assertCount(0, $list->getSubmittableElectronicGradeables());
    }

    public function testGetGradeable() {
        $gradeables = array();
        $gradeables['01_electronic'] = $this->mockGradeable("01_electronic",
            GradeableType::ELECTRONIC_FILE, '9995-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');
        $gradeables['02_checkpoint'] = $this->mockGradeable("02_checkpoint",
            GradeableType::CHECKPOINTS, '9995-01-01', '9996-01-01', '9997-01-01', '9998-01-01',
            '9999-01-01');

        $core = $this->mockCore($gradeables);
        $list = new GradeableList($core);

        $gradeable = $list->getGradeable('01_electronic');
        $this->assertNotNull($gradeable);
        $this->assertEquals($gradeables['01_electronic'], $gradeable);

        $gradeable = $list->getGradeable('02_checkpoint');
        $this->assertNotNull($gradeable);
        $this->assertEquals($gradeables['02_checkpoint'], $gradeable);

        $gradeable = $list->getGradeable('01_electronic', GradeableType::ELECTRONIC_FILE);
        $this->assertNotNull($gradeable);
        $this->assertEquals($gradeables['01_electronic'], $gradeable);

        $gradeable = $list->getGradeable('01_electronic', GradeableType::CHECKPOINTS);
        $this->assertNull($gradeable);

        $gradeable = $list->getGradeable('02_checkpoint', GradeableType::CHECKPOINTS);
        $this->assertNotNull($gradeable);
        $this->assertEquals($gradeables['02_checkpoint'], $gradeable);

        $gradeable = $list->getGradeable('02_checkpoint', GradeableType::NUMERIC_TEXT);
        $this->assertNull($gradeable);
    }

    private function mockCore($gradeables, $access_admin = true, $access_grading = true) {
        $config_values = array();
        $user_config = array('access_admin'=>$access_admin, 'access_grading'=>$access_grading);
        $queries = array('getGradeableConfigs'=>$gradeables);

        return $this->createMockCore($config_values, $user_config, $queries);
    }

    /**
     * @param $id
     * @param $type
     * @param $ta_view_start_date
     * @param $submission_open_date
     * @param $submission_due_date
     * @param $grade_start_date
     * @param $grade_released_date
     * @param $ta_grading
     * @param $student_submit
     * @param $has_due_date
     * @param $has_submission, from perspective of the user
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function mockGradeable($id, $type, $ta_view_start_date, $submission_open_date, $submission_due_date, $grade_start_date,
                                   $grade_released_date, $ta_grading = true, $student_submit = true, $has_due_date = true, $has_submission = true) {
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn($id);
        $gradeable->method('getType')->willReturn($type);
        $gradeable->method('isTaGrading')->willReturn($ta_grading);
        $gradeable->method('isStudentSubmit')->willReturn($student_submit);
        $gradeable->method('hasDueDate')->willReturn($has_due_date);
        $gradeable->method('hasSubmission')->willReturn($has_submission);
        $temp = array('ta_view_start_date' => 'getTaViewStartDate', 'submission_open_date' => 'getSubmissionOpenDate',
                      'submission_due_date' => 'getSubmissionDueDate', 'grade_start_date' => 'getGradeStartDate',
                      'grade_released_date' => 'getGradeReleasedDate');
        foreach ($temp as $return => $method) {
            $return = $$return;
            if (is_string($return)) {
                $return = new \DateTime($return);
            }
            $gradeable->method($method)->willReturn($return);
        }
        return $gradeable;
    }
}
