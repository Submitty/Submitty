<?php

namespace app\views\submission;

use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\Gradeable;
use app\views\AbstractView;

class HomeworkView extends AbstractView {

    public function noGradeable($gradeable_id) {
        if ($gradeable_id === null) {
            return <<<HTML
<div class="content">
    No gradeable id specified. Contact your instructor if you think this is an error.
</div>
HTML;
        }
        else {
            $gradeable = htmlentities($gradeable_id, ENT_QUOTES);
            return <<<HTML
<div class="content">
    {$gradeable} is not a valid electronic submission gradeable. Contact your instructor if you think this
    is an error.
</div>
HTML;
        }
    }

    /**
     * @param Gradeable $gradeable
     *
     * @return bool|string
     */
    public function showGradeableError($gradeable) {
        return <<<HTML
<div class="content">
    <p class="red-message">
    {$gradeable->getName()} has not been built and cannot accept submissions at this time. The instructor
    needs to configure the config.json for this assignment and then build the course.
    </p>
</div>
HTML;
    }

    /**
     * TODO: BREAK UP THIS FUNCTION INTO EASIER TO MANAGE CHUNKS
     *
     * @param Gradeable $gradeable
     * @param int       $days_late
     *
     * @return string
     */
    public function showGradeable($gradeable, $days_late) {
        $upload_message = $this->core->getConfig()->getUploadMessage();
        $current_version = $gradeable->getCurrentVersion();
        $current_version_number = $gradeable->getCurrentVersionNumber();
        $return = <<<HTML
<script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}js/drag-and-drop.js"></script>
<div class="content">
    <h2>New submission for: {$gradeable->getName()}</h2>
HTML;
        if ($this->core->getUser()->accessAdmin()) {
            $return .= <<<HTML
    <form id="submissionForm" method="post" style="text-align: center; margin: 0 auto; width: 100%; ">
        <div >
            <input type='radio' id="radio_normal" name="submission_type" checked="true"> 
                Normal Submission
            <input type='radio' id="radio_student" name="submission_type">
                Make Submission for a Student
        </div>
        <div id="student_id_input" style="display: none">
            <div class="sub">
                Input the user_id of the student you wish to submit for. This <i>permanently</i> affects the student's submissions, so please use with caution.
            </div>
            <div class="sub">
                <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
                user_id: <input type="text" float="right" id= "student_id" name="student_id" value ="" placeholder="{$gradeable->getUser()->getId()}"/>
            </div class="sub">
        </div>

    </form>
HTML;
        }   

            $return .= <<<HTML
    <script type="text/javascript">
        $(document).ready(function() {
            var cookie = document.cookie;
            if (cookie.indexOf("student_checked=") !== -1) {
                var cookieValue = cookie.substring(cookie.indexOf("student_checked=")+16, cookie.indexOf("student_checked=")+17);
                $("#radio_student").prop("checked", cookieValue==1);
                document.cookie="student_checked="+0;
            }
            if ($("#radio_student").is(":checked")) {
                $('#student_id_input').show();
            }
            $('#radio_normal').click(function() {
                $('#student_id_input').hide();
                $('#student_id').val('');
            });
            $('#radio_student').click(function() {
                $('#student_id_input').show();
            });
        });
    </script>
HTML;



