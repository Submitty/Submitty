<?php

namespace app\views\submission;

use app\libraries\Core;
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
                                                                                 'assignment_id' => 'change_assignment_id'))}', this);">
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
     *
     * @return string
     */
    public function showAssignment($assignment_select, $assignment) {
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
    
    <script type="text/javascript">
        // CLICK ON THE DRAG-AND-DROP ZONE TO OPEN A FILE BROWSER OR DRAG AND DROP FILES TO UPLOAD
        var num_parts = {$assignment->getNumParts()};
        createArray(num_parts);
        var assignment_version = {$assignment->getCurrentVersion()};
        var active_version = {$assignment->getActiveVersion()};
        var highest_version = {$assignment->getHighestVersion()};
        for(var i = 1; i <= num_parts; i++ ){
            var dropzone = document.getElementById("upload" + i);
            dropzone.addEventListener("click", clicked_on_box, false);
            dropzone.addEventListener("dragenter", draghandle, false);
            dropzone.addEventListener("dragover", draghandle, false);
            dropzone.addEventListener("dragleave", draghandle, false);
            dropzone.addEventListener("drop", drop, false);
            /*
            // Uncomment if want buttons for emptying single bucket
            $("#delete" + i).click(function(e){
            //document.getElementById("delete").addEventListener("click", function(e){
              deleteFiles(get_part_number(e));
              e.stopPropagation();
            })
            */
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
                             {$assignment->getPossibleDaysLate()},
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
<div class="content">
    <span style="font-style: italic">No submissions for this assignment.</span>
</div>
HTML;

        return $return;
    }
}