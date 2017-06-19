<?php

namespace app\views\grading;

use app\views\AbstractView;

class UploadView extends AbstractView {

    public function noGradeable($gradeable_id) {
        if ($gradeable_id === null) {
            return <<<HTML
<div class="content">
    No gradeable id specified. Contact your instructor if you think this is an error.
</div>
HTML;
        }
        else {
            $gradeable = htmlentities($gradeable_id, ENT_QUOTES);
            return <<<HTML
<div class="content">
    {$gradeable} is not a valid electronic submission gradeable. Contact your instructor if you think this
    is an error.
</div>
HTML;
        }
    }

	/**
     *
     * @param Gradeable $gradeable
     *
     * @return string
     */
    public function showUpload($gradeable) {
    	$return = <<<HTML
<script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}js/drag-and-drop.js"></script>
<div class="content">
    <h2>New upload for: {$gradeable->getName()}</h2>
    <div class="upload">
HTML;
        $rcs_id = "";
        $return .= <<<HTML
        <div>Student RCS ID: <span><input type="text" name="rcs_id" value="{$rcs_id}" /></span></div>
        <div id="upload-boxes" style="display:table; border-spacing: 5px; width:100%">
HTML;
            $label = "Drag your files() here or click to open file browser";
            $return .= <<<HTML
            <div id="upload0" style="cursor: pointer; text-align: center; border: dashed 2px lightgrey; display:table-cell; height: 150px;">
                <h3 class="label" id="label0">{$label}</h3>
                <input type="file" name="files" id="input_file0" style="display: none" onchange="addFilesFromInput(0)" multiple />
            </div>
HTML;

        $return .= <<<HTML
        </div>
    </div>
</div>
<div class="content">
    <span style="font-style: italic">Students for this assignment blahbalba.</span>
</div>
HTML;
        return $return;
    }

}
