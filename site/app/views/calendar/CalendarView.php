<?php

declare(strict_types=1);

namespace app\views\calendar;

use app\models\CalendarInfo;
use app\views\AbstractView;
use app\views\NavigationView;

class CalendarView extends AbstractView {
    /**
     * This function shows a calendar with arbitrary items. It first shows a calendar view that list all items on
     * calendar by their given date. Then it shows a series of tables to list all items.
     *
     * @param CalendarInfo $info the information used to fill the calendar
     * @return string
     * @see NavigationView::gradeableSections
     */
    public function showCalendar(CalendarInfo $info): string {

        $year = (isset($_GET['year']) && $_GET['year'] != "")  ?  (int) $_GET['year']  : (int) date("Y");
        $month = (isset($_GET['month']) && $_GET['month'] != "") ?  (int) $_GET['month'] : (int) date("n");
        $show_only_calendar = (isset($_GET['show_only_calendar'])) ? (int) $_GET['show_only_calendar'] : 1; // not showing the table by default

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
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->enableMobileViewport();
        $this->core->getOutput()->addBreadcrumb("Calendar");

        return $this->core->getOutput()->renderTwigTemplate("calendar/Calendar.twig", [
            "show_only_calendar" => $show_only_calendar,
            "view_year" => $year,          // the year that the calendar is viewing
            "view_month" => $month,        // the month that the calendar is viewing
            "curr_year" => date("Y"),  // the current year
            "curr_month" => date("n"), // the current month
            "curr_day" => date("d"),   // the current date
            'date_format' => $this->core->getConfig()->getDateTimeFormat()->getFormat('gradeable'),
            "gradeables_by_date" => $info->getInfoByDate(),
            "gradeables_by_section" => $info->getInfoBySections(),
            "empty_message" => $info->getEmptyMessage(),
        ]);
    }
}
