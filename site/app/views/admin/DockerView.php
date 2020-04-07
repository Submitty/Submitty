<?php

namespace app\views\admin;

use app\libraries\Utils;
use app\views\AbstractView;

class DockerView extends AbstractView {
    public function displayDockerPage($docker_data) {

        $this->output->addBreadcrumb("Docker Interface");
        $this->output->setPageName('Docker Interface');

        $docker_images = $docker_data['docker_images'] ?? null;
        $docker_info = $docker_data['docker_info'] ?? null;
        $last_updated = $docker_data['update_time'] ?? null;

        if (!is_null($docker_info)) {
            $docker_info['MemTotal'] = Utils::formatBytes("MB", $docker_info['MemTotal']);
        }

        $found_images = [];
        $not_found = [];
        if (!is_null($docker_data)) {
            $autograding_containers = $docker_data['autograding_containers'];

            //figure out which images are installed and listed in the config
            foreach ($docker_data['docker_images'] as $image) {
                $name = $image['Repository'] . ':' . $image['Tag'];

                if (in_array($name, $autograding_containers['default'])) {
                    $found_images[] = $image;
                }
            }

            //figure out which images are listed in the config but not found
            foreach ($autograding_containers['default'] as $autograding_image) {
                $found = false;
                foreach ($docker_data['docker_images'] as $image) {
                    $name = $image['Repository'] . ':' . $image['Tag'];

                    if ($name === $autograding_image) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $not_found[] = $autograding_image;
                }
            }
        }

        $autograding_containers = [
            "found" => $found_images,
            "not_found" => $not_found
        ];

        return $this->output->renderTwigTemplate("admin/Docker.twig", [
           "docker_images" => $docker_images,
           "docker_info" => $docker_info,
           "last_updated" => $last_updated,
           "autograding_containers" => $autograding_containers
        ]);
    }
}
