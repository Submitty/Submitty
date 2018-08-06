<?php

namespace app\views\admin;

use app\views\AbstractView;

class WrapperView extends AbstractView {
    public function displayPage($wrapper_files) {
        return $this->core->getOutput()->renderTwigTemplate("admin/UploadWrapperForm.twig", [
            "wrapper_files" => $wrapper_files
        ]);
    }
}