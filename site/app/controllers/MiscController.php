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
        }
    }

    private function display_file() {
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
        if (!file_exists($_REQUEST['path'])) {
            throw new \InvalidArgumentException("File does not exist");
        }
        foreach (explode(DIRECTORY_SEPARATOR, $_REQUEST['path']) as $part) {
            if ($part == ".." || $part == ".") {
                throw new \InvalidArgumentException("Cannot have a part of the path just be dots");
            }
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
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        // Initialize archive object
        $temp_dir = "/tmp";
        //makes a random zip file name on the server
        $temp_name = md5(uniqid($this->core->getUser()->getId(), true));
        $zip_name = $temp_dir . "/" . $temp_name . ".zip";
        chdir ($temp_dir);
        $zip = new \ZipArchive();
        $zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Create recursive directory iterator
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($_REQUEST['path']),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file)
        {
            // Skip directories (they would be added automatically)
            if (!$file->isDir())
            {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($_REQUEST['path']) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }
        // Zip archive will be created only after closing object
        $zip->close();
        header("Content-type: application/zip"); 
        header("Content-Disposition: attachment; filename=zip_file.zip");
        header("Content-length: " . filesize($zip_name));
        header("Pragma: no-cache"); 
        header("Expires: 0"); 
        readfile("$zip_name");
        unlink($zip_name); //deletes the random zip file
    }
}
