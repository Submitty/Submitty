<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\FileUtils;
use app\exceptions\CurlException;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DockerInterfaceController
 *
 * Works with Docker to provide a user inteface
 *
 */
class DockerInterfaceController extends AbstractController {

    /**
     * @Route("/admin/docker", methods={"GET"})
     * @Route("/api/docker", methods={"GET"})
     * @return MultiResponse
     */
    public function showDockerInterface(): MultiResponse {
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        try {
            $response = $this->core->curlRequest(
                FileUtils::joinPaths($this->core->getConfig()->getCgiUrl(), "docker_ui.cgi")
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

        if ($json['success'] === false) {
            return new MultiResponse(
                JsonResponse::getFailResponse($json['error']),
                new WebResponse("Error", "errorPage", $json['error'])
            );
        }

        $json['autograding_containers'] = FileUtils::readJsonFile(
            FileUtils::joinPaths(
                $this->core->getConfig()->getSubmittyInstallPath(),
                "config",
                "autograding_containers.json"
            )
        );
        
        $json['autograding_workers'] = FileUtils::readJsonFile(
            FileUtils::joinPaths(
                $this->core->getConfig()->getSubmittyInstallPath(),
                "config",
                "autograding_workers.json"
            )
        );
        return new MultiResponse(
            JsonResponse::getSuccessResponse($json),
            new WebResponse(
                ['admin', 'Docker'],
                'displayDockerPage',
                $json
            )
        );
    }
}
