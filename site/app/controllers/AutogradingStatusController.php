<?php

namespace app\controllers;

use app\libraries\response\MultiResponse;
use app\libraries\response\ResponseInterface;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\GradingQueue;
use app\views\AutogradingStatusView;
use app\views\ErrorView;
use app\libraries\FileUtils;
use Symfony\Component\Routing\Annotation\Route;

class AutogradingStatusController extends AbstractController {
    /**
     * @return WebResponse | MultiResponse
     */
    #[Route("/autograding_status", methods: ["GET"])]
    public function getGradingDonePage(): ResponseInterface {
        if (empty($this->core->getQueries()->getInstructorLevelAccessCourse($this->core->getUser()->getId())) && !$this->core->getUser()->isSuperUser()) {
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
     * @return JsonResponse
     */
    #[Route("/autograding_status/get_update", methods: ["GET"])]
    public function getProgress(): JsonResponse {
        if (empty($this->core->getQueries()->getInstructorLevelAccessCourse($this->core->getUser()->getId())) && !$this->core->getUser()->isSuperUser()) {
            return JsonResponse::getFailResponse("You do not have access to this endpoint.");
        }
        $info = $this->getAutogradingInfo();
        return JsonResponse::getSuccessResponse($info);
    }

    /**
     * Attempts to read the stack trace and find any error message
     * @return JsonResponse
     */
    #[Route("/autograding_status/get_stack", methods: ["GET"])]
    public function getErrorStackTrace(): JsonResponse {
        if (empty($this->core->getQueries()->getInstructorLevelAccessCourse($this->core->getUser()->getId())) && !$this->core->getUser()->isSuperUser()) {
            return JsonResponse::getFailResponse("You do not have access to this endpoint.");
        }
        $stack_trace_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "logs", "autograding_stack_traces");
        if (!is_readable($stack_trace_path)) {
            return JsonResponse::getFailResponse("Could not access the stack trace path.");
        }
        // Grab only the information of the 7 most recent stacktrace
        $files = array_slice(scandir($stack_trace_path, SCANDIR_SORT_DESCENDING), 0, 7);
        $info = [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $file_path = FileUtils::joinPaths($stack_trace_path, $f);
            if (is_readable($file_path)) {
                $info[$f] = file_get_contents($file_path);
            }
            else {
                $info[$f] = "Not readable";
            }
        }
        return JsonResponse::getSuccessResponse($info);
    }

    // Uses the GradingQueue class to get all the info from the necessary files
    private function getAutogradingInfo(): array {
        $gq = new GradingQueue(null, null, $this->core->getConfig()->getSubmittyPath());
        $info = $gq->getAutogradingInfo($this->core->getConfig()->getConfigPath());
        $course = [];
        $courses_full = $this->core->getQueries()->getInstructorLevelAccessCourse($this->core->getUser()->getId());
        foreach ($courses_full as $row) {
            $course[] = $row["term"] . " " . $row["course"];
        }
        foreach ($info["ongoing_job_info"] as &$i) {
            foreach ($i as &$job) {
                if (!in_array($job["term"] . " " . $job["course"], $course)) {
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
