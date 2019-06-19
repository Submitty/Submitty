<?php

namespace app\controllers\course;

use app\controllers\AbstractController;
use app\libraries\FileUtils;

class CourseMaterialsController extends AbstractController {
    public function run() {
        foreach (array('path', 'file') as $key) {
            if (isset($_REQUEST[$key])) {
                $_REQUEST[$key] = htmlspecialchars_decode(urldecode($_REQUEST[$key]));
            }
        }

        switch ($_REQUEST['action']) {
            case 'view_course_materials_page':
                $this->viewCourseMaterialsPage();
                break;
            case 'delete_course_material_file':
                $this->deleteCourseMaterialFile();
                break;
            case 'delete_course_material_folder':
                $this->deleteCourseMaterialFolder();
                break;
            case 'download_course_material_zip':
                $this->downloadCourseMaterialZip();
                break;
            case 'modify_course_materials_file_permission':
                $this->modifyCourseMaterialsFilePermission();
                break;
            case 'modify_course_materials_file_time_stamp':
                $this->modifyCourseMaterialsFileTimeStamp();
                break;
            default:
                $this->viewCourseMaterialsPage();
                break;
        }
    }

    public function viewCourseMaterialsPage() {
        $this->core->getOutput()->renderOutput(
            ['course', 'CourseMaterials'],
            'listCourseMaterials',
            $user_group = $this->core->getUser()->getGroup()
        );
    }

    public function deleteCourseMaterialFile() {
        $dir = "course_materials";
        $path = $this->core->getAccess()->resolveDirPath($dir, $_REQUEST["path"]);

        if (!$this->core->getAccess()->canI("path.write", ["path" => $path, "dir" => $dir])) {
            $message = "You do not have access to that page. ";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
                'page' => 'course_materials',
                'action' => 'view_course_materials_page')));
        }

        if ( unlink($path) )
        {
            $this->core->addSuccessMessage(basename($path) . " has been successfully removed.");
        }
        else{
            $this->core->addErrorMessage("Failed to remove " . basename($path));
        }

        // remove entry from json file
        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';

        $json = FileUtils::readJsonFile($fp);
        if ($json != false)
        {
            unset($json[$path]);

            if (file_put_contents($fp, FileUtils::encodeJson($json)) === false) {
                return "Failed to write to file {$fp}";
            }
        }

        //refresh course materials page
        $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
            'page' => 'course_materials',
            'action' => 'view_course_materials_page')));
    }

    private function deleteCourseMaterialFolder() {
        // security check
        $dir = "course_materials";
        $path = $this->core->getAccess()->resolveDirPath($dir, $_REQUEST["path"]);

        if (!$this->core->getAccess()->canI("path.write", ["path" => $path, "dir" => $dir])) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
                'page' => 'course_materials',
                'action' => 'view_course_materials_page')));
        }

        // remove entry from json file
        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';
        $json = FileUtils::readJsonFile($fp);

        if ($json != false)
        {
            $all_files = FileUtils::getAllFiles($path);
            foreach($all_files as $file){
                $filename = $file['path'];
                unset($json[$filename]);
            }

            file_put_contents($fp, FileUtils::encodeJson($json));
        }

        if ( FileUtils::recursiveRmdir($path) )
        {
            $this->core->addSuccessMessage(basename($path) . " has been successfully removed.");
        }
        else{
            $this->core->addErrorMessage("Failed to remove " . basename($path));
        }

        //refresh course materials page
        $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
            'page' => 'course_materials',
            'action' => 'view_course_materials_page')));
    }

    private function downloadCourseMaterialZip() {
        $dir_name = $_REQUEST["dir_name"];
        $root_path = realpath($_REQUEST["path"]);

        // check if the user has access to course materials
        if (!$this->core->getAccess()->canI("path.read", ["dir" => 'course_materials', "path" => $root_path])) {
            $this->core->getOutput()->showError("You do not have access to this folder");
            return false;
        }

        $temp_dir = "/tmp";
        // makes a random zip file name on the server
        $temp_name = uniqid($this->core->getUser()->getId(), true);
        $zip_name = $temp_dir . "/" . $temp_name . ".zip";
        $zip_file_name = preg_replace('/\s+/', '_', $dir_name) . ".zip";

        $zip = new \ZipArchive();
        $zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if(!$this->core->getUser()->accessGrading()) {
            // if the user is not the instructor
            // download all accessible files according to course_materials_file_data.json
            $file_data = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';
            $json = FileUtils::readJsonFile($file_data);
            foreach ($json as $path => $file) {
                // check if the file is in the requested folder
                if (!Utils::startsWith(realpath($path), $root_path)) {
                    continue;
                }
                if ($file['checked'] === '1' &&
                    $file['release_datetime'] < $this->core->getDateTimeNow()->format("Y-m-d H:i:sO")) {
                    $relative_path = substr($path, strlen($root_path) + 1);
                    $zip->addFile($path, $relative_path);
                }
            }
        }
        else {
            // if the user is an instructor
            // download all files
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root_path),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $file_path = $file->getRealPath();
                    $relativePath = substr($file_path, strlen($root_path) + 1);

                    $zip->addFile($file_path, $relativePath);
                }
            }
        }

        $zip->close();
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=$zip_file_name");
        header("Content-length: " . filesize($zip_name));
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile("$zip_name");
        unlink($zip_name); //deletes the random zip file
    }

    public function modifyCourseMaterialsFilePermission() {

        // security check
        if(!$this->core->getUser()->accessAdmin()) {
            $message = "You do not have access to that page. ";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
                'page' => 'course_materials',
                'action' => 'view_course_materials_page')));
            return;
        }

        if (!isset($_REQUEST['filename']) ||
            !isset($_REQUEST['checked'])) {
            return;
        }

        $file_name = htmlspecialchars($_REQUEST['filename']);
        $checked =  $_REQUEST['checked'];

        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';

        $release_datetime = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO");
        $json = FileUtils::readJsonFile($fp);
        if ($json != false) {
            $release_datetime  = $json[$file_name]['release_datetime'];
        }

        if (!isset($release_datetime))
        {
            $release_datetime = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO");
        }

        $json[$file_name] = array('checked' => $checked, 'release_datetime' => $release_datetime);

        if (file_put_contents($fp, FileUtils::encodeJson($json)) === false) {
            return "Failed to write to file {$fp}";
        }
    }

    public function modifyCourseMaterialsFileTimeStamp() {

        if(!$this->core->getUser()->accessAdmin()) {
            $message = "You do not have access to that page. ";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
                'page' => 'course_materials',
                'action' => 'view_course_materials_page')));
            return;
        }

        if (!isset($_REQUEST['filename']) ||
            !isset($_REQUEST['newdatatime'])) {
            return;
        }

        $file_name = htmlspecialchars($_REQUEST['filename']);
        $new_data_time = htmlspecialchars($_REQUEST['newdatatime']);

        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';

        $checked = '0';
        $json = FileUtils::readJsonFile($fp);
        if ($json != false) {
            $checked  = $json[$file_name]['checked'];
        }

        $json[$file_name] = array('checked' => $checked, 'release_datetime' => $new_data_time);

        if (file_put_contents($fp, FileUtils::encodeJson($json)) === false) {
            return "Failed to write to file {$fp}";
        }
    }
}
