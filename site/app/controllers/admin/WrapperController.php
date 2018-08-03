<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\Utils;

class WrapperController extends AbstractController {

    const WRAPPER_FILES = [
        'top_bar',
        'left_sidebar',
        'right_sidebar',
        'bottom_bar'
    ];

	public function run() {
		switch($_REQUEST['action']) {
            case 'process_upload_html':
                $this->processUploadHTML();
                break;
            case 'delete_uploaded_html':
                $this->deleteUploadedHTML();
                break;
			case 'show_page':
			default:
				$this->uploadWrapperPage();
				break;
		}
	}

	private function uploadWrapperPage() {
		$this->core->getOutput()->renderOutput(array('admin', 'Wrapper'), 'displayPage', $this->core->getConfig()->getWrapperFiles());
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
        $upload = $_FILES['wrapper_upload'];

        if(!Utils::endsWith($upload['name'],'.html')) {
            $this->core->addErrorMessage("Upload failed: Invalid file: Not an html file");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
                'action' => 'show_page')));
        }

        if(!isset($_POST['location']) || !in_array($_POST['location'], WrapperController::WRAPPER_FILES)) {
            $this->core->addErrorMessage("Upload failed: Invalid location");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
                'action' => 'show_page')));
        }
        $filename = $_POST['location'].'.html';

        if (!@copy($upload['tmp_name'], FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'site', $filename))) {
            $this->core->addErrorMessage("Upload failed: Could not copy file");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
                'action' => 'show_page')));
        }

        $this->core->addSuccessMessage("Uploaded ".$upload['name']." as ".$filename);
        $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
            'action' => 'show_page')));
    }

    private function deleteUploadedHTML() {

        if(!isset($_POST['location']) || !in_array($_POST['location'], WrapperController::WRAPPER_FILES)) {
            $this->core->addErrorMessage("Upload failed: Invalid filename");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
                'action' => 'show_page')));
        }
        $filename = $_REQUEST['filename'].'.html';

	    $filepath = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'site', $filename);

	    if(!@unlink($filepath)) {
	        $this->core->addErrorMessage("Deletion failed: Could not unlink file");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
                'action' => 'show_page')));
        }

        $this->core->addSuccessMessage("Deleted ".$filename);
        $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
            'action' => 'show_page')));
    }

}