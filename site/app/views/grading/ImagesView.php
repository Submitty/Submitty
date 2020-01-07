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

        $image_data = [];
        $error_image_data = '_NONE_';

        // image files can be specific to this course (uploaded by instructor)
        // or in a common path per term (uploaded manually by sysadmin)
        $course_location = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "student_images");
        $term = explode('/', $this->core->getConfig()->getCoursePath());
        $term = $term[count($term) - 2];
        $common_location = FileUtils::joinPaths("/var/local/submitty", "student_images", $term);

        //Assemble students into sections if they are in grader_sections based on the registration section.
        $sections = [];
        foreach ($students as $student) {
            $registration = ($student->getRegistrationSection() === null) ? "NULL" : $student->getRegistrationSection();
            if (empty($grader_sections) || in_array($registration, $grader_sections)) {
                $sections[$registration][] = $student;

                // the places we will look for this students photo (in order)
                $possible_matches =
                  [ FileUtils::joinPaths($course_location, $student->getId() . ".jpeg"),
                    FileUtils::joinPaths($course_location, $student->getId() . ".jpg"),
                    FileUtils::joinPaths($course_location, $student->getId() . ".png"),
                    FileUtils::joinPaths($course_location, $student->getId() . ".JPEG"),
                    FileUtils::joinPaths($course_location, $student->getId() . ".JPG"),
                    FileUtils::joinPaths($course_location, $student->getId() . ".PNG"),

                    FileUtils::joinPaths($common_location, $student->getId() . ".jpeg"),
                    FileUtils::joinPaths($common_location, $student->getId() . ".jpg"),
                    FileUtils::joinPaths($common_location, $student->getId() . ".png"),
                    FileUtils::joinPaths($common_location, $student->getId() . ".JPEG"),
                    FileUtils::joinPaths($common_location, $student->getId() . ".JPG"),
                    FileUtils::joinPaths($common_location, $student->getId() . ".PNG"),
                    ];

                foreach ($possible_matches as $possible_match) {
                    if (file_exists($possible_match) && FileUtils::isValidImage($possible_match)) {
                        $mime_subtype = explode('/', mime_content_type($possible_match), 2)[1];
                        $image_data[$student->getId()] =
                            [
                                'subtype' => $mime_subtype,
                                'path' => $possible_match
                             ];
                        break;
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
