<?php

namespace app\controllers\course;

use app\controllers\AbstractController;
use app\libraries\response\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class CourseRegistrationController extends AbstractController {
    #[Route("/courses/{term}/{course}/self_register")]
    public function selfRegister(string $term, string $course): RedirectResponse {
        $this->core->loadCourseConfig($term, $course);
        $this->core->loadCourseDatabase();
        $default_section = $this->core->getQueries()->getDefaultRegistrationSection();
        $this->core->getUser()->setRegistrationSection($default_section['sections_registration_id']);
        $this->core->getQueries()->insertCourseUser($this->core->getUser(), $term, $course);
        return new RedirectResponse($this->core->buildCourseUrl());
    }
}
