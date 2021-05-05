<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\GradeableType;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradeableList;
use app\views\NavigationView;
use DateTime;

/**
 * Class CalendarInfo
 *
 * Container of information used to fill the calendar.
 */
class CalendarInfo extends AbstractModel {

    /**
     * @var array<string, array<string, string|DateTime>>
     * the structure of the array is a "YYYY-mm-dd" date string as key, and value
     * contains an array with a structure of
     * 'gradeable_id' => string   (the id of the item, reserved row and useless for now)
     * 'title'        => string   (the title of the item which will be shown on each clickable button)
     * 'semester'     => string   (the semester of which the item belongs)
     * 'course'       => string   (the title of the course of which the item belongs)
     * 'url'          => string   (the url of the clickable button)
     * 'onclick'      => string   (the onclick js function of the clickable button)
     * 'submission'   => string   (the timestamp of the item, shown in the popup tooltip)
     * 'status'       => string   (the status of the gradeable, open/closed/grading..., is used to show different
     *                             colors of item, relation between color and integer are recorded in css)
     * 'status_note'  => string   (a string describing this status)
     * 'grading_open' => string   (reserved, useless for now. Can be empty)
     * 'grading_due'  => string   (reserved, useless for now. Can be empty)
     * 'show_due'     => bool     (whether to show the due date when mouse is hovering over),
     * 'icon'         => string   (the icon showed before the item),
     */
    private $items_by_date = [];

    /**
     * @see GradeableList for constant integers used as keys
     * @var array<int, array>
     * the structure of the array is a integer as key, and value
     * contains an array with a structure of
     * "title"      => string (title shown at the top of the table),
     * "subtitle"   => string (title shown at the top of the table, if any. Can be empty),
     * "section_id" => string (the id of the section. Will be used as the HTML id)
     * "gradeables" => array. The structure of this array is same as the element of value of $items_by_date
     */
    private $items_by_sections = [];

    /** @var string */
    private $empty_message = "";


    /**
     * A static factory method to create a CalendarInfo object using the information
     * from a GradeableList object.
     *
     * @param Core $core
     * @param array $gradeables_of_user container of gradeables in the system
     * @return CalendarInfo
     */
    public static function loadGradeableCalendarInfo(Core $core, array $gradeables_of_user): CalendarInfo {
        $info = new CalendarInfo($core);
        $date_format = $core->getConfig()->getDateTimeFormat()->getFormat('gradeable');

        $gradeable_list = new GradeableList($core, $core->getUser(), $gradeables_of_user["gradeables"]);

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
            foreach ($gradeables as $key => $gradeable) {
                /** @var Gradeable $gradeable */
                [$semester, $course_title, $gradeable_id] = unserialize($key);

                // Get the submit button for the gradeable to retrieve the gradeable information
                /** @var Button|null $submit_btn */
                $submit_btn = $gradeables_of_user["submit_btns"][$key];

                $currGradeable = [
                    'gradeable_id' => $gradeable_id,
                    'title' => $gradeable->getTitle(),
                    'semester' => $semester,
                    'course' => $course_title,
                    'url' => $info->core->buildUrl(['courses', $semester, $course_title, 'gradeable', $gradeable->getId()]),
                    'onclick' => '',
                    'submission' => ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) ? $gradeable->getSubmissionDueDate()->format($date_format) : '',
                    'status' => (string) $section,
                    'status_note' => ($submit_btn === null) ? "N/A" : $submit_btn->getTitle(),
                    'grading_open' => $gradeable->getGradeStartDate() !== null ? $gradeable->getGradeStartDate()->format($date_format) : '',
                    'grading_due' => $gradeable->getGradeDueDate() !== null ? $gradeable->getGradeDueDate()->format($date_format) : '',
                    'class' => ($submit_btn === null) ? "" : explode(' ', $submit_btn->getClass())[1],
                    'disabled' => !($submit_btn === null) && $submit_btn->isDisabled(),
                    'show_due' => true,
                    'icon' => '',
                ];

                // Put gradeables in current section by their id which consists of semester, course title and gradeable id
                $curr_section['gradeables'][$key] = $currGradeable;

                // Put gradeables in by-date maps according to section (close/open)
                $dueDate = ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) ? $gradeable->getSubmissionDueDate()->format('Y-m-d') : '';
                $info->items_by_date[$dueDate][] = $currGradeable;
            }

            // Put data of current section into the by-section map
            $info->items_by_sections[] = $curr_section;
        }

        $info->empty_message = "There are currently no assignments posted.  Please check back later.";

        return $info;
    }

    public function getInfoByDate(): array {
        return $this->items_by_date;
    }

    public function getInfoBySections(): array {
        return $this->items_by_sections;
    }

    public function getEmptyMessage(): string {
        return $this->empty_message;
    }
}
