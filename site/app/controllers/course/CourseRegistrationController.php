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
        else {
            $this->registerCourseUser($term, $course);
            return new RedirectResponse($this->core->buildCourseUrl());
        }
    }


    public function registerCourseUser(string $term, string $course): void {
        $default_section = $this->core->getQueries()->getDefaultRegistrationSection($term, $course);
        $user = $this->core->getUser();
        
        // Check if the user already exists in the course
        $existing_user = $this->core->getQueries()->getCourseUserById($user->getId(), $term, $course);
        
        if ($existing_user) {
            // If user is in NULL section, update their section instead of re-inserting
            if ($existing_user["registration_section"] === null) {
                $this->core->getQueries()->updateUserRegistrationSection($user->getId(), $term, $course, $default_section);
            }
        } else {
            // If user does not exist, insert them into the course
            $user->setRegistrationSection($default_section);
            $this->core->getQueries()->insertCourseUser($user, $term, $course);
        }

        // Notify instructors
        $instructor_ids = $this->core->getQueries()->getActiveUserIds(true, false, false, false, false, $term, $course);
        $this->notifyInstructors($user->getId(), $term, $course, $instructor_ids);
    }

    
}