        $return .= <<<HTML
    <div class="sub">
HTML;
        if ($gradeable->hasAssignmentMessage()) {
            $return .= <<<HTML
        <p class='green-message'>{$gradeable->getAssignmentMessage()}</p>
HTML;
        }
        $return .= <<<HTML
    </div>
HTML;
        if($gradeable->useSvnCheckout()) {
            $return .= <<<HTML
    <input type="submit" id="submit" class="btn btn-primary" value="Grade SVN" />
HTML;
        }
        else {
            $return .= <<<HTML
    <div id="upload-boxes" style="display:table; border-spacing: 5px; width:100%">
HTML;


            for ($i = 0; $i < $gradeable->getNumTextBoxes(); $i++) {
                $label = $gradeable->getTextboxes()[$i]['label'];
                $rows = $gradeable->getTextboxes()[$i]['rows'];
                if ($rows == 0) {
                  $return .= <<<HTML
                    <p style="max-width: 50em;">
                    $label<br><input type="text" name="textbox_{$i}" id="textbox_{$i}" onKeyPress="handle_textbox_keypress();">
                    </p><br>
HTML;
                } else {
                  $return .= <<<HTML
                    <p style="max-width: 50em;">
                    $label<br><textarea rows="{$rows}" cols="50"  style="width:60em; height:100%;" name="textbox_{$i}" id="textbox_{$i}" onKeyPress="handle_textbox_keypress();"></textarea>
                    </p><br>
HTML;

                  // Allow tab in the larger text boxes (normally tab moves to the next textbox)
                  // http://stackoverflow.com/questions/6140632/how-to-handle-tab-in-textarea
$return .= <<<HTML
<script>
$("#textbox_{$i}").keydown(function(e) {
HTML;
$return .= <<<'HTML'
    if(e.keyCode === 9) { // tab was pressed
        // get caret position/selection
        var start = this.selectionStart;
        var end = this.selectionEnd;
        var $this = $(this);
        var value = $this.val();
        // set textarea value to: text before caret + tab + text after caret
        $this.val(value.substring(0, start)
                    + "\t"
                    + value.substring(end));
        // put caret at right position again (add one for the tab)
        this.selectionStart = this.selectionEnd = start + 1;
        // prevent the focus lose
        e.preventDefault();
        e.stopPropagation();
        return false;
    }
});
</script>
HTML;

                }
            }
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                if ($gradeable->getNumParts() > 1) {
                    $label = "Drag your {$gradeable->getPartNames()[$i]} here or click to open file browser";
                }
                else {
                    $label = "Drag your file(s) here or click to open file browser";
                }
                $return .= <<<HTML

        <div id="upload{$i}" style="cursor: pointer; text-align: center; border: dashed 2px lightgrey; display:table-cell; height: 150px;">
            <h3 class="label" id="label{$i}">{$label}</h3>
            <input type="file" name="files" id="input_file{$i}" style="display: none" onchange="addFilesFromInput({$i})" multiple />
        </div>
HTML;
            }

            $return .= <<<HTML

    </div>
    <div>
        {$upload_message}
	<br>
	&nbsp;
    </div>

    <button type="button" id="submit" class="btn btn-success" style="margin-right: 100px;">Submit</button>
    <button type="button" id="startnew" class="btn btn-primary">Clear</button>

HTML;
            if($current_version_number === $gradeable->getHighestVersion()
                && $current_version_number > 0) {
                $return .= <<<HTML
    <button type="button" id= "getprev" class="btn btn-primary">Use Most Recent Submission</button>
HTML;
            }

            $old_files = "";
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                foreach ($gradeable->getPreviousFiles($i) as $file) {
                    $size = number_format($file['size'] / 1024, 2);
                    // $escape_quote_filename = str_replace('\'','\\\'',$file['name']);
                    if (substr($file['relative_name'], 0, strlen("part{$i}/")) === "part{$i}/") {
                        $escape_quote_filename = str_replace('\'','\\\'',substr($file['relative_name'], strlen("part{$i}/")));
                    }
                    else
                        $escape_quote_filename = str_replace('\'','\\\'',$file['relative_name']);
                    $old_files .= <<<HTML

                addLabel('$escape_quote_filename', '{$size}', {$i}, true);
                readPrevious('$escape_quote_filename', {$i});
HTML;
                }
            }
            if ($current_version_number == $gradeable->getHighestVersion()
                && $current_version_number > 0 && $this->core->getConfig()->keepPreviousFiles()) {
                $return .= <<<HTML
    <script type="text/javascript">
        $(document).ready(function() {
            setUsePrevious();
            {$old_files}
        });
    </script>
HTML;
            }
                $return .= <<<HTML
    <script type="text/javascript">
        $(document).ready(function() {
            setButtonStatus();
        });
    </script>
