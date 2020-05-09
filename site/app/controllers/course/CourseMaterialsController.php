<?php

namespace app\controllers\course;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\libraries\ErrorMessages;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;
use app\models\CourseMaterial;

class CourseMaterialsController extends AbstractController {
    /**
     * @Route("/{_semester}/{_course}/course_materials")
     */
    public function viewCourseMaterialsPage() {
        $this->core->getOutput()->renderOutput(
            ['course', 'CourseMaterials'],
            'listCourseMaterials',
            $this->core->getUser()
        );
    }

    public function deleteHelper($file, &$json) {
        if ((array_key_exists('name', $file))) {
            $filename = $file['path'];
            unset($json[$filename]);
            return;
        }
        else {
            if (array_key_exists('files', $file)) {
                $this->deleteHelper($file['files'], $json);
            }
            else {
                foreach ($file as $f) {
                    $this->deleteHelper($f, $json);
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
            foreach ($all_files as $file) {
                if (is_array($file)) {
                    $this->deleteHelper($file, $json);
                }
                else {
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
        // replacing any whitespace with underscore char.
        $zip_file_name = preg_replace('/\s+/', '_', $dir_name) . ".zip";
        // getting the meta-data of the course-material in '$json' variable
        $file_data = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';
        $json = FileUtils::readJsonFile($file_data);

        $zip = new \ZipArchive();
        $zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $isFolderEmptyForMe = true;
        // iterate over the files inside the requested directory
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root_path),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                if (!$this->core->getUser()->accessGrading()) {
                    // only add the file if the section of student is allowed and course material is released!
                    if (CourseMaterial::isSectionAllowed($this->core, $file_path, $this->core->getUser()) && $json[$file_path]['release_datetime'] < $this->core->getDateTimeNow()->format("Y-m-d H:i:sO")) {
                        $relativePath = substr($file_path, strlen($root_path) + 1);
                        $isFolderEmptyForMe = false;
                        $zip->addFile($file_path, $relativePath);
                    }
                }
                else {
                    // For graders and instructors, download the course-material unconditionally!
                    $relativePath = substr($file_path, strlen($root_path) + 1);
                    $isFolderEmptyForMe = false;
                    $zip->addFile($file_path, $relativePath);
                }
            }
        }

        // If the Course Material Folder Does not contain anything for current user display an error message.
        if ($isFolderEmptyForMe) {
            $this->core->getOutput()->showError("You do not have access to this folder");
            return false;
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
     * @Route("/{_semester}/{_course}/course_materials/modify_timestamp")
     * @AccessControl(role="INSTRUCTOR")
     */
    public function modifyCourseMaterialsFileTimeStamp($filenames, $newdatatime) {
        $data = $_POST['fn'];
        $hide_from_students = null;

        if (!isset($newdatatime)) {
            $this->core->redirect($this->core->buildCourseUrl(['course_materials']));
        }

        $new_data_time = htmlspecialchars($newdatatime);
        //Check if the datetime is correct
        if (\DateTime::createFromFormat('Y-m-d H:i:s', $new_data_time) === false) {
            return $this->core->getOutput()->renderResultMessage("ERROR: Improperly formatted date", false);
        }

        //only one will not iterate correctly
        if (is_string($data)) {
            $data = [$data];
        }

        foreach ($data as $filename) {
            if (!isset($filename)) {
                $this->core->redirect($this->core->buildCourseUrl(['course_materials']));
            }

            $file_name = htmlspecialchars($filename);
            $fp = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';

            $sections = null;
            $json = FileUtils::readJsonFile($fp);
            if ($json != false) {
                if (isset($json[$file_name]['sections'])) {
                    $sections  = $json[$file_name]['sections'];
                }
                if (isset($json[$file_name]['hide_from_students'])) {
                    $hide_from_students  = $json[$file_name]['hide_from_students'];
                }
            }
            if (!is_null($sections)) {
                $json[$file_name] = array('release_datetime' => $new_data_time, 'sections' => $sections, 'hide_from_students' => $hide_from_students);
            }
            else {
                $json[$file_name] = array('release_datetime' => $new_data_time, 'hide_from_students' => $hide_from_students);
            }
            if (file_put_contents($fp, FileUtils::encodeJson($json)) === false) {
                return $this->core->getOutput()->renderResultMessage("ERROR: Failed to update.", false);
            }
        }

        return $this->core->getOutput()->renderResultMessage("Time successfully set.", true);
    }
    
    /**
     * @Route("/{_semester}/{_course}/course_materials/edit", methods={"POST"})
     * @AccessControl(role="INSTRUCTOR")
     */
    public function ajaxEditCourseMaterialsFiles() {
        $sections = null;
        if (isset($_POST['sections'])) {
            $sections = $_POST['sections'] ?? null;
        }
        
        if (empty($sections) && !is_null($sections)) {
            $sections = [];
        }
        
        $sections_exploded = $sections;
        
        if (!(is_null($sections)) && !empty($sections)) {
            $sections_exploded = explode(",", $sections);
        }
        
        $hide_from_students = $_POST['hide_from_students'];
        
        $requested_path = "";
        if (isset($_POST['requested_path'])) {
            $requested_path = $_POST['requested_path'] ?? '';
        }
        
        $release_time = "";
        if (isset($_POST['release_time'])) {
            $release_time = $_POST['release_time'];
        }
        if ($requested_path === '') {
            return $this->core->getOutput()->renderResultMessage('Requested path cannot be empty');
        }
        $fp = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'uploads', 'course_materials_file_data.json');
        $json = FileUtils::readJsonFile($fp);
        $files_to_modify = is_dir($requested_path) ? FileUtils::getAllFiles($requested_path, [], true) : [['path' => $requested_path]];

        foreach ($files_to_modify as $file) {
            $file_path = $file['path'];
            $file_path_release_datetime = empty($release_time) ? $json[$file_path]['release_datetime'] : $release_time;
            $json[$file_path] =  array('release_datetime' => $file_path_release_datetime, 'sections' => $sections_exploded, 'hide_from_students' => $hide_from_students);
        }

        FileUtils::writeJsonFile($fp, $json);
        return $this->core->getOutput()->renderResultMessage("Successfully uploaded!", true);
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

        $release_time = "";
        if (isset($_POST['release_time'])) {
            $release_time = $_POST['release_time'];
        }

        $sections = null;
        if (isset($_POST['sections'])) {
            $sections = $_POST['sections'];
        }

        $hide_from_students = null;
        if (isset($_POST['hide_from_students'])) {
            $hide_from_students = $_POST['hide_from_students'];
        }

        if (empty($sections) && !is_null($sections)) {
            $sections = [];
        }

        //Check if the datetime is correct
        if (\DateTime::createFromFormat('Y-m-d H:i:s', $release_time) === false) {
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
        if (array_key_exists("failed", $status)) {
            return $this->core->getOutput()->renderResultMessage("Failed to validate uploads " . $status["failed"], false);
        }

        $file_size = 0;
        foreach ($status as $stat) {
            $file_size += $stat['size'];
            if ($stat['success'] === false) {
                return $this->core->getOutput()->renderResultMessage("Error " . $stat['error'], false);
            }
        }

        $max_size = Utils::returnBytes(ini_get('upload_max_filesize'));
        if ($file_size > $max_size) {
            return $this->core->getOutput()->renderResultMessage("ERROR: File(s) uploaded too large.  Maximum size is " . ($max_size / 1024) . " kb. Uploaded file(s) was " . ($file_size / 1024) . " kb.", false);
        }

        // creating uploads/course_materials directory
        $upload_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "uploads", "course_materials");
        if (!FileUtils::createDir($upload_path)) {
            return $this->core->getOutput()->renderResultMessage("ERROR: Failed to make image path.", false);
        }

        // create nested path
        if (!empty($requested_path)) {
            $upload_nested_path = FileUtils::joinPaths($upload_path, $requested_path);
            if (!FileUtils::createDir($upload_nested_path, true)) {
                return $this->core->getOutput()->renderResultMessage("ERROR: Failed to make image path.", false);
            }
            $upload_path = $upload_nested_path;
        }

        $count_item = count($status);
        if (isset($uploaded_files[1])) {
            for ($j = 0; $j < $count_item; $j++) {
                if (is_uploaded_file($uploaded_files[1]["tmp_name"][$j])) {
                    $dst = FileUtils::joinPaths($upload_path, $uploaded_files[1]["name"][$j]);

                    $is_zip_file = false;

                    if (mime_content_type($uploaded_files[1]["tmp_name"][$j]) == "application/zip") {
                        if (FileUtils::checkFileInZipName($uploaded_files[1]["tmp_name"][$j]) === false) {
                            return $this->core->getOutput()->renderResultMessage("ERROR: You may not use quotes, backslashes or angle brackets in your filename for files inside " . $uploaded_files[1]["name"][$j] . ".", false);
                        }
                        $is_zip_file = true;
                    }
                    //cannot check if there are duplicates inside zip file, will overwrite
                    //it is convenient for bulk uploads
                    if ($expand_zip == 'on' && $is_zip_file === true) {
                        //get the file names inside the zip to write to the JSON file

                        $zip = new \ZipArchive();
                        $res = $zip->open($uploaded_files[1]["tmp_name"][$j]);

                        if (!$res) {
                            return $this->core->getOutput()->renderResultMessage("ERROR: Failed to open zip archive", false);
                        }

                        $entries = [];
                        $disallowed_folders = [".svn", ".git", ".idea", "__macosx"];
                        $disallowed_files = ['.ds_store'];
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $entries[] = $zip->getNameIndex($i);
                        }
                        $entries = array_filter($entries, function ($entry) use ($disallowed_folders, $disallowed_files) {
                            $name = strtolower($entry);
                            foreach ($disallowed_folders as $folder) {
                                if (Utils::startsWith($folder, $name)) {
                                    return false;
                                }
                            }
                            if (substr($name, -1) !== '/') {
                                foreach ($disallowed_files as $file) {
                                    if (basename($name) === $file) {
                                        return false;
                                    }
                                }
                            }
                            return true;
                        });
                        $zfiles = array_filter($entries, function ($entry) {
                            return substr($entry, -1) !== '/';
                        });

                        $zip->extractTo($upload_path, $entries);

                        foreach ($zfiles as $zfile) {
                            $path = FileUtils::joinPaths($upload_path, $zfile);
                            if (!(is_null($sections))) {
                                $sections_exploded = @explode(",", $sections);
                                if ($sections_exploded == null) {
                                    $sections_exploded = [];
                                }
                                $json[$path] = [
                                    'release_datetime' => $release_time,
                                    'sections' => $sections_exploded,
                                    'hide_from_students' => $hide_from_students
                                ];
                            }
                            else {
                                $json[$path] = [
                                    'release_datetime' => $release_time,
                                    'hide_from_students' => $hide_from_students
                                ];
                            }
                        }
                    }
                    else {
                        if (!@copy($uploaded_files[1]["tmp_name"][$j], $dst)) {
                            return $this->core->getOutput()->renderResultMessage("ERROR: Failed to copy uploaded file {$uploaded_files[1]["name"][$j]} to current location.", false);
                        }
                        else {
                            if (!(is_null($sections))) {
                                $sections_exploded = @explode(",", $sections);
                                if ($sections_exploded == null) {
                                    $sections_exploded = [];
                                }
                                $json[$dst] = array('release_datetime' => $release_time, 'sections' => $sections_exploded, 'hide_from_students' => $hide_from_students);
                            }
                            else {
                                $json[$dst] = array('release_datetime' => $release_time, 'hide_from_students' => $hide_from_students);
                            }
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

        FileUtils::writeJsonFile($fp, $json);
        return $this->core->getOutput()->renderResultMessage("Successfully uploaded!", true);
    }
}
