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



        $num_questions = count($gradeable->getComponents());

        // if use student components, get the values for pages from the student's submissions
        $files = $gradeable->getSubmittedFiles();
        $student_pages = array();
        foreach ($files as $filename => $content) {
            if ($filename == "student_pages.json") {
                $path = $content["path"];
                $student_pages = FileUtils::readJsonFile($content["path"]);
            }
        }

        $return .= <<<HTML
    <div class="inner-container">
        <table class="ta-rubric-table ta-rubric-table-background" id="rubric-table" data-gradeable_id="{$gradeable->getId()}" data-user_id="{$user->getAnonId()}" data-active_version="{$gradeable->getActiveVersion()}" data-num_questions="{$num_questions}" data-your_user_id="{$this->core->getUser()->getId()}">
            <tbody>
HTML;

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
            $return .= <<<HTML
                <div id="title-{$c}" class="box" style="cursor: pointer" onclick="{$break_onclick}; toggleMark({$c}, true);">
                <div class="box-title">
<span id="gradebar-{$c}" style="{$graded_color}"; "white-space:nowrap; vertical-align:middle; text-align:center; {$background}" colspan="1" class="badge{$graded_color}">
                        <strong><span id="grade-{$c}" name="grade-{$c}" class="grades" data-lower_clamp="{$question->getLowerClamp()}" data-default="{$question->getDefault()}" data-max_points="{$question->getMaxValue()}" data-upper_clamp="{$question->getUpperClamp()}"> {$question_points}</span> / {$question->getMaxValue()}</strong>
                    </span>
HTML;
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
            $return .= <<<HTML
                    <span style="font-size: 12px;" colspan="3" data-changebg="true">
                        <b><span id="progress_points-{$c}" style="display: none;" data-changedisplay1="true"></span></b>
                        {$message}
                        <span style="float: right;">
HTML;
            if($displayVerifyUser){
                $return .= <<<HTML
                            <span style="display: inline; color: red;">
                            <input type="button" class = "btn btn-default" onclick="verifyMark('{$gradeable->getId()}','{$question->getId()}','{$user->getAnonId()}')" value = "Verify Grader"/>
                            </span>
HTML;
            }
            $return .= <<<HTML
                            <span id="graded-by-{$c}" style="font-style: italic; padding-right: 10px;">{$grader_id}</span>
                         <!--  <span id="save-mark-{$c}" style="cursor: pointer;  display: none;" data-changedisplay1="true"> <i class="fa fa-check" style="color: green;" aria-hidden="true" onclick="{$break_onclick}; closeMark({$c}, true);">Done</i> </span> -->
                        </span>
                        </span> <span id="ta_note-{$c}" style="display: none;" data-changedisplay1="true"> {$note}</span>
                        <span id="page-{$c}" style="display: none;">{$page}</span>
                        <span style="float: right;">
                            <span id="save-mark-{$c}" style="cursor: pointer;  display: none; font-size: 12px; display: none; width: 5%;" colspan="0" data-changedisplay1="true"> <i class="fa fa-check" style="color: green;" aria-hidden="true">Done</i> </span>
                        </span>
HTML;
            $student_note = htmlentities($question->getStudentComment());
            if ($student_note != ''){
                $student_note = "<div style='margin-bottom:5px; color:#777;'><i><b>Note to Student: </b>" . $student_note . "</i></div>";
            }
            $return .= <<<HTML
                        <span id="student_note-{$c}" style="display: none;" data-changedisplay1="true">{$student_note}</span>
           <!--         <span id="title-cancel-{$c}" style="font-size: 12px; display: none; width: 5%;" colspan="0" data-changebg="true" data-changedisplay1="true">
                            <span id="cancel-mark-{$c}" onclick="{$break_onclick}; closeMark(${c}, false);" style="cursor: pointer; float: right;"> <i class="fa fa-times" style="color: red;" aria-hidden="true">Cancel</i></span>
                    </span> -->
HTML;

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

            $return .= <<<HTML
                <div id="summary-{$c}" style="#FBFCFC" display="none" data-changedisplay2="true" data-question_id="{$question->getId()}" data-min="{$min}" data-max="{$max}" data-precision="{$precision}">
                    <span style="width:98%;" colspan="4">
                        <div id="rubric-{$c}">
                            <span id="rubric-textarea-{$c}" name="comment-{$c}" rows="4" style="width:95%; height:100%; min-height:20px; font-family: Source Sans Pro;  float:left; cursor: pointer;">{$initial_text}</span>
                        </div>
                    </span>
                </div></div>
                </div>
                <div class="box" id="marks-parent-{$c}" style="display: none; background-color: #e6e6e6" data-question_id="{$question->getId()}" data-changedisplay1="true">
                <div class="box-title">
                </div></div>
                <div class="box" id="marks-extra-{$c}" style="display: none; background-color: #e6e6e6" data-question_id="{$question->getId()}" data-changedisplay1="true">
                <div class="box-title">
