<?php
namespace app\views\admin;

use app\views\AbstractView;

class PlagiarismView extends AbstractView {
    public function plagiarismCompare($semester, $course, $assignment, $studenta, $studentb) {
        if (strpos($semester, '.') || strpos($semester, '/')) throw new \InvalidArgumentException("Invalid semester");
        if (strpos($course, '.') || strpos($course, '/')) throw new \InvalidArgumentException("Invalid course");
        if (strpos($assignment, '.') || strpos($assignment, '/')) throw new \InvalidArgumentException("Invalid assignment");
        if (strpos($studenta, '.') || strpos($studenta, '/')) throw new \InvalidArgumentException("Invalid assignment");
        if (strpos($studentb, '.') || strpos($studentb, '/')) throw new \InvalidArgumentException("Invalid assignment");

        $contents = file_get_contents("/var/local/submitty/courses/$semester/$course/plagiarism/report/var/local/submitty/courses/$semester/$course/submissions/$assignment/compare/" . $studenta . "_" . $studentb . ".html");
        return $this->core->getOutput()->renderTwigTemplate("admin/plagiarism/Compare.twig", [
            "contents" => $contents
        ]);
    }

    public function plagiarismIndex($semester, $course, $assignment) {
        if (strpos($semester, '.') || strpos($semester, '/')) throw new \InvalidArgumentException("Invalid semester");
        if (strpos($course, '.') || strpos($course, '/')) throw new \InvalidArgumentException("Invalid course");
        if (strpos($assignment, '.') || strpos($assignment, '/')) throw new \InvalidArgumentException("Invalid assignment");

        $contents = file_get_contents("/var/local/submitty/courses/$semester/$course/plagiarism/report/var/local/submitty/courses/$semester/$course/submissions/$assignment/index.html");
        return $this->core->getOutput()->renderTwigTemplate("admin/plagiarism/Index.twig", [
            "contents" => $contents
        ]);
    }

    public function plagiarismTree($semester, $course, $assignments) {
        return $this->core->getOutput()->renderTwigTemplate("admin/plagiarism/Tree.twig", [
            "assignments" => $assignments
        ]);
    }
}
