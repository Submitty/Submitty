<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;

class WrapperController extends AbstractController {
	public function run() {
		switch($_REQUEST['action']) {
			case 'upload_wrapper':
				$this->uploadWrapper();
				break;
			default:
				$this->core->getOutput()->showError("Invalid action request for wrapper controller");
		}
	}

	public function uploadWrapper() {
		$this->core->getOutput()->renderOutput(array('admin', 'Wrapper'), 'displayPage');
	}
}