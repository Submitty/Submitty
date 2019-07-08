<?php

namespace app\views;
use app\libraries\FileUtils;

class MiscView extends AbstractView {
    public function displayFile($file_contents) {
        return $this->core->getOutput()->renderTwigTemplate("misc/File.twig", [
            "file_contents" => $file_contents
        ]);
    }

    public function displayCode($file_type, $filename, $file_contents) {
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('codemirror', 'codemirror.css'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('codemirror', 'theme', 'eclipse.css'));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('codemirror', 'theme', 'monokai.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('jquery', 'jquery.min.js'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('codemirror', 'codemirror.js'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('codemirror', 'mode', 'clike', 'clike.js'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('codemirror', 'mode', 'python', 'python.js'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('codemirror', 'mode', 'shell', 'shell.js'));

        return $this->core->getOutput()->renderTwigTemplate("misc/Code.twig", [
            "filename" => $filename,
            "file_contents" => $file_contents,
            "file_type" => $file_type,
            "base_url" => $this->core->getConfig()->getBaseUrl()
        ]);
	}
}