HTML;
            $return .= <<<HTML

    <script type="text/javascript">
        // CLICK ON THE DRAG-AND-DROP ZONE TO OPEN A FILE BROWSER OR DRAG AND DROP FILES TO UPLOAD
        var num_parts = {$gradeable->getNumParts()};
        createArray(num_parts);
        var assignment_version = {$current_version_number};
        var highest_version = {$gradeable->getHighestVersion()};
        for (var i = 1; i <= num_parts; i++ ){
            var dropzone = document.getElementById("upload" + i);
            dropzone.addEventListener("click", clicked_on_box, false);
            dropzone.addEventListener("dragenter", draghandle, false);
            dropzone.addEventListener("dragover", draghandle, false);
            dropzone.addEventListener("dragleave", draghandle, false);
            dropzone.addEventListener("drop", drop, false);
        }

        $("#startnew").click(function(e){ // Clear all the selected files in the buckets
            for (var i = 1; i <= num_parts; i++){
              deleteFiles(i);
            }
            e.stopPropagation();
        });

        // GET FILES OF THE HIGHEST VERSION
        if (assignment_version == highest_version && highest_version > 0) {
            $("#getprev").click(function(e){
                $("#startnew").click();
                {$old_files}
                setUsePrevious();
                setButtonStatus();
                e.stopPropagation();
            });
        }
    </script>
HTML;
        }

        $svn_string = ($gradeable->useSvnCheckout()) ? "true" : "false";

        $return .= <<<HTML
    <script type="text/javascript">
        function submitStudentGradeable(student_id, highest_version) {
            handleSubmission("{$this->core->buildUrl(array('component' => 'student',
                                                            'page' => 'submission',
                                                            'action' => 'upload',
                                                            'gradeable_id' => $gradeable->getId()))}",
                                 "{$this->core->buildUrl(array('component' => 'student',
                                                               'gradeable_id' => $gradeable->getId()))}",
                                 {$days_late},
                                 {$gradeable->getAllowedLateDays()},
                                 highest_version,
                                 {$gradeable->getMaxSubmissions()},
                                 "{$this->core->getCsrfToken()}",
                                 {$svn_string},
                                 {$gradeable->getNumTextBoxes()},
                                 student_id);           
        }
        $(document).ready(function() {
            $("#submit").click(function(e){ // Submit button
                var user_id = "";
                if (document.getElementById("submissionForm")) {
                    user_id = document.getElementById("submissionForm").student_id.value;
                }
                // no RCS entered, upload for whoever is logged in
                if (user_id == ""){
                    handleSubmission("{$this->core->buildUrl(array('component' => 'student',
                                                               'page' => 'submission',
                                                               'action' => 'upload',
                                                               'gradeable_id' => $gradeable->getId()))}",
                                 "{$this->core->buildUrl(array('component' => 'student',
                                                               'gradeable_id' => $gradeable->getId()))}",
                                 {$days_late},
                                 {$gradeable->getAllowedLateDays()},
                                 {$gradeable->getHighestVersion()},
                                 {$gradeable->getMaxSubmissions()},
                                 "{$this->core->getCsrfToken()}",
                                 {$svn_string},
                                 {$gradeable->getNumTextBoxes()},
                                 "{$gradeable->getUser()->getId()}");
                }
                else {
                    validateStudentId("{$this->core->getCsrfToken()}", "{$gradeable->getId()}", user_id, submitStudentGradeable);
                }
                e.stopPropagation();
            });
        });
    </script>
</div>
HTML;

        if ($gradeable->getSubmissionCount() === 0) {
            $return .= <<<HTML
<div class="content">
    <span style="font-style: italic">No submissions for this assignment.</span>
</div>
HTML;
        }
        else {
            $return .= <<<HTML
<div class="content">

    <h3 class='label' style="float: left">Select Submission Version:</h3>
    <select style="margin: 0 10px;" name="submission_version"
    onChange="versionChange('{$this->core->buildUrl(array('component' => 'student',
                                                          'gradeable_id' => $gradeable->getId(),
                                                          'gradeable_version' => ""))}', this)">

