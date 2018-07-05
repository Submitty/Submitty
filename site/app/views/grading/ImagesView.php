<?php

namespace app\views\grading;

use app\models\User;
use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\Utils;


class ImagesView extends AbstractView {
    /**
     * @param User[] $students
     * @return string
     */
    public function listStudentImages($students, $grader_sections, $instructor_permission) {
        //Assemble students into sections if they are in grader_sections based on the registration section.
        $sections = [];
        foreach ($students as $student) {
            $registration = ($student->getRegistrationSection() === null) ? "NULL" : $student->getRegistrationSection();
            if (empty($grader_sections) || in_array($registration, $grader_sections)) {
                $sections[$registration][] = $student;
            }
        }

		
        //$images_data_array to contain base64_encoded image urls
        $images_data_array = array();
        //$images_names_array to contain the names of the images (rcs ids)
        $images_names_array = array();
        $error_image_data = '_NONE_';

        //Get the expected images path and png files to loop through
        $expected_images_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "student_images");
	
        $dir = new \DirectoryIterator($expected_images_path);
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot() && $fileinfo->getExtension() === "png") {

                $expected_image = $fileinfo->getPathname();
                $content_type = FileUtils::getContentType($expected_image);
                if (substr($content_type, 0, 5) === "image") {
                    // Read image path, convert to base64 encoding
                    $expected_img_data = base64_encode(file_get_contents($expected_image));

                    $img_name = $fileinfo->getBasename('.png');
                    if ($img_name === "error_image") {
                        $error_image_data = $expected_img_data;
                    }
                    else {
                        array_push($images_data_array, $expected_img_data);
                        array_push($images_names_array, $img_name);
                    }
                }
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("grading/Images.twig", [
            "sections" => $sections,
            "imageNameArray" => $images_names_array,
            "imageDataArray" => $images_data_array,
            "errorImageData" => $error_image_data,
            "hasInstructorPermission" => $instructor_permission
        ]);
    }
}
