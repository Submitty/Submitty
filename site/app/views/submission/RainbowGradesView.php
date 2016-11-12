<?php
/**
 * Created by IntelliJ IDEA.
 * User: mpeveler
 * Date: 9/12/16
 * Time: 11:32 AM
 */

namespace app\views\submission;


use app\libraries\Core;

class RainbowGradesView {
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function showGrades($grade_file) {
        $return = <<<HTML
<div class="content">
    <h3 class="label">Grade Summary</h3>
HTML;
        $display_iris_grades_summary = $this->core->getConfig()->displayIrisGradesSummary();
        if ($display_iris_grades_summary && $grade_file !== null) {
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