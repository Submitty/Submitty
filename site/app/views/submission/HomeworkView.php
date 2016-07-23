<?php

namespace app\views\submission;

use app\libraries\Core;
use app\libraries\DiffViewer;
use app\models\Assignment;
use app\models\User;

class HomeworkView {
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function noAssignments() {
        return <<<HTML
<div class="content">
    There are currently no released assignments. Try checking back later.
</div>

HTML;
    }

    public function assignmentSelect($assignments, $assignment_id) {
        $return = <<<HTML
<div class="sub">
    <span style="font-weight: bold;">Select Assignment:</span>
    <select style="margin-left: 5px" onChange="assignmentChange('{$this->core->buildUrl(array('component' => 'student', 
                                                                                 'assignment_id' => ''))}', this);">
HTML;
        foreach ($assignments as $assignment) {
            if ($assignment_id === $assignment['assignment_id']) {
                $selected = "selected";
            }
            else {
                $selected = "";
            }
            $return .= "\t\t<option {$selected}>{$assignment['assignment_name']}</option>\n";
        }

        $return .= <<<HTML
    </select>
</div>
HTML;

        return $return;
    }
    
    /**
     * @param string     $assignment_select
     * @param Assignment $assignment
     * @param int        $days_late
     *
     * @return string
     */
    public function showAssignment($assignment_select, $assignment, $days_late) {
        $return = <<<HTML
<script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}js/drag_and_drop.js"></script>
{$assignment_select}
<div class="content">
    <h2>View Assignment {$assignment->getAssignmentName()}</h2>
    <div class="sub">
        Prepare your assignment for submission exactly as described on the course webpage.
        By clicking "Submit File" you are confirming that you have read, understand, and agree to follow the
        Academic Integrity Policy.
    </div>
    <div id="upload-boxes" style="display:table; border-spacing: 5px; width:100%">
HTML;
        for ($i = 1; $i <= $assignment->getNumParts(); $i++) {
            $return .= <<<HTML
        
        <div id="upload{$i}" style="cursor: pointer; text-align: center; border: dashed 2px lightgrey; display:table-cell; height: 150px;">
            <h3 class="label" id="label{$i}">Drag your {$assignment->getPartsNames()[($i-1)]} here or click to open file browser</h3>
            <input type="file" name="files" id="input_file{$i}" style="display: none" onchange="addFilesFromInput({$i})" />
        </div>
HTML;
        }

        $old_files = "";
        for($i = 1; $i <= $assignment->getNumParts(); $i++) {
            foreach ($assignment->getPreviousFiles($i) as $file) {
                $old_files .= <<<HTML
            
                addLabel('{$file['name']}', '{$file['size']}', {$i}, true);
                readPrevious('{$file['name']}', {$i});
HTML;
            }
        }
    
        $return .= <<<HTML
    </div>
    <button type="button" id="submit" class="btn btn-primary">Submit</button>
    <button type="button" id="startnew" class="btn btn-primary">Start New</button>

HTML;
        if ($assignment->getCurrentVersion() === $assignment->getHighestVersion() && $assignment->getCurrentVersion() > 0) {
            $return .= <<<HTML
    <button type="button" id= "getprev" class="btn btn-primary">Get Version {$assignment->getHighestVersion()} Files</button>
HTML;
        }
        
    $return .= <<<HTML
    
    <script type="text/javascript">
        // CLICK ON THE DRAG-AND-DROP ZONE TO OPEN A FILE BROWSER OR DRAG AND DROP FILES TO UPLOAD
        var num_parts = {$assignment->getNumParts()};
        createArray(num_parts);
        var assignment_version = {$assignment->getCurrentVersion()};
        var highest_version = {$assignment->getHighestVersion()};
        for(var i = 1; i <= num_parts; i++ ){
            var dropzone = document.getElementById("upload" + i);
            dropzone.addEventListener("click", clicked_on_box, false);
            dropzone.addEventListener("dragenter", draghandle, false);
            dropzone.addEventListener("dragover", draghandle, false);
            dropzone.addEventListener("dragleave", draghandle, false);
            dropzone.addEventListener("drop", drop, false);
        }
        
        $("#startnew").click(function(e){ // Clear all the selected files in the buckets
            for(var i=1; i<= num_parts; i++){
              deleteFiles(i);
            }
            e.stopPropagation();
        });
        $("#submit").click(function(e){ // Submit button
            handleSubmission("{$this->core->buildUrl(array('component' => 'student', 
                                                           'action' => 'upload', 
                                                           'assignment_id' => $assignment->getAssignmentId()))}",
                             "{$this->core->buildUrl(array('component' => 'student', 
                                                           'assignment_id' => $assignment->getAssignmentId()))}",
                             {$days_late},
                             {$assignment->getAllowedLateDays()},
                             {$assignment->getHighestVersion()},
                             {$assignment->getMaxSubmissions()},
                             "{$this->core->getCsrfToken()}", false);
            e.stopPropagation();
        });

        // GET FILES OF THE HIGHEST VERSION
        if(assignment_version == highest_version && highest_version > 0) {
            $("#getprev").click(function(e){
                $("#startnew").click();
                {$old_files}
                e.stopPropagation();
            });
        }
    </script>
