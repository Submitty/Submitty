<?php

declare(strict_types=1);

namespace app\views\calendar;

use app\libraries\GradeableType;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradeableList;
use app\views\AbstractView;
use app\views\NavigationView;

class CalendarView extends AbstractView {

    public function showCalendar(GradeableList $gradeable_list, bool $showOnlyCalendar=false) {
        $gradeables_by_date = [];
        $render_sections = [];
        $gradeable_list_sections = [
            GradeableList::OPEN => $gradeable_list->getOpenGradeables(),
            GradeableList::GRADING => $gradeable_list->getGradingGradeables(),
            GradeableList::CLOSED => $gradeable_list->getClosedGradeables(),
            GradeableList::GRADED => $gradeable_list->getGradedGradeables(),
        ];

        foreach ($gradeable_list_sections as $section => $gradeables) {
            $render_section = NavigationView::gradeableSections[$section];
            $render_section['gradeables'] = [];
            foreach (array_reverse($gradeables, true) as $id => $gradeable) {
                [$semester, $course_title, $gradeable_id] = explode("||", $id);
                /** @var Gradeable $gradeable */
                $currGradeable = [
                    'gradeable_id' => $gradeable_id,
                    'title' => $gradeable->getTitle(),
                    'semester' => $semester,
                    'course' => $course_title,
                    'url' => $this->core->buildUrl(['courses', $semester, $course_title, 'gradeable', $gradeable->getId()]),
                    'submission' => ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) ? $gradeable->getSubmissionDueDate() : '',
                    'status' => $section,
                    'grading_open' => $gradeable->getGradeStartDate(),
                    'grading_due' => $gradeable->getGradeDueDate()
                ];
                $render_section['gradeables'][$id] = $currGradeable;

                // Put gradeables in by-date maps according to section (close/open)
                $dueDate = ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) ? $gradeable->getSubmissionDueDate()->format('Y-m-d') : '';
                $gradeables_by_date[$dueDate][] = $currGradeable;
            }
            $render_sections[] = $render_section;
        }

        $year = (isset($_GET['year']) && $_GET['year'] != "")  ?  $_GET['year']  : (int)date("Y");
        $month = (isset($_GET['month']) && $_GET['month'] != "") ?  $_GET['month'] : (int)date("n");

        // Error checking
        if ($month < 0 || $month > 12) {
            $month = (int)date("n");
        } else {
            $month = (int)sprintf("%08d", $month); // remove leading zero
        }
        if ($year < 1970 || $year > 2100) {
            $year = (int)date("Y");
        }

        $this->core->getOutput()->addInternalCss("navigation.css");
        $this->core->getOutput()->addInternalCss('calendar.css');
        $this->core->getOutput()->addInternalJs('calendar.js');
        $this->core->getOutput()->enableMobileViewport();
        $this->core->getOutput()->addBreadcrumb("Calendar");

        return $this->core->getOutput()->renderTwigTemplate("calendar/Calendar.twig", [
            "show_only_calendar" => 0,
            "view_year" => $year,
            "view_month" => $month,
            "curr_year" => date("Y"),
            "curr_month" => date("n"),
            "curr_day" => date("d"),
            "gradeables_by_date" => $gradeables_by_date,
            "gradeable_sections" => $render_sections
        ]);
    }
}
