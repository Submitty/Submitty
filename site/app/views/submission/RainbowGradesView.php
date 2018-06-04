<?php

namespace app\views\submission;

use app\views\AbstractView;

class RainbowGradesView extends AbstractView {
    public function showGrades($grade_file) {
        $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();
        return $this->core->getOutput()->renderTwigTemplate("submission/RainbowGrades.twig", [
            "show_summary" => $display_rainbow_grades_summary && $grade_file !== null,
            "grade_file" => $grade_file
        ]);
    }
}