</div>
HTML;
        if ($assignment->getSubmissionCount() === 0) {
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
    <select name="submission_version" onChange="versionChange('{$this->core->buildUrl(array('component' => 'student', 
                                                                                            'assignment_id' => $assignment->getAssignmentId(), 
                                                                                            'assignment_version' => ""))}', this)">

HTML;
            if ($assignment->getActiveVersion() == 0) {
                $selected = ($assignment->getCurrentVersion() == $assignment->getActiveVersion()) ? "selected" : "";
                $return .= <<<HTML
        <option value="0" {$selected}>Cancelled</option>
HTML;

            }
            foreach ($assignment->getVersions() as $version => $version_details) {
                $selected = "";
                $select_text = array("Version #{$version}");
                if ($assignment->getNormalPoints() > 0) {
                    $select_text[] = "Score: ".$version_details['points']." / ".$assignment->getNormalPoints();
                }
                
                if ($version_details['days_late'] > 0) {
                    $select_text[] = "Days Late: ".$version_details['days_late'];
                }
    
                if ($version == $assignment->getActiveVersion()) {
                    $select_text[] = "ACTIVE";
                }
                
                if ($version == $assignment->getCurrentVersion()) {
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
            if ($assignment->getCurrentVersion() > 0) {
                if($assignment->getCurrentVersion() == $assignment->getActiveVersion()) {
                    $version = 0;
                    $button = '<input type="submit" class="btn btn-default" value="Do Not Grade This Version (Mark All Inactive)">';
                } else {
                    $version = $assignment->getCurrentVersion();
                    $button = '<input type="submit" class="btn btn-primary" value="Make Active">';
                }
                $return .= <<<HTML
    <form style="display: inline;" method="post" onsubmit="return checkVersionChange({$assignment->getDaysLate()}, {$assignment->getAllowedLateDays()})" 
            action="{$this->core->buildUrl(array('component' => 'student',
                                                 'action' => 'update',
                                                 'assignment_id' => $assignment->getAssignmentId(),
                                                 'new_version' => $version))}">
        <input type='hidden' name="csrf_token" value="{$this->core->getCsrfToken()}" />
        {$button}
    </form>


HTML;
            }
            
            if ($assignment->getActiveVersion() == 0) {
                $return .= <<<HTML
    <div class="sub">
        <p class="red">
        Note: You have NO ACTIVE submissions for this assignment.<br />
        This assignment will not be graded by the instructor/TAs and a zero will be recorded in the gradebook.
        </p>
    </div>

HTML;
            }
            else if ($assignment->getActiveVersion() > 0 && $assignment->getActiveVersion() === $assignment->getCurrentVersion()) {
                $return .= <<<HTML
    <div class="sub">
        <p class="green-message">
        Note: This is your "ACTIVE" submission version, which will be graded by the instructor/TAs and the score recorded in the gradebook.
        </p>
    </div>
HTML;
            }
            
            $return .= <<<HTML
    <div class="sub">
        <h4>Submitted Files</h4>
        <div class="box half">
