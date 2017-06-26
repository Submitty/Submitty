<?php

namespace app\views\grading;

use app\views\AbstractView;
use app\views\submission\HomeworkView;

class UploadView extends AbstractView {

    // noGradeable and showGradeableError are in HomeworkView

	/**
     *
     * @param Gradeable $gradeable
     *
     * @return string
     */
    public function showUpload($gradeable, $days_late) {
        $upload_message = $this->core->getConfig()->getUploadMessage();
        $current_version = $gradeable->getCurrentVersion();
        $current_version_number = $gradeable->getCurrentVersionNumber();
        $return = <<<HTML
<script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}js/drag-and-drop.js"></script>
<div class="content">
    <h2>New upload for: {$gradeable->getName()}</h2>
    <form form id="idForm" method="post" action="{$this->core->buildUrl(array('component' => 'grading', 
                                                                                'page'      => 'upload', 
                                                                                'action'    => 'verify',
                                                                                'gradeable_id' => $gradeable->getId(),
                                                                                'days_late' => $days_late))}">
    <div class ="sub">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
        Student RCS ID: <input type="text" id="student_id" name="student_id" value="" placeholder="{$gradeable->getUser()->getID()}" required/>
        <button style="margin-right: 100px;" type="submit" form="idForm">
            Submit ID
        </button>
    </div>
    </form>
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
        $return .= <<<HTML
    <div id="upload-boxes" style="display:table; border-spacing: 5px; width:100%">
HTML;


        for ($i = 0; $i < $gradeable->getNumTextBoxes(); $i++) {
            $label = $gradeable->getTextBoxes()[$i]['label'];
            $rows = $gradeable->getTextBoxes()[$i]['rows'];
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
                $label = "Drag your {$gradeable->getPartsNames()[$i]} here or click to open file browser";
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
        $response = "";
        $return .= <<<HTML
    <script type="text/javascript">
        $(document).ready(function() {
            $("#submit").click(function(e){ // Submit button
                // is it a valid student ID?
                // get the student gradeable
                // submit the student gradeable
                handleSubmission("{$this->core->buildUrl(array('component' => 'grading',
                                                               'page' => 'upload',
                                                               'action' => 'upload',
                                                               'gradeable_id' => $gradeable->getId()))}",
                                     "{$this->core->buildUrl(array('component' => 'grading', 
                                                                'page' => 'upload',
                                                                   'gradeable_id' => $gradeable->getId(),
                                                                   'days_late' => $days_late))}",
                                     {$days_late},
                                 {$gradeable->getAllowedLateDays()},
                                 {$gradeable->getHighestVersion()},
                                 {$gradeable->getMaxSubmissions()},
                                 "{$this->core->getCsrfToken()}",
                                 false,
                                 {$gradeable->getNumTextBoxes()},
                                 "{$gradeable->getUser()->getID()}");
                e.stopPropagation();
            });
        });
    </script>
</div>
HTML;
        $return .= <<<HTML
<div class="content">
    <span>Instructor uploads for this gradeable.</span>
</div>
HTML;
        $gradeable_id = $gradeable->getId();
        $return .= <<<HTML
<div class="content">
    <span>{$gradeable_id}</span>
</div>
HTML;
        return $return;
    }

    private function validID($student_id) {
        // gets gradeable_id, student_id
        $student_user = $this->core->getQueries()->getUserById($student_id);

        if ($student_user === null) return false;
        else return true;
    }

}


        
