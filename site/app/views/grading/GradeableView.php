<?php

namespace app\views\grading;
use app\models\Gradeable;
use app\views\AbstractView;

class GradeableView extends AbstractView {

    public function renderComponentTable(Gradeable $gradeable, string $disabled) {
        $user = $gradeable->getUser();
        $peer = false;
        if($gradeable->getPeerGrading() && $this->core->getUser()->getGroup() == 4) {
            $peer = true;
        }

        $break_onclick = "";
        if ($disabled === "disabled") {
            $break_onclick = "return false;";
        }



        // if use student components, get the values for pages from the student's submissions
        $files = $gradeable->getSubmittedFiles();
        $student_pages = array();
        foreach ($files as $filename => $content) {
            if ($filename == "student_pages.json") {
                $path = $content["path"];
                $student_pages = FileUtils::readJsonFile($content["path"]);
            }
        }

        $c = 1;
        $precision = floatval($gradeable->getPointPrecision());
        $num_questions = count($gradeable->getComponents());
        $your_user_id = $this->core->getUser()->getId();

        foreach ($gradeable->getComponents() as $component) {
            if($peer && !is_array($component)) continue;
            $question = null;
            /* @var GradeableComponent $question */
            $show_graded_info = true;
            $num_peer_components = 0;
            if(is_array($component)) {
                $num_peer_components = count($component);
                foreach($component as $cmpt) {
                    if($cmpt->getGrader() == null) {
                        $question = $cmpt;
                        break;
                    }
                    if($cmpt->getGrader()->getId() == $this->core->getUser()->getId()) {
                        $question = $cmpt;
                        break;
                    }
                }
                if($question === null) {
                    $show_graded_info = false;
                    $question = $component[0];
                }
            }
            else {
                $question = $component;
            }
            if($question->getOrder() == -1) continue;
            $lower_clamp = $question->getLowerClamp();
            $default = $question->getDefault();
            $upper_clamp = $question->getUpperClamp();
            $max = 10000;
            $min = -10000;
            // hide auto-grading if it has no value
            if (($question->getScore() == 0) && (substr($question->getTitle(), 0, 12) === "AUTO-GRADING")) {
                $question->setScore(floatval($gradeable->getGradedAutograderPoints()));
            }

            if(substr($question->getTitle(), 0, 12) === "AUTO-GRADING") {
                $disabled = 'disabled';
            }

            if((!$question->getHasMarks() && !$question->getHasGrade()) || !$show_graded_info) {
                $initial_text = "Click me to grade!";
            }
            else if($show_graded_info) {
                $nl = "<br>";
                $initial_text = $question->getGradedTAComments($nl, false, $gradeable);
            }
            $question_points = $question->getGradedTAPoints();
            if((!$question->getHasMarks() && !$question->getHasGrade()) || !$show_graded_info) {
                $question_points = " ";
            }
            $background = "";
            if ($question_points > $question->getMaxValue()) {
                $background = "background-color: #D8F2D8;";
            }
            else if ($question_points < 0) {
                $background = "background-color: #FAD5D3;";
            }
            $grader_id = "";
            $displayVerifyUser = false;
            if($question->getGrader() === null || !$show_graded_info) {
                $grader_id = "Ungraded!";
                $graded_color = "";
            } else {
                $grader_id = "Graded by " . $question->getGrader()->getId();
                if($question->getGradedTAPoints()==$question->getMaxValue()){
                    $graded_color = " background-color: #006600";
                }
                else if($question->getGradedTAPoints()==0){
                    $graded_color = " background-color: #c00000";
                }
                else{
                    $graded_color = " background-color: #eac73d";
                }
                if($this->core->getUser()->getId() !== $question->getGrader()->getId() && $this->core->getUser()->accessFullGrading()){
                    $displayVerifyUser = true;
                }
            }
            $penalty = !(intval($question->getMaxValue()) >= 0);
            $message = htmlentities($question->getTitle());
            $message = "<b>{$message}</b>";  // {$num_peer_components}</b>";
            if ($question->getGradedVersion() != -1 && $gradeable->getActiveVersion() != $question->getGradedVersion()) {
                $message .= "<span id='wrong_version_{$c}' style='color:rgb(200, 0, 0); font-weight: bold; font-size:medium;'>  " . "Please edit or ensure that comments from version " . $question->getGradedVersion() . " still apply.</span>";
            }
            $note = htmlentities($question->getTaComment());
            if ($note != "") {
                $note = "<br/><div style='margin-bottom:5px; color:#777;'><i><b>Note to TA: </b>" . $note . "</i></div>";
            }
            $page = intval($question->getPage());
            // if the page is determined by the student json
            if ($page == -1) {
                // usually the order matches the json
                if ($student_pages[intval($question->getOrder())]["order"] == intval($question->getOrder())) {
                    $page = intval($student_pages[intval($question->getOrder())]["page #"]);
                }
                // otherwise, iterate through until the order matches
                else {
                    foreach ($student_pages as $student_page) {
                        if ($student_page["order"] == intval($question->getOrder())) {
                            $page = intval($student_page["page #"]);
                            break;
                        }
                    }
                }
            }
            if ($page > 0) {
                $message .= "<i> Page #: " . $page . "</i>";
            }

            //get the grader's id if it exists
            if($displayVerifyUser){
                $return .= <<<HTML
                            <span style="display: inline; color: red;">
                            <input type="button" class = "btn btn-default" onclick="verifyMark('{$gradeable->getId()}','{$question->getId()}','{$user->getAnonId()}')" value = "Verify Grader"/>
                            </span>
HTML;
            }
            $student_note = htmlentities($question->getStudentComment());
            if ($student_note != ''){
                $student_note = "<div style='margin-bottom:5px; color:#777;'><i><b>Note to Student: </b>" . $student_note . "</i></div>";
            }

            //gets the initial point value and text


            if((!$question->getHasMarks() && !$question->getHasGrade()) || !$show_graded_info) {
                $initial_text = "Click me to grade!";
            }
            else if($show_graded_info) {
                $nl = "<br>";
                $initial_text = $question->getGradedTAComments($nl, false, $gradeable);
            }


            $question_points = $question->getGradedTAPoints();

            if((!$question->getHasMarks() && !$question->getHasGrade()) || !$show_graded_info) {
                $question_points = " ";
            }

            $background = "";
            if ($question_points > $question->getMaxValue()) {
                $background = "background-color: #D8F2D8;";
            }
            else if ($question_points < 0) {
                $background = "background-color: #FAD5D3;";
            }

            $d = 0;
            $first = true;
            $noChange = "";
            $has_custom_mark = false;
            if (($question->getScore() == 0 && $question->getComment() == "") || !$show_graded_info) {
                $has_custom_mark = false;
            }
            else {
                $has_custom_mark = true;
            }
            $icon_mark = ($has_custom_mark === true) ? "fa-square" : "fa-square-o";
            if(!$peer) {
                $return .= <<<HTML
                        <span colspan="4">
                            <span style="cursor: pointer;" onclick="{$break_onclick} addMark(this, {$c}, '', {$min}, {$max}, '{$precision}', '{$gradeable->getId()}', '{$user->getAnonId()}', {$gradeable->getActiveVersion()}, {$question->getId()}, '{$your_user_id}'); return false;"><i class="fa fa-plus-square " aria-hidden="true"></i>
                            Add New Common Mark</span>
                        </span>
HTML;
            }
            $c++;
        }

        $overallComment = htmlentities($gradeable->getOverallComment(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->core->getOutput()->addInternalJs('ta-grading-mark.js');
        $this->core->getOutput()->addInternalJs('ta-grading.js');

        return $return;
    }

}