HTML;
            if ($gradeable->getActiveVersion() == 0) {
                $selected = ($current_version_number == $gradeable->getActiveVersion()) ? "selected" : "";
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

                if ($version->getVersion() == $current_version_number) {
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
            // If viewing the active version, show cancel button, otherwise so button to switch active
            if ($current_version_number > 0) {
                if ($current_version->getVersion() == $gradeable->getActiveVersion()) {
                    $version = 0;
                    $button = '<input type="submit" class="btn btn-default" style="float: right" value="Do Not Grade This Assignment">';
                    $onsubmit = "";
                }
                else {
                    $version = $current_version->getVersion();
                    $button = '<input type="submit" class="btn btn-primary" value="Grade This Version">';
                    $onsubmit = "onsubmit='return checkVersionChange({$gradeable->getDaysLate()},{$gradeable->getAllowedLateDays()})'";;
                }
                $return .= <<<HTML
    <form style="display: inline;" method="post" {$onsubmit}
            action="{$this->core->buildUrl(array('component' => 'student',
                                                 'action' => 'update',
                                                 'gradeable_id' => $gradeable->getId(),
                                                 'new_version' => $version))}">
        <input type='hidden' name="csrf_token" value="{$this->core->getCsrfToken()}" />
        {$button}
    </form>


HTML;
            }

            if($gradeable->getActiveVersion() === 0 && $current_version_number === 0) {
                $return .= <<<HTML
    <div class="sub">
        <p class="red-message">
            Note: You have selected to NOT GRADE THIS ASSIGNMENT.<br />
            This assignment will not be graded by the instructor/TAs and a zero will be recorded in the gradebook.<br />
            You may select any version above and press "Grade This Version" to re-activate your submission for grading.<br />
        </p>
    </div>
HTML;
            }
            else {
	            if($gradeable->getActiveVersion() > 0
                    && $gradeable->getActiveVersion() === $current_version->getVersion()) {
                    $return .= <<<HTML
    <div class="sub">
        <p class="green-message">
            Note: This version of your assignment will be graded by the instructor/TAs and the score recorded in the gradebook.
        </p>
    </div>
HTML;
                    if ($gradeable->hasConditionalMessage()) {
                        $return .= <<<HTML
    <div class="sub" id="conditional_message" style="display: none;">
        <p class='green-message'>{$gradeable->getConditionalMessage()}</p>    
    </div>
HTML;
                    }
                }
                else {
		            if($gradeable->getActiveVersion() > 0) {
		                $return .= <<<HTML
   <div class="sub">
       <p class="red-message">
            Note: This version of your assignment will not be graded the instructor/TAs. <br />
HTML;
                    }
                    else {
                       $return .= <<<HTML
    <div class="sub">
        <p class="red-message">
            Note: You have selected to NOT GRADE THIS ASSIGNMENT.<br />
            This assignment will not be graded by the instructor/TAs and a zero will be recorded in the gradebook.<br />
HTML;
		            }

		                $return .= <<<HTML
            Click the button "Grade This Version" if you would like to specify that this version of your homework should be graded.
         </p>
     </div>
HTML;
	            }

                $return .= <<<HTML
    <div class="sub">
        <h4>Submitted Files</h4>
        <div class="box half">
HTML;
                $array = ($gradeable->useSvnCheckout()) ? $gradeable->getSvnFiles() : $gradeable->getSubmittedFiles();
                foreach ($array as $file) {
                    if (isset($file['size'])) {
                        $size = number_format($file['size'] / 1024, 2);
                    }
                    else {
                        $size = number_format(-1);
                    }
                    $return .= "{$file['relative_name']} ({$size}kb)<br />";
                }
                $return .= <<<HTML
        </div>
        <div class="box half">
HTML;
                $results = $gradeable->getResults();
                if($gradeable->hasResults()) {

                    $return .= <<<HTML
submission timestamp: {$current_version->getSubmissionTime()}<br />
days late: {$current_version->getDaysLate()} (before extensions)<br />
grading time: {$results['grade_time']} seconds<br />
HTML;
                    if($results['num_autogrades'] > 1) {
                      $regrades = $results['num_autogrades']-1;
                      $return .= <<<HTML
<br />
number of re-autogrades: {$regrades}<br />
last re-autograde finished: {$results['grading_finished']}<br />
HTML;
                    }
                    else {
                      $return .= <<<HTML
queue wait time: {$results['wait_time']} seconds<br />
HTML;
                    }
                }
                $return .= <<<HTML
        </div>
HTML;
                $return .= <<<HTML
    </div>
HTML;
                $return .= <<<HTML
    <div class="sub">
HTML;
                if (count($gradeable->getTestcases()) > 0) {
                    $return .= <<<HTML
        <h4>Results</h4>
HTML;
                }
                $refresh_js = <<<HTML
        <script type="text/javascript">
            checkRefreshSubmissionPage('{$this->core->buildUrl(array('component' => 'student',
                                                                     'page' => 'submission',
                                                                     'action' => 'check_refresh',
                                                                     'gradeable_id' => $gradeable->getId(),
                                                                     'gradeable_version' => $current_version_number))}')
        </script>
