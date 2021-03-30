<?php

declare(strict_types=1);

namespace app\controllers\calendar;

use app\controllers\AbstractController;
use app\controllers\GlobalController;
use app\libraries\ExceptionHandler;
use app\libraries\response\WebResponse;
use app\models\CalendarInfo;
use app\models\gradeable\GradeableList;
use app\views\calendar\CalendarView;
use Exception;
use Symfony\Component\Routing\Annotation\Route;

class CalendarController extends AbstractController {

    /**
     * This function loads the gradeable information from all courses, and list them on a calendar. The calendar is
     * accessible through the side bar button in a global scope
     *
     * @Route("/calendar")
     *
     * @return WebResponse
     * @see GlobalController::prep_user_sidebar
     */
    public function viewCalendar(): WebResponse {
        $user = $this->core->getUser();
        try {
            $gradeable_list = GradeableList::getAllGradeableListFromUserId($this->core, $user);
        }
        catch (Exception $e) {
            ExceptionHandler::handleException($e);

            $error_messages = ['A broken gradeable was detected when collecting gradeable information from the database.  Contact the system administrator for assistance.'];
            return $this->core->getOutput()->renderOutput('Error', 'genericError', $error_messages);
        }

        return new WebResponse(CalendarView::class, 'showCalendar', CalendarInfo::loadGradeableCalendarInfo($this->core, $gradeable_list));
    }
}
