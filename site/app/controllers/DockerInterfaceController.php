<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\FileUtils;
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
     * DockerInterfaceController constructor.
     *
     * @param Core $core
     */
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
    * @Route("/admin/docker/update", methods={"GET"})
    * @Route("/api/docker/update", methods={"GET"})
    * @return MultiResponse
    *
    * Creates a json file under the top level dir "docker_data"
    * containing information about the current docker config
    *
    * This is done through a submitty job
    */
    public function updateDockerData() {
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        $json = [
            "job" => "UpdateDockerData"
        ];

        $json = json_encode($json, JSON_PRETTY_PRINT);
        $path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue", "updateDockerInfo.json");

        if (file_put_contents($path, $json) === false) {
            $err_msg = "Failed to create UpdateDockerData job";
            $this->core->addErrorMessage($err_msg);
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse($err_msg)
            );
        }

        return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse());
    }

    /**
    * @Route("/admin/docker/check_jobs", methods={"GET"})
    * @Route("/api/docker/check_jobs", methods={"GET"})
    * @return MultiResponse
    *
    * Checks if there is currently a job to update the docker UI
    * in the submitty job queue
    */
    public function checkJobStatus() {
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint.")
            );
        }

        $path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue", "updateDockerInfo.json");

        return MultiResponse::JsonOnlyResponse(
            JsonResponse::getSuccessResponse(["found" => file_exists($path)])
        );
    }

    /**
     * @Route("/admin/docker", methods={"GET"})
     * @Route("/api/docker", methods={"GET"})
     * @return MultiResponse
     */
    public function showDockerInterface() {
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        $this->core->getOutput()->addInternalCss('docker_interface.css');
        $this->core->getOutput()->addInternalJs('docker_interface.js');
        $this->core->getOutput()->enableMobileViewport();

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
        
        $json = json_decode($response, True);

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
