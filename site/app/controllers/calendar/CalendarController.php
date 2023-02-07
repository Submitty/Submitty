<?php

declare(strict_types=1);

namespace app\controllers\calendar;

use app\controllers\AbstractController;
use app\controllers\GlobalController;
use app\entities\calendar\CalendarItem;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\models\CalendarInfo;
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

        $calendar_messages = [];

        $courses = $this->core->getQueries()->getCourseForUserId($user->getId());

        $gradeables_of_user = GradeableUtils::getAllGradeableListFromUserId($this->core, $user, $courses, $calendar_messages);

        return new WebResponse(CalendarView::class, 'showCalendar', CalendarInfo::loadGradeableCalendarInfo($this->core, $gradeables_of_user, $courses, $calendar_messages));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/calendar")
     * @return WebResponse
     */
    public function viewCourseCalendar(): WebResponse {
        $calendar_messages = [];

        $gradeables = GradeableUtils::getGradeablesFromUserAndCourse($this->core, $calendar_messages);

        return new WebResponse(CalendarView::class, 'showCalendar', CalendarInfo::loadGradeableCalendarInfo($this->core, $gradeables, $calendar_messages), true);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/calendar/newItem", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function createMessage(): RedirectResponse {
        $type = $_POST['type'];
        $date = $_POST['date'];
        $text = $_POST['text'];

        if (strip_tags($text) !== $text) {
            $this->core->addErrorMessage("HTML cannot be used in this text");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        $calendar_item = new CalendarItem();
        try {
            $calendar_item->setStringType($type);
        }
        catch (\InvalidArgumentException $e) {
            $this->core->addErrorMessage("That is not a valid calendar item type");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }
        $calendar_item->setDate(new \DateTime($date));
        try {
            $calendar_item->setText($text);
        }
        catch (\InvalidArgumentException $e) {
            $this->core->addErrorMessage("Text exceeds 255 characters which is not allowed");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        $this->core->getCourseEntityManager()->persist($calendar_item);
        $this->core->getCourseEntityManager()->flush();

        $this->core->addSuccessMessage("Calendar item successfully added");
        return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/calendar/editItem", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function editMessage(): RedirectResponse {
        $type = $_POST['type'];
        $date = $_POST['date'];
        $text = $_POST['text'];
        $id = $_POST['id'];

        if (strip_tags($text) !== $text) {
            $this->core->addErrorMessage("HTML cannot be used in this text");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        $calendar_item = $this->core->getCourseEntityManager()->getRepository(CalendarItem::class)
            ->findOneBy(['id' => $id]);

        if ($calendar_item === null) {
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        $calendar_item->setText($text);
        $calendar_item->setDate(new \DateTime($date));
        try {
            $calendar_item->setStringType($type);
        }
        catch (\InvalidArgumentException  $e) {
            $this->core->addErrorMessage("That is not a valid calendar item type");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }
        $this->core->getCourseEntityManager()->flush();
        return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/calendar/deleteItem", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function deleteMessage(): JsonResponse {
        $id = $_POST['id'];
        $item = $this->core->getCourseEntityManager()->getRepository(CalendarItem::class)
            ->findOneBy(['id' => $id]);
        if ($item !== null) {
            $this->core->getCourseEntityManager()->remove($item);
            $this->core->getCourseEntityManager()->flush();
            $this->core->addSuccessMessage($item->getText() . " was successfully deleted.");
            return JsonResponse::getSuccessResponse();
        }
        return JsonResponse::getErrorResponse("Failed to delete message");
    }
}
