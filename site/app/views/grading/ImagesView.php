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
        $this->core->getOutput()->addBreadcrumb("Student Photos");
        $this->core->getOutput()->addInternalJs("drag-and-drop.js");

        //Assemble students into sections if they are in grader_sections based on the registration section.
        $sections = [];
        foreach ($students as $student) {
            $registration = ($student->getRegistrationSection() === null) ? "NULL" : $student->getRegistrationSection();
            if (empty($grader_sections) || in_array($registration, $grader_sections)) {
                $sections[$registration][] = $student;
            }
        }


        $image_data = [];
        $error_image_data = '_NONE_';

        //Get the expected images path and png files to loop through
        $expected_images_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "student_images");

        $dir = new \DirectoryIterator($expected_images_path);
        //Extensions array can be extended if we want to support more types
        $valid_image_subtypes = ['png', 'jpg', 'jpeg', 'gif'];
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot() && !$fileinfo->isDir()) {
                $expected_image = $fileinfo->getPathname();
                list($mime_type, $mime_subtype) = explode('/', FileUtils::getMimeType($expected_image), 2);
                if ($mime_type === "image" && in_array($mime_subtype, $valid_image_subtypes)) {
                    $img_name = $fileinfo->getBasename('.' . $fileinfo->getExtension());
                    if ($img_name === "error_image") {
                        $error_image_data = [
                            'subtype' => $mime_subtype,
                            'path' => $expected_image
                        ];
                    }
                    else {
                        $image_data[$img_name] = [
                            'subtype' => $mime_subtype,
                            'path' => $expected_image
                        ];
                    }
                }
            }
        }

        $this->core->getOutput()->disableBuffer();
        return $this->core->getOutput()->renderTwigTemplate("grading/Images.twig", [
            "sections" => $sections,
            "imageData" => $image_data,
            "errorImageData" => $error_image_data,
            "hasInstructorPermission" => $instructor_permission
        ]);
    }
}
