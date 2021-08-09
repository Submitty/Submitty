<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\libraries\GradingQueue;
use app\views\AutogradingStatusView;
use app\libraries\FileUtils;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @AccessControl(role="FULL_ACCESS_GRADER")
 * @AccessControl(role="INSTRUCTOR")
 */
class AutogradingStatusController extends AbstractController {
    /**
     * @Route("/courses/{_semester}/{_course}/autograding_status")
     */
    public function getGradingDonePage() {
        return new WebResponse(
            AutogradingStatusView::class,
            'displayPage',
            $this->getAutogradingInfo()
        );
    }

    public function getProgress(): WebResponse {
        return new WebResponse(
            AutogradingStatusView::class,
            'renderTable',
            $this->getAutogradingInfo()
        );
    }

    private function getAutogradingInfo() {
        $gq = new GradingQueue(null, null, $this->core->getConfig()->getSubmittyPath());
        return $gq->getAutogradingInfo($this->core->getConfig()->getConfigPath());
    }
}
