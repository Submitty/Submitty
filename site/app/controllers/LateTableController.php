<?php

namespace app\controllers;


use app\controllers\AbstractController;
use app\libraries\Core;

class LateTableController extends AbstractController {
    public function run() {
        switch($_REQUEST['action']) {
            case "late_table":
                var_dump("HI");
                break;
        }
        $student_gradeables = array();
        $order_by = [
            'CASE WHEN eg.eg_submission_due_date IS NOT NULL THEN eg.eg_submission_due_date ELSE g.g_grade_released_date END'
        ];
        foreach ($this->core->getQueries()->getGradeablesIterator(null, $user_id, 'registration_section', 'u.user_id', 0, $order_by) as $gradeable) {
            $student_gradeables[] = $gradeable;
        }

        $student_gradeables = json_encode($student_gradeables);

        $this->core->getOutput()->renderOutput(array('LateDaysTableView'), 'showLateTable', $student_gradeables);
    }
}