<?php

namespace app\controllers;

use app\controllers\AbstractController;
use app\libraries\DateUtils;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\response\RedirectResponse;
use app\models\Email;

class SelfRejoinController extends AbstractController {
    /**
     * @Route("/courses/{_semester}/{_course}/no_access")
     *
     * Creates the No Access page that gives the student a chance to rejoin
     * if they meet the right conditions.
     * @return void
     */
    public function noAccess(): void {
        $this->core->getOutput()->renderOutput(
            'Error',
            'noAccessCourse',
            $this->canRejoinCourse(),
            $this->core->buildCourseUrl(["rejoin_course"])
        );
    }

    /**
     * Returns if the user is allowed to self-readd to a course after being dropped.
     * @return bool True if can readd, false otherwise.
     */
    private function canRejoinCourse() {
        $user = $this->core->getUser();
        $user_id = $user->getId();

        $course = $this->core->getConfig()->getCourse();
        $term = $this->core->getConfig()->getTerm();


        // --------------------------------
        // Reasons why you can't rejoin:

        // Can't rejoin courses you were never in.
        if (!$this->core->getQueries()->wasStudentEverInCourse($user_id, $course, $term)) {
            return false;
        }

        // Can't rejoin a course if you're still registered.
        if ($user->getRegistrationSection() !== null) {
            return false;
        }

        // Can't rejoin archived courses.
        $course_status = $this->core->getQueries()->getCourseStatus($term, $course);
        if ($course_status === 2) {
            return false;
        }

        // If manually removed from course, this was probably intentional removal.
        if ($user->isManualRegistration()) {
            return false;
        }
        // --------------------------------


        // --------------------------------
        // Meeting the requirements to rejoin

        $acceses = $this->core->getQueries()->getAttendanceInfoOneStudent($user_id);
        foreach ($acceses as $access_place => $timestamp) {
            if (is_null($timestamp)) {
                continue;
            }
            if (abs(DateUtils::calculateDayDiff($timestamp)) <= 3) {
                return true;
            }
        }

        $term_start_date = $this->core->getQueries()->getCurrentTermStartDate();
        // If never accessed course but today is within first two weeks of term, can readd self.
        if (abs(DateUtils::calculateDayDiff($term_start_date)) <= 14) {
            return true;
        }
        // --------------------------------


        // If don't meet requirements to rejoin, then can't rejoin.
        return false;
    }

    /**
     * @Route("/courses/{_semester}/{_course}/rejoin_course", methods={"POST"})
     *
     * @return RedirectResponse Course url if the student met the conditions to be readded.
     */
    public function rejoinCourse(): RedirectResponse {

        if (!$this->canRejoinCourse()) {
            $this->core->addErrorMessage("You do not meet the conditions to rejoin.");
            return new RedirectResponse($this->core->buildCourseUrl(["no_access"]));
        }

        $term = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();

        $user = $this->core->getUser();
        $user_id = $user->getId();

        $to_join_section = $this->core->getQueries()->
            getPreviousRegistrationSection(
                $user_id,
                $term,
                $course
            );
        $to_join_rotating_section = $this->core->getQueries()->
            getPreviousRotatingSection($user_id); // TODO ADD REJOIN DEFAULT IF INVALID

        $user->setRegistrationSection($to_join_section);
        if ($to_join_rotating_section !== null) {
            $user->setRotatingSection($to_join_rotating_section);
        }

        $this->core->getQueries()->updateUser($user, $term, $course);

        $this->sendRejoinedStudentEmail($to_join_section);
        return new RedirectResponse($this->core->buildCourseUrl());
    }


    /**
     * Sends emails to instructors that a student readded themselves to the course.
     *
     * @param string $joined_section The section that the student has rejoined.
     * @return void
     */
    private function sendRejoinedStudentEmail($joined_section): void {
        $user = $this->core->getUser();
        $user_id = $user->getId();
        $first_name = $user->getPreferredGivenName();
        if (empty($first_name)) {
            $first_name = $user->getLegalGivenName();
        }
        $last_name = $user->getPreferredFamilyName();
        if (empty($last_name)) {
            $last_name = $user->getLegalFamilyName();
        }

        $course = ucwords($this->core->getConfig()->getCourse());
        $term = $this->core->getConfig()->getTerm();

        $subject = "User Rejoin: $first_name $last_name ($user_id) of $term $course";
        $body = <<<EMAIL
            The student $first_name $last_name ($user_id), who had been automatically removed
            from the course $course of term $term, has readded themselves in section $joined_section.

            Please move them to their appropiate section. If this rejoin was a mistake,
            you may move the student to the Null section.
        EMAIL;

        $instructor_ids = $this->core->getQueries()->getActiveUserIds(true, false, false, false, false);
        $emails = [];
        $details = ["subject" => $subject, "body" => $body];
        foreach ($instructor_ids as $instructor_id) {
            $details["to_user_id"] = $instructor_id;
            $email = new Email($this->core, $details);
            array_push($emails, $email);
        }

        $this->core->getNotificationFactory()->sendEmails($emails);
    }
}
