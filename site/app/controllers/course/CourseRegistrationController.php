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
        $instructors_settings = $this->core->getQueries()->getUsersNotificationSettings($instructors);

        foreach ($instructors_settings as $instructor_setting) {
            // If the instructor has notifications enabled for self registrations add to email list
            if ($instructor_setting['self_registration_email']) {
                $emails[] = new Email(
                    $this->core,
                    [
                        'subject' => $subject,
                        'body' => $body,
                        'to_user_id' => $instructor_setting['user_id'],
                    ]
                );
            }
        }

        $this->core->getNotificationFactory()->sendEmails($emails);
    }

    #[Route("/courses/{term}/{course}/register", name: "course_register")]
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

    #[Route("/courses/{term}/{course}/unregister", name: "course_unregister")]
    public function selfUnregister(string $term, string $course): RedirectResponse {
        $this->core->loadCourseConfig($term, $course);
        $this->core->loadCourseDatabase();

        if ($this->core->getQueries()->getSelfRegistrationType($term, $course) === ConfigurationController::NO_SELF_REGISTER) {
            $this->core->addErrorMessage('You cannot unregister from this course on your own.');
            return new RedirectResponse($this->core->buildCourseUrl());
        }
        $this->core->getQueries()->unregisterCourseUser($this->core->getUser(), $term, $course);
        $this->core->addSuccessMessage('You have successfully unregistered from the course.');
        return new RedirectResponse($this->core->buildUrl(['home']));
    }

    public function registerCourseUser(string $term, string $course): void {
        $user = $this->core->getUser();
        $user_id = $user->getId();
        if ($this->core->getQueries()->checkStudentActiveInCourse($user_id, $course, $term)) {
            // User is already registered and active
            return;
        }
        $default_section = $this->core->getQueries()->getDefaultRegistrationSection($term, $course);
        if ($this->core->getQueries()->wasStudentEverInCourse($user_id, $course, $term)) {
            $this->core->getUser()->setRegistrationSection($default_section);
            $this->core->getQueries()->updateUser($user, $term, $course);
        }
        else {
            $this->core->getUser()->setRegistrationSection($default_section);
            $this->core->getQueries()->insertCourseUser($this->core->getUser(), $term, $course);
        }

        $instructor_ids = $this->core->getQueries()->getActiveUserIds(true, false, false, false, false, $term, $course);
        $this->notifyInstructors($this->core->getUser()->getId(), $term, $course, $instructor_ids);
    }
}
