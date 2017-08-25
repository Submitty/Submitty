<?php

namespace app\views\submission;

use app\views\AbstractView;

class RainbowGradesView extends AbstractView {
    public function showGrades($grade_file) {
        $return = <<<HTML
<div class="content">
    <h3 class="label">Grade Summary</h3>
HTML;
        $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();
        if ($display_rainbow_grades_summary && $grade_file !== null) {
            $return .= <<<HTML
    {$grade_file}
HTML;
        }
        else {
            $return .= <<<HTML
    No grades are available...
HTML;
        }
        $return .= <<<HTML
</div>
HTML;
        return $return;
    }
}