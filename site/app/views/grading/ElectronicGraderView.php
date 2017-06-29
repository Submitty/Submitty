<?php

namespace app\views\grading;

use app\models\Gradeable;
use app\models\User;
use app\models\LateDaysCalculation;
use app\views\AbstractView;

class ElectronicGraderView extends AbstractView {
    /**
     * @param Gradeable $gradeable
     * @param array     $sections
     * @return string
     */
    public function statusPage($gradeable, $sections) {
        $course = $this->core->getConfig()->getCourse();
        $semester = $this->core->getConfig()->getSemester();
        $graded = 0;
        $total = 0;
        foreach ($sections as $key => $section) {
            if ($key === "NULL") {
                continue;
            }
            $graded += $section['graded_students'];
            $total += $section['total_students'];
        }
        if ($total === 0){
            $percentage = -1;
        }
        else{
            $percentage = round(($graded / $total) * 100);
        }
        $return = <<<HTML
<div class="content">
    <h2>Status of {$gradeable->getName()}</h2>
HTML;
    if($percentage === -1){
        $view = 'all';
        $return .= <<<HTML
    <div class="sub">
        No Grading To Be Done! :)
HTML;

    }
    else{
        $view = null;
        $return .= <<<HTML
    <div class="sub">
        Current percentage of grading done: {$percentage}% ({$graded}/{$total})
        <br />
        <br />
        By Grading Sections:
        <div style="margin-left: 20px">
HTML;
        foreach ($sections as $key => $section) {
            $percentage = round(($section['graded_students'] / $section['total_students']) * 100);
            $return .= <<<HTML
            Section {$key}: {$percentage}% ({$section['graded_students']} / {$section['total_students']})<br />
HTML;
        }
        $return .= <<<HTML
        </div>
        <br />
        Graders:
        <div style="margin-left: 20px">
HTML;
            foreach ($sections as $key => $section) {
                if ($key === "NULL") {
                    continue;
                }
                if (count($section['graders']) > 0) {
                    $graders = implode(", ", array_map(function($grader) { return $grader->getId(); }, $section['graders']));
                }
                else {
                    $graders = "Nobody";
                }
                $return .= <<<HTML
            Section {$key}: {$graders}<br />
HTML;
            }
        }
        // {$this->core->getConfig()->getTABaseUrl()}account/account-summary.php?course={$course}&semester={$semester}&g_id={$gradeable->getId()}
        $return .= <<<HTML
        </div>
        <div style="margin-top: 20px">
HTML;
        if($percentage !== -1 || $this->core->getUser()->accessFullGrading()){
            $return .= <<<HTML
            <a class="btn btn-primary" 
                href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action' => 'summary', 'gradeable_id' => $gradeable->getId(), 'view' => $view))}"">
                Grading Details
            </a>
HTML;
            if(count($this->core->getUser()->getGradingRegistrationSections()) !== 0){
                $return .= <<<HTML
            <a class="btn btn-primary"
                href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'individual'=>'0'))}">
                Grade Next Student
            </a>
        </div>
    </div>
</div>
HTML;
            }
        }
        return $return;
    }

    /**
     * @param Gradeable   $gradeable
     * @param Gradeable[] $rows
     * @param array       $graders
     * @return string
     */
    public function summaryPage($gradeable, $rows, $graders) {
        $return = <<<HTML
<div class="content">
    
HTML;
        // Default is viewing your sections
        // Limited grader does not have "View All" option
        // If nothing to grade, Instructor will see all sections
        if (!isset($_GET['view']) || $_GET['view'] !== 'all') {
            $text = 'View All';
            $view = 'all';
        }
        else{
            $text = 'View Your Sections';
            $view = null;
        }
        if($gradeable->isGradeByRegistration()){
            $grading_count = count($this->core->getUser()->getGradingRegistrationSections());
        }
        else{
            $grading_count = count($this->core->getQueries()->getRotatingSectionsForGradeableAndUser($gradeable->getId(),$this->core->getUser()->getId()));
        }

        if($this->core->getUser()->accessFullGrading() && (!$this->core->getUser()->accessAdmin() || $grading_count !== 0)){
            $return .= <<<HTML
    <div style="float: right; margin-bottom: 10px">
        <a class="btn btn-default"
            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'action' => 'summary', 'gradeable_id' => $gradeable->getId(), 'view' => $view))}">
            $text
        </a>
    </div>
HTML;
        }
        $return .= <<<HTML
    <h2>Grade Details for {$gradeable->getName()}</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="3%"></td>
                <td width="5%">Section</td>
                <td width="20%">User ID</td>
                <td width="15%">First Name</td>
                <td width="15%">Last Name</td>
                <td width="14%">Autograding</td>
                <td width="10%">TA Grading</td>
                <td width="10%">Total</td>
                <td width="8%">Viewed Grade</td>
            </tr>
        </thead>
HTML;
            $return .= <<<HTML
