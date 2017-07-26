<?php
namespace app\views\admin;

use app\views\AbstractView;

class PlagiarismView extends AbstractView {
    public function plagiarismIndex() {
        $return = "";
        $return .= <<<HTML
<div class="content">
HTML;
    readFile("/var/local/submitty/courses/f17/development/plagiarism/var/local/submitty/courses/f17/development/submissions/cpp_cats/index.html");
        $return .= <<<HTML
</div>
HTML;
        return $return;
    }
}
