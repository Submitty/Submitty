<?php

namespace app\controllers\course;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\libraries\ErrorMessages;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;

class CourseMaterialsController extends AbstractController {
    /**
     * @Route("/{_semester}/{_course}/course_materials")
     */
    public function viewCourseMaterialsPage() {
        $this->core->getOutput()->renderOutput(
            ['course', 'CourseMaterials'],
            'listCourseMaterials',
            $user_group = $this->core->getUser()->getGroup()
        );
    }

    public function deleteHelper($file,&$json){
            if ((array_key_exists('name',$file))){
                $filename = $file['path'];
                unset($json[$filename]);
                return;
            }
            else{
                if(array_key_exists('files',$file)){
                    $this->deleteHelper($file['files'],$json);
                }
                else{
                    foreach ($file as $f){
                        $this->deleteHelper($f,$json);
                    }
                }
            }
    }
    /**
     * @Route("/{_semester}/{_course}/course_materials/delete")
     */
    public function deleteCourseMaterial($path) {
        // security check
        $dir = "course_materials";
        $path = $this->core->getAccess()->resolveDirPath($dir, htmlspecialchars_decode(urldecode($path)));

        if (!$this->core->getAccess()->canI("path.write", ["path" => $path, "dir" => $dir])) {
            $message = "You do not have access to that page.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->buildCourseUrl(['course_materials']));
        }

        // remove entry from json file
        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';
        $json = FileUtils::readJsonFile($fp);

        if ($json != false) {
            $all_files = is_dir($path) ? FileUtils::getAllFiles($path) : [$path];
            foreach($all_files as $file) {
                if(is_array($file)){
                    $this->deleteHelper($file,$json);
                }
                else{
                    unset($json[$file]);
                }
            }
            file_put_contents($fp, FileUtils::encodeJson($json));
        }

        if (is_dir($path)) {
            $success = FileUtils::recursiveRmdir($path);
        }
        else {
            $success = unlink($path);
        }

        if ($success) {
            $this->core->addSuccessMessage(basename($path) . " has been successfully removed.");
        }
        else {
            $this->core->addErrorMessage("Failed to remove " . basename($path));
        }

        //refresh course materials page
        $this->core->redirect($this->core->buildCourseUrl(['course_materials']));
    }

