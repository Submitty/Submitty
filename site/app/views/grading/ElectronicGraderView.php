<?php

namespace app\views\grading;

use app\models\Gradeable;
use app\models\User;
use app\models\LateDaysCalculation;
use app\views\AbstractView;
use app\libraries\FileUtils;

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
            $graded += $section['graded_components'];
            $total += $section['total_components'];
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
    </div>
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
            $percentage = round(($section['graded_components'] / $section['total_components']) * 100);
            $return .= <<<HTML
            Section {$key}: {$percentage}% ({$section['graded_components']} / {$section['total_components']})<br />
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
        $return .= <<<HTML
        </div>
    </div>
HTML;
    }
    //{$this->core->getConfig()->getTABaseUrl()}account/account-summary.php?course={$course}&semester={$semester}&g_id={$gradeable->getId()}
    $return .= <<<HTML
    <div style="margin-top: 20px">
HTML;
        if($percentage !== -1 || $this->core->getUser()->accessFullGrading()){
            $return .= <<<HTML
        <a class="btn btn-primary" 
            href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action' => 'details', 'gradeable_id' => $gradeable->getId(), 'view' => $view))}"">
            Grading Details
        </a>
HTML;
            if(count($this->core->getUser()->getGradingRegistrationSections()) !== 0){
                $return .= <<<HTML
        <a class="btn btn-primary"
            href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'individual'=>'0'))}">
            Grade Next Student
        </a>
        <a class="btn btn-primary"
            href="{$this->core->buildUrl(array('component'=>'misc', 'page'=>'download_all_assigned', 'dir'=>'submissions', 'gradeable_id'=>$gradeable->getId()))}">
            Download Zip of All Assigned Students
        </a>
HTML;
            }
            if($this->core->getUser()->accessFullGrading()) {
                $return .= <<<HTML
        <a class="btn btn-primary" 
            href="{$this->core->buildUrl(array('component'=>'misc', 'page'=>'download_all_assigned', 'dir'=>'submissions', 'gradeable_id'=>$gradeable->getId(), 'type'=>'All'))}">
            Download Zip of All Students
        </a>
HTML;
            }
        }
        $return .= <<<HTML
    </div>
</div>
HTML;
        return $return;
    }

    /**
     * @param Gradeable   $gradeable
     * @param Gradeable[] $rows
     * @param array       $graders
     * @return string
     */
    public function detailsPage($gradeable, $rows, $graders) {
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
            href="{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'action' => 'details', 'gradeable_id' => $gradeable->getId(), 'view' => $view))}">
            $text
        </a>
    </div>
HTML;
        }
        $show_auto_grading_points = true;
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
HTML;

        if($gradeable->getTotalAutograderNonExtraCreditPoints() !== 0) {
            $return .= <<<HTML
                <td width="9%">Autograding</td>
                <td width="8%">TA Grading</td>
                <td width="7%">Total</td>
                <td width="10%">Active Version</td>
                <td width="8%">Viewed Grade</td>
            </tr>
        </thead>
HTML;
        }
        else {
            $show_auto_grading_points = false;
            $return .= <<<HTML
                <td width="12%">TA Grading</td>
                <td width="12%">Total</td>
                <td width="10%">Active Version</td>
                <td width="8%">Viewed Grade</td>
            </tr>
        </thead>
HTML;
        }
            $count = 1;
            $last_section = false;
            $tbody_open = false;
            foreach ($rows as $row) {
                $active_version = $row->getActiveVersion();
                $highest_version = $row->getHighestVersion();
                $autograding_score = $row->getGradedAutograderPoints();
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
                    $different = false;
                }
                else{
                    $viewed_grade = "";
                    $grade_viewed = "";
                    $grade_viewed_color = "";
                }
                $total_possible = $row->getTotalAutograderNonExtraCreditPoints() + $row->getTotalTANonExtraCreditPoints();
                $graded = $autograding_score + $row->getGradedTAPoints();

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
                    $cols = ($show_auto_grading_points) ? 10 : 9;
                    $return .= <<<HTML
        <tr class="info persist-header">
            <td colspan="{$cols}" style="text-align: center">Students Enrolled in Section {$display_section}</td>
        </tr>
        <tr class="info">
            <td colspan="{$cols}" style="text-align: center">Graders: {$section_graders}</td>
        </tr>
        <tbody id="section-{$section}">
