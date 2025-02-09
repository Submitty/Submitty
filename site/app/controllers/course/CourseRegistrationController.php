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

    #[Route("/courses/{term}/{course}/alert_redirect")]
    public function alertRedirect(string $term, string $course): RedirectResponse {
        // Ensure course configuration and database are loaded
        $this->core->loadCourseConfig($term, $course);
        $this->core->loadCourseDatabase();
    
        // Check if the user has confirmed the unregistration
        if (isset($_GET['confirmed']) && $_GET['confirmed'] === 'true') {
            // Perform unregistration if confirmed
            $this->unregisterCourseUser($term, $course);
            $this->core->addSuccessMessage('You have successfully unregistered from the course.');
            return new RedirectResponse($this->core->buildUrl(['home']));
        }
    
        // If not confirmed, show the confirmation alert
        $confirmUrl = $this->core->buildUrl(['courses', $term, $course, 'alert_redirect']) . '?confirmed=true';
        $cancelUrl = $this->core->buildCourseUrl();
    
        echo "<script type='text/javascript'>
                if (confirm('Are you sure you want to unregister from this course?')) {
                    window.location.href = '$confirmUrl';
                } else {
                    window.location.href = '$cancelUrl';
                }
              </script>";
        exit;  // Ensure the script runs and stops further PHP execution
    }
    
    

    #[Route("/courses/{term}/{course}/register", name: "course_register")]
    public function registerCourse(string $term, string $course): RedirectResponse {
        $this->core->loadCourseConfig($term, $course);
        $this->core->loadCourseDatabase();

        if ($this->core->getQueries()->getSelfRegistrationType($term, $course) === ConfigurationController::NO_SELF_REGISTER) {
            $this->core->addErrorMessage('Self registration is not allowed.');
            return new RedirectResponse($this->core->buildUrl(['home']));
        }

        $this->registerCourseUser($term, $course);
        return new RedirectResponse($this->core->buildCourseUrl());
    }

    #[Route("/courses/{term}/{course}/unregister_from_course", name: "course_unregister")]
    public function unregisterCourse(string $term, string $course): RedirectResponse {
        $this->core->loadCourseConfig($term, $course);
        $this->core->loadCourseDatabase();

        if ($this->core->getQueries()->getSelfRegistrationType($term, $course) === 0) {
            $this->core->addErrorMessage('Self unregistration is not allowed.');
            return new RedirectResponse($this->core->buildUrl(['home']));
        }

        // Redirect to the alert route after unregistration
        $alertRedirectUrl = $this->core->buildUrl(['courses', $term, $course, 'alert_redirect']);
        return new RedirectResponse($alertRedirectUrl);
    }

    public function registerCourseUser(string $term, string $course): void {
        $default_section = $this->core->getQueries()->getDefaultRegistrationSection($term, $course);
        $this->core->getUser()->setRegistrationSection($default_section);
        $this->core->getQueries()->insertCourseUser($this->core->getUser(), $term, $course);
        $instructor_ids = $this->core->getQueries()->getActiveUserIds(true, false, false, false, false);
        $this->notifyInstructors($this->core->getUser()->getId(), $term, $course, $instructor_ids);
    }
    public function unregisterCourseUser(string $term, string $course): void {
        $this->core->getQueries()->unregisterCourseUser($this->core->getUser(), $term, $course);
        $instructor_ids = $this->core->getQueries()->getActiveUserIds(true, false, false, false, false);
    }
}