HTML;

                if ($gradeable->inBatchQueue() && $gradeable->hasResults()) {
                    if ($gradeable->beingGradedBatchQueue()) {
                        $return .= <<<HTML
        <p class="red-message">
            This submission is currently being regraded. It is one of {$gradeable->getNumberOfGradingTotal()} grading.
        </p>
HTML;
                    }
                    else {
                        $return .= <<<HTML
        <p class="red-message">
            This submission is currently in the queue to be regraded.
        </p>
HTML;
                    }

                }

                if ($gradeable->inInteractiveQueue() || ($gradeable->inBatchQueue() && !$gradeable->hasResults())) {
                    if ($gradeable->beingGradedInteractiveQueue() ||
                        (!$gradeable->hasResults() && $gradeable->beingGradedBatchQueue())) {
                        $return .= <<<HTML
        <p class="red-message">
            This submission is currently being graded. It is one of {$gradeable->getNumberOfGradingTotal()} grading.
        </p>
HTML;
                    }
                    else {
                        $return .= <<<HTML
        <p class="red-message">
            This submission is currently in the queue to be graded. Your submission is number {$gradeable->getInteractiveQueuePosition()} out of {$gradeable->getInteractiveQueueTotal()}.
        </p>
HTML;
                    }
                    $return .= <<<HTML
        {$refresh_js}
HTML;
                }
                else if(!$gradeable->hasResults()) {
                    $return .= <<<HTML
        <p class="red-message">
            Something has gone wrong with grading this submission. Please contact your instructor about this.
        </p>
HTML;
                }
                else {
                    $has_badges = false;
                    if ($gradeable->getNormalPoints() > 0) {
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
                        $return .= <<<HTML
        <div class="box">
            <div class="box-title">
                <span class="badge {$background}">{$current_version->getNonHiddenTotal()} / {$gradeable->getNormalPoints()}</span>
                <h4>Total</h4>
            </div>
        </div>
HTML;
                    }
                    if ($gradeable->hasConditionalMessage()) {
                        $return.= <<<HTML
<script type="text/javascript">
        $(document).ready(function() {
            if (({$current_version->getNonHiddenTotal()} >= {$gradeable->getMinimumPoints()}) &&
                    ({$gradeable->getDaysEarly()} > {$gradeable->getMinimumDaysEarly()})) {
                $('#conditional_message').show();
            }
        });
</script>
HTML;
                    }
                    $count = 0;
                    $display_box = (count($gradeable->getTestcases()) == 1) ? "block" : "none";
                    foreach ($gradeable->getTestcases() as $testcase) { 
                        if (!$testcase->viewTestcase()) {
                          continue;
                        }
                        $div_click = "";
                        if ($testcase->hasDetails()) { 
                            $div_click = "onclick=\"return toggleDiv('testcase_{$count}');\" style=\"cursor: pointer;\"";
                        }
                        $return .= <<<HTML
        <div class="box">
            <div class="box-title" {$div_click}>
HTML;
                        if ($testcase->hasDetails()) {
                            $return .= <<<HTML
                <div style="float:right; color: #0000EE; text-decoration: underline">Details</div>
HTML;
                        }
                        if ($testcase->hasPoints()) {
                            if ($testcase->isHidden()) {
                                $return .= <<<HTML
                <div class="badge">Hidden</div>
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
                        }
                        else if ($has_badges) {
                            $return .= <<<HTML
                <div class="no-badge"></div>
HTML;
                        }
                        $name = htmlentities($testcase->getName());
                        $extra_credit = "";
                        if($testcase->isExtraCredit()) {
                          $extra_credit = "&nbsp;&nbsp;<span class='italics'><font color=\"0a6495\">Extra Credit</font></span>";
                        }
                        $command = htmlentities($testcase->getDetails());
                        $testcase_message = "";
                        if (!$testcase->isHidden() && $testcase->viewTestcaseMessage()) {
                            $testcase_message = <<<HTML
                        <span class='italics'><font color="#af0000">{$testcase->getTestcaseMessage()}</font></span>
HTML;
                        }
                        $return .= <<<HTML
                        <h4>{$name}&nbsp;&nbsp;&nbsp;<code>{$command}</code>{$extra_credit}&nbsp;&nbsp;{$testcase_message}</h4>
            </div>
HTML;
                        if ($testcase->hasDetails()) {
                            $return .= <<<HTML
            <div id="testcase_{$count}" style="display: {$display_box};">
HTML;
                            if (!$testcase->isHidden()) {
                                $autocheck_cnt = 0;
                                $autocheck_len = count($testcase->getAutochecks());
                                foreach ($testcase->getAutochecks() as $autocheck) {
                                    $description = $autocheck->getDescription();
                                    $diff_viewer = $autocheck->getDiffViewer();
                                    $return .= <<<HTML
                <div class="box-block"> 
                <!-- Readded css here so the popups have the css -->
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
                                <h4>{$title} <span onclick="openPopUp('{$title}', {$count}, {$autocheck_cnt}, 0)"> <i class="fa fa-window-restore" style="visibility: visible; cursor: pointer;"></i> </span> </h4>
                                <div id="container_{$count}_{$autocheck_cnt}_0">
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}css/jquery-ui.min.css" />
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}css/bootstrap.css" />
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}css/diff-viewer.css" />
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}css/glyphicons-halflings.css" />                               
HTML;
                                    foreach ($autocheck->getMessages() as $message) {
                                        $type_class = "black-message";
                                        if ($message['type'] == "information") $type_class = "black-message";
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
                            </div>
HTML;

                                    if ($diff_viewer->hasDisplayExpected()) {
                                        $title1 = "Expected ";
                                        $title1 .= $description;
                                        $return .= <<<HTML
                            <div class='diff-element'>
                                <h4>Expected {$description} <span onclick="openPopUp('{$title1}', {$count}, {$autocheck_cnt}, 1)"> <i class="fa fa-window-restore" style="visibility: visible; cursor: pointer;"></i> </span></h4>
                                <div id="container_{$count}_{$autocheck_cnt}_1">
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}css/jquery-ui.min.css" />
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}css/bootstrap.css" />
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}css/diff-viewer.css" />
    <link rel="stylesheet" type="text/css" href="{$this->core->getConfig()->getBaseUrl()}css/glyphicons-halflings.css" />
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

                                    $return .= <<<HTML
                </div>
HTML;
                                    if (++$autocheck_cnt < $autocheck_len) { 
                                        $return .= <<<HTML
                <div class="clear"></div>
HTML;
                                    }
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
HTML;
            }

            $return .= <<<HTML
</div>
HTML;
	}
        if ($gradeable->taGradesReleased()) {
            $return .= <<<HTML
<div class="content">
HTML;
            if($gradeable->hasGradeFile()) {
                $return .= <<<HTML
    <h3 class="label">TA grade</h3>
    <pre>{$gradeable->getGradeFile()}</pre>
HTML;
            }
            else {
                $return .= <<<HTML
    <h3 class="label">TA grade not available</h3>
HTML;
            }
            $return .= <<<HTML
</div>
HTML;
        }

        return $return;
    }

    public function showPopUp($gradeable) {
        $return = <<<HTML
            <p>Banana</p>
HTML;
        return $return;
    }
}
