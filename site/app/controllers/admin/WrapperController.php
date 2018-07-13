<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;

class WrapperController extends AbstractController {
	public function run() {
		switch($_REQUEST['action']) {
			case 'show_page':
			default:
				$this->uploadWrapperPage();
		}
	}

	public function uploadWrapperPage() {
		$this->core->getOutput()->renderOutput(array('admin', 'Wrapper'), 'displayPage');
	}
}