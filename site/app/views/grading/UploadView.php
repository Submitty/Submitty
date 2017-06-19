<?php

namespace app\views\grading;

use app\views\AbstractView;

class UploadView extends AbstractView {

	/**
     *
     * @param Gradeable $gradeable
     *
     * @return string
     */
    public function showUpload($gradeable) {
    	$return = <<<HTML
<div class="content">
    <h2>New submission for: {$gradeable->getName()}</h2>
    <div class="sub">
</div>
HTML;
        return $return;
    }

}