HTML;
            $count = 1;
            $last_section = false;
            $tbody_open = false;
            foreach ($rows as $row) {
                if ($row->beenTAgraded()){
                    if ($row->getUserViewedDate() === null || $row->getUserViewedDate() === "") {
                        $viewed_grade = "&#10008;";
                        $grade_viewed = "";
                        $grade_viewed_color = "color: red; font-size: 1.5em;";
                    }
                    else {
                        $viewed_grade = "&#x2714;";
                        $grade_viewed = "Last Viewed: " . date("F j, Y, g:i a", strtotime($row->getUserViewedDate()));
                        $grade_viewed_color = "color: #5cb85c; font-size: 1.5em;";
                    }
                }
                else{
                    $viewed_grade = "";
                    $grade_viewed = "";
                    $grade_viewed_color = "";
                }
                $total_possible = $row->getTotalAutograderNonExtraCreditPoints() + $row->getTotalTANonExtraCreditPoints();
                $graded = $row->getGradedAutograderPoints() + $row->getGradedTAPoints();
                if ($graded < 0) $graded = 0;
                if ($gradeable->isGradeByRegistration()) {
                    $section = $row->getUser()->getRegistrationSection();
                }
                else {
                    $section = $row->getUser()->getRotatingSection();
                }
                $display_section = ($section === null) ? "NULL" : $section;
                if ($section !== $last_section) {
                    $last_section = $section;
                    $count = 1;
                    if ($tbody_open) {
                        $return .= <<<HTML
        </tbody>
HTML;
                    }
                    if (isset($graders[$display_section]) && count($graders[$display_section]) > 0) {
                        $section_graders = implode(", ", array_map(function(User $user) { return $user->getId(); }, $graders[$display_section]));
                    }
                    else {
                        $section_graders = "Nobody";
                    }

                    $return .= <<<HTML
        <tr class="info persist-header">
            <td colspan="9" style="text-align: center">Students Enrolled in Section {$display_section}</td>
        </tr>
        <tr class="info">
            <td colspan="9" style="text-align: center">Graders: {$section_graders}</td>
        </tr>
        <tbody id="section-{$section}">
HTML;
                }
                $return .= <<<HTML
            <tr id="user-row-{$row->getUser()->getId()}">
                <td>{$count}</td>
                <td>{$display_section}</td>
                <td>{$row->getUser()->getId()}</td>
                <td>{$row->getUser()->getDisplayedFirstName()}</td>
                <td>{$row->getUser()->getLastName()}</td>
                <td>{$row->getGradedAutograderPoints()} / {$row->getTotalAutograderNonExtraCreditPoints()}</td>
                <td>
HTML;
                if ($row->beenTAgraded()) {
                    $btn_class = "btn-default";
                    $contents = "{$row->getGradedTAPoints()} / {$row->getTotalTANonExtraCreditPoints()}";
                }
                else {
                    $btn_class = "btn-primary";
                    $contents = "Grade";
                }
                $return .= <<<HTML
                    <a class="btn {$btn_class}" href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'who_id'=>$row->getUser()->getId(), 'individual'=>'1'))}">
                        {$contents}
                    </a>
                </td>
                <td>{$graded} / {$total_possible}</td>
                <td title="{$grade_viewed}" style="{$grade_viewed_color}">{$viewed_grade}</td>
            </tr>
HTML;
                $count++;
            }
            $return .= <<<HTML
        </tbody>
HTML;
        $return .= <<<HTML
    </table>
</div>
HTML;
        return $return;
    }

    public function hwGradingPage($gradeable, $progress, $prev_id, $next_id, $individual) {
        $prev_href = $prev_id == '' ? '' : "href=\"{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'who_id'=>$prev_id, 'individual'=>$individual))}\"";
        $next_href = $next_id == '' ? '' : "href=\"{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'who_id'=>$next_id, 'individual'=>$individual))}\"";
        $return = <<<HTML
<div class="grading_toolbar">
    <a {$prev_href}><i title="Go to the previous student" class="icon-left"></i></a>
    <a href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'summary', 'gradeable_id'=>$gradeable->getId()))}"><i title="Go to the main page" class="icon-home" ></i></a>
    <a {$next_href}><i title="Go to the next student" class="icon-right"></i></a>
    <i title="Reset Rubric Panel Positions (Press R)" class="icon-refresh" onclick="handleKeyPress('KeyR');"></i>
    <i title="Show/Hide Auto-Grading Testcases (Press A)" class="icon-auto-grading-results" onclick="handleKeyPress('KeyA');"></i>
    <i title="Show/Hide Grading Rubric (Press G)" class="icon-grading-panel" onclick="handleKeyPress('KeyG');"></i>
    <i title="Show/Hide Submission and Results Browser (Press O)" class="icon-files" onclick="handleKeyPress('KeyO');"></i>
    <i title="Show/Hide Student Information (Press S)" class="icon-status" onclick="handleKeyPress('KeyS');"></i>
</div>

<div class="progress_bar">
    <progress class="progressbar" max="100" value="{$progress}" style="width:80%; height: 100%;"></progress>
    <div class="progress-value" style="display:inline;"></div>
</div>

<div id="autograding_results" class="draggable rubric_panel" style="left:15px; top:170px; width:48%; height:36%;">
    <span class="grading_label">Auto-Grading Testcases</span>
    <div class="inner-container">
