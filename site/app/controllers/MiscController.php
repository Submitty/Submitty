<?php

namespace app\controllers;


use app\libraries\FileUtils;
use app\libraries\Utils;

class MiscController extends AbstractController {
    public function run() {
        switch($_REQUEST['page']) {
            case 'display_file':
                $this->display_file();
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

    private function display_file() {
        //Additional security
        if (!($this->core->getUser()->accessGrading())) {
            throw new \InvalidArgumentException("It does not look like you're allowed to access this page.");
        }
        foreach (explode(DIRECTORY_SEPARATOR, $_REQUEST['path']) as $part) {
            if ($part == ".." || $part == ".") {
                throw new \InvalidArgumentException("Cannot have a part of the path just be dots");
            }
        }
        $path = $this->core->getConfig()->getCoursePath();
        if ($_REQUEST['dir'] === "config_upload") {
            $check = FileUtils::joinPaths($path, "config_upload");
            if (!Utils::startsWith($_REQUEST['path'], $check)) {
                throw new \InvalidArgumentException("Path must start with path to config_upload");
            }
            if (!file_exists($_REQUEST['path'])) {
                throw new \InvalidArgumentException("File does not exist");
            }
        }
        else if ($_REQUEST['dir'] === "submissions") {
            if (!file_exists($_REQUEST['path'])) {
                throw new \InvalidArgumentException("File does not exist");
            }
        }
        else {
            throw new \InvalidArgumentException("Invalid dir used");
        }
        if (!FileUtils::isValidFileName($_REQUEST['path'])) {
            throw new \InvalidArgumentException("Not a valid file name");
        }
        $gradeable_path = $this->core->getConfig()->getCoursePath();
        $folder_names = array();
        $folder_names[] = "submissions";
        $folder_names[] = "results";
        $folder_names[] = "checkout";
        $path_anchors = array();
        $path_anchors[] = FileUtils::joinPaths($gradeable_path, $folder_names[0]);
        $path_anchors[] = FileUtils::joinPaths($gradeable_path, $folder_names[1]);
        $path_anchors[] = FileUtils::joinPaths($gradeable_path, $folder_names[2]);
        $arr_count = count($path_anchors);
        $access = false;
        for ($x = 0; $x < $arr_count; $x++) {
            if(Utils::startsWith($_REQUEST['path'], $path_anchors[$x])){
                $access = true;
            }
        }

        if(!$access) {
            throw new \InvalidArgumentException("You're not allowed access to that file");
        }

        $mime_type = FileUtils::getMimeType($_REQUEST['path']);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        if ($mime_type === "application/pdf" || Utils::startsWith($mime_type, "image/")) {
            header("Content-type: ".$mime_type);
            header('Content-Disposition: inline; filename="' .  basename($_REQUEST['path']) . '"');
            $this->core->getOutput()->renderString($_REQUEST['path']);
        }
        else {
            $contents = htmlentities(file_get_contents($_REQUEST['path']), ENT_SUBSTITUTE);
            if ($_REQUEST['dir'] === "submissions") {
                $filename = htmlentities($_REQUEST['file'], ENT_SUBSTITUTE);
                $this->core->getOutput()->renderOutput('Misc', 'displayCode', $filename, $contents);
            }
            else {
                $this->core->getOutput()->renderOutput('Misc', 'displayFile', $contents);
            }
        }
    }

    private function downloadFile() {
        //Additional security
        if (!($this->core->getUser()->accessGrading())) {
            throw new \InvalidArgumentException("It does not look like you're allowed to access this page.");
        }
        if (!file_exists($_REQUEST['path'])) {
            throw new \InvalidArgumentException("File does not exist");
        }
        foreach (explode(DIRECTORY_SEPARATOR, $_REQUEST['path']) as $part) {
            if ($part == ".." || $part == ".") {
                throw new \InvalidArgumentException("Cannot have a part of the path just be dots");
            }
        }
        if (!FileUtils::isValidFileName($_REQUEST['path'])) {
            throw new \InvalidArgumentException("Not a valid file name");
        }
        $gradeable_path = $this->core->getConfig()->getCoursePath();
        $folder_names = array();
        $folder_names[] = "submissions";
        $folder_names[] = "results";
        $folder_names[] = "checkout";
        $path_anchors = array();
        $path_anchors[] = FileUtils::joinPaths($gradeable_path, $folder_names[0]);
        $path_anchors[] = FileUtils::joinPaths($gradeable_path, $folder_names[1]);
        $path_anchors[] = FileUtils::joinPaths($gradeable_path, $folder_names[2]);
        $arr_count = count($path_anchors);
        $access = false;
        for ($x = 0; $x < $arr_count; $x++) {
            if(Utils::startsWith($_REQUEST['path'], $path_anchors[$x])){
                $access = true;
            }
        }

        if(!$access) {
            throw new \InvalidArgumentException("You're not allowed access to that file");
        }
        
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        $file_url = $_REQUEST['path'];
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary"); 
        header("Content-disposition: attachment; filename=\"" . basename($file_url) . "\""); 
        readfile($file_url);
    }

    private function downloadZip() {
        //Additional security
        if (!($this->core->getUser()->accessGrading())) {
            throw new \InvalidArgumentException("It does not look like you're allowed to access this page.");
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
        //Additional security
        if (!($this->core->getUser()->accessGrading())) {
            throw new \InvalidArgumentException("It does not look like you're allowed to access this page.");
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
