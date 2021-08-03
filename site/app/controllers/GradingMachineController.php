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
 */
class GradingMachineController extends AbstractController {
    /**
     * @Route("/grading_done")
     */
    public function getGradingDonePage() {
        return new WebResponse(
            GradingMachineView::class,
            'displayPage'
        );
    }

    public function getProgress() {
        
    }
}
