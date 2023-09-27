<?php

namespace app\controllers;

use app\controllers\AbstractController;
use app\libraries\DateUtils;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\response\RedirectResponse;
use app\models\Email;
use app\models\User;

class SelfRejoinController extends AbstractController {
    /**
     * @Route("/courses/{_semester}/{_course}/no_access")
     *
     * Creates the No Access page that gives the student a chance to rejoin
     * if they meet the right conditions.
     * @return void
     */
    public function noAccess(): void {
        $user_id = $this->core->getUser()->getId();
        $course = $this->core->getConfig()->getCourse();
        $term = $this->core->getConfig()->getTerm();

        $this->core->getOutput()->renderOutput(
            'Error',
            'noAccessCourse',
            $this->canRejoinCourse($user_id, $course, $term),
            $this->core->buildCourseUrl(["rejoin_course"])
        );
    }

    /**
     * Returns if the user is allowed to self-readd to a course after being dropped.
     * This function can be called from a non-coure context.
     *
     * @return bool True if can readd, false otherwise.
     */
    public function canRejoinCourse(string $user_id, string $course, string $term): bool {
        $user = $this->core->getUser();
        if ($user_id !== $user->getId()) {
            $user = $this->core->getQueries()->getUserById($user_id);
        }

        $reload_previous_course = false;
        $previous_course_name = "";
        $previous_course_term = "";
        $config = $this->core->getConfig();
        if (
            !$config->isCourseLoaded()
            || $config->getCourse() !== $course
            || $config->getTerm() !== $term
        ) {
            // We need to store the current course's name if there is a current course
            // so we can reload it at the end of the function
            // to avoid state change.
            $reload_previous_course = $config->isCourseLoaded();
            if ($reload_previous_course) {
                $previous_course_name = $config->getCourse();
                $previous_course_term = $config->getTerm();
            }

            $this->core->loadCourseConfig($term, $course);
            $this->core->loadCourseDatabase();
        }

        // Wrap logic in helper so that we can then clean up afterwards.
        $answer = $this->canRejoinCourseHelper($user, $course, $term);

        if ($reload_previous_course) {
            $this->core->loadCourseConfig($previous_course_term, $previous_course_name);
            $this->core->loadCourseDatabase();
        }
        return $answer;
    }

    /**
     * Actually determines the answer for canRejoinCourse.
     * Should only be called by canRejoinCourse.
     *
     * @param User $user User we're investigating
     * @param string $course Course we're checking if we can rejoin
     * @param string $term Term the course is in.
     * @return bool True if we can rejoin the course.
     */
    private function canRejoinCourseHelper(User $user, string $course, string $term): bool {
        $user_id = $user->getId();
        // If manually removed from course, this was probably intentional removal.
        if (
            $user->isManualRegistration()
            || !$this->core->getQueries()->
                wasStudentEverInCourse($user_id, $course, $term)
        ) {
            return false;
        }

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

        return false;
    }

    /**
     * @Route("/courses/{_semester}/{_course}/rejoin_course", methods={"POST"})
     *
     * @return RedirectResponse Course url if the student met the conditions to be readded.
     */
    public function rejoinCourse(): RedirectResponse {
        $user = $this->core->getUser();
        $user_id = $user->getId();

        $course = $this->core->getConfig()->getCourse();
        $term = $this->core->getConfig()->getTerm();

        if (!$this->canRejoinCourse($user_id, $course, $term)) {
            $this->core->addErrorMessage("You do not meet the conditions to rejoin.");
            return new RedirectResponse($this->core->buildCourseUrl(["no_access"]));
        }

        $to_join_section = $this->core->getQueries()->
            getLastNonnullRegistrationSection(
                $user_id,
                $term,
                $course
            );
        $to_join_rotating_section = $this->core->getQueries()->
            getLastNonnullRotatingSection($user_id);

        $user->setRegistrationSection($to_join_section);
        $user->setRotatingSection($to_join_rotating_section);

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

        $subject = "User Rejoin: $first_name $last_name ($user_id) of $course";
        $body = <<<EMAIL
            The student $first_name $last_name ($user_id), who had been automatically removed
            from the course $course, has readded themselves in section $joined_section.

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
