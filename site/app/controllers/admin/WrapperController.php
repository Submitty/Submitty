<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use phpDocumentor\Reflection\File;

class WrapperController extends AbstractController {
	public function run() {
		switch($_REQUEST['action']) {
            case 'process_upload_html':
                $this->processUploadHTML();
			case 'show_page':
			default:
				$this->uploadWrapperPage();
		}
	}

	private function uploadWrapperPage() {
	    $target_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'site');
	    $all_files = FileUtils::getAllFiles($target_dir);

		$this->core->getOutput()->renderOutput(array('admin', 'Wrapper'), 'displayPage');
	}

	private function processUploadHTML() {
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            $this->core->addErrorMessage("Upload failed: Invalid CSRF token");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
                'action' => 'show_page')));
        }

        if (empty($_FILES) || !isset($_FILES['wrapper_upload'])) {
            $this->core->addErrorMessage("Upload failed: No file to upload");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
                'action' => 'show_page')));
        }

        $target_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'site');

    }

}