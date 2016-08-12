<?php

namespace app\controllers\student;

use app\controllers\IController;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\ErrorMessages;
use app\libraries\FileUtils;
use app\libraries\Logger;
use app\libraries\Utils;
use app\models\GradeableList;


class SubmissionController implements IController {

    /**
     * @var Core
     */
    private $core;

    /**
     * @var GradeableList
     */
    private $gradeables_list;
    
    private $upload_details = array('version' => -1, 'version_path' => null, 'user_path' => null,
                                    'assignment_settings' => false);

    public function __construct(Core $core) {
        $this->core = $core;
        $this->gradeables_list = new GradeableList($this->core);
    }

    public function run() {
        switch($_REQUEST['action']) {
            case 'upload':
                $this->uploadSubmission();
                break;
            case 'update':
                $this->updateSubmissionVersion();
                break;
            case 'check_refresh':
                $this->checkRefresh();
                break;
            case 'display':
            default:
                $this->showHomeworkPage();
                break;
        }
    }

    private function showHomeworkPage() {
        $gradeable_list = $this->gradeables_list->getOpenElectronicGradeables(true);
        if (count($gradeable_list) > 0) {
            $gradeable_id = (isset($_REQUEST['gradeable_id'])) ? $_REQUEST['gradeable_id'] : null;
            if (array_key_exists($gradeable_id, $gradeable_list)) {
                $gradeable = $gradeable_list[$gradeable_id];
            }
            else {
                $gradeable = Utils::getLastArrayElement($gradeable_list);
            }
            $gradeable->loadSubmissionDetails();
            $loc = array('page' => 'submission',
                         'action' => 'display',
                         'gradeable_id' => $gradeable->getId());
            $this->core->getOutput()->addBreadcrumb("<a href='{$this->core->buildUrl($loc)}'>{$gradeable->getName()}</a>");
            
            $select = $this->core->getOutput()->renderTemplate(array('submission', 'Homework'), 'gradeableSelect',
                                                               $gradeable_list,
                                                               $gradeable->getId());
    
            $days_late = DateUtils::calculateDayDiff($gradeable->getDueDate());
            
            $this->core->getOutput()->renderOutput(array('submission', 'Homework'), 'showGradeable',
                                                   $gradeable, $select, $days_late);
        }
        else {
            $this->core->getOutput()->renderOutput(array('submission', 'Homework'), 'noGradeables');
        }
    }
    
    /**
     * Function for uploading a submission to the server. This should be called via AJAX, saving the result
     * to the json_buffer of the Output object, returning a true or false on whether or not it suceeded or not.
     *
     * @return boolean
     */
    private function uploadSubmission() {
        if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
            return $this->uploadResult("Invalid CSRF token: {$_POST['csrf_token']}.", false);
        }
        $svn_checkout = isset($_REQUEST['svn_checkout']) ? $_REQUEST['svn_checkout'] === "true" : false;
    
        $gradeable_list = $this->gradeables_list->getOpenElectronicGradeables(true);
        
        // This checks for an assignment id, and that it's a valid assignment id in that
        // it corresponds to one that we can access (whether through admin or it being released)
        if (!isset($_REQUEST['gradeable_id']) || !array_key_exists($_REQUEST['gradeable_id'], $gradeable_list)) {
            return $this->uploadResult("Invalid gradeable id '{$_REQUEST['gradeable_id']}'", false);
        }
        
        $gradeable = $gradeable_list[$_REQUEST['gradeable_id']];
        $gradeable->loadSubmissionDetails();
        $gradeable_path = $this->core->getConfig()->getCoursePath()."/submissions/".$gradeable->getId();
        
        /*
         * Perform checks on the following folders (and whether or not they exist):
         * 1) the assignment folder in the submissions directory
         * 2) the student's folder in the assignment folder
         * 3) the version folder in the student folder
         * 4) the part folders in the version folder in the version folder
         */
        if (!FileUtils::createDir($gradeable_path)) {
            return $this->uploadResult("Failed to make folder for this assignment.", false);
        }
    
        $user_path = $gradeable_path."/".$this->core->getUser()->getId();
        $this->upload_details['user_path'] = $user_path;
        if (!FileUtils::createDir($user_path)) {
                return $this->uploadResult("Failed to make folder for this assignment for the user.", false);
        }
    