HTML;
                }
                $style = "";
                if ($row->getUser()->accessGrading()) {
                    $style = "style='background: #7bd0f7;'";
                }
                $return .= <<<HTML
            <tr id="user-row-{$row->getUser()->getId()}" {$style}>
                <td>{$count}</td>
                <td>{$display_section}</td>
                <td>{$row->getUser()->getId()}</td>
                <td>{$row->getUser()->getDisplayedFirstName()}</td>
                <td>{$row->getUser()->getLastName()}</td>
HTML;

                if($show_auto_grading_points) {
                    if ($highest_version != 0) {
                        $return .= <<<HTML
                <td>{$autograding_score}&nbsp;/&nbsp;{$row->getTotalAutograderNonExtraCreditPoints()}</td>
HTML;
                    }
                    else {
                        $return .= <<<HTML
                <td></td>
HTML;
                    }
                }
                if ($highest_version != 0) {
                    $return .= <<<HTML
                <td>
HTML;
                    $box_background = "";
                    if ($row->getActiveDaysLate() > $row->getAllowedLateDays()) {
                        $box_background = "late-box";
                    }
                    
                    if ($row->beenTAgraded()) {
                        $btn_class = "btn-default";
                        if($row->validateVersions()) {
                            $contents = "{$row->getGradedTAPoints()}&nbsp;/&nbsp;{$row->getTotalTANonExtraCreditPoints()}";
                        }
                        else{
                            $contents = "Version Conflict";
                        }
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
HTML;
                    if($row->validateVersions()) {
                        $return .= <<<HTML
                <td><div class="{$box_background}">{$graded}&nbsp;/&nbsp;{$total_possible}</div></td>
HTML;
                    }
                    else{
                        $return .= <<<HTML
                <td></td>
HTML;
                    }
                    if($active_version == $highest_version) {
                        $return .= <<<HTML
                <td>{$active_version}</td>
HTML;
                    }
                    else {
                        $return .= <<<HTML
                <td>{$active_version}&nbsp;/&nbsp;{$highest_version}</td>
HTML;
                    }
                }
                else {
                    $return .= <<<HTML
                <td>
                    <a class="btn btn-default" style="color:#a5a5a5;" href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'grade', 'gradeable_id'=>$gradeable->getId(), 'who_id'=>$row->getUser()->getId(), 'individual'=>'1'))}">Grade
                    </a>
                </td>
                <td></td>
                <td></td>
HTML;
                }
                $return .= <<<HTML
                <td title="{$grade_viewed}" style="{$grade_viewed_color}">{$viewed_grade}</td>
            </tr>
HTML;
                $count++;
            }
            $return .= <<<HTML
        </tbody>
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
    <a href="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action'=>'details', 'gradeable_id'=>$gradeable->getId()))}"><i title="Go to the main page" class="icon-home" ></i></a>
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
            $return .= $this->core->getOutput()->renderTemplate('AutoGrading', 'showResults', $gradeable, true);
        }
        $return .= <<<HTML
    </div>
</div>

<div id="submission_browser" class="draggable rubric_panel" style="left:15px; bottom:40px; width:48%; height:30%">
    <span class="grading_label">Submissions and Results Browser</span>
    <button class="btn btn-default" onclick="openAll()">Expand All</button>
    <button class="btn btn-default" onclick="closeAll()">Close All</button>
    <button class="btn btn-default" onclick="downloadZip('{$gradeable->getId()}','{$gradeable->getUser()->getId()}')">Download Zip File</button>
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
                    $content_url = urldecode($contents); 
                    $indent_offset = $indent * -15;
                    $super_url = $content_url;
                    $return .= <<<HTML
                <div>
                    <div class="file-viewer">
                        <a class='openAllFile' onclick='openFrame("{$dir}", "{$contents}", {$count})'>
                            <span class='icon-plus' style='vertical-align:text-bottom;'></span>
                        {$dir}</a> &nbsp;
                        <a onclick='openFile("{$dir}", "{$contents}")'><i class="fa fa-window-restore" aria-hidden="true" title="Pop up the file in a new window"></i></a>
                        <a onclick='downloadFile("{$dir}", "{$contents}")'><i class="fa fa-download" aria-hidden="true" title="Download the file"></i></a>
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
                    $url = reset($contents);
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
        <h5 class='label' style="float:right; padding-right:15px;">Browse Student Submissions:</h5>
        <div class="rubric-title">
