<?php

declare(strict_types=1);

namespace app\views\calendar;

use app\libraries\FileUtils;
use app\models\CalendarInfo;
use app\models\GlobalCalendarInfo;
use app\models\User;
use app\views\AbstractView;
use app\views\NavigationView;
use app\models\Course;

class CalendarView extends AbstractView {
    /**
     * This function shows a calendar with arbitrary items. It first shows a calendar view that list all items on
     * calendar by their given date. Then it shows a series of tables to list all items.
     *
     * @param CalendarInfo $info the information used to fill the calendar
     * @param array<Course> $courses the courses that will be shown
     * @return string
     * @see NavigationView::gradeableSections
     */
    public function showCalendar(CalendarInfo $info, GlobalCalendarInfo $global_info, array $courses, bool $in_course = false): string {

        $year = (isset($_GET['year']) && $_GET['year'] != "")  ?  (int) $_GET['year']  : (int) date("Y");
        $month = (isset($_GET['month']) && $_GET['month'] != "") ?  (int) $_GET['month'] : (int) date("n");
        $show_table = (isset($_GET['show_table'])) ? (int) $_GET['show_table'] : 0; // not showing the table by default

        // Error checking
        $month = (int) sprintf("%08d", $month); // remove leading zero

        if ($month < 0 || $month > 12) {
            $month = (int) date("n");
        }
        if ($year < 1970 || $year > 2100) {
            $year = (int) date("Y");
        }

        //Create list of courses and their term and get color
        $formatted_courses = [];
        $unformatted_courses = [];
        $display_names = [];
        //$course_colors = [];
        foreach ($courses as $course) {
            $course_string = $course->getTitle() . " " . $course->getTerm();
            array_push($formatted_courses, $course_string);
        }
        $courseWithName = [];
        foreach ($courses as $index => $course) {
            $displayName = $course->getDisplayName();
            $nameString = ($displayName !== '' ? $displayName : $course->getTitle()) . ' ' . $course->getTerm();

            $courseWithName[] = [
                'course_name' => $formatted_courses[$index],
                'display_name' => $nameString,
            ];
        }


        //Set course color options
        $course_colors = [];
        $course_colors["RED"]       = "var(--category-color-1)";
        $course_colors["ORANGE"]    = "var(--category-color-2)";
        $course_colors["GREEN"]     = "var(--category-color-3)";
        $course_colors["BLUE"]      = "var(--category-color-4)";
        $course_colors["INDIGO"]    = "var(--category-color-5)";
        $course_colors["VIOLET"]    = "var(--category-color-6)";
        $course_colors["PINK"]      = "var(--category-color-7)";
        $course_colors["PURPLE"]    = "var(--category-color-8)";

        //Get if legend will be displayed
        $show_legend = (isset($_COOKIE['show_legend']))  ?  (int) $_COOKIE['show_legend'] : 1;

        $this->core->getOutput()->addInternalCss("navigation.css");
        $this->core->getOutput()->addInternalCss('calendar.css');
        $this->core->getOutput()->addInternalJs('calendar.js');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->enableMobileViewport();
        $this->core->getOutput()->addBreadcrumb($in_course ? "Course Calendar" : "Calendar");
        return $this->core->getOutput()->renderTwigTemplate("calendar/Calendar.twig", [
            "show_table" => $show_table,
            "view_year" => $year,
            "view_month" => $month,
            "curr_year" => date("Y"),
            "curr_month" => date("n"),
            "curr_day" => date("d"),
            'date_format' => $this->core->getConfig()->getDateTimeFormat()->getFormat('gradeable'),
            "gradeables_by_date" => $info->getItemsByDateInCourses(),
            "global_items_by_date" => $global_info->getGlobalItemsByDate(),
            "gradeables_by_section" => $info->getItemsBySections(),
            "empty_message" => $info->getEmptyMessage(),
            "in_course" => $in_course,
            "is_instructor" => $this->core->getUser()->getGroup() === User::GROUP_INSTRUCTOR,
            "is_superuser" => $this->core->getUser()->getAccessLevel() === User::LEVEL_SUPERUSER,
            "colors" => $info->getColors(),
            "instructor_courses" => $this->core->getQueries()->getInstructorLevelUnarchivedCourses($this->core->getUser()->getId()),
            "view_cookie" => $_COOKIE['view'] ?? $_COOKIE['view'] ?? "month",
            "month_cookie" => $_COOKIE['calendar_month'] ?? $month,
            "year_cookie" => $_COOKIE['calendar_year'] ?? $year,
            "course_names" => $courseWithName,
            "show_legend" => $show_legend,
            "color_options" => $course_colors,
            "show_all_cookie" => isset($_COOKIE['calendar_show_all']) ? $_COOKIE['calendar_show_all'] : 1,
            "calendar_course_cookie" => $_COOKIE['calendar_course'] ?? ($formatted_courses[0] ?? null),
            "display_name_cookie" => $_COOKIE['calendar_course'] ?? '',
        ]);
    }
}
