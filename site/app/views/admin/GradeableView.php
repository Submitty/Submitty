<?php

namespace app\views\admin;

use app\views\AbstractView;

class GradeableView extends AbstractView {
    public function uploadConfigForm() {
        return <<<HTML
<div class="content">
    <h2>Upload Gradeable Config</h2>
    <form action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'gradeable', 'action' => 'process_upload_config'))}" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
        Upload Config: <input type="file" name="config_upload" /><br />
        <input type="submit" value="Upload" />
    </form>
</div>
HTML;

    }
}