<?php

namespace app\views;

use app\views\AbstractView;
use app\views\admin\LateDayView;

class LateDaysTableView extends AbstractView {
    public function showLateTable($user_id, $g_id = NULL, $full_page) {
        $student_gradeables = array();
        $status_array = array();
        $late_charged_array = array();
        $order_by = [
            'CASE WHEN eg.eg_submission_due_date IS NOT NULL THEN eg.eg_submission_due_date ELSE g.g_grade_released_date END'
        ];
        $late_update = $this->core->getQueries()->getLateDayUpdates($user_id);
        $total_late_used = 0;
        foreach ($this->core->getQueries()->getGradeablesIterator(null, $user_id, 'registration_section', 'u.user_id', 0, $order_by) as $gradeable) {
            $student_gradeables[] = $gradeable;
            $gradeable->calculateLateDays($total_late_used);
            $status_array[] = $gradeable->getLateStatus();
            $late_charged_array[] = $gradeable->getCurrLateCharged();
        }
        $preferred_name = $this->core->getQueries()->getUserById($user_id)->getDisplayedFirstName() . " " . $this->core->getQueries()->getUserById($user_id)->getLastName();
        if($full_page){
            $this->core->getOutput()->addBreadcrumb("Late Days Summary", $this->core->buildUrl(array('component' => 'student', 'page' => 'view_late_table')));
            $template = "/LateDaysTable.twig";
        } else {
            $template = "/LateDaysTablePlugin.twig";
        }

        return $this->core->getOutput()->renderTwigTemplate($template, [
            "user_id" => $user_id,
            "student_gradeables" => $student_gradeables,
            "status_array" => $status_array,
            "late_charged_array" => $late_charged_array,
            "total_late_used" => $total_late_used,
            "g_id" => $g_id,
            "late_update" => $late_update,
            "preferred_name" => $preferred_name
        ]);
    }
}