        $new_version = $gradeable->getHighestVersion() + 1;
        $version_path = $user_path."/".$new_version;
        
        if (!FileUtils::createDir($version_path)) {
            return $this->uploadResult("Failed to make folder for the current version.", false);
        }
    
        $this->upload_details['version_path'] = $version_path;
        $this->upload_details['version'] = $new_version;
    
        $part_path = array();
        // We upload the assignment such that if it's multiple parts, we put it in folders "part#" otherwise
        // put all files in the root folder
        if ($gradeable->getNumParts() > 1) {
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                $part_path[$i] = $version_path."/part".$i;
                if (!FileUtils::createDir($part_path[$i])) {
                    return $this->uploadResult("Failed to make the folder for part {$i}.", false);
                }
            }
        }
        else {
            $part_path[1] = $version_path;
        }
        
        $current_time = (new \DateTime('now', new \DateTimeZone($this->core->getConfig()->getTimezone())))->format("Y/m/d H:i:s");
        $max_size = $gradeable->getMaxSize();
        
        if ($svn_checkout === false) {
            $uploaded_files = array();
            for ($i = 0; $i < $gradeable->getNumParts(); $i++){
                if (isset($_FILES["files".($i+1)])) {
                    $uploaded_files[$i+1] = $_FILES["files".($i+1)];
                }
            }
            
            $errors = array();
            $count = array();
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                if (isset($uploaded_files[$i])) {
                    $count[$i] = count($uploaded_files[$i]["name"]);
                    for ($j = 0; $j < $count[$i]; $j++) {
                        if (!isset($uploaded_files[$i]["tmp_name"][$j]) || $uploaded_files[$i]["tmp_name"][$j] == "") {
                            $error_message = $uploaded_files[$i]["name"][$j]." failed to upload. ";
                            if (isset($uploaded_files[$i]["error"][$j])) {
                                $error_message .= "Error message: ". ErrorMessages::uploadErrors($uploaded_files[$i]["error"][$j]);
                            }
                            $errors[] = $error_message;
                        }
                    }
                }
            }
            
            if (count($errors) > 0) {
                $error_text = implode("\n", $errors);
                return $this->uploadResult("Upload Failed: ".$error_text, false);
            }
    
            $previous_files = array();
            $previous_part_path = array();
            $tmp = json_decode($_POST['previous_files']);
            for ($i = 0; $i < $gradeable->getNumParts(); $i++) {
                if (count($tmp[$i]) > 0) {
                    $previous_files[$i + 1] = $tmp[$i];
                }
            }
            
            if (empty($uploaded_files) && empty($previous_files)) {
                return $this->uploadResult("No files to be submitted", false);
            }
            
            if (count($previous_files) > 0) {
                if ($gradeable->getHighestVersion() === 0) {
                    return $this->uploadResult("No submission found. There should not be any files kept from previous submission.", false);
                }
                
                $previous_path = $user_path."/".$gradeable->getHighestVersion();
                if ($gradeable->getNumParts() > 1) {
                    for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                        $previous_part_path[$i] = $previous_path."/part".$i;
                        if (!is_dir($previous_part_path[$i])) {
                            return $this->uploadResult("Files from previous submission not found. Folder for previous submission does not exist.", false);
                        }
                    }
                }
                else {
                    $previous_part_path[1] = $previous_path;
                }
    
                for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                    if (isset($previous_files[$i])) {
                        foreach ($previous_files[$i] as $prev_file) {
                            $filename = $previous_part_path[$i]."/".$prev_file;
                            if (!file_exists($filename)) {
                                return $this->uploadResult("File '{$filename}' does not exist in previous submission.", false);
                            }
                        }
                    }
                }
            }
            
            // Determine the size of the uploaded files as well as whether or not they're a zip or not.
            // We save that information for later so we know which files need unpacking or not and can save
            // a check to getMimeType()
            $file_size = 0;
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                if (isset($uploaded_files[$i])) {
                    $uploaded_files[$i]["is_zip"] = array();
                    for ($j = 0; $j < $count[$i]; $j++) {
                        if (FileUtils::getMimeType($uploaded_files[$i]["tmp_name"][$j]) == "application/zip") {
                            $uploaded_files[$i]["is_zip"][$j] = true;
                            $file_size += FileUtils::getZipSize($uploaded_files[$i]["tmp_name"][$j]);
                        }
                        else {
                            $uploaded_files[$i]["is_zip"][$j] = false;
                            $file_size += $uploaded_files[$i]["size"][$j];
                        }
                    }
                }
                if (isset($previous_files[$i]) && isset($previous_part_path[$i])) {
                    foreach ($previous_files[$i] as $prev_file) {
                        $file_size += filesize($previous_part_path[$i]."/".$prev_file);
                    }
                }
            }
            
            if ($file_size > $max_size) {
                return $this->uploadResult("File(s) uploaded too large.  Maximum size is ".($max_size/1000)." kb. Uploaded file(s) was ".($file_size/1000)." kb.", false);
            }
            
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                if (isset($uploaded_files[$i])) {
                    for ($j = 0; $j < $count[$i]; $j++) {
                        if ($uploaded_files[$i]["is_zip"][$j] === true) {
                            $zip = new \ZipArchive();
                            $res = $zip->open($uploaded_files[$i]["tmp_name"][$j]);
                            if ($res === true) {
                                $zip->extractTo($part_path[$i]);
                                $zip->close();
                            }
                            else {
                                return $this->uploadResult("Could not properly unpack zip file. Error message: ".ErrorMessages::uploadErrors($res), false);
                            }
                        }
                        else {
                            if (is_uploaded_file($uploaded_files[$i]["tmp_name"][$j])) {
                                if (!copy($uploaded_files[$i]["tmp_name"][$j], $part_path[$i]."/".$uploaded_files[$i]["name"][$j])) {
                                    return $this->uploadResult("Failed to copy uploaded file ".$uploaded_files[$i]["name"][$j]." to current submission.", false);
                                }
                            }
                            else {
                                return $this->uploadResult("The tmp file '{$uploaded_files[$i]['tmp_name'][$j]}' was not properly uploaded.", false);
                            }
                        }
                        // Is this really an error we should fail on?
                        if (!unlink($uploaded_files[$i]["tmp_name"][$j])) {
                            return $this->uploadResult("Failed to delete the uploaded file ".$uploaded_files[$i]["name"][$j]." from temporary storage.", false);
                        }
                    }
                }
    
                // copy selected previous submitted files
                if (isset($previous_files[$i])){
                    for ($j=0; $j < count($previous_files[$i]); $j++){
                        if (!copy($previous_part_path[$i]."/".$previous_files[$i][$j], $part_path[$i]."/".$previous_files[$i][$j])) {
                            return $this->uploadResult("Failed to copy previously submitted file ".$previous_files[$i][$j]." to current submission.", false);
                        }
                    }
                }
            }
        }
        else {
            if (!touch($version_path."/.submit.SVN_CHECKOUT")) {
                return $this->uploadResult("Failed to touch file for svn submission.", false);
            }
        }
    
        $settings_file = $user_path."/user_assignment_settings.json";
        if (!file_exists($settings_file)) {
            $json = array("active_version" => $new_version,
                          "history" => array(array("version" => $new_version,
                                                   "time" => $current_time)));
        }
        else {
            $json = FileUtils::readJsonFile($settings_file);
            if ($json === false) {
                return $this->uploadResult("Failed to open settings file.", false);
            }
            $json["active_version"] = $new_version;
            $json["history"][] = array("version"=> $new_version, "time" => $current_time);
        }
    
        // TODO: If any of these fail, should we "cancel" (delete) the entire submission attempt or just leave it?
        if (!file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            return $this->uploadResult("Failed to write to settings file.", false);
        }
        
        $this->upload_details['assignment_settings'] = true;

        if (!file_put_contents($version_path."/.submit.timestamp", $current_time."\n")) {
            return $this->uploadResult("Failed to save timestamp file for this submission.", false);
        }
    
        $touch_file = array($this->core->getConfig()->getSemester(), $this->core->getConfig()->getCourse(),
            $gradeable->getId(), $this->core->getUser()->getId(), $new_version);
        $touch_file = $this->core->getConfig()->getSubmittyPath()."/to_be_graded_interactive/".implode("__", $touch_file);
        if (!touch($touch_file)) {
            return $this->uploadResult("Failed to create file for grading queue.", false);
        }
        
        return $this->uploadResult("Successfully uploaded files");
    }
    
    private function uploadResult($message, $success = true) {
        if (!$success) {
            // we don't want to throw an exception here as that'll mess up our return json payload
            if ($this->upload_details['version_path'] !== null
                && !FileUtils::recursiveRmdir($this->upload_details['version_path'])) {
                Logger::error("Could not clean up folder {$this->upload_details['version_path']}");
            }
            else if ($this->upload_details['assignment_settings'] === true) {
                $settings_file = $this->upload_details['user_path']. "/user_assignment_settings.json";
                $settings = json_decode(file_get_contents($settings_file), true);
                if (count($settings['history']) == 1) {
                    unlink($settings_file);
                }
                else {
                    array_pop($settings['history']);
                    $last = Utils::getLastArrayElement($settings['history']);
                    $settings['active_version'] = $last['version'];
                    file_put_contents($settings_file, FileUtils::encodeJson($settings));
                }
            }
        }
        
        $this->core->getOutput()->renderJson(array('success' => $success, 'error' => !$success, 'message' => $message));
        return $success;
    }
    
    private function updateSubmissionVersion() {
        $gradeable_list = $this->gradeables_list->getOpenElectronicGradeables(true);
        if (!isset($_REQUEST['gradeable_id']) || !array_key_exists($_REQUEST['gradeable_id'], $gradeable_list)) {
            $_SESSION['messages']['error'][] = "Invalid gradeable id";
            $this->core->redirect($this->core->buildUrl(array('component' => 'student')));
        }
        
        $gradeable = $gradeable_list[$_REQUEST['gradeable_id']];
        $gradeable->loadSubmissionDetails();
        if (!$this->core->checkCsrfToken($_POST['csrf_token'])) {
            $_SESSION['messages']['error'][] = "Invalid CSRF token. Refresh the page and try again.";
            $this->core->redirect($this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId())));
        }
    
        $new_version = intval($_REQUEST['new_version']);
        if ($new_version < 0) {
            $_SESSION['messages']['error'][] = "Cannot set the version below 0.";
            $this->core->redirect($this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId())));
        }
        
        if ($new_version > $gradeable->getHighestVersion()) {
            $_SESSION['messages']['error'][] = "Cannot set the version past " . $gradeable->getHighestVersion();
            $this->core->redirect($this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId())));
        }
    
        $settings_file = $this->core->getConfig()->getCoursePath() . "/submissions/" .
            $gradeable->getId() . "/" . $this->core->getUser()->getId() . "/user_assignment_settings.json";
        $json = FileUtils::readJsonFile($settings_file);
        if ($json === false) {
            $_SESSION['messages']['error'][] = "Failed to open settings file.";
            $this->core->redirect($this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId())));
        }
        $json["active_version"] = $new_version;
        $json["history"][] = array("version" => $new_version,
                                   "time" => new \DateTime('now', new \DateTimeZone($this->core->getConfig()->getTimezone())));
    
        if (!file_put_contents($settings_file, FileUtils::encodeJson($json))) {
            $_SESSION['messages']['error'][] = "Could not write to settings file.";
            $this->core->redirect($this->core->buildUrl(array('component' => 'student',
                                                              'gradeable_id' => $gradeable->getId())));
        }
        
        if ($new_version == 0) {
            $_SESSION['messages']['success'][] = "Cancelled submission for gradeable";
        }
        else {
            $_SESSION['messages']['success'][] = "Updated version of gradeable to version #" . $new_version;
        }
        $this->core->redirect($this->core->buildUrl(array('component' => 'student',
                                                          'gradeable_id' => $gradeable->getId(),
                                                          'gradeable_version' => $new_version)));
    }
    
    /**
     * Check if the results folder exists for a given gradeable and version results.json
     * in the results/ directory. If the file exists, we output a string that the calling
     * JS checks for to initiate a page refresh (so as to go from "in-grading" to done
     */
    public function checkRefresh() {
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        $g_id = $_REQUEST['gradeable_id'];
        $version = $_REQUEST['gradeable_version'];
        $path = $this->core->getConfig()->getCoursePath()."/results/".$g_id."/".
                    $this->core->getUser()->getId()."/".$version;
        if (file_exists($path."/results.json")) {
            $this->core->getOutput()->renderString("REFRESH_ME");
        }
        else {
            $this->core->getOutput()->renderString("NO_REFRESH");
        }
    }
}
