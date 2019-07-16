<?php

namespace app\views\admin;

use app\views\AbstractView;

class GradeableView extends AbstractView {
    public function uploadConfigForm($target_dir, $all_files, $inuse_config) {
        $this->core->getOutput()->addBreadcrumb("upload config", $this->core->buildNewCourseUrl(['gradeable_config']));
        $course = $this->core->getConfig()->getCourse();

        return $this->core->getOutput()->renderTwigTemplate("admin/UploadConfigForm.twig", [
            "all_files" => $all_files,
            "target_dir" => $target_dir,
            "course" => $course,
            "inuse_config" => $inuse_config,
            "upload_url" => $this->core->buildNewCourseUrl(['gradeable_config', 'upload']),
            "rename_url" => $this->core->buildNewCourseUrl(['gradeable_config', 'rename']),
            "delete_url" => $this->core->buildNewCourseUrl(['gradeable_config', 'delete']),
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}
