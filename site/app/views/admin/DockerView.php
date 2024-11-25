<?php

namespace app\views\admin;

use app\libraries\Utils;
use app\libraries\FileUtils;
use app\models\DockerUI;
use app\views\AbstractView;
use ParseError;

class DockerView extends AbstractView {
    public function displayDockerPage(DockerUI $docker_ui) {
        $this->output->addBreadcrumb("Docker Interface");
        $this->output->setPageName('Docker Interface');

        $this->core->getOutput()->addInternalCss('docker_interface.css');
        $this->core->getOutput()->addInternalJs('docker_interface.js');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->enableMobileViewport();


        return $this->output->renderTwigTemplate(
            "admin/Docker.twig",
            [
                "autograding_containers" => $docker_ui->getAutogradingContainers(),
                "capabilities" => $docker_ui->getCapabilities(),
                "worker_machines" => $docker_ui->getWorkerMachineNames(),
                "no_image_capabilities" => $docker_ui->getNoImageCapabilities(),
                "image_to_capability" => $docker_ui->getImageToCapabilitiyMapping(),
                "capability_to_color" => $docker_ui->getCapabilityToColorMapping(),
                "admin_url" => $this->core->buildUrl(["admin"]),
                "last_updated" => $docker_ui->getLastRan(),
                "sysinfo_last_updated" => $sysinfo_last_updated,
                "machine_to_update" => $machine_to_update,
                "image_info" => $image_info,
                "machine_docker_version" => $machine_docker_version,
                "machine_system_details" => $machine_system_details,
                "aliases" => $aliases,
                "fail_images" => $fail_images ?? [],
                "error_logs" => $docker_ui->getErrorLogs(),
            ]
        );
    }
}
