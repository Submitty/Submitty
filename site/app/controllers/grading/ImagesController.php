<?php

namespace app\controllers\grading;

use app\controllers\AbstractController;
use app\libraries\FileUtils;

class ImagesController extends AbstractController {
	public function run() {
        switch ($_REQUEST['action']) {
            case 'view_images_page':
            		$this->view_images_page();
                break;
            default:
                $this->viewPage();
                break;
        }
    }

		public function view_images_page() {
				$target_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "config_upload");
				$all_files = FileUtils::getAllFiles($target_dir);
				//$sections = $this->core->getUser()->getGradingRegistrationSections();
				//$students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
				$students = $this->core->getQueries()->getAllUsers();
				$this->core->getOutput()->renderOutput(array('grading', 'Images'), 'listStudentImages', $students, $target_dir, $all_files);			}

		public function process_images_upload() {
				if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
						$this->core->addErrorMessage("Upload failed: Invalid CSRF token");
						$this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'images',
								'action' => 'view_images_page')));
				}

				if (empty($_FILES) || !isset($_FILES['config_upload'])) {
						$this->core->addErrorMessage("Upload failed: No file to upload");
						$this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'images',
								'action' => 'view_images_page')));
				}

				$upload = $_FILES['config_upload'];
				if (!isset($upload['tmp_name']) || $upload['tmp_name'] === "") {
						$this->core->addErrorMessage("Upload failed: Empty tmp name for file");
						$this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'images',
								'action' => 'view_images_page')));
				}

				$target_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "config_upload");
				$target_dir = FileUtils::joinPaths($target_dir, count(scandir($target_dir))-1);
				FileUtils::createDir($target_dir);

				if (FileUtils::getMimeType($upload["tmp_name"]) == "application/zip") {
						$zip = new \ZipArchive();
						$res = $zip->open($upload['tmp_name']);
						if ($res === true) {
								$zip->extractTo($target_dir);
								$zip->close();
						}
						else {
								FileUtils::recursiveRmdir($target_dir);
								$error_message = ($res == 19) ? "Invalid or uninitialized Zip object" : $zip->getStatusString();
								$this->core->addErrorMessage("Upload failed: {$error_message}");
								$this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable',
										'action' => 'upload_config')));
						}
				}
				else {
						if (!@copy($upload['tmp_name'], FileUtils::joinPaths($target_dir, $upload['name']))) {
								FileUtils::recursiveRmdir($target_dir);
								$this->core->addErrorMessage("Upload failed: Could not copy file");
								$this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable',
										'action' => 'upload_config')));
						}
				}
				$this->core->addSuccessMessage("Gradeable config uploaded");
				$this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable',
						'action' => 'upload_config')));
		}
}
