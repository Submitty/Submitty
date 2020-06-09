<?php

namespace app\views\grading;

use app\models\User;
use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\Utils;

class ImagesView extends AbstractView {

    // Define the height and width for each image displayed on the Student Images page
    const IMAGE_COLS = 150;
    const IMAGE_ROWS = 200;

    /**
     * @param User[] $students
     * @return string
     */
    public function listStudentImages($students, $grader_sections, $instructor_permission) {
        $this->core->getOutput()->addBreadcrumb("Student Photos");
        $this->core->getOutput()->addInternalJs("drag-and-drop.js");
        $this->core->getOutput()->addInternalCss(FileUtils::joinPaths('fileinput.css'));
        $this->core->getOutput()->enableMobileViewport();

        $image_data = [];
        $error_image_data = '_NONE_';

        //Assemble students into sections if they are in grader_sections based on the registration section.
        $sections = [];
        foreach ($students as $student) {
            $registration = ($student->getRegistrationSection() === null) ? "NULL" : $student->getRegistrationSection();
            if (empty($grader_sections) || in_array($registration, $grader_sections)) {
                $sections[$registration][] = $student;
                $image_path = $student->getDisplayImage()->getPath();
                if (file_exists($image_path) && FileUtils::isValidImage($image_path)) {
                    $mime_subtype = explode('/', mime_content_type($image_path), 2)[1];
                    $image_data[$student->getId()] = [
                        'subtype' => $mime_subtype,
                        'path' => $image_path
                    ];
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
