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

        //Get which courses are set visible
        $visible_courses = '';
        $num_courses = count($courses);
        //Checks if courses cookie exists
        //Second argument in if statement checks if cookie has correct # of classes (to clear outdated lengths)
        //TODO: fix second argument to look at which classes are there and if they are the same
        if (isset($_COOKIE['visible_courses']) && count(explode(' ', $_COOKIE['visible_courses'])) == $num_courses) {
            $visible_courses = explode(' ', $_COOKIE['visible_courses']);
        }
        //If no cookie, make new cookie
        else {
            //Get course names 
            $course_names = array();
            foreach ($courses as $course){
                array_push($course_names, $course->getTitle());
            }
            //Expires 10 years from today (functionally indefinite)
            if (setcookie('visible_courses', implode(' ', $course_names), time() + (10 * 365 * 24 * 60 * 60))) {
                $visible_courses = $course_names;
            }
        }

        //Filter the courses
        $filtered_courses = [];
        foreach ($courses as $course) {
            if (in_array($course->getTitle(), $visible_courses)){
                array_push($filtered_courses, $course);
            }
        }

        $gradeables_of_user = GradeableUtils::getAllGradeableListFromUserId($this->core, $user, $filtered_courses, $calendar_messages);

        return new WebResponse(
            CalendarView::class,
            'showCalendar',
            CalendarInfo::loadGradeableCalendarInfo($this->core, $gradeables_of_user, $filtered_courses, $calendar_messages),
            $courses
        );
    }

    /**
     * @Route("/calendar/items/new", methods={"POST"})
     */
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
            if ($set_course === ($course['semester'] . ' ' . $course['course'])) {
                $this->core->loadCourseConfig($course['semester'], $course['course']);
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

    /**
     * @Route("/calendar/items/edit", methods={"POST"})
     */
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
            $InputCourse = $_POST['course'];
        }
        else {
            $this->core->addErrorMessage("Invalid course");
            return new RedirectResponse($this->core->buildUrl(['calendar']));
        }

        $instructor_courses = $this->core->getQueries()->getInstructorLevelUnarchivedCourses($this->core->getUser()->getId());

        foreach ($instructor_courses as $course) {
            if (($semester === $course['semester']) && ($InputCourse === $course['course'])) {
                $this->core->loadCourseConfig($course['semester'], $course['course']);
                $this->core->loadCourseDatabase();
                $calendar_item = $this->core->getCourseEntityManager()->getRepository(CalendarItem::class)
                    ->findOneBy(['id' => $id]);
                if ($calendar_item === null) {
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

        return new RedirectResponse($this->core->buildUrl(['calendar']));
    }

    /**
     * @Route("/calendar/items/delete", methods={"POST"})
     */
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
        foreach ($instructor_courses as $currCourse) {
            if ($currCourse['semester'] === $semester && $currCourse['course'] === $course) {
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
}
