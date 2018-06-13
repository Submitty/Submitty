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
        $return .= <<<HTML
        <div class="nav-buttons">
            <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism', 'action' => 'run_plagiarism'))}">Run Lichen Plagiarism Detector</a>
        </div><br /><br />        
HTML;
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

    public function runPlagiarismForm($gradeable_ids_titles, $all_sem_gradeables) {
        $all_sem_gradeables_json = json_encode($all_sem_gradeables);
        $return = "";
        $return .= <<<HTML
<div class="content">
<h1 style="text-align: center">Plagiarism Form</h1>
<br>
HTML;
        $return .= <<<HTML
<div id="run-plagiarism-form">
    <form method="post" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism', 'action' => 'run_plagiarism'))}" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
        <input type="hidden" name="prev_gradeables_number" value="1" />
        <br /><div style="width:100%;">
            Select Gradeable: 
            <select name="gradeable_id">
HTML;
        foreach ($gradeable_ids_titles as $gradeable_id_title) {
            $title = $gradeable_id_title['g_title'];
            $id = $gradeable_id_title['g_id'];
            $return .= <<<HTML
                <option value="{$id}">$title</option>
HTML;
        }         
        $return .= <<<HTML
            </select>
        </div><br /><br />
        <div style="width:100%;">
            Instructor Provided Code: 
            <input type="radio" id="no_code_provided_id" name="provided_code_option" checked >
            <label for="no_code_provided_id">No</label>
            <input type="radio" id="code_provided_id" name="provided_code_option" >
            <label for="code_provided_id">Yes</label><br />
            <input type="file" name="provided_code_file">
        </div><br /><br />
        <div style="width:100%;">
            Version: 
            <input type="radio" id="all_version_id" name="version_option" checked >
            <label for="all_version_id">All Version</label>
            <input type="radio" id="active_version_id" name="version_option" >
            <label for="active_version_id">Only Active Version</label><br />
        </div><br /><br />
        <div style="width:100%;">
            Files to be Compared: 
            <input type="radio" id="all_files_id" name="file_option[]" checked>
            <label for="all_files_id">All Files</label>
            <input type="radio" id="regrex_matching_files_id" name="file_option[]" >
            <label for="regrex_matching_files_id">Regrex matching files</label><br /><br />
            <input type="text" name="regrex_to_select_files" />
        </div><br /><br />
        <div style="width:100%;">
            Language: 
            <select name="language">
                <option value="python">Python</option>
                <option value="cpp">C++</option>
                <option value="java">Java</option>
                <option value="plaintext">Plain Text</option>
            </select>
        </div><br /><br />
        <div style="width:100%;">
            Threshold to be considered as Plagiarism: 
            <input type="text" name="threshold"/ value="5" />
        </div><br /><br />
        <div style="width:100%;">
            Sequence Length: 
            <input type="text" name="sequence_length" value="10"/>
        </div><br /><br />
        <div name= "prev_gradeable_div" style="width:100%;">
            Previous Terms Gradeables:<br /> 
            <select name="prev_sem_0">
                <option value="">None</option>
HTML;
        foreach ($all_sem_gradeables as $sem => $sem_gradeables) {
            $return .= <<<HTML
                <option value="{$sem}">$sem</option>
HTML;
        }         
        $return .= <<<HTML
            </select>
            <select name="prev_course_0">
                <option value="">None</option>           
            </select>
            <select name="prev_gradeable_0">
                <option value="">None</option>
            </select>
        </div>
        <span style="cursor:pointer;" name="add_more_prev_gradeable">
            <i class="fa fa-plus-square" aria-hidden="true" ></i>Add more
        </span>    
        <br /><br />
        <div style="float: right; width: auto; margin-top: 10px">
            <a href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism'))}" class="btn btn-danger">Cancel</a>
            <input class="btn btn-primary" type="submit" value="Run Lichen Plagiarism Detector" />
        </div>
    </form>
</div>
<script>
    var form = $("#run-plagiarism-form");
    var all_sem_gradeables = JSON.parse('{$all_sem_gradeables_json}');
    $("select").change(function(){
        var select_element_name = $(this).attr("name");
        PlagiarismFormOptionChanged(all_sem_gradeables, select_element_name);
    });
    $('[name="add_more_prev_gradeable"]', form).on('click', function(){
        addMorePrevGradeable(all_sem_gradeables);
    });
</script>
HTML;

    return $return;
    }
}
