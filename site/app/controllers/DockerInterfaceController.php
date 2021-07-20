<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\DateUtils;
use app\exceptions\CurlException;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\routers\AccessControl;
use app\libraries\response\JsonResponse;
use app\views\ErrorView;
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
    /**
     * @Route("/admin/add_image", methods={"POST"})
     * @Route("/api/admin/add_image", methods={"GET"})
     * @return JsonResponse | MultiResponse
     */
    public function addImage(){
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse(ErrorView::class, "errorPage", "You don't have access to this page.")
            );
        }

        if (!isset($_POST['image'])) {
            $this->core->addErrorMessage("Image not set");
            return JsonResponse::getErrorResponse("Image not set");
        }
        if (!isset($_POST['capability'])) {
            $this->core->addErrorMessage("Capability not set");
            return JsonResponse::getErrorResponse("Capability not set");
        }

        // check for proper format
        $match = preg_match('/^[a-z0-9]+[a-z0-9._(__)-]*[a-z0-9]+\/[a-z0-9]+[a-z0-9._(__)-]*[a-z0-9]+:[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}$/', $_POST['image']);

        if ($match === false) {
            $this->core->addErrorMessage("An error has occurred when verifying image name");
            return JsonResponse::getErrorResponse("An error has occurred when verifying image name");
        }

        if ($match === 0) {
            $this->core->addErrorMessage("Improper docker image name");
            return JsonResponse::getErrorResponse("Improper docker image name");
        }

        $image_arr = explode(":", $_POST['image']);
        // ping the dockerhub API to check if docker exists
        $url = "https://registry.hub.docker.com/v1/repositories/" . $image_arr[0] . "/tags";
        $tag = $image_arr[1];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $return_str = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $code_success = !$http_code == 200;
        if (curl_errno($ch) || $http_code !== 200) {
            $this->core->addErrorMessage("Error: Repository not found on dockerhub.");
            return JsonResponse::getErrorResponse($image_arr[0] . ' not found on DockerHub');
        }
        $return_json = json_decode($return_str);
        $found = false;

        foreach ($return_json as $image) {
            if ($image->name == $tag) {
                $found = true;
                break;
            }
        }

        if ($found) {
            $json = FileUtils::readJsonFile(
                FileUtils::joinPaths(
                    $this->core->getConfig()->getSubmittyInstallPath(),
                    "config",
                    "autograding_containers.json"
                )
            );
            if (!in_array($_POST['image'], $json[$_POST['capability']])) {
                $json[$_POST['capability']][] = $_POST['image'];
            }
            else {
                $this->core->addErrorMessage("Error: Image already exists in capability " . $_POST['capability']);
                return JsonResponse::getFailResponse($_POST['image'] . ' already exists in capability ' . $_POST['capability']);
            }
            FileUtils::writeJsonFile(
                FileUtils::joinPaths(
                    $this->core->getConfig()->getSubmittyInstallPath(),
                    "config",
                    "autograding_containers.json"
                ),
                $json
            );

            if (!$this->updateDocker()) {
                return JsonResponse::getFailResponse("Could not update docker images, please try again later.");
            }
            $this->core->addSuccessMessage("Image found on dockerhub!\n" . $_POST['image'] . " queued to be added.");
            return JsonResponse::getSuccessResponse($_POST['image'] . ' found on DockerHub');
        }
        else {
            $this->core->addErrorMessage("Error: Image not found on dockerhub.");
            return JsonResponse::getFailResponse($_POST['image'] . ' not found on DockerHub');
        }
    }

    /**
     * @Route("/admin/update_docker", methods={"GET"})
     * @return JsonResponse
     */
    public function updateDockerCall() {
        if (!$this->updateDocker()) {
            return JsonResponse::getFailResponse("Failed to write to file");
        }
        $this->core->addSuccessMessage("Successfully queued the system to update docker, please refresh the page in a bit.");
        return JsonResponse::getSuccessResponse("Successfully updated docker images and machines");
    }

    /**
     * @return bool
     */
    private function updateDocker() {
        $now = $this->core->getDateTimeNow()->format('Ymd');
        $docker_job_file = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue/docker" . $now . ".json");
        $docker_data = [
            "job" => "UpdateDockerImages"
        ];

        if (
            (!is_writable($docker_job_file) && file_exists($docker_job_file))
            || file_put_contents($docker_job_file, json_encode($docker_data, JSON_PRETTY_PRINT)) === false
        ) {
            return false;
        }
        return true;
    }
}
