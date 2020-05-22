<?php

namespace app\views\submission;

use app\views\AbstractView;

class RainbowGradesView extends AbstractView {
    public function showGrades($grade_file) {
        $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();
        $this->core->getOutput()->addBreadcrumb('Rainbow Grades');
        $this->core->getOutput()->addInternalCss('rainbow-grades.css');
        return $this->core->getOutput()->renderTwigTemplate("submission/RainbowGrades.twig", [
            "show_summary" => $display_rainbow_grades_summary && $grade_file !== null,
            "grade_file" => $grade_file
        ]);
    }

    public function showStudentToInstructor($user, $grade_file) {
        $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();
        $manage_url = $this->core->buildCourseUrl(['users']);
        $this->core->getOutput()->addBreadcrumb('Manage Students', $manage_url);
        $this->core->getOutput()->addBreadcrumb($user->getDisplayFullName());
        $this->core->getOutput()->addInternalCss('rainbow-grades.css');
        
        return $this->core->getOutput()->renderTwigTemplate("submission/RainbowGrades.twig", [
            "show_summary" => $display_rainbow_grades_summary && $grade_file !== null,
            "grade_file" => $grade_file,
            "extra_label" => "For " . $user->getDisplayFullName()
        ]);
    }
}
