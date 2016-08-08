<?php

namespace app\views\submission;

use app\libraries\Core;
use app\models\Gradeable;

class HomeworkView {
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function noGradeables() {
        return <<<HTML
<div class="content">
    There are currently no released gradeables. Try checking back later.
</div>

HTML;
    }
    
    /**
     * @param Gradeable[] $gradeables
     * @param $gradeable_id
     *
     * @return string
     */
    public function gradeableSelect($gradeables, $gradeable_id) {
        $return = <<<HTML
<div class="sub">
    <span style="font-weight: bold;">Select Assignment:</span>
    <select style="margin-left: 5px" onChange="gradeableChange('{$this->core->buildUrl(array('component' => 'student', 
                                                                                'gradeable_id' => ''))}', this);">
HTML;
        foreach ($gradeables as $gradeable) {
            if ($gradeable_id === $gradeable->getId()) {
                $selected = "selected";
            }
            else {
                $selected = "";
            }
            $return .= "\t\t<option value='{$gradeable->getId()}' {$selected}>{$gradeable->getName()}</option>\n";
        }

        $return .= <<<HTML
    </select>
</div>
HTML;

        return $return;
    }
    
    /**
     * @param Gradeable $gradeable
     * @param string    $grade_file
     * @param string    $gradeable_select
     * @param int       $days_late
     *
     * @return string
     */
    public function showGradeable($gradeable, $grade_file, $gradeable_select, $days_late) {
        $show_ta_grades = $this->core->getConfig()->showTaGrades();
        $show_grade_summary = $this->core->getConfig()->showGradeSummary();
        $upload_message = $this->core->getConfig()->getUploadMessage();
        $return = <<<HTML
<script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}js/drag-and-drop.js"></script>
{$gradeable_select}
<div class="content">
    <h2>View Assignment {$gradeable->getName()}</h2>
    <div class="sub">
        {$upload_message}
    </div>
HTML;
        if($gradeable->useSvnCheckout()) {
            $return .= <<<HTML
    <form action="{$this->core->buildUrl(array())}" method="post" 
        onsubmit="return checkVersionsUsed('{$gradeable->getName()}', {$gradeable->getHighestVersion()},
                                            {$gradeable->getMaxSubmissions()});">
        <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
        <input type="hidden" name="svn_checkout" value="true" />
        <input type="submit" id="submit" class="btn btn-primary" value="Grade SVN" />
    </form>
HTML;
        }
        else {
            $return .= <<<HTML
    <div id="upload-boxes" style="display:table; border-spacing: 5px; width:100%">
HTML;
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                $return .= <<<HTML
        
        <div id="upload{$i}" style="cursor: pointer; text-align: center; border: dashed 2px lightgrey; display:table-cell; height: 150px;">
            <h3 class="label" id="label{$i}">Drag your {$gradeable->getPartsNames()[$i]} here or click to open file browser</h3>
            <input type="file" name="files" id="input_file{$i}" style="display: none" onchange="addFilesFromInput({$i})" />
        </div>
HTML;
            }
    
            $return .= <<<HTML
    </div>
    <button type="button" id="submit" class="btn btn-primary">Submit</button>
    <button type="button" id="startnew" class="btn btn-primary">Start New</button>

HTML;
            if($gradeable->getCurrentVersion() === $gradeable->getHighestVersion() && $gradeable->getCurrentVersion() > 0) {
                $return .= <<<HTML
    <button type="button" id= "getprev" class="btn btn-primary">Get Version {$gradeable->getHighestVersion()} Files</button>
HTML;
            }
    
            $old_files = "";
            for ($i = 1; $i <= $gradeable->getNumParts(); $i++) {
                foreach ($gradeable->getPreviousFiles($i) as $file) {
                    $old_files .= <<<HTML
            
                addLabel('{$file['name']}', '{$file['size']}', {$i}, true);
                readPrevious('{$file['name']}', {$i});
HTML;
                }
            }
    
            $return .= <<<HTML
    
    <script type="text/javascript">
        // CLICK ON THE DRAG-AND-DROP ZONE TO OPEN A FILE BROWSER OR DRAG AND DROP FILES TO UPLOAD
        var num_parts = {$gradeable->getNumParts()};
        createArray(num_parts);
        var assignment_version = {$gradeable->getCurrentVersion()};
        var highest_version = {$gradeable->getHighestVersion()};
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
            handleSubmission("{$this->core->buildUrl(array('component' => 'student', 'action' => 'upload', 'gradeable_id' => $gradeable->getId()))}",
                             "{$this->core->buildUrl(array('component' => 'student', 'gradeable_id' => $gradeable->getId()))}",
                             {$days_late},
                             {$gradeable->getAllowedLateDays()},
                             {$gradeable->getHighestVersion()},
                             {$gradeable->getMaxSubmissions()},
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
HTML;
        }
        
        $return .= <<<HTML
</div>
HTML;
        
        if($gradeable->getSubmissionCount() === 0) {
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
                                                                                            'gradeable_id' => $gradeable->getId(), 
                                                                                            'gradeable_version' => ""))}', this)">

