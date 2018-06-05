<?php

namespace app\views;

use app\models\Gradeable;
use app\views\AbstractView;
use app\libraries\FileUtils;

class AutogradingView extends AbstractView {
    /**
     * @param Gradeable $gradeable
     * @param bool      $show_hidden
     *
     * @return string
     * @throws \Exception
     */
    public function showResults(Gradeable $gradeable, $show_hidden=false) {
        $return = "";
        $current_version = $gradeable->getCurrentVersion();
        $has_badges = false;
        $num_visible_testcases = 0;
        $gradeable_name = $gradeable->getId();
        $who_id = $gradeable->getUser()->getId();

        foreach ($gradeable->getTestcases() as $testcase) {
            if ($testcase->viewTestcase()) {
                $num_visible_testcases++;
            }
        }
        $display_total = ($num_visible_testcases > 1) ? "block" : "none";
        if ($num_visible_testcases == 1) {
            $return.= <<<HTML
<script type="text/javascript">
    $(document).ready(function() {
        loadTestcaseOutput('testcase_0', '$gradeable_name', '$who_id', '0');
    });
</script>
HTML;
        }
        if ($current_version->getNonHiddenTotal() >= 0) {
            $has_badges = true;
            if ($current_version->getNonHiddenTotal() >= $gradeable->getNormalPoints()) {
                $background = "green-background";
            }
            else if ($current_version->getNonHiddenTotal() > 0) {
                $background = "yellow-background";
            }
            else {
                $background = "red-background";
            }
            if (($current_version->getNonHiddenNonExtraCredit() + $current_version->getHiddenNonExtraCredit() > $gradeable->getNormalPoints()) && $show_hidden) {
                $return .= <<<HTML
<div class="box" style="display: {$display_total};">
    <div class="box-title">
        <span class="badge {$background}">{$current_version->getNonHiddenTotal()} / {$gradeable->getNormalPoints()}</span>
        <h4>Total (No Hidden Points)</h4>
    </div>
</div>
HTML;
                $all_autograder_points = $current_version->getNonHiddenTotal() + $current_version->getHiddenTotal();
                if ($all_autograder_points >= $gradeable->getTotalAutograderNonExtraCreditPoints()) {
                    $background = "green-background";
                }
                else if ($all_autograder_points > 0) {
                    $background = "yellow-background";
                }
                else {
                    $background = "red-background";
                }
                $return .= <<<HTML
<div class="box" style="background-color:#D3D3D3;" style="display: {$display_total};">
    <div class="box-title">
        <span class="badge {$background}">{$all_autograder_points} / {$gradeable->getTotalAutograderNonExtraCreditPoints()}</span>
        <h4>Total (With Hidden Points)</h4>
    </div>
</div>
HTML;
            }
            else {
                //check if instructor grades exist and change title, display hidden points when TA grades are released (if hidden tests exist)
                $totalTitle = ($gradeable->hasGradeFile()) ? "Autograding Subtotal" : "Total";
                $autoGradingPoints = $current_version->getNonHiddenTotal();
                $all_autograder_points = $autoGradingPoints + $current_version->getHiddenTotal();
                $display_hidden = "none";
                $hidden_background = '';
                if($gradeable->taGradesReleased()){
                    foreach ($gradeable->getTestcases() as $testcase) {
                        if(!$testcase->viewTestcase()) continue;
                        if($testcase->isHidden()){
                            $display_hidden = "block";
                            break;
                        }
                    }
                    if($display_hidden === "block"){
                        if ($all_autograder_points >= $gradeable->getTotalAutograderNonExtraCreditPoints()) {
                            $hidden_background = "green-background";
                        }
                        else if ($all_autograder_points > 0) {
                            $hidden_background = "yellow-background";
                        }
                        else {
                            $hidden_background = "red-background";
                        }
                    }
                }
                $return .= <<<HTML
<div class="box" style="display: {$display_total};">
    <div class="box-title">
        <span class="badge {$background}">{$autoGradingPoints} / {$gradeable->getNormalPoints()}</span>
        <h4>{$totalTitle}</h4>
    </div>
</div>
<div class="box" style="display: {$display_hidden}">
    <div class="box-title">
        <span class="badge {$hidden_background}">{$all_autograder_points} / {$gradeable->getTotalAutograderNonExtraCreditPoints()}</span>
        <h4>{$totalTitle} <i>(With Hidden Points)</i></h4>
    </div>
</div>
HTML;
            }
        }
        $count = 0;
        $display_box = ($num_visible_testcases == 0) ? "block" : "none";
        foreach ($gradeable->getTestcases() as $testcase) {
            if (!$testcase->viewTestcase()) {
                continue;
            }
            $background = "";
            $hidden_title = "";
            if ($testcase->isHidden() && $show_hidden) {
                $background = "style=\"background-color:#D3D3D3;\"";
                $hidden_title = "HIDDEN: ";
            }
            $div_click = "";
            if ($testcase->hasDetails() && (!$testcase->isHidden() || $show_hidden)) {
                $div_click = "style=\"cursor: pointer;\"";
            }
            $div_to_populate = "testcase_".$count;

            $return .= <<<HTML
<div class="box" {$background}>
    <div class="box-title" {$div_click} onclick="loadTestcaseOutput('$div_to_populate', '$gradeable_name', '$who_id', '$count')";>
HTML;
            if ($testcase->hasDetails() && (!$testcase->isHidden() || $show_hidden)) {
                $return .= <<<HTML
        <div style="float:right; color: #0000EE; text-decoration: underline">Details</div>
HTML;
            }
            if ($testcase->hasPoints()) {
                if ($testcase->isHidden() && !$show_hidden) {
                    if($gradeable->taGradesReleased()){
                        $hiddenPoints = ($testcase->isExtraCredit()) ? '<br/>+'. $testcase->getPointsAwarded() : '<br>'.$testcase->getPointsAwarded() . " / " . $testcase->getPoints();
                    }else{
                        $hiddenPoints = "";
                    }

                    $return .= <<<HTML
        <div class="badge">Hidden {$hiddenPoints} </div>
HTML;
                }
                else {
                    $showed_badge = false;
                    $background = "";
                    if ($testcase->isExtraCredit()) {
                        if ($testcase->getPointsAwarded() > 0) {
                            $showed_badge = true;
                            $background = "green-background";
                            $return .= <<<HTML
        <div class="badge {$background}"> &nbsp; +{$testcase->getPointsAwarded()} &nbsp;</div>
HTML;
                        }
                    }
                    else if ($testcase->getPoints() > 0) {
                        if ($testcase->getPointsAwarded() >= $testcase->getPoints()) {
                            $background = "green-background";
                        }
                        else if ($testcase->getPointsAwarded() < 0.5 * $testcase->getPoints()) {
                            $background = "red-background";
                        }
                        else {
                            $background = "yellow-background";
                        }
                        $showed_badge = true;
                        $return .= <<<HTML
        <div class="badge {$background}">{$testcase->getPointsAwarded()} / {$testcase->getPoints()}</div>
HTML;
                    }
                    else if ($testcase->getPoints() < 0) {
                        if ($testcase->getPointsAwarded() < 0) {
                            if ($testcase->getPointsAwarded() < 0.5 * $testcase->getPoints()) {
                                $background = "red-background";
                            }
                            else if ($testcase->getPointsAwarded() < 0) {
                                $background = "yellow-background";
                            }
                            $showed_badge = true;
                            $return .= <<<HTML
        <div class="badge {$background}"> &nbsp; {$testcase->getPointsAwarded()} &nbsp; </div>
HTML;
                        }
                    }
                    if (!$showed_badge) {
                        $return .= <<<HTML
        <div class="no-badge"></div>
HTML;
                    }
                }
            }
            else if ($has_badges) {
                $return .= <<<HTML
        <div class="no-badge"></div>
HTML;
            }
            $name = htmlentities($testcase->getName());
            $extra_credit = "";
            if($testcase->isExtraCredit()) {
                $extra_credit = "<span class='italics'><font color=\"0a6495\">Extra Credit</font></span>";
            }
            $command = htmlentities($testcase->getDetails());
            $testcase_message = "";
            if ((!$testcase->isHidden() || $show_hidden) && $testcase->viewTestcaseMessage()) {
                $testcase_message = <<<HTML
        <span class='italics'><font color="#af0000">{$testcase->getTestcaseMessage()}</font></span>
HTML;
            }
            $return .= <<<HTML
            <h4>
                {$hidden_title}{$name}&nbsp;&nbsp;&nbsp;<code>{$command}</code>&nbsp;&nbsp;{$extra_credit}&nbsp;&nbsp;{$testcase_message}
            </h4>
    </div>
HTML;
            if ($testcase->hasDetails() && (!$testcase->isHidden() || $show_hidden)) {
                //This is the div which will house the test output (filled by script above.)
               $return .= <<<HTML
    <div id="{$div_to_populate}" style="display:{$display_box};">
HTML;
                $return .= <<<HTML
    </div>

HTML;
            }
            $return .= <<<HTML
</div>
HTML;
            $count++;
        }
        return $return;
    }



public static function loadAutoChecks(Gradeable $gradeable, $count, $who_id, $popup_css_file, $show_hidden=false) {
    $gradeable->loadResultDetails();
    $testcase = $gradeable->getTestcases()[$count];
    $return = "";

    if($testcase->isHidden() && !$show_hidden){
        return "";
    }

    $autocheck_cnt = 0;
    $autocheck_len = count($testcase->getAutochecks());
    foreach ($testcase->getAutochecks() as $autocheck) {
        $description = $autocheck->getDescription();
        $diff_viewer = $autocheck->getDiffViewer();
        $file_path = $diff_viewer->getActualFilename();
        if (substr($file_path,strlen($file_path)-4,4) == ".pdf" && isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
          $url = "http" . (isset($_SERVER['HTTPS']) ? "s://" : "://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
          $url = preg_replace('/&component.*/', '', $url);
          $file_name = preg_replace('|.*/|', '', $file_path);
          $file_path = urlencode($file_path);
          $return .= '<iframe src='.$url.'&component=misc&page=display_file&dir=results&file='.$file_name.'&path='.$file_path.' width="95%" height="1200px" style="border: 0"></iframe>';
        } else {
            $return .= <<<HTML
    <div class="box-block">
    <!-- Readded css here so the popups have the css -->
HTML;
            $title = "";
            $return .= <<<HTML
        <div class='diff-element'>
HTML;
            $display_actual = "";
            $actual_name = $diff_viewer->getActualFilename();
            if ($diff_viewer->hasDisplayExpected() ||  $actual_name != "") {
                $title = "Student ";
            }
            if ($diff_viewer->hasDisplayActual()) {
                $display_actual = $diff_viewer->getDisplayActual();
                $visible = "visible";
                $tmp_array_string = explode("\n",trim(html_entity_decode(strip_tags($display_actual)), "\xC2\xA0\t"));
                $less_than_30 = true;
                $arr_count = count($tmp_array_string);
                for ($x = 0; $x < $arr_count; $x++) {
                    if(strlen($tmp_array_string[$x]) > 30) {
                        $less_than_30 = false;
                        $x = $arr_count;
                    }
                }
                if (substr_count($display_actual, 'line_number') < 10 && $less_than_30) {
                    $visible = "hidden";
                }
            } else {
                $visible = "hidden";
            }
            $title .= $description;
            $return .= <<<HTML
             <h4>{$title} <span onclick="openPopUp('{$popup_css_file}', '{$title}', {$count}, {$autocheck_cnt}, 0);"
             style="visibility: {$visible}"> <i class="fa fa-window-restore" style="visibility: {$visible}; cursor: pointer;"></i></span></h4>



            <div id="container_{$count}_{$autocheck_cnt}_0">
HTML;
            foreach ($autocheck->getMessages() as $message) {
                $type_class = "black-message";
                if ($message['type'] == "information") $type_class = "blue-message";
                else if ($message['type'] == "success") $type_class = "green-message";
                else if ($message['type'] == "failure") $type_class = "red-message";
                else if ($message['type'] == "warning") $type_class = "yellow-message";
                $return .= <<<HTML
            <span class="{$type_class}">{$message['message']}</span><br />
HTML;
            }
            $myimage = $diff_viewer->getActualImageFilename();
            if ($myimage != "") {
                // borrowed from file-display.php
                $content_type = FileUtils::getContentType($myimage);
                if (substr($content_type, 0, 5) === "image") {
                    // Read image path, convert to base64 encoding
                    $imageData = base64_encode(file_get_contents($myimage));
                    // Format the image SRC:  data:{mime};base64,{data};
                    $myimagesrc = 'data: '.mime_content_type($myimage).';charset=utf-8;base64,'.$imageData;
                    // insert the sample image data

                    $return .= '<img src="'.$myimagesrc.'" img style="border:2px solid black">';
                }
            }
            else if ($diff_viewer->hasDisplayActual()) {
                $return .= <<<HTML
            <div id=div_{$count}_{$autocheck_cnt}>
            $display_actual
            </div>
HTML;
            }
                $return .= <<<HTML
            </div>
        </div>
HTML;
            $myExpectedimage = $diff_viewer->getExpectedImageFilename();
            if($myExpectedimage != "")
            {
                $return .= <<<HTML
        <div class='diff-element'>
        <h4>Expected {$description}</h4>
HTML;
                for ($i = 0; $i < count($autocheck->getMessages()); $i++) {
                    $return .= <<<HTML
            <br />
HTML;
                }
                // borrowed from file-display.php
                $content_type = FileUtils::getContentType($myExpectedimage);
                if (substr($content_type, 0, 5) === "image") {
                   // Read image path, convert to base64 encoding
                   $expectedImageData = base64_encode(file_get_contents($myExpectedimage));
                   // Format the image SRC:  data:{mime};base64,{data};
                   $myExpectedimagesrc = 'data: '.mime_content_type($myExpectedimage).';charset=utf-8;base64,'.$expectedImageData;
                   // insert the sample image data
                   $return .= '<img src="'.$myExpectedimagesrc.'" img style="border:2px solid black">';
                }
            $return .= <<<HTML
        </div>
HTML;
            }
            elseif ($diff_viewer->hasDisplayExpected()) {
                $visible = "visible";
                $tmp_array_string = explode("\n",trim(html_entity_decode(strip_tags($diff_viewer->getDisplayExpected())), "\xC2\xA0\t"));
                $less_than_30 = true;
                $arr_count = count($tmp_array_string);
                for ($x = 0; $x < $arr_count; $x++) {
                    if(strlen($tmp_array_string[$x]) > 30) {
                        $less_than_30 = false;
                        $x = $arr_count;
                    }
                }
                if (substr_count($diff_viewer->getDisplayExpected(), 'line_number') < 10 && $less_than_30) {
                    $visible = "hidden";
                }
                $title = "Expected ";
                $title .= $description;
                $return .= <<<HTML
        <div class='diff-element'>
            <h4>{$title} <span onclick="openPopUp('{$popup_css_file}', '{$title}', {$count}, {$autocheck_cnt}, 1)" style="visibility: {$visible}"> <i class="fa fa-window-restore" style="visibility: {$visible}; cursor: pointer;"></i></span></h4>
            <div id="container_{$count}_{$autocheck_cnt}_1">
HTML;
                for ($i = 0; $i < count($autocheck->getMessages()); $i++) {
                    $return .= <<<HTML
                <br />
HTML;
                }
                $return .= <<<HTML
                    {$diff_viewer->getDisplayExpected()}
            </div>
        </div>
HTML;
            }
            $myDifferenceImage = $diff_viewer->getDifferenceFilename();
            if($myDifferenceImage != "")
            {
                $return .= <<<HTML
        <div class='diff-element'>
                <h4>Difference {$description}</h4>
HTML;
                for ($i = 0; $i < count($autocheck->getMessages()); $i++) {
                    $return .= <<<HTML
            <br />
HTML;
                }
                // borrowed from file-display.php
                $content_type = FileUtils::getContentType($myDifferenceImage);
                if (substr($content_type, 0, 5) === "image") {
                   // Read image path, convert to base64 encoding
                   $differenceImageData = base64_encode(file_get_contents($myDifferenceImage));
                   // Format the image SRC:  data:{mime};base64,{data};
                   $differenceImagesrc = 'data: '.mime_content_type($myDifferenceImage).';charset=utf-8;base64,'.$differenceImageData;
                   // insert the sample image data
                   $return .= '<img src="'.$differenceImagesrc.'" img style="border:2px solid black">';
                }
                $return .= <<<HTML
        </div>
HTML;
            }

            $return .= <<<HTML
    </div>
HTML;
        }
        if (++$autocheck_cnt < $autocheck_len) {
            $return .= <<<HTML
    <div class="clear"></div>
HTML;
        }

        $diff_viewer->destroyViewer();

//         $return .= <<<HTML
//     </div>
// HTML;
    }
    return $return;
}




    public function showVersionChoice($gradeable, $onChange, $formatting = "") {
        $return = <<<HTML
    <select style="margin: 0 10px;{$formatting} " name="submission_version"
    onChange="{$onChange}">

HTML;
        if ($gradeable->getActiveVersion() == 0) {
            $selected = ($gradeable->getCurrentVersionNumber() == $gradeable->getActiveVersion()) ? "selected" : "";
            $return .= <<<HTML
        <option value="0" {$selected}>Do Not Grade Assignment</option>
HTML;

        }
        foreach ($gradeable->getVersions() as $version) {
            $selected = "";
            $select_text = array("Version #{$version->getVersion()}");
            if ($gradeable->getNormalPoints() > 0) {
                $select_text[] = "Score: ".$version->getNonHiddenTotal()." / " . $gradeable->getTotalNonHiddenNonExtraCreditPoints();
            }

            if ($version->getDaysLate() > 0) {
                $select_text[] = "Days Late: ".$version->getDaysLate();
            }

            if ($version->isActive()) {
                $select_text[] = "GRADE THIS VERSION";
            }

            if ($version->getVersion() == $gradeable->getCurrentVersionNumber()) {
                $selected = "selected";
            }

            $select_text = implode("&nbsp;&nbsp;&nbsp;", $select_text);
            $return .= <<<HTML
        <option value="{$version->getVersion()}" {$selected}>{$select_text}</option>

HTML;
        }

        $return .= <<<HTML
    </select>
HTML;
        return $return;
    }

    public function showTAResults(Gradeable $gradeable){
        if(!$gradeable->beenTAgraded()){
            $return = <<<HTML
            <br>
            <h3>This assignment has not been graded yet</h3>
HTML;
            return $return;
        }
        if($gradeable->getCurrentVersionNumber() != $gradeable->getActiveVersion()){
            $return = <<<HTML
            <br>
            <h3>The version you have selected above does not match the version graded by your TA/instructor, please contact TA/instructor if necessary to resolve the problem.</h3>
HTML;
            return $return;
        }
        foreach ($gradeable->getComponents() as $component) {
            if(!$component->getGrader()){
                $return = <<<HTML
                <br>
                <h3>Grading not complete, please contact an instructor/grader</h3>
HTML;
                return $return;
            }
        }
        $graders = array();
        $count = 0;
        //find all names of instructors who graded part(s) of this assignment that are full access graders
        if(!$gradeable->getPeerGrading()){
            foreach ($gradeable->getComponents() as $component) {
                $name = $component->getGrader()->getDisplayedFirstName() . " " . $component->getGrader()->getLastName();
                if (!in_array($name, $graders) && $component->getGrader()->accessFullGrading()) $graders[] = $name;
            }
            $graders = implode(", ",$graders);
        }else{
            $graders = "Graded by Peer(s)";
        }
        //get total score and max possible score
        $score = $gradeable->getGradedTAPoints();
        $totalInstructorPointsEarned = $score;
        $maxScore = $gradeable->getTotalTANonExtraCreditPoints();
        if($score >= $maxScore){
            $background = "green-background";
        }else if($score >  $maxScore* 0.5){
            $background = "yellow-background";
        }else{
            $background = "red-background";
        }
        $comment = (!empty($gradeable->getOverallComment())) ? '<hr>'. "Overall note from Grader: <span class='gradeable_comment'>" . htmlentities($gradeable->getOverallComment()) . "</span>" : "";
        //change title if autograding exists or not
        //display a sum of autograding and instructor points if both exist
        $totalTitle = "Total";
        $displayTotal = "none";
        foreach ($gradeable->getTestcases() as $testcase) {
            if ($testcase->viewTestcase()){
                $totalTitle = "TA / Instructor Grading Subtotal";
                $displayTotal = "block";
                break;
            }
        }
        $return = <<<HTML
        <div class = "sub">
            <div class="box half" style="padding: 10px; width: 40%; word-break: break-word;">
                <p>Graded by: {$graders}</p>
                <i>Any regrade requests are due within 7 days of posting</i>
                <p>{$comment}</p>
            </div>
            <a name="show_late_table" style="float:right" class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'student', 'page' => 'view_late_table', 'g_id' => $gradeable->getId()))}">Show my late days information</a>
            <div class = "box">
                <div class="box-title">
                    <span class="badge {$background}" style="float: left">{$score} / {$maxScore}</span>
                    <h4>{$totalTitle}</h4>
                </div>
            </div>
