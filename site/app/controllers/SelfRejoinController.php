<?php

namespace app\controllers;

use app\controllers\AbstractController;
use app\libraries\DateUtils;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\response\JsonResponse;
use app\models\Email;

class SelfRejoinController extends AbstractController {
    /**
     * @Route("/courses/{_semester}/{_course}/no_access")
     *
     * Creates the No Access page that gives the student a chance to rejoin
     * if they meet the right conditions.
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

        // If manually removed from course, this was probably intentional removal.
        if (
            $user->isManualRegistration()
            || !$this->core->getQueries()->wasStudentEverInCourse($user_id)
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
     * @return JsonResponse Successful response if the student meets the conditions
     * to rejoin the course.
     */
    public function rejoinCourse(): JsonResponse {
        if (!$this->canRejoinCourse()) {
            return JsonResponse::getFailResponse(
                "This student does not meet the conditions to rejoin."
            );
        }

        $term = $this->core->getConfig()->getTerm();
        $course = $this->core->getConfig()->getCourse();

        $sections = $this->core->getQueries()->getRegistrationSections();
        $first_section = $sections[0]["sections_registration_id"];

        $user = $this->core->getUser();
        $user->setRegistrationSection($first_section);

        $this->core->getQueries()->updateUser($user, $term, $course);

        $this->sendRejoinedStudentEmail($first_section);
        return JsonResponse::getSuccessResponse();
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
