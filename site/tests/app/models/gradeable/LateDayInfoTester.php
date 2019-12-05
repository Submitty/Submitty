<?php

namespace tests\app\models\gradeable;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\AutoGradedVersion;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\LateDayInfo;
use app\models\gradeable\Submitter;
use app\models\User;
use tests\BaseUnitTest;

class LateDayInfoTester extends BaseUnitTest {

    protected function makeLateDayInfo(string $due_date, int $late_days, string $submission_date, int $late_day_exception, int $late_days_remaining) {
        $core = $this->createMock(Core::class);

        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getSubmissionDueDate')->willReturn(new \DateTime($due_date));
        $gradeable->method('getLateDays')->willReturn($late_days);

        $auto_graded_gradeable = $this->createMockModel(AutoGradedGradeable::class);
        if ($submission_date !== '') {
            $auto_graded_version = $this->createMockModel(AutoGradedVersion::class);
            $auto_graded_version->method('getSubmissionTime')->willReturn(new \DateTime($submission_date));
            $auto_graded_version->method('getDaysLate')->willReturn(DateUtils::calculateDayDiff($due_date, $submission_date));
            $auto_graded_gradeable->method('getActiveVersionInstance')->willReturn($auto_graded_version);
            $auto_graded_gradeable->method('hasActiveVersion')->willReturn(true);
        }
        else {
            $auto_graded_gradeable->method('getActiveVersionInstance')->willReturn(null);
            $auto_graded_gradeable->method('hasActiveVersion')->willReturn(false);
        }

        $user = $this->createMock(User::class);
        $submitter = $this->createMock(Submitter::class);
        $submitter->method('hasUser')->willReturn(true);

        $graded_gradeable = $this->createMockModel(GradedGradeable::class);
        $graded_gradeable->method('getGradeable')->willReturn($gradeable);
        $graded_gradeable->method('getSubmitter')->willReturn($submitter);
        $graded_gradeable->method('getAutoGradedGradeable')->willReturn($auto_graded_gradeable);
        $graded_gradeable->method('getLateDayException')->willReturn($late_day_exception);

        return new LateDayInfo($core, $user, $graded_gradeable, $late_days_remaining);
    }

    public function testNegativeLateDaysRemaining() {
        $core = $this->createMock(Core::class);
        $user = $this->createMock(User::class);
        $submitter = $this->createMock(Submitter::class);
        $submitter->method('hasUser')->willReturn(true);
        $graded_gradeable = $this->createMockModel(GradedGradeable::class);
        $graded_gradeable->method('getSubmitter')->willReturn($submitter);

        $this->expectException(\InvalidArgumentException::class);
        $ldi = new LateDayInfo($core, $user, $graded_gradeable, -1);
    }

    public function testBadUserForSubmitter() {
        $core = $this->createMock(Core::class);
        $user = $this->createMock(User::class);
        $submitter = $this->createMock(Submitter::class);
        $submitter->method('hasUser')->willReturn(false);
        $graded_gradeable = $this->createMockModel(GradedGradeable::class);
        $graded_gradeable->method('getSubmitter')->willReturn($submitter);

        $this->expectException(\InvalidArgumentException::class);
        $ldi = new LateDayInfo($core, $user, $graded_gradeable, 0);
    }

