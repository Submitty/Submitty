<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\response\Response;
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
    * @return Response
    *
    * Creates a json file under the top level dir "docker_data"
    * containing information about the current docker config
    *
    * This is done through a submitty job
    */
    public function updateDockerData() {
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return new Response(
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
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse($err_msg)
            );
        }

        return Response::JsonOnlyResponse(JsonResponse::getSuccessResponse());
    }

    /**
    * @Route("/admin/docker/check_jobs", methods={"GET"})
    * @Route("/api/docker/check_jobs", methods={"GET"})
    * @return Response
    *
    * Checks if there is currently a job to update the docker UI
    * in the submitty job queue
    */
    public function checkJobStatus() {
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint.")
            );
        }

        $path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue");

        $found = false;
        try {
            foreach (scandir($path) as $job) {
                if (strpos($job, 'updateDockerInfo') !== false) {
                    $found = true;
                    break;
                }
            }
        }
        catch (\Exception $e) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse($e->getMessage())
            );
        }

        return Response::JsonOnlyResponse(JsonResponse::getSuccessResponse(["found" => $found]));
    }

    /**
     * @Route("/admin/docker", methods={"GET"})
     * @Route("/api/docker", methods={"GET"})
     * @return Response
     */
    public function showDockerInterface() {
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return new Response(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        $this->core->getOutput()->addInternalCss('docker_interface.css');
        $this->core->getOutput()->addInternalJs('docker_interface.js');

        $this->core->getOutput()->enableMobileViewport();

        $path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "docker_data", "submitty_docker.json");

        
        $docker_info = json_decode(@file_get_contents($path), true);
        $json_response = JsonResponse::getSuccessResponse($docker_info);
        if (is_null($docker_info)) {
            $err_msg = "Failed to parse submitty docker information";
            $this->core->addErrorMessage($err_msg);
            $json_response = JsonResponse::getFailResponse($err_msg);
        }

        return new Response(
            $json_response,
            new WebResponse(
                ['admin', 'Docker'],
                'displayDockerPage',
                $docker_info
            )
        );
    }
}
