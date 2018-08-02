<?php

namespace app\controllers;


use app\libraries\FileUtils;
use app\libraries\Utils;

class MiscController extends AbstractController {
    public function run() {
        foreach (array('path', 'file') as $key) {
            if (isset($_REQUEST[$key])) {
                $_REQUEST[$key] = htmlspecialchars_decode(urldecode($_REQUEST[$key]));
            }
        }

        switch($_REQUEST['page']) {
            case 'display_file':
                $this->displayFile();
                break;
            case 'download_file':
                $this->downloadFile();
                break;
            case 'download_file_with_any_role':
                $this->downloadFile(true);
                break;
            case 'delete_course_material_file':
                $this->deleteCourseMaterialFile();
                break;
            case 'delete_course_material_folder':
                $this->deleteCourseMaterialFolder();
                break;
            case 'download_zip':
                $this->downloadZip();
                break;
            case 'download_all_assigned':
                $this->downloadAssignedZips();
                break;
            case 'base64_encode_pdf':
                $this->encodePDF();
                break;
            case 'modify_course_materials_file_permission':
                $this->modifyCourseMaterialsFilePermission();
                break;
            case 'modify_course_materials_file_time_stamp':
                $this->modifyCourseMaterialsFileTimeStamp();
                break;
        }
    }

