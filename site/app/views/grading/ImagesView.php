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
        $this->core->getOutput()->addInternalCss(FileUtils::joinPaths('fileinput.css'));

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
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot() && !$fileinfo->isDir()) {
                $expected_image = $fileinfo->getPathname();
                $mime_subtype = explode('/', mime_content_type($expected_image), 2)[1];
                if (FileUtils::isValidImage($expected_image)) {
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

        $max_size = Utils::returnBytes(ini_get('upload_max_filesize'));
        $max_size_string = Utils::formatBytes("MB", $max_size) . " (" . Utils::formatBytes("KB", $max_size) . ")";

        $this->core->getOutput()->disableBuffer();
        return $this->core->getOutput()->renderTwigTemplate("grading/Images.twig", [
            "sections" => $sections,
            "imageData" => $image_data,
            "errorImageData" => $error_image_data,
            "hasInstructorPermission" => $instructor_permission,
            "csrf_token" => $this->core->getCsrfToken(),
            "max_size_string" => $max_size_string
        ]);
    }
}
