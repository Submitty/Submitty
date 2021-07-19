<?php

declare(strict_types=1);

namespace app\controllers\calendar;

use app\controllers\AbstractController;
use app\controllers\GlobalController;
use app\libraries\response\WebResponse;
use app\models\CalendarInfo;
use app\models\gradeable\GradeableList;
use app\models\gradeable\GradeableUtils;
use app\views\calendar\CalendarView;
use Symfony\Component\Routing\Annotation\Route;

class CalendarController extends AbstractController {
    /**
     * This function loads the gradeable information from all courses, and list them
     * on a calendar. The calendar is accessible through the side bar button in a
     * global scope
     *
     * @Route("/calendar")
     *
     * @return WebResponse
     * @throws \Exception if a Gradeable failed to load from the database
     * @see GlobalController::prep_user_sidebar
     * @see CalendarView::showCalendar
     */
    public function viewCalendar(): WebResponse {
        $user = $this->core->getUser();
        $gradeables_of_user = GradeableUtils::getAllGradeableListFromUserId($this->core, $user);

        return new WebResponse(CalendarView::class, 'showCalendar', CalendarInfo::loadGradeableCalendarInfo($this->core, $gradeables_of_user));
    }
}
