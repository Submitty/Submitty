<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
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
            'displayPage'
        );
    }

    public function getProgress(): WebResponse {
        // Read autograding_workers.json
        $workers = json_encode(FileUtils::readJsonFile(
            FileUtils::joinPaths(
                $this->core->getConfig()->getSubmittyInstallPath(),
                "config",
                "autograding_workers.json"
            )
        ));

        try {
            $response = $this->core->curlRequest(
                $this->core->getConfig()->getCgiUrl() . "grading_done.cgi"
            );
        }
        catch (CurlException $exc) {
            $msg = "Failed to get response from CGI process, please try again";
            return new MultiResponse(
                JsonResponse::getFailResponse($msg),
                new WebResponse("Error", "errorPage", $msg)
            );
        }
        $json = json_decode($response, true);
        return new WebResponse(
            AutogradingStatusView::class,
            'renderTable',
            $response
        );
    }
}
