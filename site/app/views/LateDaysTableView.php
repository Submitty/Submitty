<?php

namespace app\views;

use app\models\gradeable\LateDays;
use app\views\AbstractView;
use app\views\admin\LateDayView;

class LateDaysTableView extends AbstractView {
    public function showLateTable(LateDays $late_days, string $hightlight_gradeable) {
        $student_gradeables = array();
        $status_array = array();
        $late_charged_array = array();
        $order_by = [
            'CASE WHEN eg.eg_submission_due_date IS NOT NULL THEN eg.eg_submission_due_date ELSE g.g_grade_released_date END'
        ];
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
            'initial_late_days' => $this->core->getConfig()->getDefaultStudentLateDays(),
            'late_day_info' => $late_days->toArray(),
            'highlight_gradeable' => $hightlight_gradeable,
            'preferred_name' => $late_days->getUser()->getPreferredFirstName()
        ]);
    }
}

