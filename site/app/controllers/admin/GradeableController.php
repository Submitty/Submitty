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
			case 'delete_config':
			    $this->delete_config();
				break;
            case 'rename_config':
                $this->rename_config();
                break;
            case 'check_being_used':
                $this->configUsedBy();
                break;
        }
    }

    public function upload_config() {
        $target_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "config_upload");
        $all_files = FileUtils::getAllFiles($target_dir);
        $all_paths = array();
        foreach($all_files as $file){
            $all_paths[] = $file['path'];
        }
        $inuse_config = array();
        foreach($this->core->getQueries()->getGradeableConfigs(null) as $gradeable){
            foreach($all_paths as $path){
                if(strpos($gradeable->getAutogradingConfigPath(), $path) !== false){
                    $inuse_config[] = $path;
                }
            }
        }
        $this->core->getOutput()->renderOutput(array('admin', 'Gradeable'), 'uploadConfigForm', $target_dir, $all_files, $inuse_config);
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
        $counter = count(scandir($target_dir))-1;
        $try_dir = FileUtils::joinPaths($target_dir, $counter);
        while(is_dir($try_dir)){
            $counter++;
            $try_dir = FileUtils::joinPaths($target_dir, $counter);
        }
        $target_dir = $try_dir;
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

    public function rename_config(){
        $config_file_path = $_POST['curr_config_name'] ?? null;
        if($config_file_path == null){
            $this->core->addErrorMessage("Unable to find file");

        } else if (strpos($config_file_path, FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "config_upload")) === false){
            $this->core->addErrorMessage("This action can't be completed.");
        } else {
            $new_name = $_POST['new_config_name'] ?? "";
            if ($new_name === "") {
                $this->core->addErrorMessage("Could not rename upload because no name was entered.");
            }
            else if (!ctype_alnum(str_replace(['_','-'], '', $new_name))) {
                $this->core->addErrorMessage("Name can only contain alphanumeric characters, dashes, and underscores.");
            }
            else {
                $new_dir = FileUtils::joinPaths(dirname($config_file_path, 1), $new_name);
                if(rename($config_file_path, $new_dir)){
                    $this->core->addSuccessMessage("Successfully renamed file");
                } else {
                    $this->core->addErrorMessage("Directory already exist, please choose another name.");
                }
            }
        }
        $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable',
            'action' => 'upload_config')));
    }

    public function delete_config(){
        $config_path = $_GET['config'] ?? null;
        $in_use = false;
        foreach($this->core->getQueries()->getGradeableConfigs(null) as $gradeable){
            if(strpos($gradeable->getAutogradingConfigPath(), $config_path) !== false){
                $in_use = true;
                break;
            }
        }
        if ($config_path == null) {
            $this->core->addErrorMessage("Selecting config failed.");
        } else if (strpos($config_path, FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "config_upload")) === false){
            $this->core->addErrorMessage("This action can't be completed.");
        } else if ($in_use){
            $this->core->addErrorMessage("This config is currently in use.");
        } else {
            if($this->recursive_rmdir($config_path)){
                $this->core->addSuccessMessage("The config folder has been succesfully deleted");
            } else {
                $this->core->addErrorMessage("Deleting config failed.");
            }
        }
        $this->core->redirect($this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable',
            'action' => 'upload_config')));
    }

    private function configUsedBy() {
        // Returns a list of gradeables that are using this config
        $config_path = $_GET['config'] ?? null;
        if (!$config_path == null) {
            $inuse_config = array();
            foreach ($this->core->getQueries()->getGradeableConfigs(null) as $gradeable) {
                if (strpos($gradeable->getAutogradingConfigPath(), $config_path) !== false) {
                    $inuse_config[] = $gradeable->getId();
                }
            }
            $this->core->getOutput()->renderJsonSuccess($inuse_config);
        }
    }

    /**
     * @param $dir directory to remove
     *
     * This function is the same as rmdir, except it removes all the content inside as well.
     *
     * @return bool whether or not the dir is removed
     */
    private function recursive_rmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir."/".$object))
                        $this->recursive_rmdir($dir."/".$object);
                    else
                        unlink($dir."/".$object);
                }
            }
            if(!rmdir($dir)) return false;
        }
        return true;
    }
}
