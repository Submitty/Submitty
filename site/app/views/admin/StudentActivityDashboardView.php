<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\FileUtils;

class StudentActivityDashboardView extends AbstractView {

    public function createTable($data_dump) {
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalCss('activity-dashboard.css');
        $this->core->getOutput()->addInternalModuleJs('activity-dashboard.js');
        $this->core->getOutput()->addInternalCss('flatpickr.min.css');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
        return $this->core->getOutput()->renderTwigTemplate("admin/users/StudentActivityDashboard.twig", [
            "data" => $data_dump,
            "download_link" => $this->core->buildCourseUrl(['activity', 'download'])
        ]);
    }

    public function downloadFile($file_url, $fp) {
        header("Content-type: text/csv");
        header('Content-Disposition: attachment; filename="Student_Activity.csv"');
        header("Content-length: " . filesize($file_url));
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile($file_url);
        fclose($fp);
    }
}
