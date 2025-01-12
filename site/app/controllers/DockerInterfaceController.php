<?php

namespace app\controllers;

use app\libraries\FileUtils;
use app\libraries\Logger;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use app\models\DockerUI;

/**
 * Class DockerInterfaceController
 *
 * Works with Docker to provide a user interface
 *
 */
class DockerInterfaceController extends AbstractController {
    /**
     * Entry point to render the Docker UI, handles both API and webresponse calls
     */
    #[Route("/admin/docker", methods: ["GET"])]
    #[Route("/api/docker", methods: ["GET"])]
    public function showDockerInterface(): MultiResponse {
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return new MultiResponse(
                JsonResponse::getFailResponse("You don't have access to this endpoint."),
                new WebResponse("Error", "errorPage", "You don't have access to this page.")
            );
        }

        $json = [];
        $json['autograding_containers'] = FileUtils::readJsonFile(
            FileUtils::joinPaths(
                $this->core->getConfig()->getSubmittyInstallPath(),
                "config",
                "autograding_containers.json"
            )
        );

        if($json['autograding_containers'] === false) {
            $error_message = "Failed to read autograding_containers.json";
            Logger::error($error_message);
            return new MultiResponse(
                JsonResponse::getFailResponse($error_message),
                new WebResponse("Error", "errorPage", $error_message)
            );
        }

        $json['autograding_workers'] = FileUtils::readJsonFile(
            FileUtils::joinPaths(
                $this->core->getConfig()->getSubmittyInstallPath(),
                "config",
                "autograding_workers.json"
            )
        );

        if($json['autograding_workers'] === false) {
            $error_message = "Failed to read autograding_workers.json";
            Logger::error($error_message);
            return new MultiResponse(
                JsonResponse::getFailResponse($error_message),
                new WebResponse("Error", "errorPage", $error_message)
            );
        }

        return new MultiResponse(
            JsonResponse::getSuccessResponse($json),
            new WebResponse(
                ['admin', 'Docker'],
                'displayDockerPage',
                new DockerUI($this->core, $json),
            )
        );
    }

    #[Route("/admin/add_image", methods: ["POST"])]
    #[Route("/api/admin/add_image", methods: ["GET"])]
    public function addImage(): JsonResponse {
        $user = $this->core->getUser();
        if (!$user->accessFaculty()) {
            return JsonResponse::getFailResponse("You don't have access to this endpoint.");
        }
        $user_id = $this->core->getUser()->getId();

        if (!isset($_POST['image'])) {
            return JsonResponse::getErrorResponse("Image not set");
        }
        if (!isset($_POST['capability'])) {
            return JsonResponse::getErrorResponse("Capability not set");
        }

        // check for proper format
        $match = preg_match('/^[a-z0-9]+[a-z0-9._(__)-]*[a-z0-9]+\/[a-z0-9]+[a-z0-9._(__)-]*[a-z0-9]+:[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}$/', $_POST['image']);

        if ($match === false) {
            return JsonResponse::getErrorResponse("An error has occurred when verifying image name");
        }

        if ($match === 0) {
            return JsonResponse::getErrorResponse("Improper docker image name");
        }

        $image_arr = explode(":", $_POST['image']);
        // ping the dockerhub API to check if docker exists
        $url = "https://registry.hub.docker.com/v2/repositories/" . $image_arr[0] . "/tags";
        $tag = $image_arr[1];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $return_str = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $code_success = !$http_code == 200;
        if (curl_errno($ch) || $http_code !== 200) {
            return JsonResponse::getErrorResponse($image_arr[0] . ' not found on DockerHub');
        }
        $return_json = (array) json_decode($return_str);
        if (!isset($return_json['results'])) {
            return JsonResponse::getFailResponse($_POST['image'] . ' not found on DockerHub');
        }
        $found = false;
        foreach ($return_json['results'] as $result) {
            if ($result->name === $tag) {
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

            if (!array_key_exists($_POST['capability'], $json)) {
                $json[$_POST['capability']] = [];
            }

            if (!in_array($_POST['image'], $json[$_POST['capability']])) {
                $json[$_POST['capability']][] = $_POST['image'];
                $this->core->getQueries()->setDockerImageOwner($_POST['image'], $user_id);
            }
            else {
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
            return JsonResponse::getSuccessResponse($_POST['image'] . ' found on DockerHub and queued to be added!');
        }
        else {
            return JsonResponse::getFailResponse($_POST['image'] . ' not found on DockerHub');
        }
    }

    #[Route("/admin/update_docker", methods: ["GET"])]
    public function updateDockerCall(): JsonResponse {
        $user = $this->core->getUser();
        if (is_null($user) || !$user->accessFaculty()) {
            return JsonResponse::getFailResponse("You don't have access to this endpoint.");
        }
        if (!$this->updateDocker()) {
            return JsonResponse::getErrorResponse("Failed to write to file");
        }
        return JsonResponse::getSuccessResponse("Successfully queued the system to update docker, please refresh the page in a bit.");
    }

    private function updateDocker(): bool {
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


        $sysinfo_job_file = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), "daemon_job_queue/sysinfo" . $now . ".json");
        $sysinfo_data = [
            "job" => "UpdateSystemInfo"
        ];

        if (
            (!is_writable($sysinfo_job_file) && file_exists($sysinfo_job_file))
            || file_put_contents($sysinfo_job_file, json_encode($sysinfo_data, JSON_PRETTY_PRINT)) === false
        ) {
            return false;
        }
        return true;
    }

    #[Route("/admin/remove_image", methods: ["POST"])]
    public function removeImage(): JsonResponse {
        $pattern = '/^[a-z0-9]+[a-z0-9._(__)-]*[a-z0-9]+\/[a-z0-9]+[a-z0-9._(__)-]*[a-z0-9]+:[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}$/';
        $image = $_POST['image'] ?? '';

        if (!preg_match($pattern, $image)) {
            return JsonResponse::getFailResponse('Invalid Docker image name.');
        }

        if ($this->core->getQueries()->getDockerImageOwner($image) === false) {
            return JsonResponse::getFailResponse('This image is not listed.');
        }

        $user = $this->core->getUser();
        if (!$this->core->getQueries()->removeDockerImageOwner($image, $user)) {
            return JsonResponse::getFailResponse('This image is owned/managed by another instructor/superuser.');
        }

        $jsonFilePath = FileUtils::joinPaths(
            $this->core->getConfig()->getSubmittyInstallPath(),
            "config",
            "autograding_containers.json"
        );
        $json = json_decode(file_get_contents($jsonFilePath), true);

        foreach ($json as $capability_key => $capability) {
            if (($key = array_search($image, $capability, true)) !== false) {
                unset($json[$capability_key][$key]);
            }
        }

        file_put_contents($jsonFilePath, json_encode($json, JSON_PRETTY_PRINT));
        return JsonResponse::getSuccessResponse($image . ' removed from docker images!');
    }
}
