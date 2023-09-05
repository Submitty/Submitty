<?php

namespace app\controllers;

use app\controllers\AbstractController;
use app\libraries\Core;
use Symfony\Component\Routing\Annotation\Route;

class ErrorController extends AbstractController {


	/**
     * @Route("/courses/{_semester}/{_course}/no_access")
     */
    public function noAccess() {
        $this->core->getOutput()->renderOutput(
            'Error',
            'noAccessCourse',
            true,
            $this->core->buildCourseUrl(["rejoin_course"])
        );
    }

}
