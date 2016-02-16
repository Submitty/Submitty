<?php

namespace tests\e2e\accountTests\GradingServer;

use lib\Database;
use tests\e2e\BaseTestCase;

class LabsTester extends BaseTestCase {
    private static $student1_rcs;
    private static $student2_rcs;

    public static function setUpBeforeClass() {
        $_SERVER['PHP_AUTH_USER'] = 'ta';
        for ($i = 1; $i < 3; $i++) {
            $guid = uniqid();
            Database::query("INSERT INTO
    students (student_rcs, student_last_name, student_first_name, student_section_id, student_grading_id)
    VALUES ('student{$guid}', '{$guid}', 'Student', {$i}, 1);");
            $var = "student{$i}_rcs";
            LabsTester::$$var = "student{$guid}";
        }
    }

    public static function tearDownAfterClass() {
        Database::query("DELETE FROM students WHERE student_rcs='".LabsTester::$student1_rcs."'");
        Database::query("DELETE FROM students WHERE student_rcs='".LabsTester::$student2_rcs."'");
    }

    public function setUpPage() {
        $this->url('TAGradingServer/account/account-labs.php?course=test_course');
    }

    /**
     * Test changing tabs on lab page
     */
    public function testLabTabs() {
        $tabs = $this->elements($this->using('css selector')->value('.lab_tab'));
        /** @var \PHPUnit_Extensions_Selenium2TestCase_Element[] $tabs */
        $this->assertEquals(2, count($tabs));
        $this->assertNotFalse(strstr($tabs[0]->attribute('class'), 'active'));
        $this->assertFalse(strstr($tabs[1]->attribute('class'), 'active'));
        // ensure clicking on first tab again doesn't change anything
        $tabs[0]->click();
        $this->assertNotFalse(strstr($tabs[0]->attribute('class'), 'active'));
        $this->assertFalse(strstr($tabs[1]->attribute('class'), 'active'));
        // clicking on second tab does affect things
        $tabs[1]->click();
        $this->assertFalse(strstr($tabs[0]->attribute('class'), 'active'));
        $this->assertNotFalse(strstr($tabs[1]->attribute('class'), 'active'));
        $tabs[1]->click();
        $this->assertFalse(strstr($tabs[0]->attribute('class'), 'active'));
        $this->assertNotFalse(strstr($tabs[1]->attribute('class'), 'active'));
        // go back to first lab
        $tabs[0]->click();
        $this->assertNotFalse(strstr($tabs[0]->attribute('class'), 'active'));
        $this->assertFalse(strstr($tabs[1]->attribute('class'), 'active'));
    }

    public function testInputLabGrades() {
        $student_rcs = LabsTester::$student1_rcs;
        $tabs = $this->elements($this->using('css selector')->value('.lab_tab'));
        /** @var \PHPUnit_Extensions_Selenium2TestCase_Element[] $tabs */
        for ($i = 1; $i <= 3; $i++) {
            $this->assertEquals("0", $this->byId("cell-1-check{$i}-{$student_rcs}")->attribute('cell-status'));
            $this->assertTrue($this->byId("cell-1-check1-{$student_rcs}")->displayed());
            $this->assertEquals("0", $this->byId("cell-2-check{$i}-{$student_rcs}")->attribute('cell-status'));
            $this->assertFalse($this->byId("cell-2-check1-{$student_rcs}")->displayed());
        }
        $this->assertEquals("0", $this->byId("cell-1-check1-{$student_rcs}")->attribute('cell-status'));
        $this->byId("cell-1-check1-{$student_rcs}")->click();
        $this->waitUntil(function($student_rcs) use ($student_rcs) {
            if ($this->byId("cell-1-check1-{$student_rcs}")->attribute('cell-status') == "1") {
                return true;
            }
        }, 5000);
        $this->byId("cell-1-check3-{$student_rcs}")->click();
        $this->waitUntil(function($student_rcs) use ($student_rcs) {
            if ($this->byId("cell-1-check3-{$student_rcs}")->attribute('cell-status') == "1") {
                return true;
            }
        }, 5000);
        $this->byId("cell-1-check3-{$student_rcs}")->click();
        $this->waitUntil(function($student_rcs) use ($student_rcs) {
            if ($this->byId("cell-1-check3-{$student_rcs}")->attribute('cell-status') == "2") {
                return true;
            }
        }, 5000);
        $tabs[1]->click();
        $this->waitUntil(function($student_rcs) use ($student_rcs) {
            if ($this->byId("cell-2-check1-{$student_rcs}")->displayed()) {
                return true;
            }
        }, 5000);
        $this->byId("cell-2-check2-{$student_rcs}")->click();
        $this->waitUntil(function($student_rcs) use ($student_rcs) {
            if ($this->byId("cell-2-check2-{$student_rcs}")->attribute('cell-status') == "1") {
                return true;
            }
        }, 5000);
        $this->refresh();
        $cell_values = array(array("1", "0", "2"), array("0", "1", "0"));
        for ($i = 1; $i <= 3; $i++) {
            $this->assertTrue($this->byId("cell-1-check1-{$student_rcs}")->displayed());
            $this->assertEquals($cell_values[0][$i-1], $this->byId("cell-1-check{$i}-{$student_rcs}")->attribute('cell-status'));
            $this->assertFalse($this->byId("cell-2-check1-{$student_rcs}")->displayed());
            $this->assertEquals($cell_values[1][$i-1], $this->byId("cell-2-check{$i}-{$student_rcs}")->attribute('cell-status'));
        }
    }

    public function testCannotSeeStudentsOutsideSection() {
        $this->assertNotNull($this->byId("section-1"));
        try {
            $this->byId("section-2");
            $this->fail("This element should not exist");
        }
        catch (\PHPUnit_Extensions_Selenium2TestCase_WebDriverException $e) {
            $this->assertEquals(\PHPUnit_Extensions_Selenium2TestCase_WebDriverException::NoSuchElement, $e->getCode());
        }
        $student_rcs = LabsTester::$student2_rcs;
        for ($i = 1; $i < 3; $i++) {
            try {
                $this->byId("cell-{$i}-all-{$student_rcs}");
                $this->fail("This element should not exist");
            } catch (\PHPUnit_Extensions_Selenium2TestCase_WebDriverException $e) {
                $this->assertEquals(\PHPUnit_Extensions_Selenium2TestCase_WebDriverException::NoSuchElement, $e->getCode());
            }
        }
    }

    public function testInstructorSeesAllSections() {
        $this->url('TAGradingServer/account/account-labs.php?course=test_course&useUser=instructor');

        $this->assertNotNull($this->byId("section-1"));
        $this->assertNotNull($this->byId("section-2"));

        for ($j = 1; $j < 3; $j++) {
            $var = "student{$j}_rcs";
            $student_rcs = LabsTester::$$var;
            for ($i = 1; $i < 3; $i++) {
                try {
                    $this->byId("cell-{$i}-all-{$student_rcs}");
                } catch (\PHPUnit_Extensions_Selenium2TestCase_WebDriverException $e) {
                    $this->fail("This element should exist");
                }
            }
        }
    }

    public function testAllGetFilter() {
        $this->assertNotNull($this->byId("section-1"));
        try {
            $this->assertNotNull($this->byId("section-2"));
            $this->fail("This element should not exist.");
        }
        catch (\PHPUnit_Extensions_Selenium2TestCase_WebDriverException $e) {
            $this->assertEquals(\PHPUnit_Extensions_Selenium2TestCase_WebDriverException::NoSuchElement, $e->getCode());
        }

        $this->url('TAGradingServer/account/account-labs.php?course=test_course&all=true');

        $this->assertNotNull($this->byId("section-1"));
        $this->assertNotNull($this->byId("section-2"));
    }
}