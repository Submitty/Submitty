<?php

namespace tests\app\models;

use app\libraries\Core;
use app\models\GradeableVersion;

class GradeableVersionTester extends \PHPUnit\Framework\TestCase {
    private $core;
    
    public function setUp() {
        $this->core = $this->createMock(Core::class);
    }
    public function testVersion() {
        $details = array(
            'g_id' => 'test_gradeable',
            'user_id' => 'test_user',
            'team_id' => 'test_team',
            'g_version' => 1,
            'autograding_non_hidden_non_extra_credit' => 2,
            'autograding_non_hidden_extra_credit' => 4,
            'autograding_hidden_non_extra_credit' => 8,
            'autograding_hidden_extra_credit' => 10,
            'submission_time' => new \DateTime("2017-05-08 13:47:13"),
            'active_version' => null
        );
        $due_date = new \DateTime("2017-05-08 23:59:59");
        $version = new GradeableVersion($this->core, $details, $due_date);
        $this->assertEquals(1, $version->getVersion());
        $this->assertEquals(2, $version->getNonHiddenNonExtraCredit());
        $this->assertEquals(4, $version->getNonHiddenExtraCredit());
        $this->assertEquals(6, $version->getNonHiddenTotal());
        $this->assertEquals(8, $version->getHiddenNonExtraCredit());
        $this->assertEquals(10, $version->getHiddenExtraCredit());
        $this->assertEquals(18, $version->getHiddenTotal());
        $this->assertEquals(0, $version->getDaysLate());
        $this->assertEquals("05/08/2017 01:47:13 PM", $version->getSubmissionTime());

        $this->assertFalse($version->isActive());
    }

    public function testLateVersion() {
        $details = array(
            'g_id' => 'test_gradeable',
            'user_id' => 'test_user',
            'team_id' => 'test_team',
            'g_version' => 1,
            'autograding_non_hidden_non_extra_credit' => 2,
            'autograding_non_hidden_extra_credit' => 4,
            'autograding_hidden_non_extra_credit' => 8,
            'autograding_hidden_extra_credit' => 10,
            'submission_time' => new \DateTime("2017-05-09 13:47:13"),
            'active_version' => null
        );
        $due_date = new \DateTime("2017-05-08 23:59:59");

        $version = new GradeableVersion($this->core, $details, $due_date);
        $this->assertEquals(1, $version->getDaysLate());
    }

    public function testLateDayBuffer() {
        $details = array(
            'g_id' => 'test_gradeable',
            'user_id' => 'test_user',
            'team_id' => 'test_team',
            'g_version' => 1,
            'autograding_non_hidden_non_extra_credit' => 2,
            'autograding_non_hidden_extra_credit' => 4,
            'autograding_hidden_non_extra_credit' => 8,
            'autograding_hidden_extra_credit' => 10,
            'submission_time' => new \DateTime("2017-05-09 00:04:59"),
            'active_version' => null
        );
        $due_date = new \DateTime("2017-05-08 23:59:59");

        $version = new GradeableVersion($this->core, $details, $due_date);
        $this->assertEquals(0, $version->getDaysLate());

        $details = array(
            'g_id' => 'test_gradeable',
            'user_id' => 'test_user',
            'team_id' => 'test_team',
            'g_version' => 1,
            'autograding_non_hidden_non_extra_credit' => 2,
            'autograding_non_hidden_extra_credit' => 4,
            'autograding_hidden_non_extra_credit' => 8,
            'autograding_hidden_extra_credit' => 10,
            'submission_time' => new \DateTime("2017-05-09 00:05:00"),
            'active_version' => null
        );
        $due_date = new \DateTime("2017-05-08 23:59:59");

        $version = new GradeableVersion($this->core, $details, $due_date);
        $this->assertEquals(1, $version->getDaysLate());
    }

    public function testEarlyVersion() {
        $details = array(
            'g_id' => 'test_gradeable',
            'user_id' => 'test_user',
            'team_id' => 'test_team',
            'g_version' => 1,
            'autograding_non_hidden_non_extra_credit' => 2,
            'autograding_non_hidden_extra_credit' => 4,
            'autograding_hidden_non_extra_credit' => 8,
            'autograding_hidden_extra_credit' => 10,
            'submission_time' => new \DateTime("2017-05-01 00:05:00"),
            'active_version' => null
        );
        $due_date = new \DateTime("2017-05-08 23:59:59");

        $version = new GradeableVersion($this->core, $details, $due_date);
        $this->assertEquals(0, $version->getDaysLate());
    }
}
