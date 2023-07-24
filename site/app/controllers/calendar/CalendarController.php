<?php

declare(strict_types=1);

namespace app\controllers\calendar;

use app\controllers\AbstractController;
use app\controllers\GlobalController;
use app\entities\calendar\CalendarItem;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\ResponseInterface;
use app\libraries\routers\AccessControl;
use app\models\CalendarInfo;
use app\models\gradeable\GradeableUtils;
use app\views\calendar\CalendarView;
use Symfony\Component\Routing\Annotation\Route;

class CalendarController extends AbstractController {
    /**
     * This function loads the gradeable information from all courses, and list them
     * on a calendar. The calendar is accessible through the side bar button in a
     * global scope.
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

        return new WebResponse(
            CalendarView::class,
            'showCalendar',
            CalendarInfo::loadGradeableCalendarInfo($this->core, $gradeables_of_user, $courses, $calendar_messages)
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/calendar")
     */
    public function viewCourseCalendar(): WebResponse {
        $calendar_messages = [];
        $user = $this->core->getUser();
        $courses = $this->core->getQueries()->getCourseForUserId($user->getId());
        $name = $this->core->getConfig()->getCourse();
        // Initialize $oneCourse and get the specific course for the course calendar
        $oneCourse = $courses[0];
        foreach ($courses as $course) {
            if ($course->getTitle() === $name) {
                $oneCourse = [$course];
                break;
            }
        }
        $gradeables = GradeableUtils::getGradeablesFromUserAndCourse($this->core, $calendar_messages);

        return new WebResponse(CalendarView::class, 'showCalendar', CalendarInfo::loadGradeableCalendarInfo($this->core, $gradeables, $oneCourse, $calendar_messages), true);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/calendar/items/new", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function createMessage(): RedirectResponse {
        // Checks if the values exist that are set and returns an error message if not
        if (isset($_POST['type'])) {
            $type = $_POST['type'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect type given");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        if (isset($_POST['date'])) {
            $date = $_POST['date'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect date given");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        if (isset($_POST['text'])) {
            $text = $_POST['text'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect text given");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        $calendar_item = new CalendarItem();
        try {
            $calendar_item->setStringType($type);
        }
        catch (\InvalidArgumentException $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }
        $calendar_item->setDate(new \DateTime($date));
        try {
            $calendar_item->setText($text);
        }
        catch (\InvalidArgumentException $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        $this->core->getCourseEntityManager()->persist($calendar_item);
        $this->core->getCourseEntityManager()->flush();

        $this->core->addSuccessMessage("Calendar item successfully added");
        return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/calendar/items/{id}/edit", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function editMessage(): RedirectResponse {
        // Checks if the values exist that are set and returns an error message if not
        if (isset($_POST['type'])) {
            $type = $_POST['type'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect type given");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        if (isset($_POST['date'])) {
            $date = $_POST['date'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect date given");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        if (isset($_POST['text'])) {
            $text = $_POST['text'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect text given");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        if (isset($_POST['id'])) {
            $id = $_POST['id'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect id");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        if (strip_tags($text) !== $text) {
            $this->core->addErrorMessage("HTML cannot be used in this text");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }

        $calendar_item = $this->core->getCourseEntityManager()->getRepository(CalendarItem::class)
            ->findOneBy(['id' => $id]);

        if ($calendar_item === null) {
            $this->core->addErrorMessage("An error has occured.");
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
     * @Route("/courses/{_semester}/{_course}/calendar/items/{id}/delete", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function deleteMessage(): ResponseInterface {
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
        }
        else {
            $this->core->addErrorMessage("Error: No id specified");
            return new RedirectResponse($this->core->buildCourseUrl(['calendar']));
        }
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