HTML;
        foreach ($gradeable->getComponents() as $component) {
            if(is_array($component)) continue;
            $score = $component->getGradedTAPoints();
            //check if extra credit
            if(trim(strtolower($component->getTitle())) === "extra credit") {
                $background = ($score == 0) ? "hidden" : "green-background";
                $score = "+" . $score;
            }else{
                if($score >= $component->getMaxValue()){
                    $background = "green-background";
                }else if($score > $component->getMaxValue()* 0.5){
                    $background = "yellow-background";
                }else{
                    $background = "red-background";
                }
                $score = $score . " / " . $component->getMaxValue();
            }
            //add grader's name if not peer grading and is a full access grader
            $componentGrader = ($gradeable->getPeerGrading() || !$component->getGrader()->accessFullGrading())? "" :" (Graded by: " . $component->getGrader()->getLastName().")";
            $return .= <<<HTML
            <div class="box">
                <div class="box-title">
                    <span class="badge {$background}">{$score}</span>
                    <h4>{$component->getTitle()} <i>{$componentGrader}</i></h4>
                    <div style="float:left; word-break: break-word;">
                        <p style="padding-bottom: 10px;">{$component->getStudentComment()}</p>
                        <p><span class="gradeable_comment">{$component->getGradedTAComments('<br>',true,$gradeable,false)}</span></p>
                    </div>
                </div>
            </div>
HTML;
        }
        //add total points if both autograding and instructor grading exist
        $display = "none";
        $current = $gradeable->getCurrentVersion();
        $totalPointsEarned = $current->getNonHiddenTotal() + $current->getHiddenTotal() + $totalInstructorPointsEarned;
        $maxPossiblePoints = $gradeable->getTotalAutograderNonExtraCreditPoints() + $maxScore;
        $background = "";
        if($totalPointsEarned >= $maxPossiblePoints){
            $background = "green-background";
        }else if($totalPointsEarned > $maxPossiblePoints * 0.5){
            $background = "yellow-background";
        }else{
            $background = "red-background";
        }
        $totalScore = $totalPointsEarned . " / " . $maxPossiblePoints;
        $return .= <<<HTML
            <div style="display: {$displayTotal}" class="box">
                <div class="box-title" style="padding-top: 15px; padding-bottom: 15px;">
                    <span class="badge {$background}"> {$totalScore}</span>
                    <h4>Total</h4>
                </div>
            </div>
        </div>
HTML;
    return $return;
    }
}
