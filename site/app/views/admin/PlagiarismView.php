<?php
namespace app\views\admin;

use app\views\AbstractView;

class PlagiarismView extends AbstractView {
    public function plagiarismCompare($semester, $course, $assignment, $studenta, $studentb) {
        $return = "";
        $return .= <<<HTML
<div class="content" style="height: 85vh">
HTML;
        $return .= file_get_contents("/var/local/submitty/courses/$semester/$course/plagiarism/report/var/local/submitty/courses/$semester/$course/submissions/$assignment/compare/" . $studenta . "_" . $studentb . ".html");
        $return .= <<<HTML
</div>
HTML;
        return $return;
    }

    public function plagiarismIndex($semester, $course, $assignment) {
        $return = "";
        $return .= <<<HTML
<div class="content">
HTML;
        $return .= file_get_contents("/var/local/submitty/courses/$semester/$course/plagiarism/report/var/local/submitty/courses/$semester/$course/submissions/$assignment/index.html");
        $return .= <<<HTML
</div>
HTML;
        return $return;
    }

    public function plagiarismTree($semester, $course, $assignments) {
        $return = "";
        $return .= <<<HTML
<div class="content"><ul>
HTML;
        foreach ($assignments as $assignment) {
            $return .= "<li><a href=\"{$this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism', 'action' => 'index', 'assignment' => $assignment))}\">$assignment</a></li>";
        }
        $return .= <<<HTML
</ul></div>
HTML;
        return $return;
    }
}
