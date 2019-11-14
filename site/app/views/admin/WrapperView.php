<?php

namespace app\views\admin;

use app\views\AbstractView;

class WrapperView extends AbstractView {
    public function displayPage($wrapper_files) {
        $this->core->getOutput()->addBreadcrumb("Customize Website Theme");
        return $this->core->getOutput()->renderTwigTemplate("admin/UploadWrapperForm.twig", [
            "wrapper_files" => $wrapper_files,
            "csrf_token" => $this->core->getCsrfToken(),
            "upload_url" => $this->core->buildCourseUrl(['theme', 'upload']),
            "delete_url" => $this->core->buildCourseUrl(['theme', 'delete'])
        ]);
    }
}
