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

        //Get the expected course materials path and files
        $expected_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
        $path_length = strlen($expected_path)+1;
        $course_materials_array = FileUtils::getAllFilesTrimSearchPath($expected_path, $path_length);

        $this->core->getOutput()->addInternalJs("drag-and-drop.js");

        usort($course_materials_array, 'strnatcasecmp');
        return $this->core->getOutput()->renderTwigTemplate("course/CourseMaterials.twig", [
            "courseMaterialsArray" => $course_materials_array,
            "folderPath" => $expected_path,
            "hasInstructorPermission" => $instructor_permission
        ]);
    }
}
