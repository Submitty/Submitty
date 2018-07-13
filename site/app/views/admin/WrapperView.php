<?php

namespace app\views\admin;

use app\views\AbstractView;

class WrapperView extends AbstractView {
	public function displayPage($target_dir, $html_files) {
		return $this->core->getOutput()->renderTwigTemplate("admin/UploadWrapperForm.twig", [
		    "target_dir" => $target_dir,
		    "html_files" => $html_files
        ]);
	}
}