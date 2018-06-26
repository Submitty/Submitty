<?php

namespace app\views;
use app\libraries\FileUtils;

class MiscView extends AbstractView {
    public function displayFile($file_contents) {
        return $this->core->getOutput()->renderTwigTemplate("misc/File.twig", [
            "file_contents" => $file_contents
        ]);
    }

    public function displayCode($mime_type, $filename, $file_contents) {
        return $this->core->getOutput()->renderTwigTemplate("misc/Code.twig", [
            "filename" => $filename,
            "file_contents" => $file_contents,
            "mime_type" => $mime_type,
        ]);
	}
}
