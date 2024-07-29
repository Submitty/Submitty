<?php

namespace app\controllers\course;

use app\authentication\DatabaseAuthentication;
use app\controllers\AbstractController;
use app\libraries\response\JsonResponse;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\RedirectResponse;
use app\models\User;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;
//Enable us to throw, catch, and handle exceptions as needed.


class CourseRegistrationController extends AbstractController {

#[Route("/courses/{term}/{course}/self_register")]
    public function selfRegister(string $term, string $course) {
        // $this->core->getQueries()->isSelfRegistrationAllowed(course);
        $this->core->loadCourseConfig($term, $course);
        $this->core->loadCourseDatabase();
        $this->core->getQueries()->insertNewRegistrationSection('unverified');
        $this->core->getUser()->setRegistrationSection('unverified');
        $this->core->getQueries()->insertCourseUser($this->core->getUser(), $term, $course);
        // return JsonResponse::getSuccessResponse('AAA');
        return new RedirectResponse($this->core->buildCourseUrl());
    }

}
