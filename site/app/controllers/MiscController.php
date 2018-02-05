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
            case 'download_zip':
                $this->downloadZip();
                break;
            case 'download_all_assigned':
                $this->downloadAssignedZips();
                break;
        }
    }
    // function to check that this is a valid access request
    private function checkValidAccess($is_zip, &$error_string) {
        $error_string="";
        // only allow zip if it's a grader
        if ($is_zip) {
            $error_string="only graders may access zip files";
            return ($this->core->getUser()->accessGrading());
        }
        // from this point on, is not a zip
        // do path and permissions checking
        $dir = $_REQUEST['dir'];
        $path = $_REQUEST['path'];
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
            if ($part == ".." || $part == ".") {
                 $error_string=".. or . in path";
                 return false;
            }
        }
        
        if (!FileUtils::isValidFileName($path)) {
            $error_string="not valid filename";
            return false;
        }
        // TEMPORARY HACK PUT THIS HERE
        // INSTRUCTORS ARE UNABLE TO VIEW VCS CHECKOUT FILES WITHOUT THIS
        // if instructor or grader, then it's okay
        if ($this->core->getUser()->accessGrading()) {
            return true;
        }
        // END HACK
        $possible_directories = array("config_upload", "uploads", "submissions", "results", "checkout", "forum_attachments");
        if (!in_array($dir, $possible_directories)) {
            $error_string="not in possible directories list";
            return false;
        }
        $course_path = $this->core->getConfig()->getCoursePath();
        $check = FileUtils::joinPaths($course_path, $dir);
        if (!Utils::startsWith($path, $check)) {
            $error_string="does not start with course path";
            return false;
        }
        if (!file_exists($path)) {
            $error_string="path does not exist";
            return false;
        }
        if ($dir === "config_upload" || $dir === "uploads") {
            $error_string="only admin can access uploads";
            return ($this->core->getUser()->accessAdmin());
        } else if($dir === "forum_attachments"){
            //Might need to be revisted but for now fixes the problem where students can't view attachments...
            return true;
        } else if ($dir === "submissions" || $dir === "results" || $dir === "checkout") {
            // if instructor or grader, then it's okay
            if ($this->core->getUser()->accessGrading()) {
                return true;
            }
            // FIXME: need to make this work for peer grading
            $current_user_id = $this->core->getUser()->getId();
            // get the information from the path
            $path_folder = FileUtils::joinPaths($course_path, $dir);
            $path_rest = substr($path, strlen($path_folder)+1);
            $path_gradeable_id = substr($path_rest, 0, strpos($path_rest, DIRECTORY_SEPARATOR));
            $path_rest = substr($path_rest, strlen($path_gradeable_id)+1);
            $path_user_id = substr($path_rest, 0, strpos($path_rest, DIRECTORY_SEPARATOR));
            $path_rest = substr($path_rest, strlen($path_user_id)+1);
            $path_version = intval(substr($path_rest, 0, strpos($path_rest, DIRECTORY_SEPARATOR)));
            // gradeable to get temporary info from
            // if team, get one of the user ids via the team id
            $current_gradeable = $this->core->getQueries()->getGradeable($path_gradeable_id, $current_user_id);
            if($current_gradeable->getPeerGrading()) {
                $peer_grade_set = $this->core->getQueries()->getPeerAssignment($path_gradeable_id, $current_user_id);
                if(in_array($path_user_id, $peer_grade_set)) {
                    return true;
                }
            }
            if ($current_gradeable->isTeamAssignment()) {
                $path_team_id = $path_user_id;
                $path_team_members = $this->core->getQueries()->getTeamById($path_team_id)->getMembers();
                if (count($path_team_members) == 0) {
                     $error_string="this team currently has no members";
                     return false;
                }
                $path_user_id = $path_team_members[0];
            }
            // use the current user id to get the gradeable specified in the path
            $path_gradeable = $this->core->getQueries()->getGradeable($path_gradeable_id, $path_user_id);
            if ($path_gradeable === null) {
                 $error_string="something wrong with gradeable path";
                 return false;
            }
            // if gradeable student view or download false, don't allow anything
            if ($dir == "submissions" && (!$path_gradeable->getStudentView() || !$path_gradeable->getStudentDownload())) {
                 $error_string="students can't view / download submissions for this gradeable";
                 return false;
            }
            // make sure that version is active version if student any version is false
            if (!$path_gradeable->getStudentAnyVersion() && $path_version !== $path_gradeable->getActiveVersion()) {
                 $error_string="you are only allowed only view the active submission version";
                 return false;
            }
            // if team assignment, check that team id matches the team of the current user
            if ($path_gradeable->isTeamAssignment()) {
                $current_team = $this->core->getQueries()->getTeamByGradeableAndUser($path_gradeable_id,$current_user_id);
                if ($current_team === null) {
                     $error_string="this is an invalid team and/or user";
                     return false;
                }
                $current_team_id = $current_team->getId();
                if ($path_team_id != $current_team_id) {
                     $error_string="user is not a member of this team for this gradeable";
                     return false;
                }
            }
            // else, just check that the user ids match
            else {
                if ($current_user_id != $path_user_id) {
                     $error_string="user does not match";
                     return false;
                }
            }
            return true;
        }
        else {
            return false;
        }
    }
    private function displayFile() {
        // security check
        $error_string="";
        if (!$this->checkValidAccess(false,$error_string)) {
            $this->core->getOutput()->showError("You do not have access to this file ".$error_string);
            return false;
        }
        $corrected_name = pathinfo($_REQUEST['path'], 1) . "/" . urlencode( basename($_REQUEST['path']));
        $mime_type = FileUtils::getMimeType($corrected_name);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        if ($mime_type === "application/pdf" || Utils::startsWith($mime_type, "image/")) {
            header("Content-type: ".$mime_type);
            header('Content-Disposition: inline; filename="' .  basename($_REQUEST['path']) . '"');
            readfile($corrected_name);
            $this->core->getOutput()->renderString($_REQUEST['path']);
        }
        else {
            $contents = htmlentities(file_get_contents($corrected_name), ENT_SUBSTITUTE);
            if ($_REQUEST['ta_grading'] === "true") {
                $filename = htmlentities($corrected_name, ENT_SUBSTITUTE);
                $this->core->getOutput()->renderOutput('Misc', 'displayCode', $mime_type, $filename, $contents);
            }
            else {
                $this->core->getOutput()->renderOutput('Misc', 'displayFile', $contents);
            }
        }
    }
    private function downloadFile() {
        
        // security check
        $error_string="";
        if (!$this->checkValidAccess(false,$error_string)) {
            $message = "You do not have access to that page. ".$error_string;
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }
        
       $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary"); 
        header("Content-disposition: attachment; filename=\"{$_REQUEST['file']}\"");
        readfile(pathinfo($_REQUEST['path'], 1) . "/" . urlencode( basename($_REQUEST['path'])));
    }
    private function downloadZip() {
        // security check
        $error_string="";
        if (!$this->checkValidAccess(true,$error_string)) {
            $message = "You do not have access to that page. ".$error_string;
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
        $gradeable = $this->core->getQueries()->getGradeable($_REQUEST['gradeable_id'], $_REQUEST['user_id']);
        $gradeable_path = $this->core->getConfig()->getCoursePath();
        $active_version = $gradeable->getActiveVersion();
        $folder_names = array();
        $folder_names[] = "submissions";
        $folder_names[] = "results";
        $folder_names[] = "checkout";
        $submissions_path = FileUtils::joinPaths($gradeable_path, $folder_names[0], $gradeable->getId(), $gradeable->getUser()->getId(), $active_version);
        $results_path = FileUtils::joinPaths($gradeable_path, $folder_names[1], $gradeable->getId(), $gradeable->getUser()->getId(), $active_version);
        $checkout_path = FileUtils::joinPaths($gradeable_path, $folder_names[2], $gradeable->getId(), $gradeable->getUser()->getId(), $active_version);
        $zip = new \ZipArchive();
        $zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $paths = array();
        $paths[] = $submissions_path;
        $paths[] = $results_path;
        $paths[] = $checkout_path;
        for ($x = 0; $x < 3; $x++) {
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
                throw new \InvalidArgumentException("It does not look like you're allowed to access this page.");
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
        $gradeable_path = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "submissions",
            $gradeable->getId());
        $zip = new \ZipArchive();
        $zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if($type === "all") {
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
                    $zip->addFile($filePath, $relativePath);
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
}
