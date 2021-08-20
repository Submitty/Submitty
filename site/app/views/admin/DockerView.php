<?php

namespace app\views\admin;

use app\libraries\Utils;
use app\libraries\FileUtils;
use app\views\AbstractView;

class DockerView extends AbstractView {
    public function displayDockerPage(array $docker_data) {

        $this->output->addBreadcrumb("Docker Interface");
        $this->output->setPageName('Docker Interface');

        $this->core->getOutput()->addInternalCss('docker_interface.css');
        $this->core->getOutput()->addInternalJs('docker_interface.js');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->enableMobileViewport();

        //sort containers alphabetically
        $sort_containers = function (array $containers, string $key, int $order = SORT_ASC): array {
            $names = array_column($containers, $key);
            array_multisort($names, $order, $containers);
            return $containers;
        };


        $images = [];
        foreach ($docker_data['autograding_containers'] as $capability => $image_list) {
            foreach ($image_list as $image) {
                $images[] = $image;
            }
        }

        $images = array_unique($images);

        $capabilities = [];
        $worker_machines = [];
        $no_image_capabilities = [];
        $image_to_capability = [];
        foreach ($docker_data['autograding_workers'] as $name => $worker) {
            $worker_temp = [];
            $worker_temp['name'] = $name;
            $worker_temp['capabilities'] = [];
            $worker_temp['images'] = [];
            $image_names = [];
            foreach ($worker['capabilities'] as $capability) {
                $capabilities[] = $capability;
                $worker_temp['num_autograding_workers'] = $worker['num_autograding_workers'];
                $worker_temp['enabled'] = $worker['enabled'];
                $worker_temp['capabilities'] = $worker['capabilities'];
                // list of capabilities without containers
                $worker_temp['images_not_found'] = [];
                if (array_key_exists($capability, $docker_data['autograding_containers'])) {
                    foreach ($docker_data['autograding_containers'][$capability] as $image) {
                        $image_names[] = $image;
                        $image_to_capability[$image][] = $capability;
                    }
                }
                else {
                    $no_image_capabilities[] = $capability;
                }
            }
            $worker_machines[] = $worker_temp;
        }

        $capabilities = array_unique($capabilities);
        foreach ($image_to_capability as $image => $map) {
            $image_to_capability[$image] = array_unique($map);
        }
        sort($capabilities);
        $capability_to_color = [];
        for ($i = 0; $i < count($capabilities); $i++) {
            $capability_to_color[$capabilities[$i]] = min($i + 1, 20);
        }

        $array_list = scandir(
            FileUtils::joinPaths(
                $this->core->getConfig()->getSubmittyPath(),
                "logs/docker/"
            )
        );
        $last_ran = "";
        $machine_to_update = [];
        $fail_images = [];
        $error_logs = [];

        if (count($array_list) > 2) {
            $last_ran = "never";
            $most_recent = max($array_list);
            $content = file_get_contents(
                FileUtils::joinPaths(
                    $this->core->getConfig()->getSubmittyPath(),
                    "logs/docker",
                    $most_recent
                )
            );

            $reset = false;
            $content = rtrim($content);
            $buffer = strtok($content, "\n");
            while ($buffer !== false) {
                if ($reset) {
                    $error_logs = [];
                    $machine_to_update = [];
                    $fail_images = [];
                    $reset = false;
                }

                $matches = [];

                $isMatch = preg_match("/^\[Last ran on: ([0-9 :-]{19})\]/", $buffer, $matches);
                if ($isMatch === 1) {
                    $last_ran = $matches[1];
                    $reset = true;
                }
                $isMatch = preg_match("/FAILURE TO UPDATE MACHINE (.+)/", $buffer, $matches);
                if ($isMatch) {
                    $machine_to_update[$matches[1]] = false;
                    $error_logs[] = $buffer;
                }

                $isMatch = preg_match("/ERROR: Could not pull (.+)/", $buffer, $matches);
                if ($isMatch) {
                    $fail_images = $matches[1];
                    $error_logs[] = $buffer;
                }
                if (preg_last_error() != PREG_NO_ERROR) {
                    $error_logs[] = "Error while parsing the logs";
                    break;
                }
                $buffer = strtok("\n");
            }
        }

        $no_image_capabilities = array_unique($no_image_capabilities);

        return $this->output->renderTwigTemplate(
            "admin/Docker.twig",
            [
                "autograding_containers" => $docker_data['autograding_containers'],
                "capabilities" => $capabilities,
                "worker_machines" => $worker_machines,
                "no_image_capabilities" => $no_image_capabilities,
                "image_to_capability" => $image_to_capability,
                "capability_to_color" => $capability_to_color,
                "admin_url" => $this->core->buildUrl(["admin"]),
                "last_updated" => $last_ran,
                "machine_to_update" => $machine_to_update,
                "fail_images" => $fail_images,
                "error_logs" => $error_logs
            ]
        );
    }
}
