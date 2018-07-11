<?php

namespace app\views\course;

use app\models\User;
use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\Utils;


class CourseMaterialsView extends AbstractView {
    /**
     * @param User[] $students
     * @return string
     */
    public function listCourseMaterials($instructor_permission) {
        //array to hold all materials filenames
        $course_materials_array = array();

        //Get the expected course materials path and files to loop through
        $expected_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");

        $dir = new \DirectoryIterator($expected_path);
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot() && $fileinfo->isFile()) {
                $expected_filename = $fileinfo->getBasename();
                array_push($course_materials_array, $expected_filename);
            }
        }
        asort($course_materials_array);
        return $this->core->getOutput()->renderTwigTemplate("course/CourseMaterials.twig", [
            "courseMaterialsArray" => $course_materials_array,
            "folderPath" => $expected_path,
            "hasInstructorPermission" => $instructor_permission
        ]);
    }
}
