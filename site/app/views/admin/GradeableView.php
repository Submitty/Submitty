<?php

namespace app\views\admin;

use app\libraries\FileUtils;
use app\views\AbstractView;

class GradeableView extends AbstractView {
    public function uploadConfigForm($target_dir, $all_files, $inuse_config) {
        $this->core->getOutput()->addBreadcrumb("upload config", $this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable', 'action' => 'upload_config')));
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        return $this->core->getOutput()->renderTwigTemplate("admin/UploadConfigForm.twig", [
            "all_files" => $all_files,
            "target_dir" => $target_dir,
            "course" => $course,
            "inuse_config" => $inuse_config
        ]);
    }
}
