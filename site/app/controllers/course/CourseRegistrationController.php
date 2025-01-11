<?php

namespace app\controllers\course;

use app\controllers\AbstractController;
use app\controllers\admin\ConfigurationController;
use app\libraries\Logger;
use app\models\User;
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
        $instructors_settings = $this->core->getQueries()->getUsersNotificationSettings($instructors);
        foreach ($instructors as $instructor) {
            // Find the row in the settings for the instructor
            $instructor_settings_row = array_values(array_filter($instructors_settings, function ($v) use ($instructor) {
                return $v['user_id'] === $instructor;
            }));
            if (!empty($instructor_settings_row)) {
                $instructor_settings_row = $instructor_settings_row[0];
            }
            $notification_settings = User::constructNotificationSettings($instructor_settings_row);
            // If the instructor has notifications enabled for self registrations
            if ($notification_settings['all_new_self_registrations_email']) {
                $emails[] = new Email(
                    $this->core,
                    [
                        "subject" => $subject,
                        "body" => $body,
                        "to_user_id" => $instructor
                    ]
                );
            }
           
        }

        if (!empty($emails)) {
            $this->core->getNotificationFactory()->sendEmails($emails);
        }
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
        $this->core->getUser()->setRegistrationSection($default_section);
        $this->core->getQueries()->insertCourseUser($this->core->getUser(), $term, $course);
        $instructor_ids = $this->core->getQueries()->getActiveUserIds(true, false, false, false, false);
        $this->notifyInstructors($this->core->getUser()->getId(), $term, $course, $instructor_ids);
    }
}
