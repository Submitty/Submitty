<?php


namespace app\controllers;


use app\libraries\FileUtils;
use app\models\Button;

class GlobalController extends AbstractController {

    public function run() {
        //TODO: Whenever run() stops taking GET parameters require access to
        // header() and footer() to use run()
    }

    public function header() {
        $wrapper_files = $this->core->getConfig()->getWrapperFiles();
        $wrapper_urls = array_map(function($file) {
            return $this->core->buildUrl([
                'component' => 'misc',
                'page' => 'read_file',
                'dir' => 'site',
                'path' => $file,
                'file' => pathinfo($file, PATHINFO_FILENAME),
                'csrf_token' => $this->core->getCsrfToken()
            ]);
        },  $wrapper_files);

        $breadcrumbs = $this->core->getOutput()->getBreadcrumbs();
        $css = $this->core->getOutput()->getCss();
        $js = $this->core->getOutput()->getJs();

        if (array_key_exists('override.css', $wrapper_urls)) {
            $css[] = $wrapper_urls['override.css'];
        }

        /* @var Button[] $sidebar_buttons */
        $sidebar_buttons = [];
        $sidebar_buttons[] = [
            "href" => $this->core->buildUrl(array('component' => 'navigation')),
            "title" => "Navigation"
        ];

        if ($this->core->getUser()->accessAdmin()) {
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'configuration', 'action' => 'view')),
                "title" => "Course Settings"
            ];
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'view_gradeable_page')),
                "title" => "New Gradeable",
            ];
        }

        $course_path = $this->core->getConfig()->getCoursePath();
        $course_materials_path = $course_path."/uploads/course_materials";
        $any_files = FileUtils::getAllFiles($course_materials_path);
        if ($this->core->getUser()->getGroup()=== 1 || !empty($any_files)) {
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'grading', 'page' => 'course_materials', 'action' => 'view_course_materials_page')),
                "title" => "Course Materials",
            ];
        }

        if ($this->core->getConfig()->isForumEnabled()) {
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')),
                "title" => "Discussion Forum",
            ];
        }
        $sidebar_buttons[] = [];


        if ($this->core->getUser()->accessAdmin()) {
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'users')),
                "title" => "Students"
            ];
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'users', 'action' => 'graders')),
                "title" => "Graders"
            ];
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'users', 'action' => 'rotating_sections')),
                "title" => "Setup Sections"
            ];
        }

        $images_course_path = $this->core->getConfig()->getCoursePath();
        $images_path = Fileutils::joinPaths($images_course_path,"uploads/student_images");
        $any_images_files = FileUtils::getAllFiles($images_path, array(), true);
        if ($this->core->getUser()->getGroup()=== 1 && count($any_images_files)===0) {
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'grading', 'page' => 'images', 'action' => 'view_images_page')),
                "title" => "Upload Student Photos",
            ];
        }
        else if (count($any_images_files)!==0 && $this->core->getUser()->accessGrading()) {
            $sections = $this->core->getUser()->getGradingRegistrationSections();
            if (!empty($sections) || $this->core->getUser()->getGroup() !== 3) {
                $sidebar_buttons[] = [
                    "href" => $this->core->buildUrl(array('component' => 'grading', 'page' => 'images', 'action' => 'view_images_page')),
                    "title" => "Student Photos",
                ];
            }
        }

        $sidebar_buttons[] = [];

        if ($this->core->getUser()->accessAdmin()) {
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'late', 'action' => 'view_late')),
                "title" => "Late Days Allowed"
            ];
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'late', 'action' => 'view_extension')),
                "title" => "Excused Absence Extensions"
            ];
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism')),
                "title" => "Plagiarism Detection"
            ];
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'reports', 'action' => 'reportpage')),
                "title" => "Grade Reports"
            ];
        }

        $sidebar_buttons[] = [];

        $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();
        if ($display_rainbow_grades_summary) {
            $sidebar_buttons[] = [
                "href" => $this->core->buildUrl(array('component' => 'student', 'page' => 'rainbow')),
                "title" => "My Grades",
            ];
        }

        $sidebar_buttons[] = [
            "href" => $this->core->buildUrl(array('component' => 'student', 'page' => 'view_late_table')),
            "title" => "My Late Days",
        ];

        return $this->core->getOutput()->renderTemplate('Global', 'header', $breadcrumbs, $wrapper_urls, $sidebar_buttons, $css, $js);
    }

    public function footer() {
        $wrapper_files = $this->core->getConfig()->getWrapperFiles();
        $wrapper_urls = array_map(function($file) {
            return $this->core->buildUrl([
                'component' => 'misc',
                'page' => 'read_file',
                'dir' => 'site',
                'path' => $file,
                'file' => pathinfo($file, PATHINFO_FILENAME),
                'csrf_token' => $this->core->getCsrfToken()
            ]);
        },  $wrapper_files);
        $runtime = $this->core->getOutput()->getRunTime();
        return $this->core->getOutput()->renderTemplate('Global', 'footer', $runtime, $wrapper_urls);
    }

}