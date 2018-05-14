<?php

namespace app\views\submission;

use app\models\Gradeable;
use app\views\AbstractView;
use app\libraries\FileUtils;

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
     * @param int       $late_days_use
     * @param int       $extensions
     *
     * @return string
     */
    public function showGradeable($gradeable, $late_days_use, $extensions, $canViewWholeGradeable=false) {
        $return = "";
        // hiding entire page if user is not a grader and student cannot view
        if (!$this->core->getUser()->accessGrading() && !$gradeable->getStudentView()) {
            $message = "Students cannot view that gradeable.";
            $this->core->addErrorMessage($message);
            $this->core->redirect($this->core->getConfig()->getSiteUrl());
        }
        if ($extensions > 0) {
            $return .= <<<HTML
<div class="content">
    <h4>You have a {$extensions} day extension for this homework</h4>
</div>
HTML;
        }
        $upload_message = $this->core->getConfig()->getUploadMessage();
        $current_version = $gradeable->getCurrentVersion();
        $current_version_number = $gradeable->getCurrentVersionNumber();
        $student_page = false;
        $num_components = count($gradeable->getComponents());
        $time = " @ H:i";
        $return .= <<<HTML
<script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}js/drag-and-drop.js"></script>
HTML;
        // showing submission if user is grader or student can submit
        if ($this->core->getUser()->accessGrading() || $gradeable->getStudentSubmit()) {
            $return .= <<<HTML
<div class="content">
    <div class="upperinfo">
        <h2 class="upperinfo-left">New submission for: {$gradeable->getName()}</h2>
        <h2 class="upperinfo-right">Due: {$gradeable->getDueDate()->format("m/d/Y{$time}")}</h2>
    </div>
HTML;
            if ($this->core->getUser()->accessAdmin()) {
                $students = $this->core->getQueries()->getAllUsers();
                $student_ids = array();
                foreach ($students as $student) {
                    $student_ids[] = $student->getId();
                }

                $gradeables = $this->core->getQueries()->getGradeables($gradeable->getId(), $student_ids);
                $students_version = array();
                foreach ($gradeables as $g) {
                    $students_version[] = array($g->getUser(), $g->getActiveVersion());
                }

                $students_full = array();
                foreach ($students_version as $student_pair) {
                    $student = $student_pair[0];

                    $student_entry = array('value' => $student->getId(),
                                           'label' => $student->getDisplayedFirstName().' '.$student->getLastName().' <'.$student->getId().'>');

                    if ($student_pair[1] !== 0) {
                        $student_entry['label'] .= ' ('.$student_pair[1].' Prev Submission)';
                    }

                    $students_full[] = $student_entry;
                }

                $students_full = json_encode($students_full);

                $return .= <<<HTML
    <form id="submissionForm" method="post" style="text-align: center; margin: 0 auto; width: 100%; ">
        <div >
            <input type='radio' id="radio_normal" name="submission_type" checked="true"> 
                Normal Submission
            <input type='radio' id="radio_student" name="submission_type">
                Make Submission for a Student
HTML;
                if ($gradeable->getNumParts() == 1 && !$gradeable->useVcsCheckout()) {
                    $return .= <<<HTML
            <input type='radio' id="radio_bulk" name="submission_type">
                Bulk Upload
HTML;
                }
                $return .= <<<HTML
        </div>
        <div id="user_id_input" style="display: none">
            <div class="sub">
                Input the user_id of the student you wish to submit for. This <i>permanently</i> affects the student's submissions, so please use with caution.
            </div>
            <div class="sub">
                <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
                user_id: <input type="text" id= "user_id" value ="" placeholder="{$gradeable->getUser()->getId()}"/>
            </div>
        </div>
        <div class = "sub" id="pdf_submit_button" style="display: none">
            <div class="sub">
                # of page(s) per PDF: <input type="number" id= "num_pages" placeholder="required"/>
            </div>
        </div>
    </form>
HTML;
                $return .= <<<HTML
    <script type="text/javascript">
        $(function() {
            var cookie = document.cookie;
            students_full = {$students_full};
            if (cookie.indexOf("student_checked=") !== -1) {
                var cookieValue = cookie.substring(cookie.indexOf("student_checked=")+16, cookie.indexOf("student_checked=")+17);
                $("#radio_student").prop("checked", cookieValue==1);
                $("#radio_bulk").prop("checked", cookieValue==2);
                document.cookie="student_checked="+0;
            }
            if ($("#radio_student").is(":checked")) {
                $('#user_id_input').show();
            }
            if ($("#radio_bulk").is(":checked")) {
                $('#pdf_submit_button').show();
            }
            $('#radio_normal').click(function() {
                $('#user_id_input').hide();
                $('#pdf_submit_button').hide();
                $('#user_id').val('');
            });
            $('#radio_student').click(function() {
                $('#pdf_submit_button').hide();
                $('#user_id_input').show();
            });
            $('#radio_bulk').click(function()  {
                $('#user_id_input').hide();
                $('#pdf_submit_button').show();
                $('#user_id').val('');
            });
            $("#user_id").autocomplete({
                source: students_full
            });
        });
    </script>
HTML;
            }
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
            if($gradeable->useVcsCheckout()) {
                if (strpos($gradeable->getSubdirectory(),"\$repo_id") !== false) {
                    $return .= <<<HTML
    repository id: <input type="text" id="repo_id" class="required" value="" placeholder="(Required)"/><br /><br />
HTML;
                }
                else if ($gradeable->getSubdirectory() == "" && $this->core->getConfig()->getVcsBaseUrl() == "") {
                    $return .= <<<HTML
    Enter the URL for your repository, ex. <kbd>https://github.com/username/homework-1</kbd><br />
    repository URL: <input type="text" id="repo_id" class="required" value ="" placeholder="(Required)"/><br /><br />
HTML;
                }
                $return .= <<<HTML
    <input type="submit" id="submit" class="btn btn-primary" value="Grade My Repository" />
HTML;
            }
            else {
                $return .= <<<HTML
    <div id="upload-boxes" style="display:table; border-spacing: 5px; width:100%">
HTML;

                for ($i = 0; $i < $gradeable->getNumTextBoxes(); $i++) {

                    $image_width = $image_height = 0;

                    if (isset($gradeable->getTextboxes()[$i]['images']) && $gradeable->getTextboxes()[$i]['images'] != ""){
                        $images = $gradeable->getTextboxes()[$i]['images'];
                    }
                    else{
                        $images = array();
                    }

                    foreach($images as $currImage){
                        $currImageName = $currImage["image_name"];
                        $imgPath = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(),"test_input",$gradeable->getId(),$currImageName);
                        $content_type = FileUtils::getContentType($imgPath);
                        if (substr($content_type, 0, 5) === "image") {
                           // Read image path, convert to base64 encoding
                           $textBoxImageData = base64_encode(file_get_contents($imgPath));
                           // Format the image SRC:  data:{mime};base64,{data};
                           $textBoximagesrc = 'data: '.mime_content_type($imgPath).';charset=utf-8;base64,'.$textBoxImageData;
                           // insert the sample image data

                            if(isset($currImage['image_height']) && (int)$currImage['image_height'] > 0){
                                $image_height = $currImage['image_height'];
                            }

                            if(isset($currImage['image_width']) && (int)$currImage['image_width'] > 0){
                                $image_width = $currImage['image_width'];
                            }

                            $image_display = '<img src="'.$textBoximagesrc.'"';

                            if($image_width > 0){
                                $image_display .= ' width="'.$image_width.'"';
                            }
                            if($image_height > 0){
                                $image_display .= ' height="'.$image_height.'"';
                            }
                            $image_display .= ">";
                            $return .= $image_display;
                        }
                    }

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
HTML;
                // does this gradeable have parts assigned by students
                foreach ($gradeable->getComponents() as $question) {
                    if(is_array($question)) {
                        $page_num = $question[0]->getPage();
                    }
                    else {
                        $page_num = $question->getPage();
                    }
                    if ($page_num === -1) {
                        $student_page = true;
                        break;
                    }
                }
                if ($student_page) {                
                    $return .= <<<HTML
    <form id="pdfPageStudent">
        <div class="sub">
        <div>Enter the page number that corresponds to each question. If the answer spans multiple pages, enter the page the answer starts on.</div>
HTML;
                    $count = 0;
                    foreach ($gradeable->getComponents() as $question) {
                        $title = $question->getTitle();
                        $return .= <<<HTML
        <div>{$title}: <input type="number" id="page_{$count}" min="1"></div><br />
HTML;
                        $count++;
                    }
                    $return .= <<<HTML
        </div>
    </form>
HTML;
                }
                $return .= <<<HTML
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
        $(function() {
            setUsePrevious();
            {$old_files}
        });
    </script>
HTML;
                }
                $return .= <<<HTML
    <script type="text/javascript">
        $(function() {
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

            $vcs_string = ($gradeable->useVcsCheckout()) ? "true" : "false";
            $student_page_string = ($student_page) ? "true" : "false";

            $return .= <<<HTML
    <script type="text/javascript">
        function makeSubmission(user_id, highest_version, is_pdf, path, count, repo_id) {
            // submit the selected pdf
            if (is_pdf) {
                submitSplitItem("{$this->core->getCsrfToken()}", "{$gradeable->getId()}", user_id, path, count);
                moveNextInput(count);
            }
            // otherwise, this is a regular submission of the uploaded files
            else if (user_id == "") {
                handleSubmission({$late_days_use},
                                {$gradeable->getAllowedLateDays()},
                                {$gradeable->getHighestVersion()},
                                {$gradeable->getMaxSubmissions()},
                                "{$this->core->getCsrfToken()}",
                                {$vcs_string},
                                {$gradeable->getNumTextBoxes()},
                                "{$gradeable->getId()}",
                                "{$gradeable->getUser()->getId()}",
                                repo_id,
                                {$student_page_string},
                                {$num_components});
            }
            else {
                handleSubmission({$late_days_use},
                                {$gradeable->getAllowedLateDays()},
                                highest_version,
                                {$gradeable->getMaxSubmissions()},
                                "{$this->core->getCsrfToken()}",
                                {$vcs_string},
                                {$gradeable->getNumTextBoxes()},
                                "{$gradeable->getId()}",
                                user_id,
                                repo_id,
                                {$student_page_string},
                                {$num_components});
            }
        }
        $(function() {
            $("#submit").click(function(e){ // Submit button
                var user_id = "";
                var repo_id = "";
                var num_pages = 0;
                // depending on which is checked, update cookie
                if ($('#radio_normal').is(':checked')) {
                    document.cookie="student_checked="+0;
                };
                if ($('#radio_student').is(':checked')) {
                    document.cookie="student_checked="+1;
                    user_id = $("#user_id").val();
                };
                if ($('#radio_bulk').is(':checked')) {
                    document.cookie="student_checked="+2;
                    num_pages = $("#num_pages").val();
                };
                // vcs upload
                if ({$vcs_string}) {
                    repo_id = $("#repo_id").val();
                }
                // bulk upload
                if ($("#radio_bulk").is(":checked")) {
                    handleBulk("{$gradeable->getId()}", num_pages);
                }
                // no user id entered, upload for whoever is logged in
                else if (user_id == ""){
                    makeSubmission(user_id, {$gradeable->getHighestVersion()}, false, "", "", repo_id)
                }
                // user id entered, need to validate first
                else {
                    validateUserId("{$this->core->getCsrfToken()}", "{$gradeable->getId()}", user_id, false, "", "", repo_id, makeSubmission);
                }
                e.stopPropagation();
            });
        });
    </script>
</div>
HTML;
        }
        if ($this->core->getUser()->accessAdmin()) {

            $all_directories = $gradeable->getUploadsFiles();

            if (count($all_directories) > 0) {

                $return .= <<<HTML
<div class="content">
    <h2>Unassigned PDF Uploads</h2>
    <form id="bulkForm" method="post">
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="3%"></td>
                <td width="8%">Timestamp</td>
                <td width="53%">PDF preview</td>
                <td width="5%">Full PDF</td>
                <td width="15%">User ID</td>
                <td width="8%">Submit</td>
                <td width="8%">Delete</td>
            </tr>
        </thead>
        <tbody>
HTML;
                $count = 1;
                $count_array = array();
                foreach ($all_directories as $timestamp => $content) {
                    $files = $content["files"];

                    foreach ($files as $filename => $details) {
                        $clean_timestamp = str_replace("_", " ", $timestamp);
                        $path = $details["path"];
                        if (strpos($filename, "cover") === false) {
                            continue;
                        }
                        // get the full filename for PDF popout
                        // add "timestamp / full filename" to count_array so that path to each filename is to the full PDF, not the cover
                        $url = $this->core->getConfig()->getSiteUrl()."&component=misc&page=display_file&dir=uploads&file=".$filename."&path=".$path."&ta_grading=false";
                        $filename_full = str_replace("_cover.pdf", ".pdf", $filename);
                        $path_full = str_replace("_cover.pdf", ".pdf", $path);
                        $url_full = $this->core->getConfig()->getSiteUrl()."&component=misc&page=display_file&dir=uploads&file=".$filename_full."&path=".$path_full."&ta_grading=false";
                        $count_array[$count] = FileUtils::joinPaths($timestamp, $filename_full);
                        $return .= <<<HTML
            <tr class="tr tr-vertically-centered">
                <td>{$count}</td>
                <td>{$clean_timestamp}</td> 
                <td>
                    {$filename_full}</br>
                    <object data="{$url}" type="application/pdf" width="100%" height="300">
                        alt : <a href="{$url}">pdf.pdf</a>
                    </object>
                </td>
                <td>
                    <a onclick="openFile('{$url_full}')"><i class="fa fa-window-restore" aria-hidden="true" title="Pop out the full PDF in a new window"></i></a>
                </td>
                <td>
                    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
                    <input type="text" id="bulk_user_id_{$count}" value =""/>
                </td>
                <td>
                    <button type="button" id="bulk_submit_{$count}" class="btn btn-success">Submit</button>
                </td>
                <td>
                    <button type="button" id="bulk_delete_{$count}" class="btn btn-danger">Delete</button>
                </td>
            </tr>
HTML;
                        $count++;
                    }
                    $count_array_json = json_encode($count_array);
                }
                $return .= <<<HTML
<script type="text/javascript">
    $(function() {
        $("#bulkForm input").autocomplete({
            source: students_full
        });
        $("#bulkForm button").click(function(e) {
            var btn = $(document.activeElement);
            var id = btn.attr("id");
            var count = btn.parent().parent().index()+1;
            var user_id = $("#bulk_user_id_"+count).val();
            var js_count_array = $count_array_json;
            var path = js_count_array[count];
            if (id.includes("delete")) {
                message = "Are you sure you want to delete this submission?";
                if (!confirm(message)) {
                    return;
                }
                deleteSplitItem("{$this->core->getCsrfToken()}", "{$gradeable->getId()}", path, count);
                moveNextInput(count);
            } else {
                validateUserId("{$this->core->getCsrfToken()}", "{$gradeable->getId()}", user_id, true, path, count, "", makeSubmission);
            }
            e.preventDefault();
            e.stopPropagation();
        });
        $("#bulkForm input").keydown(function(e) {
            if(e.keyCode === 13) { // enter was pressed
                var text = $(document.activeElement);
                var id = text.attr("id");
                var count = text.parent().parent().index()+1;
                var user_id = $(document.activeElement).val();
                var js_count_array = $count_array_json;
                var path = js_count_array[count];
                validateUserId("{$this->core->getCsrfToken()}", "{$gradeable->getId()}", user_id, true, path, count, "", makeSubmission);
                e.preventDefault();
                e.stopPropagation();
            }
        });
        $("#bulkForm button").keydown(function(e) {
            if(e.keyCode === 9) { // tab was pressed
                var text = $(document.activeElement);
                var id = text.attr("id");
                var count = text.parent().parent().index()+1;
                // default behavior is okay for input/submit, but delete should go to next input
                if (id.includes("delete")) {
                    moveNextInput(count);
                    e.preventDefault();
                    e.stopPropagation();
                }
            }
        });
    });
</script>
HTML;
                $return .= <<<HTML
        </tbody>
    </table>
    </form>
</div>
HTML;
            }
        }
        $team_header = '';
        if ($gradeable->isTeamAssignment()) {
            $team_header = <<<HTML
    <h3>Team: {$gradeable->getTeam()->getMemberList()}</h3><br />
HTML;
        }
        if ($gradeable->getSubmissionCount() === 0) {
            $return .= <<<HTML
<div class="content">
    {$team_header}
    <span style="font-style: italic">No submissions for this assignment.</span>
</div>
HTML;
        }
        else {
            $return .= <<<HTML
<div class="content">
    {$team_header}
    <h3 class='label' id="submission_header" style="float: left">Select Submission Version:</h3>
HTML;
            $onChange = "versionChange('{$this->core->buildUrl(array('component' => 'student',
                                                          'gradeable_id' => $gradeable->getId(),
                                                          'gradeable_version' => ""))}', this)";
            $return .= $this->core->getOutput()->renderTemplate('AutoGrading', 'showVersionChoice', $gradeable, $onChange);

            // If viewing the active version, show cancel button, otherwise so button to switch active
            if ($current_version_number > 0) {
                if ($current_version->getVersion() == $gradeable->getActiveVersion()) {
                    $version = 0;
                    $button = '<input type="submit" id="do_not_grade" class="btn btn-default" style="float: right" value="Do Not Grade This Assignment">';
                    $onsubmit = "";
                }
                else {
                    $version = $current_version->getVersion();
                    $button = '<input type="submit" id="version_change" class="btn btn-primary" value="Grade This Version">';
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
            // disable changing submissions or cancelling assignment if student submit not allowed
            if (!$this->core->getUser()->accessGrading() && !$gradeable->getStudentSubmit()) {
                $return .= <<<HTML
    <script type="text/javascript">
        $(function() {
            $("#do_not_grade").prop("disabled", true);
            $("#version_change").prop("disabled", true);
        });
    </script>
HTML;
            }
            // disable looking at other submissions if student any version not allowed
            if (!$this->core->getUser()->accessGrading() && !$gradeable->getStudentAnyVersion()) {
                $return .= <<<HTML
    <script type="text/javascript">
        $(function() {
            $('select[name=submission_version]').hide();
            $('#do_not_grade').hide();
            $('#version_change').hide();
            $('#submission_header').hide();
            $('#submission_message').hide();
        });
    </script>
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
    <div class="sub" id="submission_message">
        <p class="green-message">
            Note: This version of your assignment will be graded by the instructor/TAs and the score recorded in the gradebook.
        </p>
    </div>
HTML;
                }
                else {
		            if($gradeable->getActiveVersion() > 0) {
		                $return .= <<<HTML
   <div class="sub" id="submission_message">
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

                if ($gradeable->hasIncentiveMessage()) {
                    $return .= <<<HTML
    <div class="sub" id="incentive_message" style="display: none;">
        <p class='green-message'>{$gradeable->getIncentiveMessage()}</p>    
    </div>
HTML;
                }

                $return .= <<<HTML
    <div class="sub">
        <h4>Submitted Files</h4>
        <div class="box half">
HTML;
                $array = ($gradeable->useVcsCheckout()) ? $gradeable->getVcsFiles() : $gradeable->getSubmittedFiles();
                foreach ($array as $file) {
                    if (isset($file['size'])) {
                        $size = number_format($file['size'] / 1024, 2);
                    }
                    else {
                        $size = number_format(-1);
                    }
                    $return .= "{$file['relative_name']} ({$size}kb)";
                    // download icon if student can download files
                    if (!$gradeable->useVcsCheckout() && $gradeable->getStudentDownload()) {
                        // if not active version and student cannot see any more than active version
                        if ($gradeable->getCurrentVersionNumber() !== $gradeable->getActiveVersion() && !$gradeable->getStudentAnyVersion()) {
                            $return .= "<br />";
                            continue;
                        }
                        $return .= <<<HTML
            <script type="text/javascript">
                function downloadFile(file, path) {
                    window.location = buildUrl({'component': 'misc', 'page': 'download_file', 'dir': 'submissions', 'file': file, 'path': path});
                }
            </script>
HTML;
                        $filename = $file['relative_name'];
                        $filepath = $file['path'];
                        $return .= <<< HTML
            <a onclick="downloadFile('$filename','$filepath')"><i class="fa fa-download" aria-hidden="true" title="Download the file"></i></a>
            <br />
HTML;
                    }
                    else {
                        $return .= "<br />";
                    }
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
                $num_visible_testcases = 0;
                foreach ($gradeable->getTestcases() as $testcase) {
                    if ($testcase->viewTestcase()) {
                        $num_visible_testcases++;
                    }
                }
                if ($num_visible_testcases > 0) {
                    $return .= <<<HTML
        <h4>Results</h4>
HTML;
                }
                $refresh_js = <<<HTML
        <script type="text/javascript">
            checkRefreshSubmissionPage("{$this->core->buildUrl(array('component' => 'student',
                                                                     'page' => 'submission',
                                                                     'action' => 'check_refresh',
                                                                     'gradeable_id' => $gradeable->getId(),
                                                                     'gradeable_version' => $current_version_number))}")
        </script>
HTML;

                if ($gradeable->inBatchQueue() && $gradeable->hasResults()) {
                    if ($gradeable->beingGradedBatchQueue()) {
                        $return .= <<<HTML
        <p class="red-message">
            This submission is currently being regraded.
        </p>
HTML;
                    } else {
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
            This submission is currently being graded.
        </p>
HTML;
                    } else {
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
                    if ($gradeable->hasIncentiveMessage() && $gradeable->getActiveVersion() > 0) {
                        // FIXME:  Only doing this for the current version, not looking to see if any prior version meets the criteria
                        //foreach ($gradeable->getVersions() as $version) {
                            if ($gradeable->getEarlyTotal() >= $gradeable->getMinimumPoints() &&
                                    $current_version->getDaysEarly() > $gradeable->getMinimumDaysEarly()) {
                                $return.= <<<HTML
            <script type="text/javascript">
                $(function() {
                    $('#incentive_message').show();
                });
            </script>
HTML;
                               // break;
                            }
                        //}
                    }
                    if (!$this->core->getOutput()->bufferOutput()) {
                        echo $return;
                        $return = "";
                    }
                    $return .= $this->core->getOutput()->renderTemplate('AutoGrading', 'showResults', $gradeable, $canViewWholeGradeable);
                    if (!$this->core->getOutput()->bufferOutput()) {
                        echo $return;
                        $return = "";
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
            if ($gradeable->hasGradeFile()) {
                $return .= <<<HTML
    <h3 class="label">TA grade</h3>
    <pre>{$gradeable->getGradeFile()}</pre>
HTML;
            } else {
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
}
