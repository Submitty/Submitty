<?php

namespace app\views\grading;

use app\models\User;
use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\Utils;

class ImagesView extends AbstractView {

    /** Defines the html for the icon used to flag an image */
    const FLAG_ICON_HTML = '<i class="fas fa-flag"></i>';

    /** Defines the html for the icon used to unflag an image */
    const UNDO_ICON_HTML = '<i class="fas fa-undo"></i>';

    /** Defines the maximum dimension for images being displayed on the student photos page */
    const IMG_MAX_DIMENSION = 200;

    /**
     * @param User[] $students
     * @return string
     */
    public function listStudentImages($students, $grader_sections, $has_full_access, $view) {
        $this->core->getOutput()->addBreadcrumb("Student Photos");
        $this->core->getOutput()->addInternalJs("drag-and-drop.js");
        $this->core->getOutput()->addInternalCss(FileUtils::joinPaths('fileinput.css'));
        $this->core->getOutput()->enableMobileViewport();

        //Assemble students into sections if they are in grader_sections based on the registration section.
        $sections = [];
        foreach ($students as $student) {
            $student_section = ($student->getRegistrationSection() === null) ? "NULL" : $student->getRegistrationSection();
            $student_belongs_to_grader = in_array($student_section, $grader_sections);

            if ($has_full_access && (empty($grader_sections) || $view === 'all')) {
                // Full access no sections or view all
                $sections[$student_section][] = $student;
            }
            elseif ($has_full_access && $view === 'sections' && $student_belongs_to_grader) {
                // Full access view sections
                $sections[$student_section][] = $student;
            }
            elseif ($student_belongs_to_grader) {
                // Limited access only show their sections
                $sections[$student_section][] = $student;
            }
        }

        $max_size = Utils::returnBytes(ini_get('upload_max_filesize'));
        $max_size_string = Utils::formatBytes("MB", $max_size) . " (" . Utils::formatBytes("KB", $max_size) . ")";

        $this->core->getOutput()->disableBuffer();
        return $this->core->getOutput()->renderTwigTemplate("grading/Images.twig", [
            "sections" => $sections,
            "has_full_access" => $has_full_access,
            "csrf_token" => $this->core->getCsrfToken(),
            "max_size_string" => $max_size_string,
            "view" => $view,
            "student_photos_url" => $this->core->buildCourseUrl(['student_photos']),
            "has_sections" => !empty($grader_sections)
        ]);
    }
}
