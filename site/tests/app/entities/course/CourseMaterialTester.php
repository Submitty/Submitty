<?php

namespace tests\app\entities\course;

use app\entities\course\CourseMaterial;
use app\entities\course\CourseMaterialSection;
use tests\BaseUnitTest;

class CourseMaterialTester extends BaseUnitTest {
    public function testFileWithSections() {
        $details = [
            'type' => CourseMaterial::FILE,
            'path' => '/tmp/file.txt',
            'release_date' => new \DateTime('9998-01-01 05:00:00'),
            'hidden_from_students' => false,
            'priority' => 1.2
        ];
        $course_material = new CourseMaterial(
            $details['type'],
            $details['path'],
            $details['release_date'],
            $details['hidden_from_students'],
            $details['priority'],
            null,
            null,
            false,
            "testUser",
            $details['release_date'],
            null,
            null
        );
        $sections = ['1', '2'];
        foreach ($sections as $section) {
            $course_material_section = new CourseMaterialSection($section, $course_material);
            $course_material->addSection($course_material_section);
        }
        $this->assertEquals($details['type'], $course_material->getType());
        $this->assertEquals($details['path'], $course_material->getPath());
        $this->assertEquals($details['release_date'], $course_material->getReleaseDate());
        $this->assertEquals($details['hidden_from_students'], $course_material->isHiddenFromStudents());
        $this->assertEquals($details['priority'], $course_material->getPriority());
        $index = 0;
        foreach ($course_material->getSections()->toArray() as $section) {
            $this->assertEquals($sections[$index], $section->getSectionId());
            $index++;
        }
    }

    public function testLinkNoSections() {
        $details = [
            'type' => CourseMaterial::LINK,
            'path' => '/tmp/file.txt',
            'release_date' => new \DateTime('9998-01-01 05:00:00'),
            'hidden_from_students' => true,
            'priority' => 2.4
        ];
        $course_material = new CourseMaterial(
            $details['type'],
            $details['path'],
            $details['release_date'],
            $details['hidden_from_students'],
            $details['priority'],
            null,
            null,
            false,
            "testUser",
            $details['release_date'],
            null,
            null
        );
        $this->assertEquals($details['type'], $course_material->getType());
        $this->assertEquals($details['path'], $course_material->getPath());
        $this->assertEquals($details['release_date'], $course_material->getReleaseDate());
        $this->assertEquals($details['hidden_from_students'], $course_material->isHiddenFromStudents());
        $this->assertEquals($details['priority'], $course_material->getPriority());
    }

    public function testUpdateHistory() {
        $details = [
            'type' => CourseMaterial::LINK,
            'path' => '/tmp/file.txt',
            'release_date' => new \DateTime('9998-01-01 05:00:00'),
            'hidden_from_students' => true,
            'priority' => 2.4,
            'uploaded_by' => "Dummy Uploader",
            'uploaded_date' => new \DateTime('9998-01-01 05:00:00'),
            'last_edit_by' => "Dummy User",
            'last_edit_date' => new \DateTime('9998-01-01 05:00:00'),
        ];
        $course_material = new CourseMaterial(
            $details['type'],
            $details['path'],
            $details['release_date'],
            $details['hidden_from_students'],
            $details['priority'],
            null,
            null,
            $details['uploaded_by'],
            $details['uploaded_date'],
            $details['last_edit_by'],
            $details['last_edit_date']
        );
        $this->assertEquals($details['type'], $course_material->getType());
        $this->assertEquals($details['path'], $course_material->getPath());
        $this->assertEquals($details['release_date'], $course_material->getReleaseDate());
        $this->assertEquals($details['hidden_from_students'], $course_material->isHiddenFromStudents());
        $this->assertEquals($details['priority'], $course_material->getPriority());
        $this->assertEquals($details['uploaded_by'], $course_material->getUploadedBy());
        $this->assertEquals($details['uploaded_date'], $course_material->getUploadedDate());
        $this->assertEquals($details['last_edit_by'], $course_material->getLastEditBy());
        $this->assertEquals($details['last_edit_date'], $course_material->getLastEditDate());
    }
}
