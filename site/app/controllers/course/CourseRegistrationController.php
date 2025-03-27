<?php

namespace app\controllers\course;

use app\controllers\AbstractController;
use app\controllers\admin\ConfigurationController;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\models\Email;

class CourseRegistrationController extends AbstractController {
    /**
     * @param array<string> $instructors
     */
    private function notifyInstructors(string $user, string $term, string $course, array $instructors): void {
        $subject = "Self-registration of $user for course $course";
        $body = "Student $user has self-registered for course $course for term $term.";
        $emails = [];
        foreach ($instructors as $instructor) {
            $emails[] = new Email(
                $this->core,
                [
                    "subject" => $subject,
                    "body" => $body,
                    "to_user_id" => $instructor
                ]
            );
        }

        $this->core->getNotificationFactory()->sendEmails($emails);
    }

    #[Route("/courses/{term}/{course}/register")]
    public function selfRegister(string $term, string $course): RedirectResponse {
        $this->core->loadCourseConfig($term, $course);
        $this->core->loadCourseDatabase();
        if ($this->core->getQueries()->getSelfRegistrationType($term, $course) === ConfigurationController::NO_SELF_REGISTER) {
            $this->core->addErrorMessage('Self registration is not allowed.');
            return new RedirectResponse($this->core->buildUrl(['home']));
        }
        elseif ($this->core->getQueries()->getDefaultRegistrationSection($term, $course) === null) {
            $this->core->addErrorMessage('Default section ID is not set, alert your instructor.');
            return new RedirectResponse($this->core->buildUrl(['home']));
        }
        else {
            $this->registerCourseUser($term, $course);
            return new RedirectResponse($this->core->buildCourseUrl());
        }
    }

    public function registerCourseUser(string $term, string $course): void {
        $default_section = $this->core->getQueries()->getDefaultRegistrationSection($term, $course);
        $user_id = $this->core->getUser()->getId();

        // Check if user already exists in the course
        $existing_user = $this->core->getQueries()->getUserBySubmittyId($user_id);
        if ($existing_user !== null) {
            // Update the user's section if they exist but were moved to NULL
            $this->core->getQueries()->updateUser($user_id, $term, $course, 4, $default_section, false);
        }
        else {
            // Insert new user if they don't exist
            $this->core->getQueries()->insertCourseUser($user_id, $term, $course, 4, $default_section, false);
        }

        // Notify instructors about the registration
        $instructors = $this->core->getQueries()->getInstructors($term, $course);
        $this->notifyInstructors($user_id, $term, $course, $instructors);

        $this->core->addSuccessMessage("Successfully registered!");
    }
}
