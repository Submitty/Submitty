<?php

namespace tests\app\models\course;

use app\models\CourseMaterial;
use tests\BaseUnitTest;

class CourseMaterialTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    public function testFileWithSections() {
        $details = [
            'type' => CourseMaterial::FILE,
            'path' => '/tmp/file.txt',
            'release_date' => '9998-01-01 05:00:00',
            'hidden_from_students' => false,
            'priority' => 1.2,
            'section_lock' => true,
            'sections' => [
                '1',
                '2'
            ]
        ];
        $course_material = new CourseMaterial($this->core, $details);
        $this->assertEquals($details['type'], $course_material->getType());
        $this->assertEquals($details['path'], $course_material->getPath());
        $release_date = new \DateTime($details['release_date']);
        $this->assertEquals($release_date, $course_material->getReleaseDate());
        $this->assertEquals($details['hidden_from_students'], $course_material->getHiddenFromStudents());
        $this->assertEquals($details['priority'], $course_material->getPriority());
        $this->assertEquals($details['section_lock'], $course_material->getSectionLock());
        $this->assertEquals($details['sections'], $course_material->getSections());
    }

    public function testLinkNoSections() {
        $details = [
            'type' => CourseMaterial::LINK,
            'path' => '/tmp/file.txt',
            'release_date' => '9998-01-01 05:00:00',
            'hidden_from_students' => true,
            'priority' => 2.4,
            'section_lock' => false
        ];
        $course_material = new CourseMaterial($this->core, $details);
        $this->assertEquals($details['type'], $course_material->getType());
        $this->assertEquals($details['path'], $course_material->getPath());
        $release_date = new \DateTime($details['release_date']);
        $this->assertEquals($release_date, $course_material->getReleaseDate());
        $this->assertEquals($details['hidden_from_students'], $course_material->getHiddenFromStudents());
        $this->assertEquals($details['priority'], $course_material->getPriority());
    }
}
