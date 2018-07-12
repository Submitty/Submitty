<?php

namespace app\views\admin;

use app\views\AbstractView;

class WrapperView extends AbstractView {
	public function displayPage() {
		return $this->core->getOutput()->renderTwigTemplate("admin/UploadWrapperForm.twig", []);
	}
}