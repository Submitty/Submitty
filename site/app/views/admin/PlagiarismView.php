<?php
namespace app\views\admin;

use app\views\AbstractView;

class PlagiarismView extends AbstractView {
    public function plagiarismCompare($semester, $course, $assignment, $studenta, $studentb) {
        if (strpos($semester, '.') || strpos($semester, '/')) throw new \InvalidArgumentException("Invalid semester");
        if (strpos($course, '.') || strpos($course, '/')) throw new \InvalidArgumentException("Invalid course");
        if (strpos($assignment, '.') || strpos($assignment, '/')) throw new \InvalidArgumentException("Invalid assignment");
        if (strpos($studenta, '.') || strpos($studenta, '/')) throw new \InvalidArgumentException("Invalid assignment");
        if (strpos($studentb, '.') || strpos($studentb, '/')) throw new \InvalidArgumentException("Invalid assignment");
        $return = "";
        $return .= <<<HTML
<div class="content" style="height: 85vh">
HTML;
        $return .= file_get_contents("/var/local/submitty/courses/$semester/$course/plagiarism/report/var/local/submitty/courses/$semester/$course/submissions/$assignment/compare/" . $studenta . "_" . $studentb . ".html");
        $return .= <<<HTML
</div>
HTML;
        return $return;
    }

    public function plagiarismIndex($semester, $course, $assignment) {
        if (strpos($semester, '.') || strpos($semester, '/')) throw new \InvalidArgumentException("Invalid semester");
        if (strpos($course, '.') || strpos($course, '/')) throw new \InvalidArgumentException("Invalid course");
        if (strpos($assignment, '.') || strpos($assignment, '/')) throw new \InvalidArgumentException("Invalid assignment");
        $return = "";
        $return .= <<<HTML
<div class="content">
<h1 class="centered">Plagiarism Detection - $assignment</h1>
<br>
HTML;
        $return .= file_get_contents("/var/local/submitty/courses/$semester/$course/plagiarism/report/var/local/submitty/courses/$semester/$course/submissions/$assignment/index.html");
        $return .= <<<HTML
</div>
HTML;
        return $return;
    }

    public function plagiarismTree($semester, $course, $assignments) {
        $return = "";
        $return .= <<<HTML
<div class="content">
<h1 style="text-align: center">Plagiarism Detection</h1>
<br>
HTML;   
        // ======================================================================================
        // Buttons for Navigation Bar
        // ======================================================================================
        $return .= <<<HTML
        <div class="nav-buttons">
            <button style="float: right;" class="btn btn-primary" onclick="runPlagiarismForm();">Run Lichen Plagiarism Detector</button>
        </div>        
HTML;
        // ======================================================================================
        // Assignments whose plagiarism results can be seen
        // ======================================================================================
        if ($assignments) {
            $return .= '<ul>';
                foreach ($assignments as $assignment) {
                    $return .= "<li><a href=\"{$this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism', 'action' => 'index', 'assignment' => $assignment))}\">$assignment</a></li>";
                }
            $return .= '</ul>';
        } 
        else {
            $return .= <<<HTML
<p>It looks like you have yet to run plagiarism detection on any assignment. See <a href="http://submitty.org/instructor/plagiarism">http://submitty.org/instructor/plagiarism</a> for details.</p>
HTML;
        }
        $return .= <<<HTML
</div>
HTML;
        return $return;
    }

    public function runPlagiarismForm($gradeables) {
        $return = <<<HTML
<div class="popup-form" id="run-plagiarism-form">
    <form method="post" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism', 'action' => 'run_plagiarism'))}" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
        <div>
            Select Gradeable: 
            <select name="gradeable_id">
HTML;
        foreach ($gradeables as $gradeable) {
            $return .= <<<HTML
                <option value="{$gradeable->getId()}">$gradeable->getName()</option>
HTML;
        }         
        $return .= <<<HTML
            </select>
        </div><br />
        <div>
            Instructor Provided Code: 
            <input type="checkbox" id="no_code_provided_id" name="provided_code_option[]" >
            <label for="no_code_provided_id">No</label>
            <input type="checkbox" id="code_provided_id" name="provided_code_option[]" >
            <label for="code_provided_id">Yes</label><br />
            <input type="file" name="provided_code_file">
        </div><br />
        <div>
            Version: 
            <input type="checkbox" id="all_version_id" name="version_option[]" >
            <label for="all_version_id">All Version</label>
            <input type="checkbox" id="active_version_id" name="version_option[]" >
            <label for="active_version_id">Only Active Version</label><br />
        </div><br />
        <div>
            Files to be Compared: 
            <input type="checkbox" id="all_files_id" name="file_option[]" >
            <label for="all_files_id">All Files</label>
            <input type="checkbox" id="regrex_matching_files_id" name="file_option[]" >
            <label for="regrex_matching_files_id">Regrex matching files</label><br />
            <input type="text" name="regrex_to_select_files" />
        </div><br />
        <div>
            Language: 
            <select name="language">
                <option value="python">Python</option>
                <option value="cpp">C++</option>
                <option value="java">Java</option>
                <option value="plaintext">Plain Text</option>
            </select>
        </div><br />
        <div>
            Threshold to be considered as Plagiarism: 
            <input type="text" name="threshold"/>
        </div><br />
        <div>
            Sequence Length: 
            <input type="text" name="sequence_length"/>
        </div><br />
        <div style="float: right; width: auto; margin-top: 10px">
            <a onclick="$('#run-plagiarism-form').css('display', 'none');" class="btn btn-danger">Cancel</a>
            <input class="btn btn-primary" type="submit" value="Run" />
        </div>
    </form>
</div>
<script>
    var form = $("#run-plagiarism-form");
    $('[name="language"]',form).change(function() {
        if ($(this).val() == "python") {
            $('[name="sequence_length"]', form).val('1');
        } 
        else if ($(this).val() == "cpp") {
            $('[name="sequence_length"]', form).val('2');
        }
        else if ($(this).val() == "java") {
            $('[name="sequence_length"]', form).val('3');
        }
        else if ($(this).val() == "plaintext") {
            $('[name="sequence_length"]', form).val('4');
        }
    });
</script>
HTML;

    return $return;
    }
}
