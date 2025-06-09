<?php

namespace app\views\admin;

use app\models\User;
use app\models\DockerUI;
use app\views\AbstractView;

class DockerView extends AbstractView {
    public function displayDockerPage(DockerUI $docker_ui): string {
        $this->output->addBreadcrumb("Docker Interface");
        $this->output->setPageName('Docker Interface');

        $this->core->getOutput()->addInternalCss('docker_interface.css');
        $this->core->getOutput()->addInternalJs('docker_interface.js');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->enableMobileViewport();

        $sort = $_COOKIE['sort'] ?? 'name';
        $direction = $_COOKIE['direction'] ?? 'ASC';

        return $this->output->renderTwigTemplate(
            "admin/Docker.twig",
            [
                "autograding_containers" => $docker_ui->getAutogradingContainers(),
                "capabilities" => $docker_ui->getCapabilities(),
                "worker_machines" => $docker_ui->getWorkerMachines(),
                "no_image_capabilities" => $docker_ui->getNoImageCapabilities(),
                "capability_to_color_mapping" => $docker_ui->getCapabilityToColorMapping(),
                "admin_url" => $this->core->buildUrl(["admin"]),
                "last_updated" => $docker_ui->getLastRan(),
                "sysinfo_last_updated" => $docker_ui->getSysinfoLastUpdated(),
                "sort" => $sort,
                "direction" => $direction,
                "docker_images" => $docker_ui->getDockerImages($sort, $direction),
                "fail_images" => $docker_ui->getFailImages(),
                "error_logs" => $docker_ui->getErrorLogs(),
                "docker_image_owners" => $docker_ui->getDockerImageOwners(),
                "is_super_user" => $this->core->getUser()->getAccessLevel() === User::LEVEL_SUPERUSER,
                "user_id" => $this->core->getUser()->getId(),
            ]
        );
    }
}