HTML;
        if ($gradeable->getActiveVersion() === 0){
            $return .= <<<HTML
        <h4>No Submission</h4>
HTML;
        }
        else if (count($gradeable->getTestcases()) === 0) {
            $return .= <<<HTML
        <h4>No Autograding For This Assignment</h4>
HTML;
        }
        else{
            $has_badges = false;
            if ($gradeable->getNormalPoints() > 0) {
                $has_badges = true;
                if ($gradeable->getGradedNonHiddenPoints() >= $gradeable->getNormalPoints()) {
                    $background = "green-background";
                }
                else if ($gradeable->getGradedNonHiddenPoints() > 0) {
                    $background = "yellow-background";
                }
                else {
                    $background = "red-background";
                }
                if ($gradeable->getTotalAutograderNonExtraCreditPoints() > $gradeable->getNormalPoints()) {
                    $return .= <<<HTML
        <div class="box">
            <div class="box-title">
                <span class="badge {$background}">{$gradeable->getGradedNonHiddenPoints()} / {$gradeable->getNormalPoints()}</span>
                <h4>Total (No Hidden Points)</h4>
            </div>
        </div>
HTML;
                    if ($gradeable->getGradedAutograderPoints() >= $gradeable->getTotalAutograderNonExtraCreditPoints()) {
                        $background = "green-background";
                    }
                    else if ($gradeable->getGradedAutograderPoints() > 0) {
                        $background = "yellow-background";
                    }
                    else {
                        $background = "red-background";
                    }
                    $return .= <<<HTML
        <div class="box">
            <div class="box-title">
                <span class="badge {$background}">{$gradeable->getGradedAutograderPoints()} / {$gradeable->getTotalAutograderNonExtraCreditPoints()}</span>
                <h4>Total (With Hidden Points)</h4>
            </div>
        </div>
HTML;
                }
                else {
                    $return .= <<<HTML
        <div class="box">
            <div class="box-title">
                <span class="badge {$background}">{$gradeable->getGradedNonHiddenPoints()} / {$gradeable->getNormalPoints()}</span>
                <h4>Total</h4>
            </div>
        </div>
HTML;
                }
            }
            $count = 0;
            $display_box = (count($gradeable->getTestcases()) == 1) ? "block" : "none";
            foreach ($gradeable->getTestcases() as $testcase) {
                if (!$testcase->viewTestcase()) {
                    continue;
                }
                $div_click = "";
                if ($testcase->hasDetails() || (count($testcase->getAutochecks()) > 0)) {
                    $div_click = "onclick=\"return toggleDiv('testcase_{$count}');\" style=\"cursor: pointer;\"";
                }
                $return .= <<<HTML
        <div class="box">
            <div class="box-title" {$div_click}>
HTML;
                if ($testcase->hasDetails() || (count($testcase->getAutochecks()) > 0)) {
                    $return .= <<<HTML
                <div style="float:right; color: #0000EE; text-decoration: underline">Details</div>
HTML;
                }
                if ($testcase->hasPoints()) {
                    $showed_badge = false;
                    $background = "";
                    if ($testcase->isExtraCredit()) {
                        if ($testcase->getPointsAwarded() > 0) {
                            $showed_badge = true;
                            $background = "green-background";                                $return .= <<<HTML
                <div class="badge {$background}"> &nbsp; +{$testcase->getPointsAwarded()} &nbsp; </div>
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
                else if ($has_badges) {
                    $return .= <<<HTML
                <div class="no-badge"></div>
HTML;
                }
                if ($testcase->isHidden()) {
                    $return .= <<<HTML
                <div class="badge" style="margin-left:0px; margin-right:10px;">Hidden</div>
HTML;
                }
                $name = htmlentities($testcase->getName());
                $extra_credit = "";
                if($testcase->isExtraCredit()) {
                    $extra_credit = "<span class='italics'><font color=\"0a6495\">Extra Credit</font></span>";
                }
                $command = htmlentities($testcase->getDetails());
                    $return .= <<<HTML
                <h4>{$name}&nbsp;&nbsp;&nbsp;<code>{$command}</code>&nbsp;&nbsp;{$extra_credit}</h4>
            </div>
HTML;
                if ($testcase->hasDetails() || (count($testcase->getAutochecks()) > 0)) {
                    $return .= <<<HTML
            <div id="testcase_{$count}" style="display: {$display_box};">
HTML;
                    $autocheck_cnt = 0;
                    $autocheck_len = count($testcase->getAutochecks());
                    foreach ($testcase->getAutochecks() as $autocheck) {
                        $description = $autocheck->getDescription();
                        $diff_viewer = $autocheck->getDiffViewer();
                        $return .= <<<HTML
                <div class="box-block">
HTML;
                        $title = "";
                        $return .= <<<HTML
                    <div class='diff-element'>
HTML;
                        if ($diff_viewer->hasDisplayExpected()) {
                            $title = "Student ";
                        }
                        $title .= $description;
                        $return .= <<<HTML
                        <h4>{$title}</h4>
HTML;
                        foreach ($autocheck->getMessages() as $message) {
                            $return .= <<<HTML
                        <span class="red-message">{$message}</span><br />
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
                                $return .= '<img src="'.$myimagesrc.'">';
                            }
                        }
                        else if ($diff_viewer->hasDisplayActual()) {
                            $return .= <<<HTML
                        {$diff_viewer->getDisplayActual()}
HTML;
                        }
                        $return .= <<<HTML
                    </div>
HTML;
                        if ($diff_viewer->hasDisplayExpected()) {
                            $return .= <<<HTML
                    <div class='diff-element'>
                        <h4>Expected {$description}</h4>
HTML;
                            for ($i = 0; $i < count($autocheck->getMessages()); $i++) {
                                $return .= <<<HTML
                        <br />
HTML;
                            }
                            $return .= <<<HTML
                        {$diff_viewer->getDisplayExpected()}
                    </div>
HTML;
                        }
                        $return .= <<<HTML
                </div>
HTML;
                        if (++$autocheck_cnt < $autocheck_len) {
                             $return .= <<<HTML
                <div class="clear"></div>
HTML;
                        }
                    }
                    $return .= <<<HTML
            </div>
HTML;
                }
                $return .= <<<HTML
        </div>
HTML;
                $count++;
            }
        }
        $return .= <<<HTML
    </div>
</div>

<div id="submission_browser" class="draggable rubric_panel" style="left:15px; bottom:40px; width:48%; height:30%">
    <span class="grading_label">Submissions and Results Browser</span>
    <button class="btn btn-default" onclick="openAll()">Expand All</button>
    <button class="btn btn-default" onclick="closeAll()">Close All</button>
    <br />
    <div class="inner-container">
HTML;
        function add_files(&$files, $new_files, $start_dir_name) {
            $files[$start_dir_name] = array(); 
            foreach($new_files as $file) {
                $path = explode('/', $file['relative_name']);
                array_pop($path);
                $working_dir = &$files[$start_dir_name];
                foreach($path as $dir) {
                    if (!isset($working_dir[$dir])) {
                        $working_dir[$dir] = array();
                    }
                    $working_dir = &$working_dir[$dir];
                }
                $working_dir[$file['name']] = $file['path'];
            }
        }
        function display_files($files, &$count, $indent, &$return) {
            foreach ($files as $dir => $contents) {
                if (!is_array($contents)) {
                    $dir = htmlentities($dir);
                    $contents = urlencode(htmlentities($contents));
                    $indent_offset = $indent * -15;
                    $return .= <<<HTML
                <div>
                    <div class="file-viewer">
                        <a class='openAllFile' onclick='openFrame("{$dir}", "{$contents}", {$count})'>
                            <span class='icon-plus' style='vertical-align:text-bottom;'></span>
                        {$dir}</a> &nbsp;
                        <a onclick='openFile("{$dir}", "{$contents}")'>(Popout)</a>
                    </div><br/>
                    <div id="file_viewer_{$count}" style="margin-left:{$indent_offset}px"></div>
                </div>
HTML;
                    $count++;
                }
            }
            foreach ($files as $dir => $contents) {
                if (is_array($contents)) {
                    $dir = htmlentities($dir);
                    $return .= <<<HTML
            <div>
                <div class="div-viewer">
                    <a class='openAllDiv' onclick='openDiv({$count});'>
                        <span class='icon-folder-closed' style='vertical-align:text-top;'></span>
                    {$dir}</a>
                </div><br/>
                <div id='div_viewer_{$count}' style='margin-left:15px; display: none'>
HTML;
                    $count++;
                    display_files($contents, $count, $indent+1, $return);
                    $return .= <<<HTML
                </div>
            </div>
HTML;
                }
            }
        }
        $files = array();
        add_files($files, array_merge($gradeable->getMetaFiles(), $gradeable->getSubmittedFiles(), $gradeable->getSvnFiles()), 'submissions');
        add_files($files, $gradeable->getResultsFiles(), 'results');
        $count = 1;
        display_files($files,$count,1,$return);
        $return .= <<<HTML
    </div>
</div>
HTML;

        $user = $gradeable->getUser();
        $return .= <<<HTML

<div id="student_info" class="draggable rubric_panel" style="right:15px; bottom:40px; width:48%; height:30%;">
    <span class="grading_label">Student Information</span>
    <div class="inner-container">
        <div class="rubric-title">
            <b>{$user->getFirstName()} {$user->getLastName()} ({$user->getId()})<br/>
            Submission Number: {$gradeable->getActiveVersion()} / {$gradeable->getHighestVersion()}<br/>
            Submitted: {$gradeable->getSubmissionTime()->format("m/d/Y H:i:s")}<br/></b>
        </div>
HTML;
        $cookie_auto = ((isset($_COOKIE['auto']) && intval($_COOKIE["auto"]) == 1) ? "checked" : "");
        $return .= <<<HTML
        <form id="rubric_form" action="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action' => 'submit'))}" method="post">
            <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
            <input type="hidden" name="g_id" value="{$gradeable->getId()}" />
            <input type="hidden" name="u_id" value="{$user->getId()}" />
            <input type="hidden" name="individual" value="{$individual}" />
            <input type="checkbox" style="margin-right:5px;" id="rubric-autoscroll-checkbox" {$cookie_auto} />
                <span style="display: inline-block; margin-top:15px; margin-bottom:15px;">Rubric Auto Scroll</span>
HTML;

        //Late day calculation
        $ldu = new LateDaysCalculation($this->core);
        $return .= $ldu->generateTableForUserDate($user->getId(), $gradeable->getDueDate());
        $late_days_data = $ldu->getGradeable($user->getId(), $gradeable->getId());
        $status = $late_days_data['status'];
        $late_charged = $late_days_data['late_days_charged'];
        $return .= <<<HTML
            <input type="hidden" name="late" value="{$late_charged}" />
HTML;

        $color = "green";
        if($status != "Good" && $status != "Late") {
            $color = "red";
            $return .= <<<HTML
        <script>
            $('body').css('background-color', 'red');
            $("#rubric_form").submit(function(event){
                var confirm = window.confirm("This submission has a bad status. Are you sure you want to submit a grade for it?");
                if(!confirm){
                    event.preventDefault();
                }
            });
        </script>
HTML;
        }
        $return .= <<<HTML
        <b>Status:</b> <span style="color:{$color};">{$status}</span><br />
    </div>
</div>

<div id="grading_rubric" class="draggable rubric_panel" style="right:15px; top:140px; width:48%; height:42%;">
    <span class="grading_label">Grading Rubric</span>
    <div style="margin:3px;">
        <table class="rubric-table" id="rubric-table">
            <tbody>
HTML;

        $c = 1;
        $precision = floatval($gradeable->getPointPrecision());

        foreach ($gradeable->getComponents() as $question) {
            // hide auto-grading if it has no value
            if ($question->getMaxValue() == 0) {
                continue;
            }
            // FIXME add autograding extra credit 
            else if (($question->getScore() == 0) && (substr($question->getTitle(), 0, 12) === "AUTO-GRADING")) {
                $question->setScore(floatval($gradeable->getGradedAutograderPoints()));
            }
    
            $disabled = '';
            if(substr($question->getTitle(), 0, 12) === "AUTO-GRADING") {
                $disabled = 'disabled';
            }
    
            $return .= <<<HTML
                <tr>
HTML;
            $penalty = !(intval($question->getMaxValue()) > 0);
            $message = htmlentities($question->getTitle());
            $note = htmlentities($question->getTaComment());
            if ($note != "") {
                $note = "<br/><div style='margin-bottom:5px; color:#777;'><i><b>Note to TA: </b>" . $note . "</i></div>";
            }

            //adds an icon depending on the question type (extra credit, normal, penalty)
            //adds background color as well.
            if($question->getIsExtraCredit()) {
                $return .= <<<HTML
                    <td style="font-size: 12px; background-color: #D8F2D8;" colspan="4">
                        <i class="icon-plus"></i> <b>{$message}</b> {$note}
HTML;
            }
            else if($penalty) {
                $return .= <<<HTML
                    <td style="font-size: 12px; background-color: #FAD5D3;" colspan="4">
                        <i class="icon-minus"></i> <b>{$message}</b> {$note}
HTML;
            }
            else {
                $return .= <<<HTML
                    <td style="font-size: 12px;" colspan="4">
                        <b>{$message}</b> {$note}
HTML;
            }

            $student_note = htmlentities($question->getStudentComment());
            if ($student_note != ''){
                $student_note = "<div style='margin-bottom:5px; color:#777;'><i><b>Note to Student: </b>" . $student_note . "</i></div>";
        
            }
            $return .= <<<HTML
                        {$student_note}
                    </td>
                </tr>
HTML;

            $min_val = (intval($question->getMaxValue()) > 0) ? 0 : intval($question->getMaxValue());
            $max_val = (intval($question->getMaxValue()) > 0) ? intval($question->getMaxValue()) : 0;

            $background = "";
            if ($question->getIsExtraCredit()) {
                $background = "background-color: #D8F2D8;";
            }
            else if ($penalty) {
                $background = "background-color: #FAD5D3;";
            }
            
            $return .= <<<HTML
                <tr style="background-color: #f9f9f9;">
                    <td style="white-space:nowrap; vertical-align:middle; text-align:center; {$background}" colspan="1">
                        <input type="number" id="grade-{$question->getOrder()}" class="grades" name="grade-{$question->getOrder()}" value="{$question->getScore()}" min="{$min_val}" max="{$max_val}" step="{$precision}" placeholder="&plusmn;{$precision}" onchange="validateInput('grade-{$question->getOrder()}', '{$question->getMaxValue()}', {$precision}); calculatePercentageTotal();" style="width:50px; resize:none;" {$disabled}></textarea>
                        <strong> / {$question->getMaxValue()}</strong>
                    </td>
                    <td style="width:98%; {$background}" colspan="3">
                        <div id="rubric-{$c}">
                            <textarea name="comment-{$question->getOrder()}" onkeyup="autoResizeComment(event);" rows="4" style="width:98%; height:100%; min-height:80px; resize:none; float:left;" placeholder="Message for the student..." comment-position="0" {$disabled}>{$question->getComment()}</textarea>
HTML;

            $comment = htmlspecialchars($question->getComment());
            if ($comment != "") {
                $return .= <<<HTML
                            <div>
                                <a class="btn" name="comment-{$question->getOrder()}-up" style="border-radius: 0px; padding:0px;" onclick="updateCommentBox_{$question->getOrder()}(-1);" disabled="true">
                                    <i class="icon-chevron-up" style="height:20px; width:13px;"></i>
                                </a><br/>
                                <a class="btn" name="comment-{$question->getOrder()}-down" style="border-radius: 0px; padding:0px;" onclick="updateCommentBox_{$question->getOrder()}(1);">
                                    <i class="icon-chevron-down" style="height:20px; width:13px;"></i>
                                </a>
                            </div>
                            <script type="text/javascript">
                                function updateCommentBox_{$question->getOrder()}(delta) {
                                    var pastComments = [];
                                    pastComments[0] = "{$comment}";

                                    var new_position = parseInt($('[name=comment-{$question->getOrder()}]').attr("comment-position"));
                                    new_position += delta;

                                    if(new_position >= pastComments.length - 1) {
                                        new_position = pastComments.length - 1;
                                        $('a[name=comment-{$question->getOrder()}-down]').attr("disabled", "true");
                                    }
                                    else {
                                        $('a[name=comment-{$question->getOrder()}-down]').removeAttr("disabled");
                                    }

                                    if(new_position <= 0) {
                                        new_position = 0;
                                        $('a[name=comment-{$question->getOrder()}-up]').attr("disabled", "true");
                                    }
                                    else {
                                        $('a[name=comment-{$question->getOrder()}-up]').removeAttr("disabled");
                                    }

                                    var textarea = $('textarea[name=comment-{$question->getOrder()}]');
                                    textarea.attr("comment-position", new_position);
                                    textarea.html(pastComments[new_position]);
                                }
                            </script>
HTML;
            }
            $return .= <<<HTML
                        </div>
                    </td>
                </tr>
HTML;
            $c++;
        }

        $return .= <<<HTML
                <tr>
                    <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top:5px #FAA732 solid;" colspan="1"><strong>TOTAL</strong></td>
                    <td style="background-color: #EEE; border-top:5px #FAA732 solid;" colspan="1"><strong id="score_total">0 / {$gradeable->getTotalPoints()}&emsp;&emsp;&emsp;
                        AUTO-GRADING {$gradeable->getGradedAutograderPoints()} / {$gradeable->getTotalAutograderNonExtraCreditPoints()}</strong>
                    </td>
                </tr>
            </tbody>
        </table><br/>
        <div style="width:100%;"><b>General Comment:</b>
        <textarea name="comment-general" rows="5" style="width:98%; height:100%; min-height:100px; resize:none; float:left;" onkeyup="autoResizeComment(event);" placeholder="Overall message for student about the gradeable..." comment-position="0">{$gradeable->getOverallComment()}</textarea>
        </div>
HTML;
        if ($gradeable->beenTAgraded()) {
            $return .= <<<HTML
        <div style="width:100%; margin-left:10px;">
            Graded By: {$gradeable->getGraderId()}<br />Overwrite Grader: <input type='checkbox' name='overwrite' value='1' /><br />
        </div>
HTML;
        }
        else { //Adding this checkbox to simplify checking for grader overwrite.  It's hidden from view so that the first time someone grades, $_POST['overwrite'] is guarenteed to exist
            $return .= <<<HTML
        <input type='checkbox' class='hidden' name='overwrite' value='1' checked='checked' style='display:none;' /> 
HTML;
        }
        $return .= <<<HTML
        <div style="width:100%;">
HTML;
        $now = new \DateTime('now');        
        if (!($now < $gradeable->getGradeStartDate()) && ($gradeable->getTotalPoints() > 0)) {
            if($gradeable->beenTAgraded()) {
                $return .= <<<HTML
            <input class="btn btn-large btn-warning" type="submit" value="Submit Homework Re-Grade" onclick="createCookie('backup',1,1000);"/>
HTML;
            }
            else {
                $return .= <<<HTML
            <input class="btn btn-large btn-primary" type="submit" value="Submit Homework Grade"/>
HTML;
            }
        }
        else {
            $return .= <<<HTML
            <input class="btn btn-large btn-primary" type="button" value="Cannot Submit Homework Grade" />
        <div style="width:100%; text-align:left; color:#777;">This homework has not been opened for grading.</div>
HTML;
        }
        $return .= <<<HTML
        </div>
    </form>
    </div>
</div>

<script type="text/javascript">
    // expand all files in Submissiona and Results section
    function openAll() {
        // click on all with the class openAllDiv that hasn't been expanded yet
        $(".openAllDiv").each(function() {
            if ($(this).parent().find('span').hasClass('icon-folder-closed')) {
                $(this).click();
            }
        });

        // click on all with the class openAllFile that hasn't been expanded yet
        $(".openAllFile").each(function() {
            if($(this).find('span').hasClass('icon-plus')) {
                $(this.click());
            }
        });
    }

    // close all files in Submission and results section
    function closeAll() {
        // click on all with the class openAllFile that is expanded
        $(".openAllFile").each(function() {
            if($(this).find('span').hasClass('icon-minus')) {
                $(this.click());
            }
        });

        // click on all with the class openAllDiv that is expanded
        $(".openAllDiv").each(function() {
            if ($(this).parent().find('span').hasClass('icon-folder-open')) {
                $(this).click();
            }
        });
    }

    function resizeFrame(id) {
        var height = parseInt($("iframe#" + id).contents().find("body").css('height').slice(0,-2));
        if (height > 500) {
            document.getElementById(id).height= "500px";
        }
        else {
            document.getElementById(id).height = (height+18) + "px";
        }
    }

    function openDiv(num) {
        var elem = $('#div_viewer_' + num);
        //console.log(typeof(elem));
        if (elem.hasClass('open')) {
            elem.hide();
            elem.removeClass('open');
            $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('icon-folder-open').addClass('icon-folder-closed');
        }
        else {
            elem.show();
            elem.addClass('open');
            $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('icon-folder-closed').addClass('icon-folder-open');
        }
        return false;
    }

    function openFrame(html_file, url_file, num) {
        var iframe = $('#file_viewer_' + num);
        if (!iframe.hasClass('open')) {
            var iframeId = "file_viewer_" + num + "_iframe";
            // handle pdf
            if(url_file.substring(url_file.length - 3) == "pdf") {
                iframe.html("<iframe id='" + iframeId + "' src='{$this->core->getConfig()->getSiteUrl()}&component=misc&page=display_file&dir=submissions&file=" + html_file + "&path=" + url_file + "' width='750px' height='600px' style='border: 0'></iframe>");
            }
            else {
                iframe.html("<iframe id='" + iframeId + "' onload='resizeFrame(\"" + iframeId + "\");' src='{$this->core->getConfig()->getSiteUrl()}&component=misc&page=display_file&dir=submissions&file=" + html_file + "&path=" + url_file + "' width='750px' style='border: 0'></iframe>");
            }
            iframe.addClass('open');
        }

        if (!iframe.hasClass('shown')) {
            iframe.show();
            iframe.addClass('shown');
            $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('icon-plus').addClass('icon-minus');
        }
        else {
            iframe.hide();
            iframe.removeClass('shown');
            $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('icon-minus').addClass('icon-plus');
        }
        return false;
    }

    function openFile(html_file, url_file) {
        window.open("{$this->core->getConfig()->getSiteUrl()}&component=misc&page=display_file&dir=submissions&file=" + html_file + "&path=" + url_file,"_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
        return false;
    }

    // delta in this function is the incremental step of points, currently hardcoded to 0.5pts
    function validateInput(id, question_total, delta){
        var ele = $('#' + id);
        if(isNaN(parseFloat(ele.val())) || ele.val() == ""){
            ele.val("");
            return;
        }
        if(ele.val() < 0 && parseFloat(question_total) > 0) {
            ele.val( 0 );
        }
        if(ele.val() > 0 && parseFloat(question_total) < 0) {
            ele.val( 0 );
        }
        if(ele.val() < parseFloat(question_total) && parseFloat(question_total) < 0) {
            ele.val(question_total);
        }
        if(ele.val() > parseFloat(question_total) && parseFloat(question_total) > 0) {
            ele.val(question_total);
        }
        if(ele.val() % delta != 0) {
            ele.val( Math.round(ele.val() / delta) * delta );
        }
    }

    // autoresize the comment box
    function autoResizeComment(e){
        e.target.style.height ="";
        e.target.style.height = e.target.scrollHeight + "px";
    }

    function calculatePercentageTotal() {
        var total=0;

        $('#rubric-table').find('.grades').each(function() {
            if(!isNaN(parseFloat($(this).val()))) {
                total += parseFloat($(this).val());
            }
        });
        
        total = Math.max(parseFloat(total + {$gradeable->getGradedAutograderPoints()}), 0);

        $("#score_total").html(total+" / "+parseFloat({$gradeable->getTotalPoints()}) + "&emsp;&emsp;&emsp;" + " AUTO-GRADING: " + {$gradeable->getGradedAutograderPoints()} + "/" + {$gradeable->getTotalAutograderNonExtraCreditPoints()});
    }
    calculatePercentageTotal();

    $('body').css({'position':'fixed', 'width':'100%'});
    $('#header').css({'position':'fixed', 'z-index':'1099'});

    var progressbar = $(".progressbar"),
        value = progressbar.val();
    $(".progress-value").html("<b>" +value + '%</b>');

    //Used to reset users cookies
    var cookie_version = 1;

    //Set positions and visibility of configurable ui elements
    $(document).ready(function(){

        //Check each cookie and test for 'undefined'. If any cookie is undefined
        $.each(document.cookie.split(/; */), function(){
            var cookie = this.split("=")
            if(!cookie[1] || cookie[1] == 'undefined'){
                deleteCookies();
            }
        });

        if(document.cookie.replace(/(?:(?:^|.*;\s*)cookie_version\s*\=\s*([^;]*).*$)|^.*$/, "$1") != cookie_version) {
            //If cookie version is not the same as the current version then toggle the visibility of each
            //rubric panel then update the cookies
            deleteCookies();
            handleKeyPress("KeyG");
            handleKeyPress("KeyA");
            handleKeyPress("KeyS");
            handleKeyPress("KeyO");
            handleKeyPress("KeyR");
            updateCookies();
        }
        else{
            readCookies();
        }
    });

    function createCookie(name,value,seconds)  {
        if(seconds) {
            var date = new Date();
            date.setTime(date.getTime()+(seconds*1000));
            var expires = "; expires="+date.toGMTString();
        }
        else var expires = "";
        document.cookie = name+"="+value+expires+"; path=/";
    }

    function eraseCookie(name) {
        createCookie(name,"",-3600);
    }

    function deleteCookies(){
        $.each(document.cookie.split(/; */), function(){
            var cookie = this.split("=")
            if(!cookie[1] || cookie[1] == 'undefined'){
                document.cookie = cookie[0] + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
                document.cookie = "cookie_version=-1; path=/;";
            }
        });
    }

    function readCookies(){
        var output_top = document.cookie.replace(/(?:(?:^|.*;\s*)output_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var output_left = document.cookie.replace(/(?:(?:^|.*;\s*)output_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var output_width = document.cookie.replace(/(?:(?:^|.*;\s*)output_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var output_height = document.cookie.replace(/(?:(?:^|.*;\s*)output_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var output_visible = document.cookie.replace(/(?:(?:^|.*;\s*)output_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");


        var files_top = document.cookie.replace(/(?:(?:^|.*;\s*)files_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var files_left = document.cookie.replace(/(?:(?:^|.*;\s*)files_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var files_width = document.cookie.replace(/(?:(?:^|.*;\s*)files_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var files_height = document.cookie.replace(/(?:(?:^|.*;\s*)files_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var files_visible = document.cookie.replace(/(?:(?:^|.*;\s*)files_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

        var rubric_top = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var rubric_left = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var rubric_width = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var rubric_height = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var rubric_visible = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

        var status_top = document.cookie.replace(/(?:(?:^|.*;\s*)status_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var status_left = document.cookie.replace(/(?:(?:^|.*;\s*)status_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var status_width = document.cookie.replace(/(?:(?:^|.*;\s*)status_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var status_height = document.cookie.replace(/(?:(?:^|.*;\s*)status_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        var status_visible = document.cookie.replace(/(?:(?:^|.*;\s*)status_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

        (output_top) ? $("#autograding_results").css("top", output_top):{};
        (output_left) ? $("#autograding_results").css("left", output_left):{};
        (output_width) ? $("#autograding_results").css("width", output_width):{};
        (output_height) ? $("#autograding_results").css("height", output_height):{};
        (output_visible) ? $("#autograding_results").css("display", output_visible):{};

        (rubric_top) ? $("#grading_rubric").css("top", rubric_top):{};
        (rubric_left) ? $("#grading_rubric").css("left", rubric_left):{};
        (rubric_width) ? $("#grading_rubric").css("width", rubric_width):{};
        (rubric_height) ? $("#grading_rubric").css("height", rubric_height):{};
        (rubric_visible) ? $("#grading_rubric").css("display", rubric_visible):{};

        (files_top) ? $("#submission_browser").css("top", files_top):{};
        (files_left) ? $("#submission_browser").css("left", files_left):{};
        (files_width) ? $("#submission_browser").css("width", files_width):{};
        (files_height) ? $("#submission_browser").css("height", files_height):{};
        (files_visible) ? $("#submission_browser").css("display", files_visible):{};

        (status_top) ? $("#student_info").css("top", status_top):{};
        (status_left) ? $("#student_info").css("left", status_left):{};
        (status_width) ? $("#student_info").css("width", status_width):{};
        (status_height) ? $("#student_info").css("height", status_height):{};
        (status_visible) ? $("#student_info").css("display", status_visible):{};

        (output_visible) ? ((output_visible) == "none" ? $(".icon-auto-grading-results").removeClass("icon-selected") : $(".icon-auto-grading-results").addClass("icon-selected")) : {};
        (files_visible) ? ((files_visible) == "none" ? $(".icon-files").removeClass("icon-selected") : $(".icon-files").addClass("icon-selected")) : {};
        (rubric_visible) ? ((rubric_visible) == "none" ? $(".icon-grading-panel").removeClass("icon-selected") : $(".icon-grading-panel").addClass("icon-selected")) : {};
        (status_visible) ? ((status_visible) == "none" ? $(".icon-status").removeClass("icon-selected") : $(".icon-status").addClass("icon-selected")) : {};
    }

    function updateCookies(){
        document.cookie = "output_top=" + $("#autograding_results").css("top") + "; path=/;";
        document.cookie = "output_left=" + $("#autograding_results").css("left") + "; path=/;";
        document.cookie = "output_width=" + $("#autograding_results").css("width") + "; path=/;";
        document.cookie = "output_height=" + $("#autograding_results").css("height") + "; path=/;";
        document.cookie = "output_visible=" + $("#autograding_results").css("display") + "; path=/;";

        document.cookie = "rubric_top=" + $("#grading_rubric").css("top") + "; path=/;";
        document.cookie = "rubric_left=" + $("#grading_rubric").css("left") + "; path=/;";
        document.cookie = "rubric_width=" + $("#grading_rubric").css("width") + "; path=/;";
        document.cookie = "rubric_height=" + $("#grading_rubric").css("height") + "; path=/;";
        document.cookie = "rubric_visible=" + $("#grading_rubric").css("display") + "; path=/;";

        document.cookie = "files_top=" + $("#submission_browser").css("top") + "; path=/;";
        document.cookie = "files_left=" + $("#submission_browser").css("left") + "; path=/;";
        document.cookie = "files_width=" + $("#submission_browser").css("width") + "; path=/;";
        document.cookie = "files_height=" + $("#submission_browser").css("height") + "; path=/;";
        document.cookie = "files_visible=" + $("#submission_browser").css("display") + "; path=/;";

        document.cookie = "status_top=" + $("#student_info").css("top") + "; path=/;";
        document.cookie = "status_left=" + $("#student_info").css("left") + "; path=/;";
        document.cookie = "status_width=" + $("#student_info").css("width") + "; path=/;";
        document.cookie = "status_height=" + $("#student_info").css("height") + "; path=/;";
        document.cookie = "status_visible=" + $("#student_info").css("display") + "; path=/;";

        document.cookie = "cookie_version=" + cookie_version + "; path=/;";
    }

    $( ".draggable" ).draggable({snap:false, grid:[2, 2], stack:".draggable"}).resizable();

    $(".draggable").on("dragstop", function(){
        updateCookies();
    });

    $(".draggable").on("resizestop", function(){
        updateCookies();
    });

    if($("#rubric-autoscroll-checkbox").is(':checked')) {
        $('#grading_rubric').css("overflow-y", "hidden");
    }

    $("#rubric-autoscroll-checkbox").change(function() {
        if($("#rubric-autoscroll-checkbox").is(':checked')) {
            createCookie('auto',1,1000);
            $('#grading_rubric').css("overflow-y", "hidden");
        }
        else {
            eraseCookie('auto');
            $('#grading_rubric').css("overflow-y", "scroll");
        }
    });

    $('.content').scroll(function() {
        if($("#rubric-autoscroll-checkbox").is(':checked')) {
            var scrollPercentage = this.scrollTop / (this.scrollHeight-this.clientHeight);
            document.getElementById('grading_rubric').scrollTop = scrollPercentage * (document.getElementById('grading_rubric').scrollHeight-document.getElementById('grading_rubric').clientHeight);
        }
    });

    window.onkeydown = function(e) {
        if (e.target.tagName == "TEXTAREA" || e.target.tagName == "INPUT" || e.target.tagName == "SELECT") return; // disable keyboard event when typing to textarea/input
        handleKeyPress(e.code);
    };

    function handleKeyPress(key) {
        switch (key) {
            case "KeyA":
                $('.icon-auto-grading-results').toggleClass('icon-selected');
                $("#autograding_results").toggle();
                break;
            case "KeyG":
                $('.icon-grading-panel').toggleClass('icon-selected');
                $("#grading_rubric").toggle();
                break;
            case "KeyO":
                $('.icon-files').toggleClass('icon-selected');
                $("#submission_browser").toggle();
                break;
            case "KeyS":
                $('.icon-status').toggleClass('icon-selected');
                $("#student_info").toggle();
                break;
            case "KeyR":
                $('.icon-auto-grading-results').addClass('icon-selected');
                $("#autograding_results").attr("style", "left:15px; top:175px; width:48%; height:37%; display:block;");
                $('.icon-grading-panel').addClass('icon-selected');
                $("#grading_rubric").attr("style", "right:15px; top:175px; width:48%; height:37%; display:block;");
                $('.icon-files').addClass('icon-selected');
                $("#submission_browser").attr("style", "left:15px; bottom:40px; width:48%; height:30%; display:block;");
                $('.icon-status').addClass('icon-selected');
                $("#student_info").attr("style", "right:15px; bottom:40px; width:48%; height:30%; display:block;");
                deleteCookies();
                updateCookies();
                break;
            default:
                break;
        }
        updateCookies();
    };

    eraseCookie("reset");
</script>
HTML;
        return $return;
    }
}