HTML;
        $who = $gradeable->getUser()->getId();
        $onChange = "versionChange('{$this->core->buildUrl(array('component' => 'grading', 'page' => 'electronic', 'action' => 'grade', 'gradeable_id' => $gradeable->getId(), 'who_id'=>$who, 'individual'=>$individual,
                                                      'gradeable_version' => ""))}', this)";
        $formatting = "font-size: 13px;float:right;";
        $return .= $this->core->getOutput()->renderTemplate('AutoGrading', 'showVersionChoice', $gradeable, $onChange, $formatting);
        $return .= <<<HTML
        <b>{$user->getFirstName()} {$user->getLastName()} ({$user->getId()})<br/>
        Submission Number: {$gradeable->getActiveVersion()} / {$gradeable->getHighestVersion()}<br/>
        Submitted:&nbsp{$gradeable->getSubmissionTime()->format("m/d/Y H:i:s")}<br/></b>
HTML;
            
        // If viewing the active version, show cancel button, otherwise so button to switch active
        if ($gradeable->getCurrentVersionNumber() > 0) {
            if ($gradeable->getCurrentVersionNumber() == $gradeable->getActiveVersion()) {
                $version = 0;
                $button = '<input type="submit" class="btn btn-default btn-xs" style="float: right;" value="Cancel Student Submission">';
            }
            else {
                $version = $gradeable->getCurrentVersionNumber();
                $button = '<input type="submit" class="btn btn-default btn-xs" style="float: right;" value="Grade This Version">';
            }
            $return .= <<<HTML
        <form style="display: inline;" method="post" onsubmit='return checkTaVersionChange();'
                action="{$this->core->buildUrl(array('component' => 'student',
                                                     'action' => 'update',
                                                     'gradeable_id' => $gradeable->getId(),
                                                     'new_version' => $version, 'ta' => true, 'who' => $who, 'individual' => $individual))}">
            <input type='hidden' name="csrf_token" value="{$this->core->getCsrfToken()}" />
            {$button}
        </form>
        </div>

HTML;
        }
        $return .= <<<HTML
        <form id="rubric_form" action="{$this->core->buildUrl(array('component'=>'grading', 'page'=>'electronic', 'action' => 'submit'))}" method="post">
            <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
            <input type="hidden" name="g_id" value="{$gradeable->getId()}" />
            <input type="hidden" name="u_id" value="{$user->getId()}" />
            <input type="hidden" name="individual" value="{$individual}" />
            <input type="hidden" name="graded_version" value="{$gradeable->getActiveVersion()}" />
HTML;

        //Late day calculation
        $ldu = new LateDaysCalculation($this->core);
        $return .= $ldu->generateTableForUserDate($gradeable->getName(), $user->getId(), $gradeable->getDueDate());
        $late_days_data = $ldu->getGradeable($user->getId(), $gradeable->getId());
        $status = $late_days_data['status'];

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
</div>

<div id="grading_rubric" class="draggable rubric_panel" style="right:15px; top:140px; width:48%; height:42%;">
    <span class="grading_label">Grading Rubric</span>
HTML;
        $disabled = '';
        if($gradeable->getCurrentVersionNumber() != $gradeable->getActiveVersion() || $gradeable->getCurrentVersionNumber() == 0){
            $disabled='disabled';
            $return .= <<<HTML
    <div class="red-message" style="text-align: center">Select the correct submission version to grade</div>
HTML;
        }
        $return .= <<<HTML
    <div style="margin:3px;">
        <table class="rubric-table" id="rubric-table">
            <tbody>
