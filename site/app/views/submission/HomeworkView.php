<?php

namespace app\views\submission;

use app\libraries\Core;
use app\libraries\Utils;
use app\models\Gradeable;

class HomeworkView {
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function noGradeable($gradeable_id) {
        if ($gradeable_id === null) {
            return <<<HTML
<div class="content">
    No gradeable id specified. Contact your instructor if you think this
    is an error.
</div>
HTML;
        }
        else {
            return <<<HTML
<div class="content">
    {$gradeable_id} is not a valid electronic submission gradeable. Contact your instructor if you think this
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
        $return = <<<HTML
<script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}js/drag-and-drop.js"></script>
<div class="content">
    <h2>New submission for: {$gradeable->getName()}</h2>
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
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                if ($gradeable->getNumParts() > 1) {
                    $label = "Drag your {$gradeable->getPartsNames()[$i]} here or click to open file browser";
                }
                else {
                    $label = "Drag your file(s) here or click to open file browser";
                }
                $return .= <<<HTML

        <div id="upload{$i}" style="cursor: pointer; text-align: center; border: dashed 2px lightgrey; display:table-cell; height: 150px;">
            <h3 class="label" id="label{$i}">{$label}</h3>
            <input type="file" name="files" id="input_file{$i}" style="display: none" onchange="addFilesFromInput({$i})" />
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
            if($gradeable->getCurrentVersion() === $gradeable->getHighestVersion() && $gradeable->getCurrentVersion() > 0) {
                $return .= <<<HTML
    <button type="button" id= "getprev" class="btn btn-primary">Get Most Recent Files</button>
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
            if ($gradeable->getCurrentVersion() == $gradeable->getHighestVersion() && $gradeable->getCurrentVersion() > 0
                && $this->core->getConfig()->keepPreviousFiles()) {
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
        var assignment_version = {$gradeable->getCurrentVersion()};
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
        $(document).ready(function() {
            $("#submit").click(function(e){ // Submit button
                handleSubmission("{$this->core->buildUrl(array('component' => 'student',
                                                               'action' => 'upload',
                                                               'gradeable_id' => $gradeable->getId()))}",
                                 "{$this->core->buildUrl(array('component' => 'student',
                                                               'gradeable_id' => $gradeable->getId()))}",
                                 {$days_late},
                                 {$gradeable->getAllowedLateDays()},
                                 {$gradeable->getHighestVersion()},
                                 {$gradeable->getMaxSubmissions()},
                                 "{$this->core->getCsrfToken()}", {$svn_string});
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
                $selected = ($gradeable->getCurrentVersion() == $gradeable->getActiveVersion()) ? "selected" : "";
                $return .= <<<HTML
        <option value="0" {$selected}>Do Not Grade Assignment</option>
HTML;

            }
            foreach ($gradeable->getVersions() as $version => $version_details) {
                $selected = "";
                $select_text = array("Version #{$version}");
                if ($gradeable->getNormalPoints() > 0) {
                    $select_text[] = "Score: ".$version_details['points']." / " . $gradeable->getNormalPoints();
                }

                if ($version_details['days_late'] > 0) {
                    $select_text[] = "Days Late: ".$version_details['days_late'];
                }

                if ($version == $gradeable->getActiveVersion()) {
                    $select_text[] = "GRADE THIS VERSION";
                }

                if ($version == $gradeable->getCurrentVersion()) {
                    $selected = "selected";
                }

                $select_text = implode("&nbsp;&nbsp;&nbsp;", $select_text);
                $return .= <<<HTML
        <option value="{$version}" {$selected}>{$select_text}</option>

HTML;
            }

            $return .= <<<HTML
    </select>
HTML;
            // If viewing the active version, show cancel button, otherwise so button to switch active
            if ($gradeable->getCurrentVersion() > 0) {
                if ($gradeable->getCurrentVersion() == $gradeable->getActiveVersion()) {
                    $version = 0;
                    $button = '<input type="submit" class="btn btn-default" style="float: right" value="Do Not Grade This Assignment">';
                }
                else {
                    $version = $gradeable->getCurrentVersion();
                    $button = '<input type="submit" class="btn btn-primary" value="Grade This Version">';
                }
                $return .= <<<HTML
    <form style="display: inline;" method="post"
            onsubmit="return checkVersionChange({$gradeable->getDaysLate()},{$gradeable->getAllowedLateDays()})"
            action="{$this->core->buildUrl(array('component' => 'student',
                                                 'action' => 'update',
                                                 'gradeable_id' => $gradeable->getId(),
                                                 'new_version' => $version))}">
        <input type='hidden' name="csrf_token" value="{$this->core->getCsrfToken()}" />
        {$button}
    </form>


HTML;
            }

            if($gradeable->getActiveVersion() == 0 && $gradeable->getCurrentVersion() == 0) {
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
	            if($gradeable->getActiveVersion() > 0 && $gradeable->getActiveVersion() === $gradeable->getCurrentVersion()) {
                    $return .= <<<HTML
    <div class="sub">
        <p class="green-message">
            Note: This version of your assignment will be graded by the instructor/TAs and the score recorded in the gradebook.
        </p>
    </div>
HTML;
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
		    } else {
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
submission timestamp: {$results['submission_time']}<br />
days late: {$results['days_late']} (before extensions)<br />
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
                                                                     'gradeable_version' => $gradeable->getCurrentVersion()))}')
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
                        if($results['points'] >= $gradeable->getNormalPoints()) {
                            $background = "green-background";
                        }
                        else if($results['points'] > 0) {
                            $background = "yellow-background";
                        }
                        else {
                            $background = "red-background";
                        }
                        $return .= <<<HTML
        <div class="box">
            <div class="box-title">
                <span class="badge {$background}">{$results['points']} / {$gradeable->getNormalPoints()}</span>
                <h4>Total</h4>
            </div>
        </div>
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
                          $extra_credit = "<span class='italics'><font color=\"0a6495\">Extra Credit</font></span>";
                        }
                        $command = htmlentities($testcase->getDetails());
                        $return .= <<<HTML
                        <h4>{$name}&nbsp;&nbsp;&nbsp;<code>{$command}</code>&nbsp;&nbsp;{$extra_credit}</h4>
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
					$content_type = Utils::getContentType($myimage);
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
        }

        return $return;
    }
}
