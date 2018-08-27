<?php

namespace app\views;

use app\libraries\GradeableType;
use app\models\Gradeable;
use app\views\AbstractView;
use app\views\admin\LateDayView;

class LateDaysTableView extends AbstractView {
    public function showLateTable($user_id, $g_id = NULL, $full_page) {
        $student_gradeables = array();
        $status_array = array();
        $late_charged_array = array();
        //TODO: Move all this logic to the controller
        $order_by = [
            'CASE WHEN eg.eg_submission_due_date IS NOT NULL THEN eg.eg_submission_due_date ELSE g.g_grade_released_date END'
        ];
        $late_update = $this->core->getQueries()->getLateDayUpdates($user_id);
        $total_late_used = 0;
        foreach ($this->core->getQueries()->getGradeablesIterator(null, $user_id, 'registration_section', 'u.user_id', 0, $order_by) as $gradeable) {
            $gradeable->calculateLateDays($total_late_used);

            if (!$this->filterCanView($gradeable)) {
                continue;
            }

            $student_gradeables[] = $gradeable;
            $status_array[] = $gradeable->getLateStatus();
            $late_charged_array[] = $gradeable->getCurrLateCharged();
        }
        $preferred_name = $this->core->getQueries()->getUserById($user_id)->getDisplayedFirstName() . " " . $this->core->getQueries()->getUserById($user_id)->getLastName();
        if($full_page){
            $this->core->getOutput()->addBreadcrumb("My Late Days");
            $template = "/LateDaysTable.twig";
        } else {
            $template = "/LateDaysTablePlugin.twig";
        }

        $table_data =
          $this->core->getOutput()->renderTwigTemplate($template, [
            "user_id" => $user_id,
            "student_gradeables" => $student_gradeables,
            "status_array" => $status_array,
            "late_charged_array" => $late_charged_array,
            "total_late_used" => $total_late_used,
            "g_id" => $g_id,
            "late_update" => $late_update,
            "preferred_name" => $preferred_name
        ]);
        if (!$full_page) {
          $table_data = "<hr><h2>Late Day Usage by ".$preferred_name." (".$user_id.")</h2><br>".$table_data;
        }
        return $table_data;
    }


    /**
     * Test if the current user is allowed to view this gradeable
     * @param Gradeable $gradeable
     * @return bool True if they are
     */
    private function filterCanView(Gradeable $gradeable) {
        //TODO: Move all this logic to the controller

        $user = $this->core->getUser();

        //Remove incomplete gradeables for non-instructors
        if (!$user->accessAdmin() && $gradeable->getType() == GradeableType::ELECTRONIC_FILE &&
            !$gradeable->hasConfig()) {
            return false;
        }

        // student users should only see electronic gradeables -- NOTE: for now, we might change this design later
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE && !$user->accessGrading()) {
            return false;
        }

        // if student view false, never show
        if (!$gradeable->getStudentView() && !$user->accessGrading()) {
            return false;
        }

        //If we're not instructor and this is not open to TAs
        $date = new \DateTime("now", $this->core->getConfig()->getTimezone());
        if ($gradeable->getTAViewDate() > $date && !$user->accessAdmin()) {
            return false;
        }
        if ($gradeable->getOpenDate() > $date && !$user->accessGrading()) {
            return false;
        }

        return true;
    }

}