HTML;
            if($gradeable->getActiveVersion() == 0) {
                $selected = ($gradeable->getCurrentVersion() == $gradeable->getActiveVersion()) ? "selected" : "";
                $return .= <<<HTML
        <option value="0" {$selected}>Cancelled</option>
HTML;

            }
            foreach ($gradeable->getVersions() as $version => $version_details) {
                $selected = "";
                $select_text = array("Version #{$version}");
                if($gradeable->getNormalPoints() > 0) {
                    $select_text[] = "Score: ".$version_details['points']." / " . $gradeable->getNormalPoints();
                }
                
                if ($version_details['days_late'] > 0) {
                    $select_text[] = "Days Late: ".$version_details['days_late'];
                }
    
                if ($version == $gradeable->getActiveVersion()) {
                    $select_text[] = "ACTIVE";
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
            if($gradeable->getCurrentVersion() > 0) {
                if($gradeable->getCurrentVersion() == $gradeable->getActiveVersion()) {
                    $version = 0;
                    $button = '<input type="submit" class="btn btn-default" value="Do Not Grade This Version (Mark All Inactive)">';
                } else {
                    $version = $gradeable->getCurrentVersion();
                    $button = '<input type="submit" class="btn btn-primary" value="Make Active">';
                }
                $return .= <<<HTML
    <form style="display: inline;" method="post" onsubmit="return checkVersionChange({$gradeable->getDaysLate()}, {$gradeable->getAllowedLateDays()})" 
            action="{$this->core->buildUrl(array('component' => 'student',
                                                 'action' => 'update',
                                                 'gradeable_id' => $gradeable->getId(),
                                                 'new_version' => $version))}">
        <input type='hidden' name="csrf_token" value="{$this->core->getCsrfToken()}" />
        {$button}
    </form>


HTML;
            }
            
            if($gradeable->getActiveVersion() == 0) {
                $return .= <<<HTML
    <div class="sub">
        <p class="red">
        Note: You have NO ACTIVE submissions for this assignment.<br />
        This assignment will not be graded by the instructor/TAs and a zero will be recorded in the gradebook.
        </p>
    </div>

HTML;
            }
            else if($gradeable->getActiveVersion() > 0 && $gradeable->getActiveVersion() === $gradeable->getCurrentVersion()) {
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
            // TODO: This is going to be different for SVN
            foreach ($gradeable->getSubmittedFiles() as $submitted_file) {
                $size = number_format($submitted_file['size']/1024,2);
                $return .= "{$submitted_file['name']} ({$size}kb)<br />";
            }
            $return .= <<<HTML
        </div>
        <div class="box half">
HTML;
            $results = $gradeable->getResults();
            if($gradeable->getResults()) {
                
                $return .= <<<HTML
submission timestamp: {$results['submission_time']}<br />
days late (before extensions): {$results['days_late']}<br />
<br />
wait time: {$results['wait_time']}<br />
grade time: {$results['grade_time']}<br />
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
            if($gradeable->hasAssignmentMessage()) {
                $return .= <<<HTML
        <span class="green-message">Note: {$gradeable->getAssignmentMessage()}</span> 
HTML;
            }
            if (!$gradeable->hasResults()) {
                $return .= <<<HTML
        <p class="red-message">
            Currently being graded
        </p>
        <script type="text/javascript">
            checkRefreshSubmissionPage('{$this->core->buildUrl(array('component' => 'student', 
                                                                    'page' => 'submission', 
                                                                    'action' => 'check_refresh', 
                                                                    'gradeable_id' => $gradeable->getId(), 
                                                                    'gradeable_version' => $gradeable->getCurrentVersion()))}')
        </script>
HTML;
            }
            else {
                if($gradeable->getNormalPoints() > 0) {
                    if ($results['points'] >= $gradeable->getNormalPoints()) {
                        $background = "green-background";
                    }
                    else if ($results['points'] > 0) {
                        $background = "yellow-background";
                    }
                    else {
                        $background = "red-background";
                    }
                    $return .= <<<HTML
        <div class="box">
            <span class="badge {$background}">{$results['points']} / {$gradeable->getNormalPoints()}</span>
            <h4>Total</h4>
        </div>
HTML;
                }
                
                $count = 0;
                foreach ($gradeable->getTestcases() as $testcase) {
                    $return .= <<<HTML
        <div class="box">
HTML;
                    $display_box = "block";
                    if ($testcase->hasPoints()) {
                        if($testcase->isHidden()) {
                            $return .= <<<HTML
            <span class="badge">Hidden</span>
HTML;
                        }
                        else {
                            if ($testcase->getPointsAwarded() >= $testcase->getPoints()) {
                                $background = "green-background";
                                $display_box = "none";
                            }
                            else if ($testcase->getPointsAwarded() > 0) {
                                $background = "yellow-background";
                            }
                            else {
                                $background = "red-background";
                            }
                            $return .= <<<HTML
                            <span class="badge {$background}">{$testcase->getPointsAwarded()} / {$testcase->getPoints()}</span>
HTML;
                        }
                    }
    
                    $name = htmlentities($testcase->getName());
                    if ($testcase->isExtraCredit()) {
                        $name = "<span class='italics'>Extra Credit</span> ".$name;
                    }
                    $command = htmlentities($testcase->getDetails());
                    $return .= <<<HTML
            <h4 onclick="return toggleDiv('testcase_{$count}');" style="cursor: pointer;">{$name} <code>{$command}</code></h4>
            <div id="testcase_{$count}" style="display: {$display_box};">
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
                    
                        foreach ($testcase->getAutochecks() as $autocheck) {
                            $return .= <<<HTML
                <div class="box-block">
                    <span class="red-message">{$autocheck->getMessage()}</span>
HTML;
                            $diff_viewer = $autocheck->getDiffViewer();
                            $description = $autocheck->getDescription();
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
HTML;
            
            $return .= <<<HTML
</div>
HTML;
            if ($show_ta_grades && $gradeable->taGradesReleased()) {
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
    
        if ($show_grade_summary) {
            $return .= <<<HTML
<div class="content">
    <h3 class="label">Grade Summary</h3>
HTML;
            if ($grade_file !== null) {
                $return .= <<<HTML
    {$grade_file}
HTML;
            }
            
            $return .= <<<HTML
</div>
HTML;

        }
        
        return $return;
    }
}