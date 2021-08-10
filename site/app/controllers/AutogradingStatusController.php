<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
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
     * @Route("/courses/{_semester}/{_course}/autograding_status", methods={"GET"})
     */
    public function getGradingDonePage() {
        return new WebResponse(
            AutogradingStatusView::class,
            'displayPage',
            $this->getAutogradingInfo()
        );
    }

    /**
     * @Route("/courses/{_semester}/{_course}/autograding_status/get_update", methods={"GET"})
     * @return JsonResponse
     */
    public function getProgress(): JsonResponse {
        $info = $this->getAutogradingInfo();
        $j = [
            "time" => date("H:i:s", time()),
            "interactive_ongoing" => $info["queue_counts"]["Ongoing grading"],
            "interactive_queue" => $info["queue_counts"]["Grading"],
            "regrade_ongoing" => $info["queue_counts"]["Ongoing regrade"],
            "regrade_queue" => $info["queue_counts"]["Regrade"],
            "machine_count" => $info["machine_grading_counts"],
            "capability_count" => $info["capability_queue_counts"]
        ];
        return JsonResponse::getSuccessResponse($j);
    }

    private function getAutogradingInfo(): array {
        $gq = new GradingQueue(null, null, $this->core->getConfig()->getSubmittyPath());
        return $gq->getAutogradingInfo($this->core->getConfig()->getConfigPath());
    }
}