HTML;

        $c = 1;
        $precision = floatval($gradeable->getPointPrecision());
        $num_questions = count($gradeable->getComponents());

        foreach ($gradeable->getComponents() as $question) {
            $type = 0; //0 is common deductable, 1 is common additive
            $min = -1000;
            $max = 0;
            foreach ($question->getMarks() as $mark) {
                if($mark->getPoints() < 0) {
                    $min = -1000;
                    $max = 0;
                    $type = 0;
                    break;
                }
                else if ($mark->getPoints() > 0) {
                    $min = 0;  
                    $max = 1000;
                    $type = 1;
                    break;
                }
            }
            $word = ($type === 1) ? "Addition" : "Deduction";
            // hide auto-grading if it has no value
            if (($question->getScore() == 0) && (substr($question->getTitle(), 0, 12) === "AUTO-GRADING")) {
                $question->setScore(floatval($gradeable->getGradedAutograderPoints()));
            }
    
            if(substr($question->getTitle(), 0, 12) === "AUTO-GRADING") {
                $disabled = 'disabled';
            }
    
            $return .= <<<HTML
                <tr>
HTML;
            $penalty = !(intval($question->getMaxValue()) >= 0);
            $message = htmlentities($question->getTitle());
            $message = "<b>{$message}</b>";
            if ($question->getGradedVersion() != -1 && $gradeable->getActiveVersion() != $question->getGradedVersion()) {
                $message .= "  " . "Before submitting regrade, please edit or ensure that comments from version " . $question->getGradedVersion() . " still apply.";
            }
            $note = htmlentities($question->getTaComment());
            if ($note != "") {
                $note = "<br/><div style='margin-bottom:5px; color:#777;'><i><b>Note to TA: </b>" . $note . "</i></div>";
            }

            //adds an icon depending on the question type (extra credit, normal, penalty)
            //adds background color as well.
            if($question->getIsExtraCredit()) {
                $return .= <<<HTML
                    <td style="font-size: 12px; background-color: #D8F2D8;" colspan="4">
                        <i class="icon-plus"></i> <b>{$message}</b>
HTML;
            }
            else if($penalty) {
                $return .= <<<HTML
                    <td style="font-size: 12px; background-color: #FAD5D3;" colspan="4">
                        <i class="icon-minus"></i> <b>{$message}</b>
HTML;
            }
            else {
                $return .= <<<HTML
                    <td style="font-size: 12px;" colspan="4">
                        <b>{$message}</b>
HTML;
            }
            $return .= <<<HTML
            <span onclick=""> <i id="icon-{$c}" data-question_id="{$question->getId()}" class="fa fa-window-maximize" style="visibility: visible;"></i> </span> {$note} 
HTML;

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
                <tr id="summary-{$c}" style="background-color: #f9f9f9;" onclick="saveMark(-2,'{$gradeable->getId()}' ,'{$user->getId()}', {$gradeable->getActiveVersion()}); openClose({$c}, {$num_questions});">
                    <td style="white-space:nowrap; vertical-align:middle; text-align:center; {$background}" colspan="1">
                        <input readonly type="text" id="grade-{$question->getOrder()}" name="grade-{$question->getOrder()}" value="{$question->getScore()}" onchange="validateInput('grade-{$question->getOrder()}', '{$question->getMaxValue()}', {$precision}); calculatePercentageTotal();" style="width:50px; resize:none;" {$disabled}></textarea>
                        <strong> / {$question->getMaxValue()}</strong>
                    </td>
                    <td style="width:98%; {$background}" colspan="3">
                        <div id="rubric-{$c}">
                            <textarea readonly id="rubric-textarea-{$c}" name="comment-{$question->getOrder()}" rows="4" style="width:95%; height:100%; min-height:80px; resize:none; float:left;" placeholder="Message for the student..." comment-position="0" {$disabled}>{$question->getComment()}</textarea>
                        </div>
                    </td>
                </tr>
                <tbody id="extra-{$c}" style="{$background}; display: none" colspan="4">
                <tr id="mark_header_id={$c}" name="mark_header_{$c}">
                    <td colspan="4", style="{$background}">
                            Common Grade {$word}
                        <span onclick="saveMark({$c},'{$gradeable->getId()}' ,'{$user->getId()}', {$gradeable->getActiveVersion()}, {$question->getId()}); openClose({$c}, {$num_questions});" style="float: right; cursor: pointer;"> <i class="fa fa-check" style="color: green;" aria-hidden="true">Done</i>
                        </span>
                    </td>
                </tr>
HTML;

            $d = 0;
            $first = true;
            $noChange = "";
            foreach ($question->getMarks() as $mark) {
                if ($first === true) {
                    $first = false;
                    $noChange = "readonly";
                }
                else {
                    $noChange = "";
                }
                $icon_mark = ($mark->getHasMark() === true) ? "fa-square" : "fa-square-o";
                $return .= <<<HTML
                <tr id="mark_id-{$c}-{$d}" name="mark_{$c}">
                    <td colspan="1" style="{$background}; text-align: center;"> <input name="mark_points_{$c}_{$d}" type="number" step="{$precision}" value="{$mark->getPoints()}" min="{$min}" max="{$max}" style="width: 50%; resize:none;" {$noChange}>
                        <span onclick="selectMark(this);"> <i class="fa {$icon_mark}" name="mark_icon_{$c}_{$d}" style="visibility: visible; cursor: pointer; position: relative; top: 2px;"></i> </span>
                    </td>
                    <td colspan="3" style="{$background}">
                        <textarea name="mark_text_{$c}_{$d}" onkeyup="" rows="1" style="width:95%; resize:none; float:left;" {$noChange}>{$mark->getNote()}</textarea>
                    </td>
                </tr>
HTML;
            $d++;
            }

                $return .= <<<HTML
                <tr>
                    <td colspan="4" style="{$background};">
                        <span style="cursor: pointer;" onclick="addMark(this, {$c}, '{$background}', {$min}, {$max}, '{$precision}'); return false;"><i class="fa fa-plus-square " aria-hidden="true"></i>
                        Add New {$word}</span>
                    </td>
                </tr>
                <tr id="mark_custom_id-{$c}" name="mark_custom_{$c}">
                    <td colspan="1" style="{$background}; text-align: center;"> <input name="mark_points_custom_{$c}" type="number" step="{$precision}" value="0" min="{$min}" max="{$max}" style="width: 50%; resize:none;">
                    </td>
                    <td colspan="3" style="{$background}">
                        <textarea name="mark_text_custom_{$c}" onkeyup="autoResizeComment(event);" rows="1" placeholder="Custom message for student..." style="width:95%; resize:none; float:left;">{$question->getComment()}</textarea>
                    </td>
                </tr>
                </tbody>
HTML;
            $c++;
        }

        $return .= <<<HTML
            <tr>
                <td colspan="4">
                    <b>General Comment</b> <span onclick=""> <i id="icon-general-comment" class="fa fa-window-maximize" style="visibility: visible;"></i>
                </td>
            </tr>
            <tr onclick="saveMark(-2,'{$gradeable->getId()}' ,'{$user->getId()}', {$gradeable->getActiveVersion()}); openClose(-1, {$num_questions});">
                <td colspan="4">
                    <textarea name="comment-general" rows="5" style="width:98%; height:100%; min-height:100px; resize:none; float:left;" onkeyup="autoResizeComment(event);" placeholder="Overall message for student about the gradeable..." comment-position="0" {$disabled}>{$gradeable->getOverallComment()}</textarea>
                </td>
            </tr>