HTML;

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
            $return .= <<<HTML
                    <div class="box" id="mark_custom_id-{$c}" name="mark_custom_{$c}">
                    <div class="box-title">
                        <span colspan="1" style="text-align: center; white-space: nowrap;">
                        <span onclick=""> <i class="fa {$icon_mark} mark fa-lg" name="mark_icon_{$c}_custom" style="visibility: visible; cursor: pointer; position: relative; top: 2px;"></i>&nbsp;</span>
                        <input name="mark_points_custom_{$c}" type="number" step="{$precision}" onchange="fixMarkPointValue(this); checkIfSelected(this); updateProgressPoints({$c});" value="{$question->getScore()}" min="{$min}" max="{$max}" style="width: 50%; resize:none;  min-width: 50px; max-width: 70px;">
                        </span>
                        <span colspan="3" style="white-space: nowrap;">
                            Custom: <textarea name="mark_text_custom_{$c}" onkeyup="autoResizeComment(event); checkIfSelected(this);" onchange="checkIfSelected(this); updateProgressPoints({$c});" cols="100" rows="1" placeholder="Custom message for student..." style="width:80.4%; resize:none;">{$question->getComment()}</textarea>
                        </span>
                    </div></div>
                </div></div>
HTML;
            $c++;
        }
        if ($peer) {
            $break_onclick = 'return false;';
            $disabled = 'disabled';
        }
        $overallComment = htmlentities($gradeable->getOverallComment(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $return .= <<<HTML
                <div class="box" style="background-color:#E9EFEF;">
                <div class="box-title">
                    <div id="title-general" onclick="{$break_onclick}; toggleGeneralMessage(true);" data-changebg="true">
                        <b>General Comment</b>
                        <span style="float: right;">
                            <span id="save-mark-general" style="cursor: pointer;  display: none;" data-changedisplay1="true"> <i class="fa fa-check" style="color: green;" aria-hidden="true">Done</i> </span>
                        </span>
                    </div>
                    <span id="title-cancel-general" style="font-size: 12px; display: none; width: 5%" colspan="0" data-changebg="true" data-changedisplay1="true">
                        <span id="cancel-mark-general" onclick="{$break_onclick}; closeGeneralMessage(false);" style="cursor: pointer; display: none; float: right;" data-changedisplay1="true"> <i class="fa fa-times" style="color: red;" aria-hidden="true">Cancel</i></span>
                    </span>
                </div><div>
                <div class="box" id="summary-general" style="" onclick="{$break_onclick}; openGeneralMessage();" data-changedisplay2="true">
                <div class"box-title">    
                    <span style="white-space:nowrap; vertical-align:middle; text-align:center" colspan="1">
                    </span>
                    <span style="width:98%;" colspan="3">
                        <div id="rubric-custom">
                            <span id="rubric-textarea-custom" name="comment-custom" rows="4" class="rubric-textarea">{$overallComment}</span>
                        </div>
                    </span>
                </div></div>
                <span id="extra-general" style="display: none" colspan="4" data-changebg="true" data-changedisplay1="true">
                    <div class="box">
                    <div class="box-title">
                        <span colspan="4">
                            <textarea id="comment-id-general" name="comment-general" rows="5" style="width:98%; height:100%; min-height:100px; resize:none; float:left;" onkeyup="autoResizeComment(event);" placeholder="Overall message for student about the gradeable..." comment-position="0" {$disabled}>{$overallComment}</textarea>
                        </span>
                    </div></div></div></div>
                </span>
HTML;

        if ($peer) {
            $total_points = $gradeable->getTotalNonHiddenNonExtraCreditPoints() + $gradeable->getTotalPeerGradingNonExtraCredit();
        }
        else {
            $total_points = $gradeable->getTotalAutograderNonExtraCreditPoints() + $gradeable->getTotalTANonExtraCreditPoints();
        }
        //Must replace the 0 below
        $return .= <<<HTML
                 <div class="box">
                <div class="box-title">
                    <span style="background-color: #EEE; border-left: 1px solid #EEE; border-top:5px #FAA732 solid;" colspan="1"><strong>TOTAL</strong></td>
                    <span style="background-color: #EEE; border-top:5px #FAA732 solid;" colspan="1"><strong id="score_total"> 0/ {$total_points}&emsp;&emsp;&emsp;
                        AUTO-GRADING {$gradeable->getGradedAutograderPoints()} / {$gradeable->getTotalAutograderNonExtraCreditPoints()}</strong></td>
                    <span style="background-color: #EEE; border-left: 1px solid #EEE; border-top:5px #FAA732 solid;" colspan="2"></td>
                </div></div>
            </span>
        </table>
HTML;
        $return .= <<<HTML
        <div style="width:100%;">
HTML;
        $now = new \DateTime('now');
        $return .= <<<HTML
            </form>
        </div>
HTML;
        $this->core->getOutput()->addInternalJs('ta-grading-mark.js');

        $return .= <<<HTML
<script type="text/javascript">
//
// This is needed to resolve conflicts between Chrome and other browsers
//   where Chrome can only do synchronous ajax calls on 'onbeforeunload'
//   and other browsers can only do synchronous ajax calls on 'onunload'
//
// Reference:
//    https://stackoverflow.com/questions/4945932/window-onbeforeunload-ajax-request-in-chrome
//
var __unloadRequestSent = false;
function unloadSave() {
    if (!__unloadRequestSent) {
        __unloadRequestSent = true;
        saveLastOpenedMark('{$gradeable->getId()}' ,'{$user->getAnonId()}', {$gradeable->getActiveVersion()}, '{$your_user_id}', '-1', false, function() {
        }, function() {
            // Unable to save so try saving at a different time
            __unloadRequestSent = false;
        });
    }
}
// Will work for Chrome
window.onbeforeunload = unloadSave;
// Will work for other browsers
window.onunload = unloadSave;
</script>
HTML;
        $this->core->getOutput()->addInternalJs('ta-grading.js');
        $this->core->getOutput()->addInternalJs('ta-grading-mark.js');

        return $return;
    }

}