HTML;
            foreach ($assignment->getSubmittedFiles() as $submitted_file) {
                $size = number_format($submitted_file['size']/1024,2);
                $return .= "{$submitted_file['name']} ({$size}kb)<br />";
            }
            $return .= <<<HTML
        </div>
        <div class="box half">
HTML;
            if ($assignment->hasCurrentResults()) {
                $details = $assignment->getCurrentResults();
                $return .= <<<HTML
submission timestamp: {$details['submission_time']}<br />
days late (before extensions): {$details['days_late']}<br />
<br />
wait time: {$details['wait_time']}<br />
grade time: {$details['grade_time']}<br />
HTML;
            }
            $return .= <<<HTML
        </div>
HTML;
            $return .= <<<HTML
    </div>
    <div class="sub">
        <h4>Results</h4>
HTML;
            if ($assignment->hasMessage()) {
                $return .= <<<HTML
        <span class="green-message">Note: {$assignment->getMessage()}</span> 
HTML;
            }
            if (!$assignment->hasCurrentResults()) {
                $return .= <<<HTML
        <p class="red-message">
            Currently being graded
        </p>
HTML;
            }
            else {
                $results = $assignment->getCurrentResults();
                if ($assignment->getNormalPoints() > 0) {
                    $return .= <<<HTML
        <div class="box">
            <span class="badge">{$results['points']} / {$assignment->getNormalPoints()}</span>
            <h4>Total</h4>
        </div>
HTML;
                }
                
                $count = 0;
                foreach ($assignment->getTestcases() as $testcase) {
                    $return .= <<<HTML
        <div class="box">
HTML;
                    if ($testcase->hasPoints()) {
                        if($testcase->isHidden()) {
                            $return .= <<<HTML
            <span class="badge">Hidden</span>
HTML;
                        }
                        else {
                            $return .= <<<HTML
                            <span class="badge">{$testcase->getPointsAwarded()} / {$testcase->getPoints()}</span>
HTML;
                        }
                    }
    
                    $name = htmlentities($testcase->getName());
                    if ($testcase->isExtraCredit()) {
                        $name = "<span class='italics'>Extra Credit</span> ".$name;
                    }
                    $command = htmlentities($testcase->getCommand());
                    $return .= <<<HTML
            <h4 onclick="return toggleDiv('testcase_{$count}');" style="cursor: pointer;">{$name} <code>{$command}</code></h4>
            <div id="testcase_{$count}">
HTML;
                    if (!$testcase->isHidden()) {
                        if ($testcase->hasCompilationOutput()) {
                            $compile_output = htmlentities($testcase->getCompilationOutput());
                            $return .= <<<HTML
                <div class="box-block">
                    <h4>Compilation Output</h4>
                    <pre>{$compile_output}</pre>
                </div>
HTML;
                        }
                    
                        if ($testcase->hasExecuteLog()) {
                            $log_file = htmlentities($testcase->getLogfile());
                            $return .= <<<HTML
                <div class="box-block">
                    <h4>Execution Output</h4>
                    <pre>{$log_file}</pre>
                </div>
HTML;
                        }
                    
                        foreach ($testcase->getDiffs() as $diff) {
                            $return .= <<<HTML
                <div class="box-block">
HTML;
                            /** @var DiffViewer $diff_viewer */
                            $diff_viewer = $diff['diff_viewer'];
                            $description = (isset($diff['description']) && $diff['description'] != "") ? $diff['description'] : "";
                            if($diff_viewer->hasActualOutput()) {
                                $return .= <<<HTML
                            <div class='diff-element'>
                                <h4>Student {$description}</h4>
                                {$diff_viewer->getDisplayActual()}
                            </div>
HTML;
                            }
    
                            if($diff_viewer->hasDisplayExpected() && $diff_viewer->hasExpectedOutput()) {
                                $return .= <<<HTML
                            <div class='diff-element'>
                                <h4>Instructor {$description}</h4>
                                {$diff_viewer->getDisplayExpected()}
                            </div>
HTML;
                            }
    
                            $return .= <<<HTML
                </div>
HTML;
                        }
                    }
                    $return .= <<<HTML
            </div>
        </div>
HTML;
                    $count++;
                }
            }
            $return .= <<<HTML
    </div>
</div>
HTML;
        }
        return $return;
    }
}