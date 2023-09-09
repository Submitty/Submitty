<?php

namespace app\controllers;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\libraries\response\JsonResponse;
use app\models\Email;

class ErrorController extends AbstractController {


	/**
     * @Route("/courses/{_semester}/{_course}/no_access")
     */
    public function noAccess() {
        $this->rejoinCourse();
        $this->core->getOutput()->renderOutput(
            'Error',
            'noAccessCourse',
            $this->canRejoinCourse(),
            $this->core->buildCourseUrl(["rejoin_course"])
        );
    }

    /** 
     * Returns if the user is allowed to self-readd to a course after being dropped.
     * @param bool True if can readd, false otherwise.
     */
    private function canRejoinCourse() {
        $user = $this->core->getUser();

        // If manually removed from course, this was probably intentional removal.
        if ($user->isManualRegistration()) {
            return false;
        }

        $user_id = $user->getId();
        $most_recent_access = $this->core->getQueries()->getMostRecentGradeableAccessForUser($user_id);
        // If removed from course within last 3 days, can readd self.
        if ($most_recent_access !== "Never accessed a gradeable" && DateUtils::calculateDayDiff($most_recent_access) <= 3) {
            return true;
        }

        $term_start_date = $this->core->getQueries()->getCurrentTermStartDate();
        // If never accessed course but is within first two weeks of term, can readd self.
        if (DateUtils::calculateDayDiff($most_recent_access, $term_start_date) <= 14) {
            return true;
        }

        return false;
    }

    /**
     * @Route("/courses/{_semester}/{_course}/rejoin_course", methods={"POST"})
     */
    public function rejoinCourse() {
        if (!$this->canRejoinCourse())
            return JsonResponse::getFailResponse();

        $user_id = $this->core->getUser()->getId();
        $joined_section = $this->core->getQueries()->removeUserFromNullSection($user_id);

        $this->sendRejoinedStudentEmail($joined_section);
        return JsonResponse::getSuccessResponse();
    }


    private function sendRejoinedStudentEmail($joined_section) {
        $user_id = $this->core->getUser()->getId();
        $course = $this->core->getConfig()->getCourse();

        $subject = "User Rejoin: $user_id of $course";
        $body = <<<EMAIL
            The student $user_id, who had been automatically removed from the course $course,
            has readded themselves in section $joined_section.

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
