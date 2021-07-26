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

        $found_images = [];
        $not_found = [];
        $autograding_containers = $docker_data['autograding_containers']['default'];

        //make data more human readable
        $copy = [];
        foreach ($docker_data['docker_images'] as $image) {
            $full_name = $image['tags'][0];
            $parts = explode(":", $full_name);
            $date = \DateTime::createFromFormat('Y-m-d\TH:i:s+', $image['created'])->format("Y-m-d H:i:s");
            $image["name"] = $parts[0];
            $image["tag"] = $parts[1];
            $image["created"] = $date;
            $image["size"] = Utils::formatBytes('mb', $image["size"], true);
            $image["virtual_size"] = Utils::formatBytes('mb', $image["virtual_size"], true);
            $image["additional_names"] = array_slice($image['tags'], 1);

            $copy[$full_name] = $image;
        }

        $docker_data['docker_images'] = $copy;

        //figure out which images are installed and listed in the config
        foreach ($docker_data['docker_images'] as $image) {
            foreach ($autograding_containers as $container) {
                if (in_array($container, $image['tags'])) {
                    $found_images[] = $image;
                    break;
                }
            }
        }

        //figure out which images are listed in the config but not found
        foreach ($autograding_containers as $autograding_image) {
            $found = false;
            foreach ($docker_data['docker_images'] as $image) {
                $name = $image['tags'][0];

                if (in_array($autograding_image, $image['tags'])) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $not_found[] = $autograding_image;
            }
        }


        //sort containers alphabetically
        $sort_containers = function (array $containers, string $key, int $order = SORT_ASC): array {
            $names = array_column($containers, $key);
            array_multisort($names, $order, $containers);
            return $containers;
        };


        $autograding_containers = [
            "found" => $sort_containers($found_images, 'name'),
            "all_images" => $sort_containers($docker_data['docker_images'], 'name'),
            "not_found" => sort($not_found)
        ];

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
            // Get ride of duplicate images
            $image_names = array_unique($image_names);
            foreach ($image_names as $image) {
                // Extra precaution since data is quite messy
                if (array_key_exists($image, $autograding_containers['all_images'])) {
                    $worker_temp['images'][] = $autograding_containers['all_images'][$image];
                }
                else {
                    $worker_temp['images_not_found'][] = $image;
                }
            }
            $worker_machines[] = $worker_temp;
        }

        $capabilities = array_unique($capabilities);
        foreach ($image_to_capability as $map) {
            $map = array_unique($map);
        }
        asort($capabilities);
        $capability_to_color = [];
        $colors = ['#c3a2d2','#99b270','#cd98aa','#6bb88f','#c8938d','#6b9fb8','#c39e83','#98a3cd','#8ac78e','#b39b61','#6eb9aa','#b4be79','#94a2cc','#80be79','#b48b64','#b9b26e','#83a0c3','#ada5d4','#e57fcf','#c0c246'];
        for ($i = 0; $i < count($capabilities); $i++) {
            $capability_to_color[$capabilities[$i]] = $colors[$i] ?? $colors[19];
        }

        foreach ($worker_machines as $worker) {
            foreach ($capabilities as $capability) {
                $worker_temp['capabilities'][] = in_array($capability, $worker['capabilities']);
            }
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
            $buffer = strtok($content, "\n");
            while ($buffer !== false) {
                if ($reset) {
                    $error_logs = [];
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
                "autograding_containers" => $autograding_containers,
                "docker_info" => $docker_data['docker_info'],
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