HTML;

        $total_points = $gradeable->getTotalAutograderNonExtraCreditPoints() + $gradeable->getTotalTANonExtraCreditPoints();
        $return .= <<<HTML
                <tr>
                    <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top:5px #FAA732 solid;" colspan="1"><strong>TOTAL</strong></td>
                    <td style="background-color: #EEE; border-top:5px #FAA732 solid;" colspan="1"><strong id="score_total">0 / {$total_points}&emsp;&emsp;&emsp;
                        AUTO-GRADING {$gradeable->getGradedAutograderPoints()} / {$gradeable->getTotalAutograderNonExtraCreditPoints()}</strong>
                    </td>
                </tr>
            </tbody>
        </table><br/>
HTML;
        if ($gradeable->beenTAgraded()) {
            // assumes that the person who graded the first question graded everything... also in electronicGraderController:150...have to rewrite to be per component
            $return .= <<<HTML
        <div style="width:100%; margin-left:10px;">
            <br />Overwrite Grader: <input type='checkbox' name='overwrite' value='1' /><br />
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
        if (!($now < $gradeable->getGradeStartDate()) && ($total_points > 0)) {
            if($gradeable->beenTAgraded()) {
                $return .= <<<HTML
            <input class="btn btn-large btn-warning" type="submit" value="Submit Homework Re-Grade" onclick="createCookie('backup',1,1000);" {$disabled}/>
HTML;
            }
            else {
                $return .= <<<HTML
            <input class="btn btn-large btn-primary" type="submit" value="Submit Homework Grade" {$disabled}/>
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
<script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}/js/ta-grading.js"></script>
<script type="text/javascript">
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

    function downloadZip(grade_id, user_id) {
        window.location = buildUrl({'component': 'misc', 'page': 'download_zip', 'dir': 'submissions', 'gradeable_id': grade_id, 'user_id': user_id});
        return false;
    }

    function downloadFile(html_file, url_file) {
        url_file = decodeURIComponent(url_file);        
        window.location = buildUrl({'component': 'misc', 'page': 'download_file', 'dir': 'submissions', 'file': html_file, 'path': url_file});
        return false;
    }

    function openFile(html_file, url_file) {
        url_file = decodeURIComponent(url_file);
        window.open("{$this->core->getConfig()->getSiteUrl()}&component=misc&page=display_file&dir=submissions&file=" + html_file + "&path=" + url_file,"_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
        return false;
    }

    function calculatePercentageTotal() {
        var total=0;

        $('#rubric-table').find('.grades').each(function() {
            if(!isNaN(parseFloat($(this).val()))) {
                total += parseFloat($(this).val());
            }
        });
            
        total = Math.max(parseFloat(total + {$gradeable->getGradedAutograderPoints()}), 0);

        $("#score_total").html(total+" / "+parseFloat({$gradeable->getTotalAutograderNonExtraCreditPoints()} + {$gradeable->getTotalTANonExtraCreditPoints()}) + "&emsp;&emsp;&emsp;" + " AUTO-GRADING: " + {$gradeable->getGradedAutograderPoints()} + "/" + {$gradeable->getTotalAutograderNonExtraCreditPoints()});
    }

    function addMark(me, num, background, min, max, precision) {
        var last_num = -10;
        var current_row = $(me.parentElement.parentElement);
        var current = $('[name=mark_'+num+']').last().attr('id');
        if (current == null) {
            last_num = -1;
        } 
        else {
            last_num = parseInt($('[name=mark_'+num+']').last().attr('id').split('-')[2]);
        }

        var new_num = last_num + 1;
        current_row.before(' \
<tr id="mark_id-'+num+'-'+new_num+'" name="mark_'+num+'"> \
    <td colspan="1" style="'+background+'; text-align: center;"> <input name="mark_points_'+num+'_'+new_num+'" type="number" step="'+precision+'" value="0" min="'+min+'" max="'+max+'" style="width: 50%; resize:none;"> \
                        <span onclick="selectMark(this);"> <i class="fa fa-square-o" name="mark_icon_'+num+'_'+new_num+'" style="visibility: visible; cursor: pointer; position: relative; top: 2px;"></i> </span> \
    </td> \
    <td colspan="3" style="'+background+'"> \
        <textarea name="mark_text_'+num+'_'+new_num+'" onkeyup="autoResizeComment(event);" rows="1" style="width:95%; resize:none; float:left;"></textarea> \
        <span id="mark_remove_id-'+num+'-'+new_num+'" onclick="deleteMark(this,'+num+','+new_num+');"> <i class="fa fa-times" style="visibility: visible; cursor: pointer; position: relative; top: 2px; left: 10px;"></i> </span> \
    </td> \
</tr> \
        '); 
    }

    function deleteMark(me, num, last_num) {
        var current_row = $(me.parentElement.parentElement);
        current_row.remove();
        var last_row = $('[name=mark_'+num+']').last().attr('id');
        var totalD = -1;
        if (last_row == null) {
            totalD = -1;
        } 
        else {
            totalD = parseInt($('[name=mark_'+num+']').last().attr('id').split('-')[2]);
        }
        var current_num = parseInt(last_num);
        for (var i = current_num + 1; i <= totalD; i++) {
            var new_num = i-1;
            var current_mark = $('#mark_id-'+num+'-'+i);
            current_mark.find('input[name=mark_points_'+num+'_'+i+']').attr('name', 'mark_points_'+num+'_'+new_num);
            current_mark.find('textarea[name=mark_text_'+num+'_'+i+']').attr('name', 'mark_text_'+num+'_'+new_num);
            current_mark.find('span[id=mark_remove_id-'+num+'-'+i+']').attr('onclick', 'deleteMark(this,'+num+','+new_num+');');
            current_mark.find('i[name=mark_icon_'+num+'_'+i+']').attr('name', 'mark_icon_'+num+'_'+new_num);
            current_mark.find('span[id=mark_remove_id-'+num+'-'+i+']').attr('id', 'mark_remove_id-'+num+'-'+new_num);
            current_mark.attr('id', 'mark_id-'+num+'-'+new_num);
        }
    }

    function selectMark(me) {
        var icon = $(me).find("i");
        icon.toggleClass("fa-square-o fa-square");
    }

    function openClose(row_id, num_questions) {
        var row_num = parseInt(row_id);
        var total_num = parseInt(num_questions);
        for (var x = 1; x <= num_questions; x++) {
            var current = document.getElementById('extra-' + x);
            var current_summary = document.getElementById('summary-' + x);
            var icon = document.getElementById('icon-' + x);
            if (x === row_num) {
                if (current.style.display === 'none') {
                    current.style.display = '';
                    current_summary.style.display = 'none';
                    if (icon.classList.contains('fa-window-maximize'))
                    {
                        icon.classList.remove('fa-window-maximize');
                    }
                    if(!(icon.classList.contains('fa-window-close-o'))) {
                        icon.classList.add('fa-window-close-o');
                    }
                } else {
                    current.style.display = 'none';
                    current_summary.style.display = '';
                    if (icon.classList.contains('fa-window-close-o'))
                    {
                        icon.classList.remove('fa-window-close-o');
                    }
                    if(!(icon.classList.contains('fa-window-maximize'))) {
                        icon.classList.add('fa-window-maximize');
                    }
                }
            } else {
                current.style.display = 'none';
                current_summary.style.display = '';
                if (icon.classList.contains('fa-window-close-o'))
                {
                    icon.classList.remove('fa-window-close-o');
                }
                if(!(icon.classList.contains('fa-window-maximize'))) {
                    icon.classList.add('fa-window-maximize');
                }
            }
        }
    }

    //num === -2 means save last opened component
    //num === -1 means save all components, TO DO?
    function saveMark(num, gradeable_id, user_id, active_version, gc_id = -1) {
        if (num === -2) {
            var index = 1;
            var found = false;
            var doesExist = ($('#icon-' + index).length) ? true : false;
            while(doesExist) {
                if($('#icon-' + index).length) {
                    if ($('#icon-' + index)[0].classList.contains('fa-window-close-o')) {
                        found = true;
                        doesExist = false;
                        index--;
                    }
                }
                else{
                    doesExist = false;
                }
                index++;
            }
            if (found === true) {
                var gradeable_component_id = parseInt($('#icon-' + index)[0].dataset.question_id);
                saveMark(index, gradeable_id, user_id, active_version, gradeable_component_id);
            }
        } else if (num === -1) {

        } else {
            var arr_length = $('tr[name=mark_'+num+']').length;
            var mark_data = new Array(arr_length);
            for (var i = 0; i < arr_length; i++) {
                var current_row = $('#mark_id-'+num+'-'+i);
                var delete_mark = $('#mark_remove_id-'+num+'-'+i);
                var is_selected = false;
                if (current_row.find('i[name=mark_icon_'+num+'_'+i+']')[0].classList.contains('fa-square')) {
                    is_selected = true;
                }
                var mark = {
                    points: current_row.find('input[name=mark_points_'+num+'_'+i+']').val(),
                    note: current_row.find('textarea[name=mark_text_'+num+'_'+i+']').val(),
                    order: i,
                    selected: is_selected
                };
                mark_data[i] = mark;
                delete_mark.remove();
            }
            current_row = $('#mark_custom_id-'+num);
            var custom_points = current_row.find('input[name=mark_points_custom_'+num+']').val();
            var custom_message = current_row.find('textarea[name=mark_text_custom_'+num+']').val();
            //alert (JSON.stringify(mark_data));
            $.ajax({
                type: "POST",
                url: buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'save_one_component'}),
                data: {
                    'gradeable_id' : gradeable_id,
                    'user_id' : user_id,
                    'gradeable_component_id' : gc_id,
                    'num_mark' : arr_length,
                    'active_version' : active_version,
                    'custom_points' : custom_points,
                    'custom_message' : custom_message,
                    marks : mark_data
                },
                success: function(data) {
                    console.log("success");
                },
                error: function() {
                    console.log("Something went wront with saving marks...");
                }
            })
        }
    }
</script>
HTML;
        return $return;
    }
}
