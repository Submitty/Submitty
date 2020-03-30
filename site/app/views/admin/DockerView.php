<?php

namespace app\views\admin;

use app\libraries\Utils;
use app\views\AbstractView;

class DockerView extends AbstractView {
    public function displayDockerPage($docker_data) {

        $this->output->addBreadcrumb("Docker Interface");
        $this->output->setPageName('Docker Interface');

        $docker_images = null;
        $docker_info = null;
        $last_updated = null;

        if (!is_null($docker_data)) {
            $docker_images = $docker_data['docker_images'];
            $docker_info = $docker_data['docker_info'];
            $last_updated = $docker_data['update_time'];

            $docker_info['MemTotal'] = Utils::formatBytes("MB", $docker_info['MemTotal']);
        }

        return $this->output->renderTwigTemplate("admin/Docker.twig", [
           "docker_images" => $docker_images,
           "docker_info" => $docker_info,
           "last_updated" => $last_updated
        ]);
    }
}
