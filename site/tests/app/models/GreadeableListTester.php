<?php

namespace tests\app\models;

use app\libraries\Core;
use app\libraries\database\DatabaseQueries;
use app\libraries\GradeableType;
use app\models\Config;
use app\models\Gradeable;
use app\models\GradeableList;
use app\models\User;
use tests\BaseUnitTest;

class GreadeableListTester extends BaseUnitTest {

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

    public function testSubmittableAdmin() {
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

    public function testSubmittableGrader() {
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

    public function testSubmittableStudent() {
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
        $core = $this->createMock(Core::class);
        $config = $this->createMockModel(Config::class);
        $config->method('getTimezone')->willReturn(new \DateTimeZone('America/New_York'));
        $core->method('getConfig')->willReturn($config);

        $queries = $this->createMock(DatabaseQueries::class);

        $queries->method('getAllGradeables')->willReturn($gradeables);
        $core->method('getQueries')->willReturn($queries);

        $user = $this->createMockModel(User::class);
        $user->method('getId')->willReturn("testUser");
        $user->method('accessGrading')->willReturn($access_grading);
        $user->method('accessAdmin')->willReturn($access_admin);

        $core->method('getUser')->willReturn($user);

        return $core;
    }

    /**
     * @param $id
     * @param $type
     * @param $ta_view_date
     * @param $open_date
     * @param $due_date
     * @param $grade_start_date
     * @param $grade_released_date
     * @param $ta_grading
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function mockGradeable($id, $type, $ta_view_date, $open_date, $due_date, $grade_start_date,
                                   $grade_released_date, $ta_grading = true) {
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn($id);
        $gradeable->method('getType')->willReturn($type);
        $gradeable->method('useTAGrading')->willReturn($ta_grading);
        $temp = array('ta_view_date' => 'getTAViewDate', 'open_date' => 'getOpenDate',
                      'due_date' => 'getDueDate', 'grade_start_date' => 'getGradeStartDate',
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
