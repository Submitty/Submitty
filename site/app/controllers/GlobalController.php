<?php

namespace app\controllers;

use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\Button;
use app\models\User;

class GlobalController extends AbstractController {

    public function header() {
        $wrapper_files = $this->core->getConfig()->getWrapperFiles();
        $wrapper_urls = array_map(function ($file) {
            return $this->core->buildCourseUrl(['read_file']) . '?' . http_build_query([
                'dir' => 'site',
                'path' => $file,
                'file' => pathinfo($file, PATHINFO_FILENAME),
                'csrf_token' => $this->core->getCsrfToken()
            ]);
        }, $wrapper_files);

        $breadcrumbs = $this->core->getOutput()->getBreadcrumbs();
        $page_name = $this->core->getOutput()->getPageName();
        $css = $this->core->getOutput()->getCss();
        $js = $this->core->getOutput()->getJs();

        if (array_key_exists('override.css', $wrapper_urls)) {
            $css->add($wrapper_urls['override.css']);
        }

        $unread_notifications_count = null;
        if ($this->core->getUser() && $this->core->getConfig()->isCourseLoaded()) {
            $unread_notifications_count = $this->core->getQueries()->getUnreadNotificationsCount($this->core->getUser()->getId(), null);
        }

        $sidebar_buttons = [];

        $this->prep_sidebar($sidebar_buttons, $unread_notifications_count);

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

        $now = $this->core->getDateTimeNow();
        $duck_img = $this->getDuckImage($now);

        return $this->core->getOutput()->renderTemplate('Global', 'header', $breadcrumbs, $wrapper_urls, $sidebar_buttons, $unread_notifications_count, $css->toArray(), $js->toArray(), $duck_img, $page_name);
    }

    // ==========================================================================================
    // ==========================================================================================
    public function prep_sidebar(&$sidebar_buttons, $unread_notifications_count) {

        if (!$this->core->userLoaded()) {
            return;
        }
        if ($this->core->getConfig()->isCourseLoaded()) {
            $this->prep_course_sidebar($sidebar_buttons, $unread_notifications_count);
        }
        $this->prep_user_sidebar($sidebar_buttons);

        $sidebar_buttons[] = new Button($this->core, [
            "href" => "javascript: toggleSidebar();",
            "title" => "Collapse Sidebar",
            "class" => "nav-row",
            "id" => "nav-sidebar-collapse",
            "icon" => "fa-bars"
        ]);

        $sidebar_buttons[] = new Button($this->core, [
            "href" => $this->core->buildUrl(['authentication', 'logout']),
            "title" => "Logout " . $this->core->getUser()->getDisplayedFirstName(),
            "id" => "logout",
            "class" => "nav-row",
            "icon" => "fa-power-off"
        ]);
    }

