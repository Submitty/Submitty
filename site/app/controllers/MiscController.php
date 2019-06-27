<?php

namespace app\controllers;


use app\libraries\FileUtils;
use app\libraries\Utils;
use app\libraries\Core;

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
            case 'read_file':
                $this->readFile();
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
            case 'download_course_material_zip':
                $this->downloadCourseMaterialZip();
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
            case 'check_bulk_progress':
                $this->checkBulkProgress();
                break;
        }
    }

    private function encodePDF(){
        $gradeable_id = $_POST['gradeable_id'] ?? NULL;
        $id = $_POST['user_id'] ?? NULL;
        $file_name = $_POST['filename'] ?? NULL;
        $file_name = html_entity_decode($file_name);
        $gradeable = $this->tryGetGradeable($gradeable_id);
        $submitter = $this->core->getQueries()->getSubmitterById($id);
        $graded_gradeable = $this->core->getQueries()->getGradedGradeableForSubmitter($gradeable, $submitter);
        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();


        $dir = "submissions";
        $path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), $dir, $gradeable_id, $id, $active_version, $file_name);

        //See if we are allowed to access this path
        $path = $this->core->getAccess()->resolveDirPath($dir, $path);
        if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path, "gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
            $this->core->getOutput()->renderJsonError("You do not have access to this file");
            return false;
        }

        $pdf64 = base64_encode(file_get_contents($path));
        $this->core->getOutput()->renderJson($pdf64);
    }

    private function displayFile() {
        //Is this per-gradeable?
        $dir = $_REQUEST["dir"];
        $path = $this->core->getAccess()->resolveDirPath($dir, $_REQUEST["path"]);

        if (array_key_exists('gradeable_id', $_REQUEST)) {
            $gradeable = $this->tryGetGradeable($_REQUEST['gradeable_id'], false);
            if ($gradeable === false) {
                return false;
            }
            $graded_gradeable =  $this->tryGetGradedGradeable($gradeable, $_REQUEST['user_id'], false);
            if ($graded_gradeable === false) {
                return false;
            }
            if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path, "gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
                $this->core->getOutput()->showError("You do not have access to this file");
                return false;
            }
        } else {
            if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path])) {
                $this->core->getOutput()->showError("You do not have access to this file");
                return false;
            }
        }
        $file_name = basename(rawurldecode(htmlspecialchars_decode($path)));
        $corrected_name = pathinfo($path, PATHINFO_DIRNAME) . "/" .  $file_name;
        $mime_type = FileUtils::getMimeType($corrected_name);
        $file_type = FileUtils::getContentType($file_name);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        if ($mime_type === "application/pdf" || Utils::startsWith($mime_type, "image/")) {
            header("Content-type: ".$mime_type);
            header('Content-Disposition: inline; filename="' . $file_name . '"');
            readfile($corrected_name);
            $this->core->getOutput()->renderString($path);
        }
        else {
            $contents = file_get_contents($corrected_name);
            if (array_key_exists('ta_grading', $_REQUEST) && $_REQUEST['ta_grading'] === "true") {
                $this->core->getOutput()->renderOutput('Misc', 'displayCode', $file_type, $corrected_name, $contents);
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

    private function readFile() {
        $dir = $_REQUEST["dir"];
        $path = $this->core->getAccess()->resolveDirPath($dir, $_REQUEST["path"]);

        // security check
        if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path])) {
            $this->core->getOutput()->showError("You do not have access to this file");
            return false;
        }

        //Since this can serve raw html files we should make sure they're coming from a valid source
        if (!array_key_exists("csrf_token", $_REQUEST) || !$this->core->checkCsrfToken($_REQUEST["csrf_token"])) {
            $this->core->getOutput()->showError("Invalid csrf token");
            return false;
        }

        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        readfile($path);
    }

    private function downloadFile($download_with_any_role = false) {
        // security check
        $dir = $_REQUEST["dir"];
        $path = $this->core->getAccess()->resolveDirPath($dir, $_REQUEST["path"]);

        if (array_key_exists('gradeable_id', $_REQUEST)) {
            $gradeable = $this->tryGetGradeable($_REQUEST['gradeable_id']);
            if ($gradeable === false) {
                return false;
            }
            $graded_gradeable = $this->tryGetGradedGradeable($gradeable, $_REQUEST['user_id']);
            if ($graded_gradeable === false) {
                return false;
            }
            if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path, "gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable])) {
                $this->core->getOutput()->showError("You do not have access to this file");
                return false;
            }
        } else {
            if (!$this->core->getAccess()->canI("path.read", ["dir" => $dir, "path" => $path])) {
                $this->core->getOutput()->showError("You do not have access to this file");
                return false;
            }
        }

        $filename = pathinfo($path, PATHINFO_BASENAME);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"{$filename}\"");
        readfile($path);
    }

    private function downloadZip() {
        $gradeable = $this->core->getQueries()->getGradeableConfig($_REQUEST['gradeable_id']);
        if ($gradeable === null) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildNewCourseUrl());
        }

        $graded_gradeable = $this->core->getQueries()->getGradedGradeable($gradeable, $_REQUEST['user_id'], null);

        if ($graded_gradeable === null) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildNewCourseUrl());
        }

        $gradeable_version = $graded_gradeable->getAutoGradedGradeable()->getAutoGradedVersionInstance($_REQUEST["version"]);

        if ($gradeable_version === null) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildNewCourseUrl());
        }

        $folder_names = array();
        //See which directories we are allowed to read.
        if ($this->core->getAccess()->canI("path.read.submissions", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "gradeable_version" => $gradeable_version->getVersion()])) {
            //These two have the same check
            $folder_names[] = "submissions";
            $folder_names[] = "checkout";
        }
        if ($this->core->getAccess()->canI("path.read.results", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "gradeable_version" => $gradeable_version->getVersion()])) {
            $folder_names[] = "results";
        }
        if ($this->core->getAccess()->canI("path.read.results_public", ["gradeable" => $gradeable, "graded_gradeable" => $graded_gradeable, "gradeable_version" => $gradeable_version->getVersion()])) {
            $folder_names[] = "results_public";
        }
        //No results, no download
        if (count($folder_names) === 0) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildNewCourseUrl());
        }

        $zip_file_name = $_REQUEST['gradeable_id'] . "_" . $_REQUEST['user_id'] . "_" . date("m-d-Y") . ".zip";
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        $temp_dir = "/tmp";
        //makes a random zip file name on the server
        $temp_name = uniqid($this->core->getUser()->getId(), true);
        $zip_name = $temp_dir . "/" . $temp_name . ".zip";
        $gradeable_path = $this->core->getConfig()->getCoursePath();
        $active_version = $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
        $version = isset($_REQUEST['version']) ? $_REQUEST['version'] : $active_version;

        $paths = [];
        foreach ($folder_names as $folder_name) {
            $paths[] = FileUtils::joinPaths($gradeable_path, $folder_name, $gradeable->getId(), $graded_gradeable->getSubmitter()->getId(), $version);
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
            $this->core->redirect($this->core->buildNewCourseUrl());
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
                $this->core->redirect($this->core->buildNewCourseUrl());
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
        $gradeable = $this->tryGetGradeable($_REQUEST['gradeable_id']);
        if ($gradeable === false) {
            return;
        }
        $paths = ['submissions'];
        if ($gradeable->isVcs()) {
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
                if (file_exists($gradeable_path)) {
                    if (!is_dir($gradeable_path)) { //if dir is already present, but it's a file
                        $message = "Oops! That page is not available.";
                        $this->core->addErrorMessage($message);
                        $this->core->redirect($this->core->buildNewCourseUrl());
                    }
                    else{
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
                    }
                } else { //no dir exists with this name
                    $message = "Oops! That page is not available.";
                    $this->core->addErrorMessage($message);
                    $this->core->redirect($this->core->buildNewCourseUrl());
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
                    $sections = $this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(),
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

    public function checkBulkProgress(){
        $gradeable_id = $_POST['gradeable_id'] ?? NULL;
        if($gradeable_id === NULL){
            $result = ['error' => "gradeable_id cannot be null"];
            $this->core->getOutput()->renderJson($result);
            return $this->core->getOutput()->getOutput();
        }

        $job_path = "/var/local/submitty/daemon_job_queue/";
        $result = [];
        $found = false;
        $job_data = NULL;
        $complete_count = 0;
        try{
            foreach(scandir($job_path) as $job){
                if(strpos($job, 'bulk_upload_') !== false)
                    $found = true;
                else
                    continue;
                //remove 'bulk_upload_' and '.json' from job file name
                $result[] = substr($job,11,-5);
            }
            //look in the split upload folder to see what is complete
            $split_uploads = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "split_pdf", $_POST['gradeable_id']);
            $sub_dirs = array_filter(glob($split_uploads . '/*'), 'is_dir');
            foreach ($sub_dirs as $dir) {
                foreach (scandir($dir) as $file) {
                    if(pathinfo($file)['extension'] !== "pdf")
                        continue;

                    if(strpos($file, "_cover"))
                        $complete_count++;
                }
            }
            $result = ['success' => true, 'found' => $found, 'job_data' => $result, 'count' => $complete_count];
        }catch(Exception $e){
            $result = ['error' => $e->getMessage()];
        }
        $this->core->getOutput()->renderJson($result);
        return $this->core->getOutput()->getOutput();
    }
}
