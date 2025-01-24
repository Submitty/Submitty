<?php

declare(strict_types=1);

namespace app\controllers\calendar;

use app\controllers\AbstractController;
use app\controllers\GlobalController;
use app\entities\calendar\CalendarItem;
use app\entities\calendar\GlobalItem;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\ResponseInterface;
use app\models\CalendarInfo;
use app\models\GlobalCalendarInfo;
use app\models\gradeable\GradeableUtils;
use app\views\calendar\CalendarView;
use Symfony\Component\Routing\Annotation\Route;

class CalendarController extends AbstractController {
    /**
     * This function loads the gradeable information from all courses, and list them
     * on a calendar. The calendar is accessible through the side bar button in a
     * global scope.
     *
     *
     * @return WebResponse
     * @throws \Exception if a Gradeable failed to load from the database
     * @see GlobalController::prep_user_sidebar
     * @see CalendarView::showCalendar
     */
    #[Route("/calendar")]
    public function viewCalendar(): WebResponse {
        $user = $this->core->getUser();

        $calendar_messages = [];
        $global_calendar_messages = [];
        $courses = $this->core->getQueries()->getCourseForUserId($user->getId());
        $filtered_courses = [];

        //If there aren't any courses, don't filter
        if (count($courses) != 0) {
            //Check if should see all courses
            $show_all_courses = '1';
            if (isset($_COOKIE['calendar_show_all'])) { //Check if show_all cookie exists
                $show_all_courses = $_COOKIE['calendar_show_all'];
            }
            else { //No cookie, create cookie
                setcookie('calendar_show_all', '1', time() + (10 * 365 * 24 * 60 * 60));
                $show_all_courses = '1';
            }

            if ($show_all_courses === '1') {
                $filtered_courses = $courses;
            }
            else {
                //If can't see all courses, see specific course
                if (isset($_COOKIE['calendar_course'])) { //if cookie exists, find matching course
                    $found_course = false;
                    foreach ($courses as $course) {
                        $course_string = sprintf("%s %s", $course->getTitle(), $course->getTerm());
                        if ($course_string === $_COOKIE['calendar_course']) {
                            $found_course = true;
                            array_push($filtered_courses, $course);
                            break;
                        }
                    }
                    if (!$found_course) { //If can't find course, default to first course
                        $course_cookie_value = sprintf("%s %s", $courses[0]->getTitle(), $courses[0]->getTerm());
                        setcookie('calendar_course', $course_cookie_value, time() + (10 * 365 * 24 * 60 * 60));
                        array_push($filtered_courses, $courses[0]);
                    }
                }
                else { //if cookie doesn't exist, choose first course
                    $course_cookie_value = sprintf("%s %s", $courses[0]->getTitle(), $courses[0]->getTerm());
                    setcookie('calendar_course', $course_cookie_value, time() + (10 * 365 * 24 * 60 * 60));
                    array_push($filtered_courses, $courses[0]);
                }
            }
        }

        $gradeables_of_user = GradeableUtils::getAllGradeableListFromUserId($this->core, $user, $filtered_courses, $calendar_messages);

        return new WebResponse(
            CalendarView::class,
            'showCalendar',
            CalendarInfo::loadGradeableCalendarInfo($this->core, $gradeables_of_user, $filtered_courses, $calendar_messages),
            GlobalCalendarInfo::loadGlobalCalendarInfo($this->core),
            $courses
        );
    }

