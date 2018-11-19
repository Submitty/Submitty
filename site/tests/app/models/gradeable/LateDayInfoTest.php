<?php

namespace tests\app\models\gradeable;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\Config;
use app\models\gradeable\AutoGradedGradeable;
use app\models\gradeable\AutoGradedVersion;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\gradeable\LateDayInfo;
use app\models\gradeable\Submitter;
use app\models\User;
use PHPUnit\Framework\TestCase;
use tests\BaseUnitTest;

class LateDayInfoTest extends BaseUnitTest {

    protected function makeLateDayInfo(string $due_date, int $late_days, string $submission_date, int $late_day_exception, int $late_days_remaining) {
        $core = $this->createMock(Core::class);

        $gradeable = $this->createMock(Gradeable::class);
        $gradeable->method('getSubmissionDueDate')->willReturn(new \DateTime($due_date));
        $gradeable->method('getLateDays')->willReturn($late_days);

        $auto_graded_version = $this->createMock(AutoGradedVersion::class);
        $auto_graded_version->method('getSubmissionTime')->willReturn(new \DateTime($submission_date));
        $auto_graded_version->method('getDaysLate')->willReturn(DateUtils::calculateDayDiff($due_date, $submission_date));

        $auto_graded_gradeable = $this->createMock(AutoGradedGradeable::class);
        $auto_graded_gradeable->method('getActiveVersionInstance')->willReturn($auto_graded_version);
        $auto_graded_gradeable->method('hasActiveVersion')->willReturn(true);

        $user = $this->createMock(User::class);
        $submitter = $this->createMock(Submitter::class);
        $submitter->method('hasUser')->willReturn(true);

        $graded_gradeable = $this->createMock(GradedGradeable::class);
        $graded_gradeable->method('getGradeable')->willReturn($gradeable);
        $graded_gradeable->method('getSubmitter')->willReturn($submitter);
        $graded_gradeable->method('getAutoGradedGradeable')->willReturn($auto_graded_gradeable);
        $graded_gradeable->method('getLateDayExceptions')->with([$user_id])->willReturn($late_day_exception);

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
        $this->assertTrue($ldi->getLateDaysAllowed() === 2, "Late days remaining less than gradeable late days");

        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date, 0, 4);
        $this->assertTrue($ldi->getLateDaysAllowed() === 3, "Late days remaining more than gradeable late days");

        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date, 4, 3);
        $this->assertTrue($ldi->getLateDaysAllowed() === 7, 'Late day exception');

        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date, 4, 5);
        $this->assertTrue($ldi->getLateDaysAllowed() === 7, 'Late day exception, remaining more than gradeable late days');

        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date, 4, 0);
        $this->assertTrue($ldi->getLateDaysAllowed() === 4, 'Only late day exception');
    }

    public function testGetLateDayException() {
        $due_date = '10-10-2010 11:59:59';
        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date, 10, 1);
        $this->assertTrue($ldi->getLateDayException() == 10, "Late day exception fetch");
    }

    public function testGetStatusAndMesage() {
        // TODO: 'no submission' status
        $due_date_minus_d1 = '10-9-2010 11:00:00';
        $due_date = '10-10-2010 11:59:59';
        $due_date_s1 = '10-11-2010 00:00:00';
        $due_date_d2 = '10-12-2010 11:00:00';

        $ldi = $this->makeLateDayInfo($due_date, 0, $due_date_minus_d1, 0, 0);
        $this->assertTrue($ldi->getStatus() === LateDayInfo::STATUS_GOOD, "Normal good submission");
        $this->assertTrue($ldi->getStatusMessage() === 'Good');

        $ldi = $this->makeLateDayInfo($due_date, 0, $due_date_d2, 4, 0);
        $this->assertTrue($ldi->getStatus() === LateDayInfo::STATUS_GOOD, "Extended good submission");
        $this->assertTrue($ldi->getStatusMessage() === 'Good');


        $ldi = $this->makeLateDayInfo($due_date, 4, $due_date_d2, 0, 3);
        $this->assertTrue($ldi->getStatus() === LateDayInfo::STATUS_LATE, "Normal late submission");
        $this->assertTrue($ldi->getStatusMessage() === 'Late');

        $ldi = $this->makeLateDayInfo($due_date, 4, $due_date_s1, 0, 3);
        $this->assertTrue($ldi->getStatus() === LateDayInfo::STATUS_LATE, "Slightly late submission");
        $this->assertTrue($ldi->getStatusMessage() === 'late');

        $ldi = $this->makeLateDayInfo($due_date, 4, $due_date_d2, 1, 3);
        $this->assertTrue($ldi->getStatus() === LateDayInfo::STATUS_LATE, "Late extended submission");
        $this->assertTrue($ldi->getStatusMessage() === 'Late');


        $ldi = $this->makeLateDayInfo($due_date, 0, $due_date_s1, 0, 0);
        $this->assertTrue($ldi->getStatus() === LateDayInfo::STATUS_BAD, "Double bad submission (no exceptions)");
        $this->assertTrue($ldi->getStatusMessage() === 'Bad (too many late days used this term)');

        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date_d2, 0, 1);
        $this->assertTrue($ldi->getStatus() === LateDayInfo::STATUS_BAD, "Bad submission - too few remaining (no exceptions)");
        $this->assertTrue($ldi->getStatusMessage() === 'Bad (too many late days used this term)');

        $ldi = $this->makeLateDayInfo($due_date, 1, $due_date_d2, 0, 10);
        $this->assertTrue($ldi->getStatus() === LateDayInfo::STATUS_BAD, "Bad submission - too few in gradeable (no exceptions)");
        $this->assertTrue($ldi->getStatusMessage() === 'Bad (too many late days used on this assignment)');
    }

    public function testGetLateDaysCharged() {
        $due_date = '10-10-2010 11:59:59';
        $due_date_d2 = '10-12-2010 11:00:00';


        $ldi = $this->makeLateDayInfo($due_date, 0, $due_date_d2, 0, 3);
        $this->assertTrue($ldi->getLateDaysCharged() === 0, "Bad submission should charge no late days");

        $ldi = $this->makeLateDayInfo($due_date, 10, $due_date_d2, 0, 0);
        $this->assertTrue($ldi->getLateDaysCharged() === 0, "Bad submission should charge no late days");


        $ldi = $this->makeLateDayInfo($due_date, 3, $due_date_d2, 0, 2);
        $this->assertTrue($ldi->getLateDaysCharged() === 2, 'Charge all late days');

        $ldi = $this->makeLateDayInfo($due_date, 5, $due_date_d2, 0, 10);
        $this->assertTrue($ldi->getLateDaysCharged() == 2, 'Normal late day usage');

        $ldi = $this->makeLateDayInfo($due_date, 5, $due_date_d2, 2, 3);
        $this->assertTrue($ldi->getLateDaysCharged() == 0, "Late Day exception usage");

        $ldi = $this->makeLateDayInfo($due_date, 5, $due_date_d2, 2, 0);
        $this->assertEquals(0, $ldi->getLateDaysCharged(), "Would-be-bad submission");

        $ldi = $this->makeLateDayInfo($due_date, 0, $due_date_d2, 2, 5);
        $this->assertEquals(0, $ldi->getLateDaysCharged(), "Would-be-bad submission(2)");

        //TODO: late extended submission
    }
}
