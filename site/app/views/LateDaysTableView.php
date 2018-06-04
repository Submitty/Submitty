<?php

namespace app\views;

use app\views\AbstractView;

class LateDaysTableView extends AbstractView {
    public function showLateTable($user_id) {
        $student_gradeables = array();
        $order_by = [
            'CASE WHEN eg.eg_submission_due_date IS NOT NULL THEN eg.eg_submission_due_date ELSE g.g_grade_released_date END'
        ];
        foreach ($this->core->getQueries()->getGradeablesIterator(null, $user_id, 'registration_section', 'u.user_id', 0, $order_by) as $gradeable) {
            $student_gradeables[] = $gradeable;
        }
        $student_gradeables = json_encode($student_gradeables);

        return $this->core->getOutput()->renderTwigTemplate("/LateDaysTable.twig", [
            "user_id" => $user_id,
            "student_gradeables" => $student_gradeables
        ]);
    }
}

