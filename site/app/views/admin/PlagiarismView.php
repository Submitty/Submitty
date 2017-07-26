<?php
namespace app\views\admin;

use app\views\AbstractView;

class PlagiarismView extends AbstractView {
    public function plagiarismIndex() {
        $return = "";
        $return .= <<<HTML
<div class="content">
HTML;
        $return .= file_get_contents("/var/local/submitty/courses/f17/development/plagiarism/report/var/local/submitty/courses/f17/development/submissions/cpp_cats/index.html");
        $return .= <<<HTML
</div>
HTML;
        return $return;
    }

    public function plagiarismCompare($studenta, $studentb) {
        $return = "";
        $return .= <<<HTML
<div class="content">
HTML;
        $return .= file_get_contents("/var/local/submitty/courses/f17/development/plagiarism/report/var/local/submitty/courses/f17/development/submissions/cpp_cats/compare/" . $studenta . "_" . $studentb . ".html");
        $return .= <<<HTML
</div>
HTML;
        return $return;
    }
}
