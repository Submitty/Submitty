<?php

namespace app\views\admin;

use app\libraries\Utils;
use app\views\AbstractView;

class DockerView extends AbstractView {
    public function displayDockerPage(array $docker_data) {

        $this->output->addBreadcrumb("Docker Interface");
        $this->output->setPageName('Docker Interface');

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


            $copy[] = $image;
        }

        $docker_data['docker_images'] = $copy;

        //figure out which images are installed and listed in the config
        foreach ($docker_data['docker_images'] as $image) {
            $name = $image['tags'][0];

            if (in_array($name, $autograding_containers)) {
                $found_images[] = $image;
            }
        }

        //figure out which images are listed in the config but not found
        foreach ($autograding_containers as $autograding_image) {
            $found = false;
            foreach ($docker_data['docker_images'] as $image) {
                $name = $image['tags'][0];

                if ($name === $autograding_image) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $not_found[] = $autograding_image;
            }
        }

        $autograding_containers = [
            "found" => $found_images,
            "not_found" => $not_found,
            "all_images" => $docker_data['docker_images']
        ];

        return $this->output->renderTwigTemplate(
            "admin/Docker.twig",
            [
                "autograding_containers" => $autograding_containers,
                "docker_info" => $docker_data['docker_info'],
            ]
        );
    }
}
