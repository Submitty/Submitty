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
<h1 class="centered">Lichen Plagiarism Detection - $assignment</h1>
<br>
HTML;
        $return .= file_get_contents("/var/local/submitty/courses/$semester/$course/plagiarism/report/var/local/submitty/courses/$semester/$course/submissions/$assignment/index.html");
        $return .= <<<HTML
</div>
HTML;
        return $return;
    }

    public function plagiarismMainPage($semester, $course, $gradeables_with_plagiarism_result) {
        $return = "";
        $return .= <<<HTML
<div class="content">
    <h1 style="text-align: center">Lichen Plagiarism Detection -- WORK IN PROGRESS</h1><br>
    <div class="nav-buttons">
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism', 'semester' => $semester, 'course'=> $course, 'action' => 'configure_new_gradeable_for_plagiarism'))}">+ Configure New Gradeable for Plagiarism Detection</a>
    </div><br /><br />
    <div class="sub">
    <center>
    <table style="border-collapse: separate;border-spacing: 15px 10px;"><tr>
HTML;
        foreach ($gradeables_with_plagiarism_result as $gradeable) {
            $title = $gradeable['g_title'];
            $id = $gradeable['g_id'];
            $return .= <<<HTML
        <td><a href="{$this->core->buildUrl(array('component' => 'admin', 'semester' => $semester, 'course'=> $course, 'page' => 'plagiarism', 'action' => 'show_plagiarism_result', 'gradeable_id' => $id))}">$title</a>
        </td>
        <td><a href="{$this->core->buildUrl(array('component' => 'admin', 'semester' => $semester, 'course'=> $course, 'page' => 'plagiarism', 'action' => 'edit_plagiarism_saved_config', 'gradeable_id' => $id))}"><i class="fa fa-pencil" aria-hidden="true"></i></a>
        </td>
        <td><a href="{$this->core->buildUrl(array('component' => 'admin', 'semester' => $semester, 'course'=> $course, 'page' => 'plagiarism', 'action' => 're_run_plagiarism', 'gradeable_id' => $id))}"><i class="fa fa-refresh" aria-hidden="true"></i></a>
        </td>
        <td><a href="{$this->core->buildUrl(array('component' => 'admin', 'semester' => $semester, 'course'=> $course, 'page' => 'plagiarism', 'action' => 'delete_plagiarism_result_and_config', 'gradeable_id' => $id))}"><i class="fa fa-trash" aria-hidden="true"></i></a>
        </td>
        </tr>
HTML;
        }

        $return .= <<<HTML
    </table></center>
    </div>
</div>    
HTML;
        return $return;   
    }

    public function showPlagiarismResult($semester, $course, $gradeable_id, $gradeable_title , $rankings) {
        $return = "";
        $return .= <<<HTML
<div class="content">
<h1 style="text-align: center">Lichen Plagiarism Detection -- WORK IN PROGRESS</h1>
<br>
HTML;

        $return .= <<<HTML
        <div class="sub">
        Gradeable: <b>$gradeable_title</b><br />
        <br>
        <form id="users_with_plagiarism">
            User 1 (sorted by %match): 
            <select name="user_id_1">
                <option value="">None</option>
HTML;
        foreach ($rankings as $ranking) {
            $return .= <<<HTML
                <option value="{$ranking[1]}">$ranking[3]  ($ranking[0])</option>    
HTML;
        }

        $return .= <<<HTML
            </select>
            Version: 
            <select name="version_user_1">
                <option value="">None</option>
            </select> 
            <span style="float:right;"> User 2:
                <select name="user_id_2">
                    <option value="">None</option>
                </select>
                <a name="toggle" class="btn btn-primary" onclick="toggleUsersPlagiarism('{$gradeable_id}');">Toggle</a>
            </span>   
        </form><br />
        <div name="code_box_1" style="float:left;width:45%;height:500px;line-height:1.5em;overflow:scroll;padding:5px;border: solid 1px #555;background:white;border-width: 2px;">
        </div>
        <div name="code_box_2" style="float:right;width:45%;height:500px;line-height:1.5em;overflow:scroll;padding:5px;border: solid 1px #555;background:white;border-width: 2px;">
        </div>
        </div>
HTML;
        $return .= <<<HTML
</div>
<script>
    var form = $("#users_with_plagiarism");
    $('[name="user_id_1"]', form).change(function(){
        setUserSubmittedCode('{$gradeable_id}','user_id_1');
    });
    $('[name="version_user_1"]', form).change(function(){
        setUserSubmittedCode('{$gradeable_id}', 'version_user_1');
    });
    $('[name="user_id_2"]', form).change(function(){
        setUserSubmittedCode('{$gradeable_id}', 'user_id_2');
    });
    $(document).click(function() {
        if($('#popup_to_show_matches_id').css('display') == 'block'){
            $('#popup_to_show_matches_id').css('display', 'none');
        }
    });
</script>
HTML;
        return $return;
    }

    public function plagiarismPopUpToShowMatches() {
        $return = <<<HTML
    <ul id="popup_to_show_matches_id" tabindex="0" class="ui-menu ui-widget ui-widget-content ui-autocomplete ui-front" style="display: none;top:0px;left:0px;width:auto;" >
    </ul>
HTML;
        return $return;       
    }

    public function reRunPlagiarismForm($semester, $course, $lichen_saved_configs) {
        $return = <<<HTML
    <div class="popup-form" id="rerun-plagiarism-form">
        <br />
        <h2 style="text-align: center">Re-Run Plagiarism Detector</h2>
        <br />
        <form method="post" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism', 'course' => $course, 'semester' => $semester, 'action' => 're_run_plagiarism'))}">
            <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
            Gradeables with Saved Configuration: <br />
            <select name="gradeable_id">
HTML;
        foreach ($lichen_saved_configs as $id=>$lichen_saved_config) {
            $return .= <<<HTML
                <option value="{$id}">$lichen_saved_config</option>
HTML;
        }
        $return .= <<<HTML
            </select>
            <br /><br />
            <div style="float: right; width: auto; margin-top: 5px;">
                <a onclick="$('#rerun-plagiarism-form').css('display', 'none');" class="btn btn-danger">Cancel</a>
                <input class="btn btn-primary" type="submit" value="Re-Run Plagiarism" />
            </div><br /><br /><br />
        </form>
    </div>
HTML;
        return $return;       
    }

    public function saveConfigurationForm($gradeable_ids_titles, $prior_term_gradeables) {
        $prior_term_gradeables_json = json_encode($prior_term_gradeables);
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();
        $return = "";
        $return .= <<<HTML
<div class="content">
<h1 style="text-align: center">Lichen Plagiarism Detection Configuration -- WORK IN PROGRESS</h1>
<br>
HTML;
        $return .= <<<HTML
    <div id="run-plagiarism-form" style="overflow:auto;">
        <form method="post" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism', 'course' => $course, 'semester' => $semester, 'action' => 'save_plagiarism_configuration'))}" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
            <input type="hidden" name="prior_term_gradeables_number" value="1" />
            <input type="hidden" name="ignore_submission_number" value="1" /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Select Gradeable:</div> 
                <div style="width:70%;float:right">
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
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Instructor Provided Code:</div>
                <div style="width:70%;float:right"> 
                    <input type="radio" id="no_code_provided_id" value="no_code_provided" name="provided_code_option" checked >
                    <label for="no_code_provided_id">No</label>
                    <input type="radio" id="code_provided_id" value="code_provided" name="provided_code_option" >
                    <label for="code_provided_id">Yes</label><br />
                    <input type="file" name="provided_code_file">
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Version:</div> 
                <div style="width:70%;float:right">
                    <input type="radio" id="all_version_id" value="all_version" name="version_option" checked >
                    <label for="all_version_id">All Version</label>
                    <input type="radio" id="active_version_id" value="active_version" name="version_option" >
                    <label for="active_version_id">Only Active Version</label><br />
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Files to be Compared:</div>
                <div style="width:70%;float:right">
                    <input type="radio" id="all_files_id" value="all_files" name="file_option" checked>
                    <label for="all_files_id">All Files</label>
                    <input type="radio" id="regrex_matching_files_id" value="regrex_matching_files" name="file_option" >
                    <label for="regrex_matching_files_id">Regrex matching files</label><br />
                    <input type="text" name="regrex_to_select_files" />
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Language:</div> 
                <div style="width:70%;float:right">
                    <select name="language">
                        <option value="python">Python</option>
                        <option value="cpp">C++</option>
                        <option value="java">Java</option>
                        <option value="plaintext">Plain Text</option>
                    </select>
                </div>    
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Threshold to be considered as Plagiarism:</div> 
                <div style="width:70%;float:right">
                    <input type="text" name="threshold"/ value="5" />
                </div>    
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Sequence Length:</div> 
                <div style="width:70%;float:right">    
                    <input type="text" name="sequence_length" value="10"/>
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;overflow:auto;">
                <div style="width:20%;float:left">Prior Terms Gradeables:</div>
                <div style="width:70%;float:right;overflow:auto;" name= "prev_gradeable_div"> 
                    <select name="prev_sem_0">
                        <option value="">None</option>
HTML;
        foreach ($prior_term_gradeables as $sem => $sem_gradeables) {
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
                </div><br />
                <div style="width:70%;float:right">
                    <span name="add_more_prev_gradeable">
                        <i class="fa fa-plus-square" aria-hidden="true" ></i>Add more
                    </span>
                </div>
            </div><br /><br /><br /><br /><br /> 
            <div style="width:100%;overflow:auto;">
                <div style="width:20%;float:left">Are there any submissions that should be ignored?</div>
                <div name="ignore_submission_div" style="width:70%;float:right;overflow:auto;">
                    <input type="radio" id="ignore_none_id" value="no_ignore" name="ignore_submission_option" checked >
                    <label for="ignore_none_id">No</label>
                    <input type="radio" id="ignore_id" value="ignore" name="ignore_submission_option" >
                    <label for="ignore_id">Yes</label><br />
                    <input type="text" name="ignore_submission_0" />
                </div><br />
                <div style="width:70%;float:right">
                    <span name="add_more_ignore">
                        <i class="fa fa-plus-square" aria-hidden="true" ></i>Add more
                    </span>     
                </div>    
            </div><br /><br />
            <div style="float: right; width: auto; margin-top: 5px;">
                <a href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism', 'course' => $course, 'semester' => $semester))}" class="btn btn-danger">Cancel</a>
                <input class="btn btn-primary" type="submit" value="Save Configuration" />
            </div><br /><br /><br />
        </form>
    </div>    
</div>
<script>
    var form = $("#run-plagiarism-form");
    var prior_term_gradeables = JSON.parse('{$prior_term_gradeables_json}');
    $("select").change(function(){
        var select_element_name = $(this).attr("name");
        PlagiarismConfigurationFormOptionChanged(prior_term_gradeables, select_element_name);
    });
    $('[name="add_more_prev_gradeable"]', form).on('click', function(){
        addMorePriorTermGradeable(prior_term_gradeables);
    });
    $('[name="add_more_ignore"]', form).on('click', function(){
        var ignore_submission_number = $('[name="ignore_submission_number"]', form).val();
        $('[name="ignore_submission_div"]', form).append('<br /><input type="text" name="ignore_submission_'+ ignore_submission_number +'" />');
        $('[name="ignore_submission_number"]', form).val(parseInt(ignore_submission_number)+1);
    });
</script>
HTML;

    return $return;
    }
}
