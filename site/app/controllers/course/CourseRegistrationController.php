<?php

namespace app\controllers\course;

use app\controllers\AbstractController;
use app\libraries\response\RedirectResponse;
use app\models\User;
use Symfony\Component\Routing\Annotation\Route;

class CourseRegistrationController extends AbstractController {

    #[Route("/courses/{term}/{course}/self_register")]
    public function selfRegister(string $term, string $course) {
        $this->core->loadCourseConfig($term, $course);
        $this->core->loadCourseDatabase();
        $this->core->getQueries()->insertNewRegistrationSection('unverified');
        $this->core->getUser()->setRegistrationSection('unverified');
        $this->core->getQueries()->insertCourseUser($this->core->getUser(), $term, $course);
        return new RedirectResponse($this->core->buildCourseUrl());
    }

}
