<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\Utils;

class WrapperController extends AbstractController {
	public function run() {
		switch($_REQUEST['action']) {
            case 'process_upload_html':
                $this->processUploadHTML();
            case 'delete_uploaded_html':
                $this->deleteUploadedHTML();
			case 'show_page':
			default:
				$this->uploadWrapperPage();
		}
	}

	private function uploadWrapperPage() {
	    $target_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'site');
	    $all_files = FileUtils::getAllFiles($target_dir);
	    $html_files = array();
	    foreach($all_files as $file) {
	        if($file['name'] === 'upper-left.html') {
	            $html_files['up_left_file'] = $file;
            }
            else if($file['name'] === 'upper-right.html') {
	            $html_files['up_right_file'] = $file;
            }
            else if($file['name'] === 'lower-left.html') {
	            $html_files['low_left_file'] = $file;
            }
        }


		$this->core->getOutput()->renderOutput(array('admin', 'Wrapper'), 'displayPage', $target_dir, $html_files);
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

        if(!isset($_POST['location']) && ($_POST['location'] !== 'upper-left' || $_POST['location'] !== 'upper-right' || $_POST['location'] !== 'lower-left')) {
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

        if(!isset($_REQUEST['filename']) && ($_REQUEST['filename'] !== 'upper-left' || $_REQUEST['filename'] !== 'upper-right' || $_REQUEST['filename'] !== 'lower-left')) {
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