    public function testGetLateDaysAllowed() {
        $due_date = '10-10-2010 11:59:59';

        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date, 0, 2);
        $this->assertEquals(2, $ldi->getLateDaysAllowed(), "Late days remaining less than gradeable late days");

        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date, 0, 4);
        $this->assertEquals(3, $ldi->getLateDaysAllowed(), "Late days remaining more than gradeable late days");

        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date, 4, 3);
        $this->assertEquals(7, $ldi->getLateDaysAllowed(), 'Late day exception');

        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date, 4, 5);
        $this->assertEquals(7, $ldi->getLateDaysAllowed(), 'Late day exception, remaining more than gradeable late days');

        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date, 4, 0);
        $this->assertEquals(4, $ldi->getLateDaysAllowed(), 'Only late day exception');
    }

    public function testGetLateDayException() {
        $due_date = '10-10-2010 11:59:59';
        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date, 10, 1);
        $this->assertEquals(10, $ldi->getLateDayException(), "Late day exception fetch");
    }

    public function testGetStatusAndMesage() {
        $due_date_minus_d1 = '10-9-2010 11:00:00';
        $due_date = '10-10-2010 11:59:59';
        $due_date_s1 = '11-10-2010 00:00:00';
        $due_date_d2 = '12-10-2010 11:00:00';

        $no_sub_message = 'No Submission';
        $good_message = 'Good';
        $late_message = 'Late';
        $bad_message_1 = 'Bad (too many late days used this term)';
        $bad_message_2 = 'Bad (too many late days used on this assignment)';

        $ldi = $this->makeLateDayInfo($due_date, 0, '', 0, 0);
        $this->assertEquals(LateDayInfo::STATUS_NO_ACTIVE_VERSION, $ldi->getStatus(), "No submission");
        $this->assertEquals($no_sub_message, $ldi->getStatusMessage());

        $ldi = $this->makeLateDayInfo($due_date, 0, $due_date_minus_d1, 0, 0);
        $this->assertEquals(LateDayInfo::STATUS_GOOD, $ldi->getStatus(), "Normal good submission");
        $this->assertEquals($good_message, $ldi->getStatusMessage());

        $ldi = $this->makeLateDayInfo($due_date, 0, $due_date_d2, 4, 0);
        $this->assertEquals(LateDayInfo::STATUS_GOOD, $ldi->getStatus(), "Extended good submission");
        $this->assertEquals($good_message, $ldi->getStatusMessage());


        $ldi = $this->makeLateDayInfo($due_date, 4, $due_date_d2, 0, 3);
        $this->assertEquals(LateDayInfo::STATUS_LATE, $ldi->getStatus(), "Normal late submission");
        $this->assertEquals($late_message, $ldi->getStatusMessage());

        $ldi = $this->makeLateDayInfo($due_date, 4, $due_date_s1, 0, 3);
        $this->assertEquals(LateDayInfo::STATUS_LATE, $ldi->getStatus(), "Slightly late submission");
        $this->assertEquals($late_message, $ldi->getStatusMessage());

        $ldi = $this->makeLateDayInfo($due_date, 4, $due_date_d2, 1, 3);
        $this->assertEquals(LateDayInfo::STATUS_LATE, $ldi->getStatus(), "Late extended submission");
        $this->assertEquals($late_message, $ldi->getStatusMessage());


        $ldi = $this->makeLateDayInfo($due_date, 0, $due_date_s1, 0, 0);
        $this->assertEquals(LateDayInfo::STATUS_BAD, $ldi->getStatus(), "Double bad submission (no exceptions)");
        $this->assertEquals($bad_message_1, $ldi->getStatusMessage());

        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date_d2, 0, 1);
        $this->assertEquals(LateDayInfo::STATUS_BAD, $ldi->getStatus(), "Bad submission - too few remaining (no exceptions)");
        $this->assertEquals($bad_message_1, $ldi->getStatusMessage());

        $ldi = $this->makeLateDayInfo($due_date, 1, $due_date_d2, 0, 10);
        $this->assertEquals(LateDayInfo::STATUS_BAD, $ldi->getStatus(), "Bad submission - too few in gradeable (no exceptions)");
        $this->assertEquals($bad_message_2, $ldi->getStatusMessage());
    }

    public function testGetLateDaysCharged() {
        $due_date = '10-10-2010 11:59:59';
        $due_date_d2 = '12-10-2010 11:00:00';


        $ldi = $this->makeLateDayInfo($due_date, 0, $due_date_d2, 0, 3);
        $this->assertEquals(0, $ldi->getLateDaysCharged(), "Bad submission should charge no late days");

        $ldi = $this->makeLateDayInfo($due_date, 10, $due_date_d2, 0, 0);
        $this->assertEquals(0, $ldi->getLateDaysCharged(), "Bad submission should charge no late days");


        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date_d2, 0, 2);
        $this->assertEquals(2, $ldi->getLateDaysCharged(), 'Charge all late days');

        $ldi = $this->makeLateDayInfo($due_date, 5, $due_date_d2, 0, 10);
        $this->assertEquals(2, $ldi->getLateDaysCharged(), 'Normal late day usage');

        $ldi = $this->makeLateDayInfo($due_date, 5, $due_date_d2, 2, 3);
        $this->assertEquals(0, $ldi->getLateDaysCharged(), "Late Day extension usage");

        $ldi = $this->makeLateDayInfo($due_date, 5, $due_date_d2, 2, 0);
        $this->assertEquals(0, $ldi->getLateDaysCharged(), "Would-be-bad submission(1)");

        $ldi = $this->makeLateDayInfo($due_date, 0, $due_date_d2, 2, 5);
        $this->assertEquals(0, $ldi->getLateDaysCharged(), "Would-be-bad submission(2)");

        $ldi = $this->makeLateDayInfo($due_date, 1, $due_date_d2, 1, 2);
        $this->assertEquals(1, $ldi->getLateDaysCharged(), 'Late extended submission');
    }

    public function testIsValidStatus() {
        $this->assertTrue(LateDayInfo::isValidStatus(LateDayInfo::STATUS_GOOD));
        $this->assertTrue(LateDayInfo::isValidStatus(LateDayInfo::STATUS_LATE));
        $this->assertTrue(LateDayInfo::isValidStatus(LateDayInfo::STATUS_BAD));
        $this->assertFalse(LateDayInfo::isValidStatus(LateDayInfo::STATUS_NO_ACTIVE_VERSION));
        $this->assertFalse(LateDayInfo::isValidStatus(-1));
        $this->assertFalse(LateDayInfo::isValidStatus(4));
    }
}
