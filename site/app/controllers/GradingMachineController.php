<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\views\GradingMachineView;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @AccessControl(role="FULL_ACCESS_GRADER")
 * @AccessControl(role="INSTRUCTOR")
 */
class GradingMachineController extends AbstractController {
    /**
     * @Route("/courses/{_semester}/{_course}/grading_done")
     */
    public function getGradingDonePage() {
        return new WebResponse(
            GradingMachineView::class,
            'displayPage',
            $this->getProgress()
        );
    }

    public function getProgress() {
        $result = exec('sudo python3 /usr/local/submitty/bin/grading_done.py');

    }
}
