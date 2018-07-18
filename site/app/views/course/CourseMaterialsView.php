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

        function add_files(&$files, $expected_path, $course_materials_array, $start_dir_name) {
            $files[$start_dir_name] = array();
            $working_dirRoot = &$files[$start_dir_name];
			
            $arrlength = count($course_materials_array);

            foreach($course_materials_array as $file) {
                $path = explode('/', $file);
                $working_dir = &$files[$start_dir_name];
                $filename = array_pop($path);
                foreach($path as $dir) {
                    if (!isset($working_dir[$dir])) {
                        $working_dir[$dir] = array();
                    }
                    $working_dir = &$working_dir[$dir];
                }
				
                $expected_file_path = FileUtils::joinPaths($expected_path, $file);
				
                $working_dir[$filename] = $expected_file_path;
            }
        }
		 
        $submissions = array();

        $course_materials_array = array();

        //Get the expected course materials path and files
        $expected_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
        $path_length = strlen($expected_path)+1;
        $course_materials_array = FileUtils::getAllFilesTrimSearchPath($expected_path, $path_length);
        usort($course_materials_array, 'strnatcasecmp');

        add_files($submissions, $expected_path, $course_materials_array, 'course_materials');

        return $this->core->getOutput()->renderTwigTemplate("course/CourseMaterials.twig", [
            "courseMaterialsArray" => $course_materials_array,
            "folderPath" => $expected_path,
            "submissions" => $submissions,
            "hasInstructorPermission" => $instructor_permission
        ]);
    }
}