    // ==========================================================================================
    public function prep_course_sidebar(&$sidebar_buttons, $unread_notifications_count) {

        if ($this->core->getConfig()->getCourseHomeUrl() != "") {
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->getConfig()->getCourseHomeUrl(),
                "title" => "Course Home",
                "class" => "nav-row",
                "id" => "nav-sidebar-course-home",
                "icon" => "fa-home"
            ]);
        }

        $navigation_url = $this->core->buildCourseUrl();
        $sidebar_buttons[] = new Button($this->core, [
            "href" => $navigation_url,
            "title" => "Gradeables",
            "class" => "nav-row",
            "id" => "nav-sidebar-submitty",
            "icon" => "fas fa-star"
         ]);

        if ($unread_notifications_count !== null) {
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['notifications']),
                "title" => "Notifications",
                "badge" => $unread_notifications_count,
                "class" => "nav-row",
                "id" => "nav-sidebar-notifications",
                "icon" => "fa-bell"
            ]);
        }

        if ($this->core->getUser()->accessAdmin()) {
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['gradeable']),
                "title" => "New Gradeable",
                "class" => "nav-row",
                "id" => "nav-sidebar-new-gradeable",
                "icon" => "fa-plus-square"
            ]);
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['config']),
                "title" => "Course Settings",
                "class" => "nav-row",
                "id" => "nav-sidebar-course-settings",
                "icon" => "fa-cog"
            ]);
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['sql_toolbox']),
                "title" => "SQL Toolbox",
                "class" => "nav-row",
                "id" => "nav-sidebar-sql-toolbox",
                "icon" => "fa-toolbox"
            ]);
        }

        if ($this->core->getConfig()->isQueueEnabled()) {
            if ($this->core->getQueries()->isAnyQueueOpen()) {
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildCourseUrl(['office_hours_queue']),
                    "title" => "Office Hours Queue",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-queue",
                    "icon" => "fa-door-open"
                ]);
            }
            else {
                $sidebar_buttons[] = new Button($this->core, [
                   "href" => $this->core->buildCourseUrl(['office_hours_queue']),
                   "title" => "Office Hours Queue",
                   "class" => "nav-row",
                   "id" => "nav-sidebar-queue",
                   "icon" => "fa-door-closed"
                ]);
            }
        }

        if ($this->core->getConfig()->isPollsEnabled()) {
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['polls']),
                "title" => "Submini Polls",
                "class" => "nav-row",
                "id" => "nav-sidebar-polls",
                "icon" => "fa-question-circle"
            ]);
        }

        $course_path = $this->core->getConfig()->getCoursePath();
        $course_materials_path = $course_path . "/uploads/course_materials";
        $any_files = FileUtils::getAllFiles($course_materials_path);
        if ($this->core->getUser()->accessAdmin() || !empty($any_files)) {
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['course_materials']),
                "title" => "Course Materials",
                "class" => "nav-row",
                "id" => "nav-sidebar-course-materials",
                "icon" => "fa-copy"
            ]);
        }

        if ($this->core->getConfig()->isForumEnabled()) {
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['forum']),
                "title" => "Discussion Forum",
                "class" => "nav-row",
                "id" => "nav-sidebar-forum",
                "icon" => "fa-comments"
            ]);
        }

        // --------------------------------------------------------------------------

        $sidebar_buttons[] = new Button($this->core, [
            "class" => "nav-row short-line"
        ]);

        $sidebar_links = FileUtils::joinPaths(
            $this->core->getConfig()->getCoursePath(),
            'site',
            'sidebar.json'
        );
        if (file_exists($sidebar_links)) {
            $links = json_decode(file_get_contents($sidebar_links), true);
            if (is_array($links)) {
                foreach ($links as $link) {
                    if (is_array($link)) {
                        if (empty($link['title'])) {
                            continue;
                        }
                        if (empty($link['icon'])) {
                            $link['icon'] = "fa-question";
                        }
                        if (!Utils::startsWith($link['icon'], "fa-")) {
                            $link['icon'] = "fa-" . $link['icon'];
                        }
                        $sidebar_buttons[] = new Button($this->core, [
                            "href" => $link['link'] ?? null,
                            "title" => $link['title'],
                            "class" => "nav-row",
                            "id" => "nav-sidebar-" . strtolower(str_replace(" ", "_", $link['title'])),
                            "icon" => $link['icon']
                        ]);
                    }
                }
                if (count($links) > 0) {
                    $sidebar_buttons[] = new Button($this->core, [
                        "class" => "nav-row short-line"
                    ]);
                }
            }
        }

        // --------------------------------------------------------------------------

        if ($this->core->getUser()->accessAdmin()) {
            $sidebar_buttons[] = new Button($this->core, [
               "href" => $this->core->buildCourseUrl(['users']),
               "title" => "Manage Students",
               "class" => "nav-row",
               "id" => "nav-sidebar-students",
               "icon" => "fa-user-graduate"
            ]);
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['graders']),
                "title" => "Manage Graders",
                "class" => "nav-row",
                "id" => "nav-sidebar-graders",
                "icon" => "fa-address-book"
            ]);
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['sections']),
                "title" => "Manage Sections",
                "class" => "nav-row",
                "id" => "nav-sidebar-sections",
                "icon" => "fa-users"
            ]);
        }

        if ($this->core->getUser()->accessGrading()) {
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['student_photos']),
                "title" => "Student Photos",
                "class" => "nav-row",
                "id" => "nav-sidebar-photos",
                "icon" => "fa-id-card"
            ]);
        }

        if (
            $this->core->getUser()->accessAdmin()
            && $this->core->getConfig()->displayRainbowGradesSummary()
        ) {
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(["gradebook"]),
                "title" => "Gradebook",
                "class" => "nav-row",
                "id" => "nav-sidebar-gradebook",
                "icon" => "fa-book-reader"
            ]);
        }

        // --------------------------------------------------------------------------

        if ($this->core->getUser()->accessGrading()) {
            $sidebar_buttons[] = new Button($this->core, [
                "class" => "nav-row short-line"
            ]);
        }

        if ($this->core->getUser()->accessAdmin()) {
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['late_days']),
                "title" => "Late Days Allowed",
                "class" => "nav-row",
                "id" => "nav-sidebar-late-days-allowed",
                "icon" => "fa-calendar-check"
            ]);
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['extensions']),
                "title" => "Excused Absence Extensions",
                "class" => "nav-row",
                "id" => "nav-sidebar-extensions",
                "icon" => "fa-calendar-plus"
            ]);
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['grade_override']),
                "title" => "Grade Override",
                "class" => "nav-row",
                "id" => "nav-sidebar-grade-override",
                "icon" => "fa-eraser"
            ]);

            if ($this->core->getConfig()->checkFeatureFlagEnabled('plagiarism')) {
                $sidebar_buttons[] = new Button($this->core, [
                    "href" => $this->core->buildCourseUrl(['plagiarism']),
                    "title" => "Plagiarism Detection",
                    "class" => "nav-row",
                    "id" => "nav-sidebar-plagiarism",
                    "icon" => "fa-exclamation-triangle"
                ]);
            }

            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['reports']),
                "title" => "Grade Reports",
                "class" => "nav-row",
                "id" => "nav-sidebar-reports",
                "icon" => "fa-chart-bar"
            ]);
            $sidebar_buttons[] = new Button($this->core, [
                "class" => "nav-row short-line",
            ]);
        }

        // --------------------------------------------------------------------------

        $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();
        if ($display_rainbow_grades_summary) {
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildCourseUrl(['grades']),
                "title" => "Rainbow Grades",
                "class" => "nav-row",
                "id" => "nav-sidebar-grades",
                "icon" => "fa-chart-line"
            ]);
        }

        $sidebar_buttons[] = new Button($this->core, [
            "href" => $this->core->buildCourseUrl(['late_table']),
            "title" => "My Late Days/Extensions",
            "class" => "nav-row",
            "id" => "nav-sidebar-late-days",
            "icon" => "fa-calendar"
        ]);

        // --------------------------------------------------------------------------
        $sidebar_buttons[] = new Button($this->core, [
            "class" => "nav-row short-line",
        ]);
    }

    // ==========================================================================================
    public function prep_user_sidebar(&$sidebar_buttons) {

        // --------------------------------------------------------------------------
        // ALL USERS
        $sidebar_buttons[] = new Button($this->core, [
            "href" => $this->core->buildUrl(['home']),
            "title" => "My Courses",
            "class" => "nav-row",
            "id" => "nav-Wsidebar-my-courses",
            "icon" => "fa-book-reader"
        ]);
        $sidebar_buttons[] = new Button($this->core, [
            "href" => $this->core->buildUrl(['user_profile']),
            "title" => "My Profile",
            "class" => "nav-row",
            "id" => "nav-sidebar-my-profile",
            "icon" => "fa-user"
        ]);

        // --------------------------------------------------------------------------
        // FACULTY & SUPERUSERS ONLY
        if ($this->core->getUser()->accessFaculty()) {
            $sidebar_buttons[] = new Button($this->core, [
                "class" => "nav-row short-line",
            ]);
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildUrl(['admin', 'docker']),
                "title" => "Docker UI",
                "class" => "nav-row",
                "id" => "nav-sidebar-docker-link",
                "icon" => "fa-docker",
                "prefix" => "fab",
            ]);

            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildUrl(['home', 'courses', 'new']),
                "title" => "New Course",
                "class" => "nav-row",
                "id" => "nav-sidebar-new-course",
                "icon" => "fa-plus-square"
            ]);
        }

        // --------------------------------------------------------------------------
        // SUPERUSERS ONLY
        if ($this->core->getUser()->getAccessLevel() === User::LEVEL_SUPERUSER) {
            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildUrl(['superuser', 'gradeables']),
                "title" => "Pending Gradeables",
                "class" => "nav-row",
                "id" => "nav-sidebar-submitty",
                "icon" => "fas fa-clock"
            ]);

            $sidebar_buttons[] = new Button($this->core, [
                "href" => $this->core->buildUrl(['update']),
                "title" => "System Update",
                "class" => "nav-row",
                "id" => "nav-sidebar-update",
                "icon" => "fas fa-sync"
            ]);
        }

        $sidebar_buttons[] = new Button($this->core, [
            "class" => "nav-row short-line",
        ]);
    }

    // ==========================================================================================
    private function getDuckImage(\DateTime $now): string {
        $duck_img = 'moorthy_duck/00-original.svg';
        $day = (int) $now->format('j');
        $month = (int) $now->format('n');
        $year = $now->format('Y');

        switch ($month) {
            case 12:
                break;
            case 11:
                //November (Thanksgiving)
                //last week of Novemeber
                $tgt_date = date('Y-W-n', strtotime("fourth Thursday of November $year"));
                if ($tgt_date === $now->format('Y-W-n')) {
                    $duck_img = 'moorthy_duck/11-november.svg';
                }
                break;
            case 10:
                //October (Halloween)
                if ($day >= 25 && $day <= 31) {
                    $duck_img = 'moorthy_duck/halloween.png';
                }
                break;
            case 9:
                break;
            case 8:
                break;
            case 7:
                //July (Independence)
                if ($day >= 1 && $day <= 7) {
                    $duck_img = 'moorthy_duck/07-july.svg';
                }
                break;
            case 6:
                //June (Pride)
                $duck_img = 'moorthy_duck/06-june.svg';
                break;
            case 5:
                //May (Graduation)
                $duck_img = 'moorthy_duck/05-may.svg';
                break;
            case 4:
                //April (Flowers)
                $duck_img = 'moorthy_duck/04-april.svg';
                break;
            case 3:
                //Saint Patrick's Day (Shamrock)
                if ($day >= 14 && $day <= 20) {
                    $duck_img = 'moorthy_duck/03-march.svg';
                }
                break;
            case 2:
                //Valentines (Hearts)
                if ($day >= 11 && $day <= 17) {
                    $duck_img = 'moorthy_duck/02-february.svg';
                }
                break;
            case 1:
                //January (Snowflakes)
                $duck_img = 'moorthy_duck/01-january.svg';
                break;
            default:
                break;
        }

        return $duck_img;
    }


    public function footer() {
        $wrapper_files = $this->core->getConfig()->getWrapperFiles();
        $wrapper_urls = array_map(function ($file) {
            return $this->core->buildCourseUrl(['read_file']) . '?' . http_build_query([
                'dir' => 'site',
                'path' => $file,
                'file' => pathinfo($file, PATHINFO_FILENAME),
                'csrf_token' => $this->core->getCsrfToken()
            ]);
        }, $wrapper_files);
        // Get additional links to display in the global footer.
        $footer_links = [];
        $footer_links_json_file = FileUtils::joinPaths($this->core->getConfig()->getConfigPath(), "footer_links.json");
        if (file_exists($footer_links_json_file)) {
            $footer_links_json_data = file_get_contents($footer_links_json_file);
            if ($footer_links_json_data !== false) {
                $footer_links_json_data = json_decode($footer_links_json_data, true);
                // Validate that every footer link ($row) has required columns: 'url' and 'title'.
                // $row can also have an 'icon' column, but it is optional.
                foreach ($footer_links_json_data as $row) {
                    switch (false) {
                        case array_key_exists('url', $row):
                        case array_key_exists('title', $row):
                            //Validation fail.  Exclude $row.
                            continue 2;
                        default:
                            //Validation OK.  Include $row.
                            if (isset($row['icon']) && !Utils::startsWith($row['icon'], "fa-")) {
                                $row['icon'] = "fa-" . $row['icon'];
                            }
                            $footer_links[] = $row;
                    }
                }
            }
        }
        // append the help links
        if ($this->core->getConfig()->getSysAdminUrl() !== '') {
            $footer_links[] =  ["title" => "Report Issues", "url" => $this->core->getConfig()->getSysAdminUrl()];
        }
        if ($this->core->getConfig()->getSysAdminEmail() !== '') {
            $footer_links[] =  ["title" => "Email Admin", "url" => $this->core->getConfig()->getSysAdminEmail(), "is_email" => true];
        }

        $runtime = $this->core->getOutput()->getRunTime();
        return $this->core->getOutput()->renderTemplate('Global', 'footer', $runtime, $wrapper_urls, $footer_links);
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

        $query_a = array_filter($query_a, function ($param) use ($ignored_params) {
            return !in_array(explode("=", $param)[0], $ignored_params);
        });
        $query_b = array_filter($query_b, function ($param) use ($ignored_params) {
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