    private function encodePDF(){
        $gradeable_id = $_POST['gradeable_id'] ?? NULL;
        $user_id = $_POST['user_id'] ?? NULL;
        $file_name = $_POST['filename'] ?? NULL;
        $active_version = $this->core->getQueries()->getGradeable($gradeable_id, $user_id)->getActiveVersion();
        $pdf64 = base64_encode(file_get_contents(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'submissions', $gradeable_id, $user_id, $active_version, $file_name)));
        return $this->core->getOutput()->renderJson($pdf64);
    }

    private function displayFile() {
        //Is this per-gradeable?
        $dir = $_REQUEST["dir"];
        $path = $this->core->getAccess()->resolveDirPath($dir, $_REQUEST["path"]);

        if (array_key_exists('gradeable_id', $_REQUEST)) {
            $gradeable = $this->core->getQueries()->getGradeable($_REQUEST['gradeable_id'], $_REQUEST['user_id']);
            if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path, "gradeable" => $gradeable])) {
                $this->core->getOutput()->showError("You do not have access to this file");
                return false;
            }
        } else {
            if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path])) {
                $this->core->getOutput()->showError("You do not have access to this file");
                return false;
            }
        }

        $corrected_name = pathinfo($path, PATHINFO_DIRNAME) . "/" .  basename(rawurldecode(htmlspecialchars_decode($path)));
        $mime_type = FileUtils::getMimeType($corrected_name);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        if ($mime_type === "application/pdf" || Utils::startsWith($mime_type, "image/")) {
            header("Content-type: ".$mime_type);
            header('Content-Disposition: inline; filename="' . basename(rawurldecode(htmlspecialchars_decode($path))) . '"');
            readfile($corrected_name);
            $this->core->getOutput()->renderString($path);
        }
        else {
            $contents = file_get_contents($corrected_name);
            if (array_key_exists('ta_grading', $_REQUEST) && $_REQUEST['ta_grading'] === "true") {
                $this->core->getOutput()->renderOutput('Misc', 'displayCode', $mime_type, $corrected_name, $contents);
            }
            else {
                $this->core->getOutput()->renderOutput('Misc', 'displayFile', $contents);
            }
        }
    }

    private function deleteCourseMaterialFile() {
        $dir = "course_materials";
        $path = $this->core->getAccess()->resolveDirPath($dir, $_REQUEST["path"]);

        if (!$this->core->getAccess()->canI("path.write", ["path" => $path, "dir" => $dir])) {
            $message = "You do not have access to that page. ";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildUrl(array('component' => 'grading',
                                                    'page' => 'course_materials',
                                                    'action' => 'view_course_materials_page')));
        }

        // delete the file from upload/course_materials
        // $filename = (pathinfo($_REQUEST['path'], PATHINFO_DIRNAME) . "/" . basename(rawurldecode(htmlspecialchars_decode($_REQUEST['path']))));

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

    private function downloadFile($download_with_any_role = false) {
        // security check
        $dir = $_REQUEST["dir"];
        $path = $this->core->getAccess()->resolveDirPath($dir, $_REQUEST["path"]);

        if (array_key_exists('gradeable_id', $_REQUEST)) {
            $gradeable = $this->core->getQueries()->getGradeable($_REQUEST['gradeable_id'], $_REQUEST['user_id']);
            if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path, "gradeable" => $gradeable])) {
                $this->core->getOutput()->showError("You do not have access to this file");
                return false;
            }
        } else {
            if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path])) {
                $this->core->getOutput()->showError("You do not have access to this file");
                return false;
            }
        }

        $filename = rawurldecode(htmlspecialchars_decode($_REQUEST['file']));
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"{$filename}\"");
        readfile(pathinfo($_REQUEST['path'], PATHINFO_DIRNAME) . "/" . basename(rawurldecode(htmlspecialchars_decode($_REQUEST['path']))));
    }

    private function downloadZip() {
        $gradeable = $this->core->getQueries()->getGradeable($_REQUEST['gradeable_id'], $_REQUEST['user_id']);

        $folder_names = array();
        //See which directories we are allowed to read.
        if ($this->core->getAccess()->canI("path.read.submissions", ["gradeable" => $gradeable])) {
            //These two have the same check
            $folder_names[] = "submissions";
            $folder_names[] = "checkout";
        }
        if ($this->core->getAccess()->canI("path.read.results", ["gradeable" => $gradeable])) {
            $folder_names[] = "results";
        }
        //No results, no download
        if (count($folder_names) === 0) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $zip_file_name = $_REQUEST['gradeable_id'] . "_" . $_REQUEST['user_id'] . "_" . date("m-d-Y") . ".zip";
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        $temp_dir = "/tmp";
        //makes a random zip file name on the server
        $temp_name = uniqid($this->core->getUser()->getId(), true);
        $zip_name = $temp_dir . "/" . $temp_name . ".zip";
        $gradeable_path = $this->core->getConfig()->getCoursePath();
        $active_version = $gradeable->getActiveVersion();
        $version = isset($_REQUEST['version']) ? $_REQUEST['version'] : $active_version;

        $paths = [];
        foreach ($folder_names as $folder_name) {
            $paths[] = FileUtils::joinPaths($gradeable_path, $folder_name, $gradeable->getId(), $gradeable->getUser()->getId(), $version);
        }
        $zip = new \ZipArchive();
        $zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        for ($x = 0; $x < count($paths); $x++) {
            if (is_dir($paths[$x])) {
                    $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($paths[$x]),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                $zip -> addEmptyDir($folder_names[$x]);
                foreach ($files as $name => $file)
                {
                    // Skip directories (they would be added automatically)
                    if (!$file->isDir())
                    {
                        // Get real and relative path for current file
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($paths[$x]) + 1);

                        // Add current file to archive
                        $zip->addFile($filePath, $folder_names[$x] . "/" . $relativePath);
                    }
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

    private function downloadAssignedZips() {
        // security check
        if (!($this->core->getUser()->accessGrading())) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }

        $zip_file_name = $_REQUEST['gradeable_id'] . "_section_students_" . date("m-d-Y") . ".zip";
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        if(isset($_REQUEST['type']) && $_REQUEST['type'] === "All") {
            $type = "all";
            $zip_file_name = $_REQUEST['gradeable_id'] . "_all_students_" . date("m-d-Y") . ".zip";
            if (!($this->core->getUser()->accessFullGrading())) {
                $message = "You do not have access to that page.";
                $this->core->addErrorMessage($message);
                $this->core->redirect($this->core->getConfig()->getSiteUrl());
            }
        }
        else
        {
            $type = "";
        }

        $temp_dir = "/tmp";
        //makes a random zip file name on the server
        $temp_name = uniqid($this->core->getUser()->getId(), true);
        $zip_name = $temp_dir . "/" . $temp_name . ".zip";
        $gradeable = $this->core->getQueries()->getGradeable($_REQUEST['gradeable_id']);
        $paths = ['submissions'];
        if ($gradeable->useVcsCheckout()) {
            //VCS submissions are stored in the checkout directory
            $paths[] = 'checkout';
        }
        $zip = new \ZipArchive();
        $zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($paths as $path) {
            $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), $path,
                $gradeable->getId());
            if($type === "all") {
                $zip->addEmptyDir($path);
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($gradeable_path),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $name => $file)
                {
                    // Skip directories (they would be added automatically)
                    if (!$file->isDir())
                    {
                        // Get real and relative path for current file
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($gradeable_path) + 1);
                        // Add current file to archive
                        $zip->addFile($filePath, $path . "/" . $relativePath);
                    }
                }
           } else {
               //gets the students that are part of the sections
               if ($gradeable->isGradeByRegistration()) {
                   $section_key = "registration_section";
                   $sections = $this->core->getUser()->getGradingRegistrationSections();
                   $students = $this->core->getQueries()->getUsersByRegistrationSections($sections);
               }
               else {
                   $section_key = "rotating_section";
                   $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable_id,
                       $this->core->getUser()->getId());
                   $students = $this->core->getQueries()->getUsersByRotatingSections($sections);
               }
               $students_array = array();
               foreach($students as $student) {
                   $students_array[] = $student->getId();
               }
               $files = scandir($gradeable_path);
               $arr_length = count($students_array);
               foreach($files as $file) {
                   for ($x = 0; $x < $arr_length; $x++) {
                       if ($students_array[$x] === $file) {
                           $temp_path = $gradeable_path . "/" . $file;
                           $files_in_folder = new \RecursiveIteratorIterator(
                               new \RecursiveDirectoryIterator($temp_path),
                               \RecursiveIteratorIterator::LEAVES_ONLY
                           );

                           //makes a new directory in the zip to add the files in
                           $zip -> addEmptyDir($file);

                           foreach ($files_in_folder as $name => $file_in_folder)
                           {
                               // Skip directories (they would be added automatically)
                               if (!$file_in_folder->isDir())
                               {
                                   // Get real and relative path for current file
                                   $filePath = $file_in_folder->getRealPath();
                                   $relativePath = substr($filePath, strlen($temp_path) + 1);
                                   // Add current file to archive
                                   $zip->addFile($filePath, $file . "/" . $relativePath);
                               }
                           }
                           $x = $arr_length; //cuts the for loop early when found
                        }
                    }
                }
            }
        }
        // Zip archive will be created only after closing object
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
        if($this->core->getUser()->getGroup() !== 1) {
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

        $release_datetime = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO");
        $json = FileUtils::readJsonFile($fp);
        if ($json != false) {
            $release_datetime  = $json[$file_name]['release_datetime'];
        }

        if (!isset($release_datetime))
        {
            $release_datetime = (new \DateTime('now', $this->core->getConfig()->getTimezone()))->format("Y-m-d H:i:sO");
        }

        $json[$file_name] = array('checked' => $checked, 'release_datetime' => $release_datetime);

        if (file_put_contents($fp, FileUtils::encodeJson($json)) === false) {
            return "Failed to write to file {$fp}";
        }
    }

    public function modifyCourseMaterialsFileTimeStamp() {

        if($this->core->getUser()->getGroup() !== 1) {
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
