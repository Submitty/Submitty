<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\ResponseInterface;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\routers\AccessControl;
use app\libraries\GradingQueue;
use app\views\AutogradingStatusView;
use app\views\ErrorView;
use Symfony\Component\Routing\Annotation\Route;

class AutogradingStatusController extends AbstractController {
    /**
     * @Route("/autograding_status", methods={"GET"})
     * @return WebResponse | MultiResponse
     */
    public function getGradingDonePage(): ResponseInterface {
        if (empty($this->core->getQueries()->getInstructorLevelAccessCourse($this->core->getUser()->getId()))) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse(ErrorView::class, "errorPage", "You don't have access to this page.")
            );
        }

        return new WebResponse(
            AutogradingStatusView::class,
            'displayPage',
            $this->getAutogradingInfo()
        );
    }

    /**
     * Used to continuous in the page's continuous updates
     * @Route("/autograding_status/get_update", methods={"GET"})
     * @return JsonResponse
     */
    public function getProgress(): JsonResponse {
        if (empty($this->core->getQueries()->getInstructorLevelAccessCourse($this->core->getUser()->getId()))) {
            return JsonResponse::getFailResponse("You do not have access to this endpoint.");
        }
        $info = $this->getAutogradingInfo();
        return JsonResponse::getSuccessResponse($info);
    }

    // Uses the GradingQueue class to get all the info from the necessary files
    private function getAutogradingInfo(): array {
        $gq = new GradingQueue(null, null, $this->core->getConfig()->getSubmittyPath());
        $info = $gq->getAutogradingInfo($this->core->getConfig()->getConfigPath());
        $course = [];
        $courses_full = $this->core->getQueries()->getInstructorLevelAccessCourse($this->core->getUser()->getId());
        foreach ($courses_full as $row) {
            $course[] = $row["semester"] . " " . $row["course"];
        }
        foreach ($info["ongoing_job_info"] as &$i) {
            foreach ($i as &$job) {
                if (!in_array($job["semester"] . " " . $job["course"], $course)) {
                    $job["user_id"] = "Hidden";
                }
            }
        }
        $j = [
            "time" => date("H:i:s", time())
        ];
        return array_merge($j, $info);
    }
}
