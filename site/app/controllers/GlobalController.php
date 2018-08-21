<?php


namespace app\controllers;


use app\libraries\FileUtils;
use app\libraries\Utils;
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

        $unread_notifications_count = null;
        if ($this->core->getUser() && $this->core->getConfig()->isCourseLoaded()) {
            $unread_notifications_count = $this->core->getQueries()->getUnreadNotificationsCount($this->core->getUser()->getId(), null);
        }

        $sidebar_buttons = [];
        if ($this->core->userLoaded()) {

            if ($this->core->getConfig()->isCourseLoaded()) {
                if ($this->core->getConfig()->getCourseHomeUrl() != "") {
                    $sidebar_buttons[] = new Button($this->core, [
                        "href" => $this->core->getConfig()->getCourseHomeUrl(),
                        "title" => "Course Home",
                        "class" => "nav-row",
                        "id" => "nav-sidebar-course-home",
                        "icon" => "fa-home"
                    ]);
                }
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'navigation')),
                    "title" => "Gradeables",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-submitty",
                    "icon" => "fa-star"
                ]);
            }

            if ($unread_notifications_count !== null) {
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'navigation', 'page' => 'notifications')),
                    "title" => "Notifications",
                    "badge" => $unread_notifications_count,
                    "class" => "nav-row",
                    "id" => "nav-sidebar-notifications",
                    "icon" => "fa-bell"
                ]);
            }
        }

        if ($this->core->userLoaded() && $this->core->getConfig()->isCourseLoaded()) {
            if ($this->core->getUser()->accessAdmin()) {
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => 'view_gradeable_page')),
                    "title" => "New Gradeable",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-new-gradeable",
                    "icon" => "fa-plus-square"
                ]);
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'configuration', 'action' => 'view')),
                    "title" => "Course Settings",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-course-settings",
                    "icon" => "fa-gear"
                ]);
            }

            $course_path = $this->core->getConfig()->getCoursePath();
            $course_materials_path = $course_path . "/uploads/course_materials";
            $any_files = FileUtils::getAllFiles($course_materials_path);
            if ($this->core->getUser()->getGroup() === 1 || !empty($any_files)) {
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'grading', 'page' => 'course_materials', 'action' => 'view_course_materials_page')),
                    "title" => "Course Materials",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-course-materials",
                    "icon" => "fa-files-o"
                ]);
            }

            if ($this->core->getConfig()->isForumEnabled()) {
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')),
                    "title" => "Discussion Forum",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-forum",
                    "icon" => "fa-comments"
                ]);
            }

            $sidebar_buttons[] = new Button($this->core, [
                "class" => "nav-row short-line"
            ]);

            $sidebar_links = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'config', 'sidebar.json');
            if (file_exists($sidebar_links)) {
                $links = json_decode(file_get_contents($sidebar_links), true);
                if (is_array($links)) {
                    foreach ($links as $link) {
                        if (empty($link['title'])) {
                            continue;
                        }
                        if (empty($link['icon'])) {
                            $link['icon'] = "fa-question";
                        }
                        if (!Utils::startsWith($link['icon'], "fa-")) {
                            $link['icon'] = "fa-".$link['icon'];
                        }
                        $sidebar_buttons[] = new Button($this->core, [
                            "href" => $link['link'] ?? null,
                            "title" => $link['title'],
                            "class" => "nav-row",
                            "id" => "nav-sidebar-".strtolower(str_replace(" ", "_", $link['title'])),
                            "icon" => $link['icon']
                        ]);
                    }
                    $sidebar_buttons[] = new Button($this->core, ["class" => "nav-row short-line"]);
                }
            }

            if ($this->core->getUser()->accessAdmin()) {
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'users')),
                    "title" => "Manage Students",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-students",
                    "icon" => "fa-graduation-cap"
                ]);
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'users', 'action' => 'graders')),
                    "title" => "Manage Graders",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-graders",
                    "icon" => "fa-address-book"
                ]);
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'users', 'action' => 'rotating_sections')),
                    "title" => "Manage Sections",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-sections",
                    "icon" => "fa-users"
                ]);
            }

            if ($this->core->getUser()->accessGrading()) {
                $images_course_path = $this->core->getConfig()->getCoursePath();
                $images_path = Fileutils::joinPaths($images_course_path, "uploads/student_images");
                $any_images_files = FileUtils::getAllFiles($images_path, array(), true);
                if ($this->core->getUser()->getGroup() === 1 && count($any_images_files) === 0) {
                    $sidebar_buttons[] = new Button($this->core, [
                        "href" => $this->core->buildUrl(array('component' => 'grading', 'page' => 'images', 'action' => 'view_images_page')),
                        "title" => "Student Photos",
                        "class" => "nav-row",
                        "id" => "nav-sidebar-photos",
                        "icon" => "fa-id-card"
                    ]);
                } else if (count($any_images_files) !== 0 && $this->core->getUser()->accessGrading()) {
                    $sections = $this->core->getUser()->getGradingRegistrationSections();
                    if (!empty($sections) || $this->core->getUser()->getGroup() !== 3) {
                        $sidebar_buttons[] = new Button($this->core, [
                            "href" => $this->core->buildUrl(array('component' => 'grading', 'page' => 'images', 'action' => 'view_images_page')),
                            "title" => "Student Photos",
                            "class" => "nav-row",
                            "id" => "nav-sidebar-photos",
                            "icon" => "fa-id-card"
                        ]);
                    }
                }
                //$sidebar_buttons[] = new Button($this->core, [
                //    "class" => "nav-row short-line"
                //]);
            }

            if ($this->core->getUser()->accessGrading()) {
                $sidebar_buttons[] = new Button($this->core, [
                    "class" => "nav-row short-line"
                ]);
            }
            //$sidebar_buttons[] = new Button($this->core, [
            //    "class" => "nav-row short-line"
            //]);

            if ($this->core->getUser()->accessAdmin()) {
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'late', 'action' => 'view_late')),
                    "title" => "Late Days Allowed",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-late-days-allowed",
                    "icon" => "fa-calendar-plus-o"
                ]);
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'late', 'action' => 'view_extension')),
                    "title" => "Excused Absence Extensions",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-extensions",
                    "icon" => "fa-calendar-plus-o"
                ]);
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism')),
                    "title" => "Plagiarism Detection",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-plagiarism",
                    "icon" => "fa-exclamation-triangle"
                ]);
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'admin', 'page' => 'reports', 'action' => 'reportpage')),
                    "title" => "Grade Reports",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-reports",
                    "icon" => "fa-bar-chart"
                ]);
                $sidebar_buttons[] = new Button($this->core, [
                    "class" => "nav-row short-line",
                ]);
            }


            $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();
            if ($display_rainbow_grades_summary) {
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildUrl(array('component' => 'student', 'page' => 'rainbow')),
                    "title" => "Rainbow Grades",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-grades",
                    "icon" => "fa-line-chart"
                ]);
            }

            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildUrl(array('component' => 'student', 'page' => 'view_late_table')),
                "title" => "My Late Days",
                "class" => "nav-row",
                "id" => "nav-sidebar-late-days",
                "icon" => "fa-calendar-o"
            ]);
        }

        if ($this->core->userLoaded()) {
            if ($this->core->getConfig()->isCourseLoaded()) {
                $sidebar_buttons[] = new Button($this->core, [
                    "class" => "nav-row short-line",
                ]);
            }

            $sidebar_buttons[] = new Button($this->core, [
                "href" => "javascript: toggleSidebar();",
                "title" => "Collapse Sidebar",
                "class" => "nav-row",
                "id" => "nav-sidebar-collapse",
                "icon" => "fa-bars"
            ]);

            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildUrl(array('component' => 'authentication', 'page' => 'logout')),
                "title" => "Logout ".$this->core->getUser()->getDisplayedFirstName(),
                "id" => "logout",
                "class" => "nav-row",
                "icon" => "fa-sign-out"
            ]);
        }

        $current_route = $_SERVER["REQUEST_URI"];
        foreach ($sidebar_buttons as $button) {
            /* @var Button $button */
            $href = $button->getHref();
            if ($href !== null) {
                $parse = parse_url($href);
                $path = isset($parse['path']) ? $parse['path'] : '';
                $query = isset($parse['query']) ? '?' . $parse['query'] : '';
                $fragment = isset($parse['fragment']) ? '#' . $parse['fragment'] : '';
                $route = $path . $query . $fragment;

                if ($this->routeEquals($route, $current_route)) {
                    $class = $button->getClass() ?? "";
                    $class = ($class === "" ? "selected" : $class . " selected");
                    $button->setClass($class);
                }
            }
        }

        return $this->core->getOutput()->renderTemplate('Global', 'header', $breadcrumbs, $wrapper_urls, $sidebar_buttons, $unread_notifications_count, $css, $js);
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

    private function routeEquals(string $a, string $b) {
        //TODO: Have an actual router and use that instead of this string comparison

        $parse_a = parse_url($a);
        $parse_b = parse_url($b);

        $path_a = isset($parse_a['path']) ? $parse_a['path'] : '';
        $path_b = isset($parse_b['path']) ? $parse_b['path'] : '';
        $query_a = isset($parse_a['query']) ? $parse_a['query'] : '';
        $query_b = isset($parse_b['query']) ? $parse_b['query'] : '';

        //Different paths, different urls
        if ($path_a !== $path_b) {
            return false;
        }

        //Query parameters to discard when checking routes
        $ignored_params = [
            "success_login"
        ];

        //Query strings can be in (basically) arbitrary order. Make sure they at least
        // have the same parts though
        $query_a = array_filter(explode("&", $query_a));
        $query_b = array_filter(explode("&", $query_b));

        $query_a = array_filter($query_a, function($param) use($ignored_params) {
            return !in_array(explode("=", $param)[0], $ignored_params);
        });
        $query_b = array_filter($query_b, function($param) use($ignored_params) {
            return !in_array(explode("=", $param)[0], $ignored_params);
        });

        $diff_a = array_values(array_diff($query_a, $query_b));
        $diff_b = array_values(array_diff($query_b, $query_a));
        $diff = array_merge($diff_a, $diff_b);
        if (count($diff) > 0) {
            //Wacky checking because the navigation page is the default when there
            // is no route in the query
            if (count($diff) === 1 && $diff[0] === "component=navigation") {
                return true;
            }
            return false;
        }

        return true;
    }

}
