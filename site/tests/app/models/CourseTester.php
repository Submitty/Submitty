<?php

namespace tests\app\models;

use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\Course;
use tests\BaseUnitTest;

class CourseTester extends BaseUnitTest {
    public function testCourse() {
        $details = [
            'semester' => 's18',
            'term_name' => 'Spring 2018',
            'course' => 'csci1000',
            'user_group' => 1
        ];
        $course = new Course($this->createMockCore(), $details);
        $this->assertEquals('s18', $course->getSemester());
        $this->assertEquals('Spring 2018', $course->getLongSemester());
        $this->assertEquals('csci1000', $course->getTitle());
        $this->assertEquals('CSCI1000', $course->getCapitalizedTitle());
        $this->assertEquals('', $course->getDisplayName());
        $this->assertEquals('Spring 2018', $course->getSemesterName());

        $array = [
            'semester' => 's18',
            'semester_name' => 'Spring 2018',
            'title' => 'csci1000',
            'display_name' => '',
            'user_group' => 1,
            'modified' => false
        ];
        $this->assertEquals($array, $course->toArray());
    }

    public function testLoadDisplayName() {
        $temp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $config_path = FileUtils::joinPaths($temp_dir, 'courses', 's18', 'csci1000', 'config');
        FileUtils::createDir($config_path, true);
        $config = [
            'course_details' => [
                'course_name' => 'Test Course',
            ]
        ];
        FileUtils::writeJsonFile(FileUtils::joinPaths($config_path, 'config.json'), $config);
        $details = ['semester' => 's18', 'term_name' => 'Spring 2018', 'course' => 'csci1000'];
        try {
            $course = new Course($this->createMockCore(['tmp_path' => $temp_dir]), $details);
            $this->assertTrue($course->loadDisplayName());
            $this->assertEquals('Test Course', $course->getDisplayName());
            $array = [
                'semester' => 's18',
                'semester_name' => 'Spring 2018',
                'title' => 'csci1000',
                'display_name' => 'Test Course',
                'user_group' => 3,
                'modified' => false
            ];
            $this->assertEquals($array, $course->toArray());
        }
        finally {
            FileUtils::recursiveRmdir($temp_dir);
        }
    }

    public function testInvalidPath() {
        $details = ['semester' => 's18', 'term_name' => 'Spring 2018', 'course' => 'csci1000'];
        $course = new Course($this->createMockCore(['tmp_path' => '/invalid/path']), $details);
        $this->assertFalse($course->loadDisplayName());
        $this->assertEquals('', $course->getDisplayName());
    }

    public function testMissingSection() {
        $temp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $config_path = FileUtils::joinPaths($temp_dir, 'courses', 's18', 'csci1000', 'config');
        FileUtils::createDir($config_path, true);
        $config = [];
        FileUtils::writeJsonFile(FileUtils::joinPaths($config_path, 'config.json'), $config);
        $details = ['semester' => 's18', 'term_name' => 'Spring 2018', 'course' => 'csci1000'];
        try {
            $course = new Course($this->createMockCore(['tmp_path' => $temp_dir]), $details);
            $this->assertFalse($course->loadDisplayName());
            $this->assertEquals('', $course->getDisplayName());
        }
        finally {
            FileUtils::recursiveRmdir($temp_dir);
        }
    }

    public function testMissingSetting() {
        $temp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $config_path = FileUtils::joinPaths($temp_dir, 'courses', 's18', 'csci1000', 'config');
        FileUtils::createDir($config_path, true);
        $config = ['course_details' => []];
        FileUtils::writeJsonFile(FileUtils::joinPaths($config_path, 'config.json'), $config);
        $details = ['semester' => 's18', 'term_name' => 'Spring 2018', 'course' => 'csci1000'];
        try {
            $course = new Course($this->createMockCore(['tmp_path' => $temp_dir]), $details);
            $this->assertFalse($course->loadDisplayName());
            $this->assertEquals('', $course->getDisplayName());
        }
        finally {
            FileUtils::recursiveRmdir($temp_dir);
        }
    }
}
