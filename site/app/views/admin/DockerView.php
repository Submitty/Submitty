<?php

namespace app\views\admin;

use app\libraries\Utils;
use app\views\AbstractView;

class DockerView extends AbstractView {
    public function displayDockerPage($json) {

        $this->output->addBreadcrumb("Docker Interface");
        $this->output->setPageName('Docker Interface');

        var_dump($json);

    
        // $found_images = [];
        // $not_found = [];
        // if ($docker_data !== false) {
        //     $autograding_containers = $container_config['default'];

        //     //figure out which images are installed and listed in the config
        //     foreach ($docker_data['docker_images'] as $image) {
        //         $name = $image['Repository'] . ':' . $image['Tag'];

        //         if (in_array($name, $autograding_containers)) {
        //             $found_images[] = $image;
        //         }
        //     }

        //     //figure out which images are listed in the config but not found
        //     foreach ($autograding_containers as $autograding_image) {
        //         $found = false;
        //         foreach ($docker_data['docker_images'] as $image) {
        //             $name = $image['Repository'] . ':' . $image['Tag'];

        //             if ($name === $autograding_image) {
        //                 $found = true;
        //                 break;
        //             }
        //         }

        //         if (!$found) {
        //             $not_found[] = $autograding_image;
        //         }
        //     }
        // }

        // $autograding_containers = [
        //     "found" => $found_images,
        //     "not_found" => $not_found
        // ];

        return $this->output->renderTwigTemplate(
            "admin/Docker.twig",
            [ ]
        );
    }
}
