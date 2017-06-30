<?php

namespace app\controllers;


use app\libraries\FileUtils;
use app\libraries\Utils;

class MiscController extends AbstractController {
    public function run() {
        switch($_REQUEST['page']) {
            case 'display_file':
                $this->display_file();
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
}
