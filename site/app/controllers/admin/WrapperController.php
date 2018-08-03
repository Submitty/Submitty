<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\Utils;

class WrapperController extends AbstractController {

    const WRAPPER_FILES = [
        'top_bar.html',
        'left_sidebar.html',
        'right_sidebar.html',
        'bottom_bar.html',
        'override.css'
    ];

    public function run() {
        if (!$this->core->getAccess()->canI("admin.wrapper")) {
            $this->core->getOutput()->showError("You do not have permission to do this.");
        }
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
        $filename = $_REQUEST['location'];
        $location = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'site', $filename);

        if (!$this->core->getAccess()->canI("path.write.site", ["dir" => "site", "path" => $location])) {
            $this->core->getOutput()->showError("You do not have permission to do this.");
        }

        if (empty($_FILES) || !isset($_FILES['wrapper_upload'])) {
            $this->core->addErrorMessage("Upload failed: No file to upload");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
                'action' => 'show_page')));
        }
        $upload = $_FILES['wrapper_upload'];

        if(!isset($_REQUEST['location']) || !in_array($_REQUEST['location'], WrapperController::WRAPPER_FILES)) {
            $this->core->addErrorMessage("Upload failed: Invalid location");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
                'action' => 'show_page')));
        }

        if (!@copy($upload['tmp_name'], $location)) {
            $this->core->addErrorMessage("Upload failed: Could not copy file");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
                'action' => 'show_page')));
        }

        $this->core->addSuccessMessage("Uploaded ".$upload['name']." as ".$filename);
        $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
            'action' => 'show_page')));
    }

    private function deleteUploadedHTML() {
        $filename = $_REQUEST['location'];
        $location = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'site', $filename);

        if (!$this->core->getAccess()->canI("path.write.site", ["dir" => "site", "path" => $location])) {
            $this->core->getOutput()->showError("You do not have permission to do this.");
        }

        if(!isset($_REQUEST['location']) || !in_array($_REQUEST['location'], WrapperController::WRAPPER_FILES)) {
            $this->core->addErrorMessage("Delete failed: Invalid filename");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
                'action' => 'show_page')));
        }
        if(!@unlink($location)) {
            $this->core->addErrorMessage("Deletion failed: Could not unlink file");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
                'action' => 'show_page')));
        }

        $this->core->addSuccessMessage("Deleted ".$filename);
        $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'wrapper',
            'action' => 'show_page')));
    }

}