<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;

class GradeableController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'upload_config':
                $this->upload_config();
                break;
            case 'process_upload_config':
                $this->process_config_upload();
                break;
        }
    }

    public function upload_config() {
        $target_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "config_upload");
        $all_files = FileUtils::getAllFiles($target_dir);
        $this->core->getOutput()->renderOutput(array('admin', 'Gradeable'), 'uploadConfigForm', $target_dir, $all_files);
    }

    public function process_config_upload() {
        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            $this->core->addErrorMessage("Upload failed: Invalid CSRF token");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable',
                'action' => 'upload_config')));
        }

        if (empty($_FILES) || !isset($_FILES['config_upload'])) {
            $this->core->addErrorMessage("Upload failed: No file to upload");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable',
                'action' => 'upload_config')));
        }

        $upload = $_FILES['config_upload'];
        if (!isset($upload['tmp_name']) || $upload['tmp_name'] === "") {
            $this->core->addErrorMessage("Upload failed: Empty tmp name for file");
            $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable',
                'action' => 'upload_config')));
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
