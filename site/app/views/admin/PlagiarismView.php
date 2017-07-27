<?php
namespace app\views\admin;

use app\views\AbstractView;

class PlagiarismView extends AbstractView {
    public function plagiarismCompare($semester, $course, $assignment, $studenta, $studentb) {
        if (strpos($semester, '.') === FALSE || strpos($semester == '/') === FALSE) throw new \InvalidArgumentException("Invalid semester");
        if (strpos($course, '.') === FALSE || strpos($course == '/') == FALSE) throw new \InvalidArgumentException("Invalid course");
        if (strpos($assignment, '.') === FALSE || strpos($assignment == '/') == FALSE) throw new \InvalidArgumentException("Invalid assignment");
        if (strpos($studenta, '.') === FALSE || strpos($studenta == '/') == FALSE) throw new \InvalidArgumentException("Invalid assignment");
        if (strpos($studentb, '.') === FALSE || strpos($studentb == '/') == FALSE) throw new \InvalidArgumentException("Invalid assignment");
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
        if (strpos($semester, '.') === FALSE || strpos($semester == '/') === FALSE) throw new \InvalidArgumentException("Invalid semester");
        if (strpos($course, '.') === FALSE || strpos($course == '/') == FALSE) throw new \InvalidArgumentException("Invalid course");
        if (strpos($assignment, '.') === FALSE || strpos($assignment == '/') == FALSE) throw new \InvalidArgumentException("Invalid assignment");
        $return = "";
        $return .= <<<HTML
<div class="content">
<h1 class="centered">Plagiarism Detection - $assignment</h1>
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
<div class="content">
<h1 style="text-align: center">Plagiarism Detection</h1>
<ul>
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
