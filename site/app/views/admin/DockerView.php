<?php

namespace app\views\admin;

use app\libraries\Utils;
use app\libraries\FileUtils;
use app\views\AbstractView;
use ParseError;

class DockerView extends AbstractView {
    public function displayDockerPage(array $docker_data) {

        $this->output->addBreadcrumb("Docker Interface");
        $this->output->setPageName('Docker Interface');

        $this->core->getOutput()->addInternalCss('docker_interface.css');
        $this->core->getOutput()->addInternalJs('docker_interface.js');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->enableMobileViewport();

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
        $machine_docker_version = [];
        $machine_system_details = [];
        foreach ($docker_data['autograding_workers'] as $name => $worker) {
            $worker_temp = [];
            $worker_temp['name'] = $name;
            $machine_docker_version[$name] = "Error";
            $worker_temp['capabilities'] = [];
            $worker_temp['images'] = [];
            foreach ($worker['capabilities'] as $capability) {
                $capabilities[] = $capability;
                $worker_temp['num_autograding_workers'] = $worker['num_autograding_workers'];
                $worker_temp['enabled'] = $worker['enabled'];
                $worker_temp['capabilities'] = $worker['capabilities'];
                // list of capabilities without containers
                $worker_temp['images_not_found'] = [];
                if (array_key_exists($capability, $docker_data['autograding_containers'])) {
                    foreach ($docker_data['autograding_containers'][$capability] as $image) {
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
        $last_ran = "Unknown";
        $machine_to_update = [];
        $error_logs = [];
        $image_info = [];
        // Maps the images name to the name used to store the image information
        $aliases = [];
        // To account for the . and .. directories
        if (count($array_list) > 2) {
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
            $current_machine = "";
            while ($buffer !== false) {
                if ($reset) {
                    $error_logs = [];
                    $machine_to_update = [];
                    $fail_images = [];
                    $reset = false;
                }

                $matches = [];

                $is_match = preg_match("/^\[Last ran on: ([0-9 :-]{19})\]/", $buffer, $matches);
                if ($is_match === 1) {
                    $last_ran = $matches[1];
                    $reset = true;
                }

                $is_match = preg_match("/FAILURE TO UPDATE MACHINE (.+)$/", $buffer, $matches);
                if ($is_match) {
                    $machine_to_update[$matches[1]] = false;
                    $error_logs[] = $buffer;
                }

                $is_match = preg_match("/ERROR: Could not pull (.+)/", $buffer, $matches);
                if ($is_match) {
                    $fail_images = $matches[1];
                    $error_logs[] = $buffer;
                }
                // Note the machine currently described by the log
                $is_match = preg_match("/UPDATE MACHINE: (.+)/", $buffer, $matches);
                if ($is_match) {
                    $current_machine = $matches[1];
                }

                $is_match = preg_match("/Distributor ID:(.+)/", $buffer, $matches);
                if ($is_match) {
                    $machine_system_details[$current_machine]["Distributor"] = $matches[0];
                }

                $is_match = preg_match("/Description:(.+)/", $buffer, $matches);
                if ($is_match) {
                    $machine_system_details[$current_machine]["Description"] = $matches[0];
                }

                $is_match = preg_match("/Release:(.+)/", $buffer, $matches);
                if ($is_match) {
                    $machine_system_details[$current_machine]["Release"] = $matches[0];
                }

                $is_match = preg_match("/Codename:(.+)/", $buffer, $matches);
                if ($is_match) {
                    $machine_system_details[$current_machine]["Codename"] = $matches[0];
                }

                // Parse the docker version
                $is_match = preg_match("/Docker Version: (.+)/", $buffer, $matches);
                if ($is_match) {
                    $machine_docker_version[$current_machine] = $matches[1];
                }

                // Check if the log entry is describing a machine
                $is_match = preg_match("/Tag: (.+)/", $buffer, $matches);
                if ($is_match) {
                    $image_arr = explode(", ", $matches[1]);
                    $current_image = $image_arr[0];
                    array_shift($image_arr);
                    // reset this for newer entries of the log
                    $aliases[$current_image] = [$current_image];
                    foreach ($image_arr as $image) {
                        $aliases[$image][] = $current_image;
                    }
                    // Read the next 3 lines for more info
                    // read id
                    $buffer = strtok("\n");
                    $is_match = preg_match("/\t-id: (.+)/", $buffer, $matches);
                    if (!$buffer || !$is_match) {
                        throw new ParseError("Unexpected log input, attempted to read image id");
                    }
                    $id = $matches[1];

                    // read created
                    $buffer = strtok("\n");
                    $is_match = preg_match("/\t-created: (.+)/", $buffer, $matches);
                    if (!$buffer || !$is_match) {
                        throw new ParseError("Unexpected log input, attempted to read image creation date");
                    }
                    $created = $matches[1];

                    // read size
                    $buffer = strtok("\n");
                    $is_match = preg_match("/\t-size: (.+)/", $buffer, $matches);
                    if (!$buffer || !$is_match) {
                        throw new ParseError("Unexpected log input, attempted to read image size");
                    }
                    $size = $matches[1];

                    foreach ($aliases[$current_image] as $alias) {
                        $image_info[$alias] = [
                            "id" => $id,
                            "created" => \DateTime::createFromFormat('Y-m-d\TH:i:s+', $created)->format("Y-m-d H:i:s"),
                            "size" => Utils::formatBytes('mb', $size, true)
                        ];
                    }
                }

                $is_match = preg_match("/APIError was raised./", $buffer, $matches);
                if ($is_match) {
                    $error_logs[] = "APIError has occured, please update the machines.";
                }
                if (preg_last_error() != PREG_NO_ERROR) {
                    $error_logs[] = "Error while parsing the logs";
                    break;
                }
                $buffer = strtok("\n");
            }
        }

        $no_image_capabilities = array_unique($no_image_capabilities);
        foreach ($aliases as &$alias) {
            $alias = array_unique($alias);
        }
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
                "image_info" => $image_info,
                "machine_docker_version" => $machine_docker_version,
                "machine_system_details" => $machine_system_details,
                "aliases" => $aliases,
                "fail_images" => $fail_images ?? [],
                "error_logs" => $error_logs
            ]
        );
    }
}