    /**
     */
    #[Route("/calendar/items/new", methods: ["POST"])]
    public function createMessage(): RedirectResponse {
        // Checks if the values exist that are set and returns an error message if not
        if (isset($_POST['type'])) {
            $type = $_POST['type'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect type given");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['date'])) {
            $date = $_POST['date'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect date given");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['text'])) {
            $text = $_POST['text'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect text given");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        $calendar_item = new CalendarItem();
        try {
            $calendar_item->setStringType($type);
        }
        catch (\InvalidArgumentException $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }
        $calendar_item->setDate(new \DateTime($date));
        try {
            $calendar_item->setText($text);
        }
        catch (\InvalidArgumentException $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['course'])) {
            $set_course = $_POST['course'];
        }
        else {
            $this->core->addErrorMessage("Invalid course given.");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        $instructor_courses = $this->core->getQueries()->getInstructorLevelUnarchivedCourses($this->core->getUser()->getId());
        $exists = false;
        foreach ($instructor_courses as $course) {
            if ($set_course === ($course['term'] . ' ' . $course['course'])) {
                $this->core->loadCourseConfig($course['term'], $course['course']);
                $this->core->loadCourseDatabase();
                $this->core->getCourseEntityManager()->persist($calendar_item);
                $this->core->getCourseEntityManager()->flush();
                $this->core->getCourseDB()->disconnect();
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $this->core->addErrorMessage("No valid course found by that name.");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        $this->core->addSuccessMessage("Calendar item successfully added");
        return new RedirectResponse($this->core->buildUrl(['calendar']));
    }

    #[Route("/calendar/items/edit", methods: ["POST"])]
    public function editMessage(): RedirectResponse {
        // Checks if the values exist that are set and returns an error message if not
        if (isset($_POST['type'])) {
            $type = $_POST['type'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect type given");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['date'])) {
            $date = $_POST['date'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect date given");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['text'])) {
            $text = $_POST['text'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect text given");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['id'])) {
            $id = $_POST['id'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect id");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['semester'])) {
            $semester = $_POST['semester'];
        }
        else {
            $this->core->addErrorMessage("Invalid semester");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['course'])) {
            $input_course = $_POST['course'];
        }
        else {
            $this->core->addErrorMessage("Invalid course");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        $instructor_courses = $this->core->getQueries()->getInstructorLevelUnarchivedCourses($this->core->getUser()->getId());

        foreach ($instructor_courses as $course) {
            if (($semester === $course['term']) && ($input_course === $course['course'])) {
                $this->core->loadCourseConfig($course['term'], $course['course']);
                $this->core->loadCourseDatabase();
                $calendar_item = $this->core->getCourseEntityManager()->getRepository(CalendarItem::class)
                    ->findOneBy(['id' => $id]);
                if ($calendar_item === null) {
                    $this->core->addErrorMessage("Calendar item does not exist");
                    return new RedirectResponse($this->core->buildUrl(['calendar']));
                }
                try {
                    $calendar_item->setText($text);
                    $calendar_item->setDate(new \DateTime($date));
                    $calendar_item->setStringType($type);
                }
                catch (\InvalidArgumentException $e) {
                    $this->core->addErrorMessage($e->getMessage());
                    return new RedirectResponse($this->core->buildUrl(['calendar']));
                }
                $this->core->getCourseEntityManager()->flush();
                $this->core->getCourseDB()->disconnect();
            }
        }

        $this->core->addSuccessMessage("Successfully edited calendar item");
        return new RedirectResponse($this->core->buildUrl(['calendar']));
    }

    #[Route("/calendar/items/delete", methods: ["POST"])]
    public function deleteMessage(): ResponseInterface {
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
        }
        else {
            $this->core->addErrorMessage("Error: No id specified");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }
        if (isset($_POST['course'])) {
            $course = $_POST['course'];
        }
        else {
            $this->core->addErrorMessage("Error: No course specified");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }
        if (isset($_POST['semester'])) {
            $semester = $_POST['semester'];
        }
        else {
            $this->core->addErrorMessage("Error: No semester specified");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        $instructor_courses = $this->core->getQueries()->getInstructorLevelUnarchivedCourses($this->core->getUser()->getId());
        $exists = false;
        foreach ($instructor_courses as $current_course) {
            if ($current_course['term'] === $semester && $current_course['course'] === $course) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $this->core->addErrorMessage("Error: Invalid Course");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        $this->core->loadCourseConfig($semester, $course);
        $this->core->loadCourseDatabase();
        $item = $this->core->getCourseEntityManager()->getRepository(CalendarItem::class)
            ->findOneBy(['id' => $id]);
        if ($item !== null) {
            $this->core->getCourseEntityManager()->remove($item);
            $this->core->getCourseEntityManager()->flush();
            $this->core->getCourseDB()->disconnect();
            $this->core->addSuccessMessage($item->getText() . " was successfully deleted.");
            return JsonResponse::getSuccessResponse();
        }
        return JsonResponse::getErrorResponse("Failed to delete message");
    }

    #[Route(path: "/calendar/global_items/new", methods: ["POST"])]
    public function createGlobalEvent(): RedirectResponse {
        // Checks if the values exist that are set and returns an error message if not
        if (isset($_POST['type'])) {
            $type = $_POST['type'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect type given");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['date'])) {
            $date = $_POST['date'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect date given");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['text'])) {
            $text = $_POST['text'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect text given");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        $global_event = new GlobalItem();
        try {
            $global_event->setStringType($type);
        }
        catch (\InvalidArgumentException $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }
        $global_event->setDate(new \DateTime($date));
        try {
            $global_event->setText($text);
        }
        catch (\InvalidArgumentException $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        $this->core->getSubmittyEntityManager()->persist($global_event);
        $this->core->getSubmittyEntityManager()->flush();

        $this->core->addSuccessMessage("Global event successfully created");
        return new RedirectResponse($this->core->buildUrl(['calendar']));
    }


    #[Route(path: "/calendar/global_items/edit", methods: ["POST"])]
    public function editGlobalEvent(): RedirectResponse {
        // Checks if the values exist that are set and returns an error message if not
        if (isset($_POST['type'])) {
            $type = $_POST['type'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect type given");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['date'])) {
            $date = $_POST['date'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect date given");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['text'])) {
            $text = $_POST['text'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect text given");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        if (isset($_POST['id'])) {
            $id = $_POST['id'];
        }
        else {
            $this->core->addErrorMessage("Invalid or incorrect id");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }


        $global_event = $this->core->getSubmittyEntityManager()
            ->getRepository(GlobalItem::class)
            ->findOneBy(['id' => $id]);

        if ($global_event === null) {
            $this->core->addErrorMessage("Global event not found");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        // Edit the global event
        try {
            $global_event->setText($text);
            $global_event->setDate(new \DateTime($date));
            $global_event->setStringType($type);
        }
        catch (\InvalidArgumentException $e) {
            $this->core->addErrorMessage($e->getMessage());
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        // Persist the changes and flush to save them
        $this->core->getSubmittyEntityManager()->flush();

        $this->core->addSuccessMessage("Global event successfully updated");
        return new RedirectResponse($this->core->buildUrl(['calendar']));
    }

    #[Route(path: "/calendar/global_items/delete", methods: ["POST"])]
    public function deleteGlobalEvent(): ResponseInterface {
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
        }
        else {
            $this->core->addErrorMessage("Error: No id specified");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        // Find the announcement in the master database
        $global_event = $this->core->getSubmittyEntityManager()
            ->getRepository(GlobalItem::class)
            ->findOneBy(['id' => $id]);

        if ($global_event === null) {
            $this->core->addErrorMessage("Global event not found");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        $this->core->getSubmittyEntityManager()->remove($global_event);
        $this->core->getSubmittyEntityManager()->flush();
        $this->core->addSuccessMessage($global_event->getText() . " was successfully deleted.");

        return JsonResponse::getSuccessResponse();
    }
}
