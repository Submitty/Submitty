<?php

declare(strict_types=1);

namespace app\views\calendar;

use app\libraries\GradeableType;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradeableList;
use app\views\AbstractView;
use app\views\NavigationView;

class CalendarView extends AbstractView {
    /**
     * Shows a list of Gradeable objects on a calendar.
     *
     * @param GradeableList $gradeable_list the list of gradeables
     * @return string
     */
    public function showGradeableCalendar(GradeableList $gradeable_list): string {
        $gradeables_by_date = [];
        $gradeables_by_sections = [];

        // get the gradeables from the GradeableList and group them by section
        $gradeable_list_sections = [
            GradeableList::OPEN => $gradeable_list->getOpenGradeables(),
            GradeableList::GRADING => $gradeable_list->getGradingGradeables(),
            GradeableList::CLOSED => $gradeable_list->getClosedGradeables(),
            GradeableList::GRADED => $gradeable_list->getGradedGradeables(),
        ];

        foreach ($gradeable_list_sections as $section => $gradeables) {
            /** @var int $section */
            /** @var array<Gradeable> $gradeables */
            $curr_section["title"] = NavigationView::gradeableSections[$section]["title"];
            $curr_section["subtitle"] = NavigationView::gradeableSections[$section]["subtitle"];
            $curr_section["section_id"] = NavigationView::gradeableSections[$section]["section_id"];
            $curr_section['gradeables'] = [];

            // Iterate over the Gradeable objects in current section and summarize data
            foreach (array_reverse($gradeables, true) as $id => $gradeable) {
                /** @var Gradeable $gradeable */
                [$semester, $course_title, $gradeable_id] = explode("||", $id);
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

                // Put gradeables in current section by their id which consists of semester, course title and gradeable id
                $curr_section['gradeables'][$id] = $currGradeable;

                // Put gradeables in by-date maps according to section (close/open)
                $dueDate = ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) ? $gradeable->getSubmissionDueDate()->format('Y-m-d') : '';
                $gradeables_by_date[$dueDate][] = $currGradeable;
            }

            // Put data of current section into the by-section map
            $gradeables_by_sections[] = $curr_section;
        }

        return $this->showCalendar($gradeables_by_date, $gradeables_by_sections, "There are currently no assignments posted.  Please check back later.");
    }

    /**
     * This function shows a calendar with arbitrary items. It first shows a calendar view that list all items on
     * calendar by their given date. Then it shows a series of tables to list all items.
     *
     * @param array $items_by_date array of "YYYY-mm-dd" -> array(ELEMENT),
     *              the calendar can accept anything as long as ELEMENT maintains a structure of
     *              'gradeable_id' => string (the id of the item, reserved row and useless for now)
     *              'title' => string (the title of the item which will be shown on each clickable button)
     *              'semester' => string (the semester of which the item belongs)
     *              'course' => string (the title of the course of which the item belongs)
     *              'url' => string (the url of the clickable button)
     *              'submission' => DateTime (the timestamp of the item, shown in the popup tooltip)
     *              'status' => int (the status of the gradeable, open/closed/grading..., is used to show different
     *                               colors of item, relation between color and integer are recorded in css)
     *              'grading_open' => DateTime (reserved, useless for now. Can be empty)
     *              'grading_due' => DateTime (reserved, useless for now. Can be empty)
     * @param array $item_by_sections array of id (int) -> array(ELEMENTS).
     *              the calendar can accept anything as long as $item_by_sections maintains a structure of
     *              "title" => string (title shown at the top of the table),
     *              "subtitle" =>  string (title shown at the top of the table, if any. Can be empty),
     *              "section_id" => string (the id of the section. Will be used as the HTML id)
     *              "gradeables" => array of ELEMENT. The structure of ELEMENT is described above
     * @param string $empty_message string that will shown at the table of list section when no items are listed
     * @return string
     * @see NavigationView::gradeableSections
     */
    public function showCalendar(array $items_by_date, array $item_by_sections, string $empty_message): string {
        $year = (isset($_GET['year']) && $_GET['year'] != "")  ?  (int) $_GET['year']  : (int) date("Y");
        $month = (isset($_GET['month']) && $_GET['month'] != "") ?  (int) $_GET['month'] : (int) date("n");

        // Error checking
        $month = (int) sprintf("%08d", $month); // remove leading zero

        if ($month < 0 || $month > 12) {
            $month = (int) date("n");
        }
        if ($year < 1970 || $year > 2100) {
            $year = (int) date("Y");
        }

        $this->core->getOutput()->addInternalCss("navigation.css");
        $this->core->getOutput()->addInternalCss('calendar.css');
        $this->core->getOutput()->addInternalJs('calendar.js');
        $this->core->getOutput()->enableMobileViewport();
        $this->core->getOutput()->addBreadcrumb("Calendar");

        return $this->core->getOutput()->renderTwigTemplate("calendar/Calendar.twig", [
            "show_only_calendar" => 0,
            "view_year" => $year,          // the year that the calendar is viewing
            "view_month" => $month,        // the month that the calendar is viewing
            "curr_year" => date("Y"),  // the current year
            "curr_month" => date("n"), // the current month
            "curr_day" => date("d"),   // the current date
            "gradeables_by_date" => $items_by_date,
            "gradeables_by_section" => $item_by_sections,
            "empty_message" => $empty_message
        ]);
    }
}