    /**
     * @Route("/{_semester}/{_course}/course_materials/download_zip")
     */
    public function downloadCourseMaterialZip($dir_name, $path) {
        $root_path = realpath(htmlspecialchars_decode(urldecode($path)));

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
                if ($file['release_datetime'] < $this->core->getDateTimeNow()->format("Y-m-d H:i:sO")) {
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

    /**
     * @Route("/{_semester}/{_course}/course_materials/modify_permission")
     * @AccessControl(role="INSTRUCTOR")
     */
    public function modifyCourseMaterialsFilePermission($checked) {
        $data=$_POST['fn'];
        if(is_string($data)){
            $data = [$data];
        }
    
        foreach ($data as $filename){
            if (!isset($filename) ||
                !isset($checked)) {
                $this->core->redirect($this->core->buildCourseUrl(['course_materials']));
            }

            $file_name = htmlspecialchars($filename);

            $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';


            $end_of_time = new \DateTime("9998-01-01");
            $release_datetime = $end_of_time->format("Y-m-d H:i:sO");
            $json = FileUtils::readJsonFile($fp);

            if ($json != false) {
                $release_datetime  = $json[$file_name]['release_datetime'];
            }

            $json[$file_name] = array('checked' => $checked, 'release_datetime' => $release_datetime);

            if (file_put_contents($fp, FileUtils::encodeJson($json)) === false) {
                return "Failed to write to file {$fp}";
            }
        }
        

    }

    /**
     * @Route("/{_semester}/{_course}/course_materials/modify_timestamp")
     * @AccessControl(role="INSTRUCTOR")
     */
    public function modifyCourseMaterialsFileTimeStamp($filenames, $newdatatime) {
        $data=$_POST['fn'];

        if(!isset($newdatatime)) {
            $this->core->redirect($this->core->buildCourseUrl(['course_materials']));
        }

        $new_data_time = htmlspecialchars($newdatatime);
        //Check if the datetime is correct
        if(\DateTime::createFromFormat ( 'Y-m-d H:i:s', $new_data_time ) === FALSE){
          return $this->core->getOutput()->renderResultMessage("ERROR: Improperly formatted date", false);
        }

        //only one will not iterate correctly
        if(is_string($data)){
            $data = [$data];
        }
    
        foreach ($data as $filename){
            if (!isset($filename)) {
                $this->core->redirect($this->core->buildCourseUrl(['course_materials']));
            }

            $file_name = htmlspecialchars($filename);
            $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';

            $checked = '0';
            $json = FileUtils::readJsonFile($fp);
            if ($json != false) {
                $checked  = $json[$file_name]['checked'];
            }

            $json[$file_name] = array('checked' => $checked, 'release_datetime' => $new_data_time);
            if (file_put_contents($fp, FileUtils::encodeJson($json)) === false) {
                return $this->core->getOutput()->renderResultMessage("ERROR: Failed to update.", false);
            }
        }
    
        return $this->core->getOutput()->renderResultMessage("Time successfully set.", true);
    }

    /**
     * @Route("/{_semester}/{_course}/course_materials/upload", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function ajaxUploadCourseMaterialsFiles() {
        if (empty($_POST)) {
            $max_size = ini_get('post_max_size');
            return $this->core->getOutput()->renderResultMessage("Empty POST request. This may mean that the sum size of your files are greater than {$max_size}.", false, false);
        }

        if (!isset($_POST['csrf_token']) || !$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return $this->core->getOutput()->renderResultMessage("Invalid CSRF token.", false, false);
        }

        $expand_zip = "";
        if (isset($_POST['expand_zip'])) {
            $expand_zip = $_POST['expand_zip'];
        }

        $requested_path = "";
        if (isset($_POST['requested_path'])) {
            $requested_path = $_POST['requested_path'];
        }

        $release_time ="";
        if(isset($_POST['release_time'])){
            $release_time = $_POST['release_time'];
        }

        //Check if the datetime is correct
        if(\DateTime::createFromFormat ( 'Y-m-d H:i:s', $release_time ) === FALSE){
          return $this->core->getOutput()->renderResultMessage("ERROR: Improperly formatted date", false);
        }


        $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';
        $json = FileUtils::readJsonFile($fp);

        $n = strpos($requested_path, '..');
        if ($n !== false) {
            return $this->core->getOutput()->renderResultMessage("ERROR: .. is not supported in a course materials filepath.", false, false);
        }

        $uploaded_files = array();
        if (isset($_FILES["files1"])) {
            $uploaded_files[1] = $_FILES["files1"];
        }

        if (empty($uploaded_files)) {
            return $this->core->getOutput()->renderResultMessage("ERROR: No files were submitted.", false);
        }

        $status = FileUtils::validateUploadedFiles($_FILES["files1"]);  
        if(array_key_exists("failed", $status)){
            return $this->core->getOutput()->renderResultMessage("Failed to validate uploads " . $status["failed"], false);
        }
        
        $file_size = 0;
        foreach ($status as $stat) {
            $file_size += $stat['size'];
            if($stat['success'] === false){
                return $this->core->getOutput()->renderResultMessage("Error " . $stat['error'], false);
            }
        }

        $max_size = Utils::returnBytes(ini_get('upload_max_filesize'));
        if ($file_size > $max_size) {
            return $this->core->getOutput()->renderResultMessage("ERROR: File(s) uploaded too large.  Maximum size is ".($max_size/1024)." kb. Uploaded file(s) was ".($file_size/1024)." kb.", false);
        }

        // creating uploads/course_materials directory
        $upload_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
        if (!FileUtils::createDir($upload_path)) {
            return $this->core->getOutput()->renderResultMessage("ERROR: Failed to make image path.", false);
        }

        // create nested path
        if (!empty($requested_path)) {
            $upload_nested_path = FileUtils::joinPaths($upload_path, $requested_path);
            if (!FileUtils::createDir($upload_nested_path, null, true)) {
                return $this->core->getOutput()->renderResultMessage("ERROR: Failed to make image path.", false);
            }
            $upload_path = $upload_nested_path;
        }

        $count_item = count($status);   
        if (isset($uploaded_files[1])) {
            for ($j = 0; $j < $count_item; $j++) {
                if ($this->core->isTesting() || is_uploaded_file($uploaded_files[1]["tmp_name"][$j])) {
                    $dst = FileUtils::joinPaths($upload_path, $uploaded_files[1]["name"][$j]);
                    
                    $is_zip_file = false;

                    if (FileUtils::getMimeType($uploaded_files[1]["tmp_name"][$j]) == "application/zip") {
                        if(FileUtils::checkFileInZipName($uploaded_files[1]["tmp_name"][$j]) === false) {
                            return $this->core->getOutput()->renderResultMessage("ERROR: You may not use quotes, backslashes or angle brackets in your filename for files inside ".$uploaded_files[1]["name"][$j].".", false);
                        }
                        $is_zip_file = true;
                    }
                    //cannot check if there are duplicates inside zip file, will overwrite
                    //it is convenient for bulk uploads
                    if ($expand_zip == 'on' && $is_zip_file === true) {
                        $zip = new \ZipArchive();
                        $res = $zip->open($uploaded_files[1]["tmp_name"][$j]);
                        if ($res === true) {
                            $zip->extractTo($upload_path);
                            $zip->close();
                            $subfiles = FileUtils::getAllFiles($upload_path, array(), true );
                            foreach ($subfiles as $file) {
                                $json[$file['path']] = array('checked' => '1', 'release_datetime' => $release_time  );
                            }
                        }
                    }
                    else
                    {
                        if (!@copy($uploaded_files[1]["tmp_name"][$j], $dst)) {
                            return $this->core->getOutput()->renderResultMessage("ERROR: Failed to copy uploaded file {$uploaded_files[1]["name"][$j]} to current location.", false);
                        }else{
                            $json[$dst] = array('checked' => '1', 'release_datetime' => $release_time  );
                        }
                    }
                    //
                }
                else {
                    return $this->core->getOutput()->renderResultMessage("ERROR: The tmp file '{$uploaded_files[1]['name'][$j]}' was not properly uploaded.", false);
                }
                // Is this really an error we should fail on?
                if (!@unlink($uploaded_files[1]["tmp_name"][$j])) {
                    return $this->core->getOutput()->renderResultMessage("ERROR: Failed to delete the uploaded file {$uploaded_files[1]["name"][$j]} from temporary storage.", false);
                }
            }
        }

        FileUtils::writeJsonFile($fp,$json);
        return $this->core->getOutput()->renderResultMessage("Successfully uploaded!", true);
    }
}
