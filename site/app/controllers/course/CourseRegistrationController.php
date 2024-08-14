<?php

namespace app\controllers\course;

use app\controllers\AbstractController;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\models\Email;

class CourseRegistrationController extends AbstractController {
    /**
     * @param array<string> $instructors
     */
    public function notifyInstructors(string $user, string $term, string $course, array $instructors): void {
        $subject = "User $user has joined course $course";
        $body = <<<EMAIL
                $user has joined the course $course for term $term.
        EMAIL;
        $emails = [];
        foreach ($instructors as $instructor) {
            $details = ["subject" => $subject, "body" => $body, "to_user_id" => $instructor];
            $email = new Email($this->core, $details);
            $emails[] = $email;
        }

        $this->core->getNotificationFactory()->sendEmails($emails);
    }

    #[Route("/courses/{term}/{course}/self_register")]
    public function selfRegister(string $term, string $course): RedirectResponse {
        $this->core->loadCourseConfig($term, $course);
        $this->core->loadCourseDatabase();
        if (!($this->core->getQueries()->isSelfRegistrationAllowed($course) > 0)) {
            $this->core->addErrorMessage('Self registration is not allowed.');
            return new RedirectResponse($this->core->buildUrl(['home']));
        }
        $default_section = $this->core->getQueries()->getDefaultRegistrationSection();
        $this->core->getUser()->setRegistrationSection($default_section['sections_registration_id']);
        $this->core->getQueries()->insertCourseUser($this->core->getUser(), $term, $course);
        $instructor_ids = $this->core->getQueries()->getActiveUserIds(true, false, false, false, false);
        $this->notifyInstructors($this->core->getUser()->getId(), $term, $course, $instructor_ids);
        return new RedirectResponse($this->core->buildCourseUrl());
    }
}
