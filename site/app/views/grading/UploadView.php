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
    </div>
</div>
HTML;
        return $return;
    }

}
