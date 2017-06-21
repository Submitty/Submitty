<?php

namespace app\views\grading;

use app\views\AbstractView;
use app\views\submission\HomeworkView;

class UploadView extends AbstractView {

    // noGradeable and showGradeableError are in HomeworkView

	/**
     *
     * @param Gradeable $gradeable
     *
     * @return string
     */
    public function showUpload($gradeable, $days_late) {
        $upload_message = $this->core->getConfig()->getUploadMessage();
        $current_version = $gradeable->getCurrentVersion();
        $current_version_number = $gradeable->getCurrentVersionNumber();
        $return = <<<HTML
<script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}js/drag-and-drop.js"></script>
<div class="content">
    <h2>New upload for: {$gradeable->getName()}</h2>
    <p>Student RCS ID:</p>
    <p input="text"></p>
</div>
HTML;
        $return .= <<<HTML
<div class="content">
    <span style="font-style: italic">Students for this assignment blahbalba.</span>
</div>
HTML;
        return $return;
    }
}
