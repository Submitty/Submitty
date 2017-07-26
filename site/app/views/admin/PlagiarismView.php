<?php
namespace app\views\admin;

use app\views\AbstractView;

class PlagiarismView extends AbstractView {
    public function plagiarismIndex() {
        $return = "";
        $return .= <<<HTML
<div class="content">
    <iframe src="/var/local/submitty/courses/f17/development/plagiarism/var/local/submitty/courses/f17/development/submissions/cpp_cats/index.html"></iframe>
</div>
HTML;
        return $return;
    }
}
