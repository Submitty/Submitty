<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\views\GradingMachineView;
use app\libraries\FileUtils;
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

    public function getProgress(): WebResponse {
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
    }
}
