<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\FileUtils;

class PlagiarismView extends AbstractView {

    public function plagiarismMainPage($semester, $course, $gradeables_with_plagiarism_result, $refresh_page, $nightly_rerun_info) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection');

        $return = "";
        $return .= <<<HTML
<div class="content">
    <h1>Plagiarism Detection -- WORK IN PROGRESS</h1><br>
    <div class="nav-buttons">
        <a class="btn btn-primary" href="{$this->core->buildCourseUrl(['plagiarism', 'configuration', 'new'])}">+ Configure New Gradeable for Plagiarism Detection</a>
    </div><br /><br />
    <div class="sub">
    <center>
    <table style="border-collapse: separate;border-spacing: 15px 10px;">
HTML;
        $course_path = $this->core->getConfig()->getCoursePath();
        foreach ($gradeables_with_plagiarism_result as $gradeable) {
            $title = $gradeable['g_title'];
            $id = $gradeable['g_id'];

            $delete_form_action = $this->core->buildCourseUrl(['plagiarism', 'gradeable', $id, 'delete']);

            if (file_exists($course_path . "/lichen/ranking/" . $id . ".txt")) {
                $timestamp = date("F d Y H:i:s.", filemtime($course_path . "/lichen/ranking/" . $id . ".txt"));
                $students = array_diff(scandir($course_path . "/lichen/concatenated/" . $id), array('.', '..'));
                $submissions = 0;
                foreach ($students as $student) {
                    $submissions += count(array_diff(scandir($course_path . "/lichen/concatenated/" . $id . "/" . $student), array('.', '..')));
                }
                $students = count($students);
            }
            else {
                $timestamp = "N/A";
                $students = "N/A";
                $submissions = "N/A";
            }
            $night_rerun_status = "";
            if ($nightly_rerun_info[$id] == true) {
                $night_rerun_status = "checked";
            }

            // lichen job in queue for this gradeable but processing not started
            if (file_exists("/var/local/submitty/daemon_job_queue/lichen__" . $semester . "__" . $course . "__" . $id . ".json")) {
                $return .= <<<HTML
        <tr style="color:grey;">
            <td>$title
            </td>
            <td colspan=3><i>in queue</i>
            </td>
            <td>
                Last run: $timestamp
            </td>
            <td>
                $students students, $submissions submissions
            </td>
            <td>
                <label><input type="checkbox" {$night_rerun_status} >Nightly Re-run </label>
            </td>
        </tr>
HTML;
            }
            elseif (file_exists("/var/local/submitty/daemon_job_queue/PROCESSING_lichen__" . $semester . "__" . $course . "__" . $id . ".json")) {
                // lichen job in processing stage for this gradeable but not completed
                $return .= <<<HTML
        <tr style="color:green;">
            <td>$title
            </td>
            <td colspan=3><i>running</i>
            </td>
            <td>
                Last run: $timestamp
            </td>
            <td>
                $students students, $submissions submissions
            </td>
            <td>
                <label><input type="checkbox" {$night_rerun_status} >Nightly Re-run </label>
            </td>
        </tr>
HTML;
            }
            else {
                // no lichen job
                $ranking_file_path = "/var/local/submitty/courses/" . $semester . "/" . $course . "/lichen/ranking/" . $id . ".txt";
                if (file_get_contents($ranking_file_path) == "") {
                    $matches_and_topmatch = "0 students matched, N/A top match";

                    $return .= <<<HTML
        <tr>
            <td>$title
            </td>
HTML;
                }
                else {
                    $content = file_get_contents($ranking_file_path);
                    $content = trim(str_replace(array("\r", "\n"), '', $content));
                    $rankings = preg_split('/ +/', $content);
                    $rankings = array_chunk($rankings, 3);
                    $matches_and_topmatch = count($rankings) . " students matched, " . $rankings[0][0] . " top match";

                    $return .= <<<HTML
        <tr>
            <td><a href="{$this->core->buildCourseUrl(['plagiarism', 'gradeable', $id])}">$title</a>
            </td>
HTML;
                }

                $return .= <<<HTML
            <td><a href="{$this->core->buildCourseUrl(['plagiarism', 'configuration', 'edit'])}?gradeable_id={$id}" aria-label="Edit {$title}"><i class="fas fa-pencil-alt"></i></a>
            </td>
            <td><a href="{$this->core->buildCourseUrl(['plagiarism', 'gradeable', $id, 'rerun'])}" aria-label="Rerun {$title}"><i class="fas fa-sync"></i></a>
            </td>
            <td><a onclick="deletePlagiarismResultAndConfigForm('{$delete_form_action}', '{$title}');" aria-label="Delete {$title}"><i class="fas fa-trash"></i></a>
            </td>
            <td>
                Last run: $timestamp
            </td>
            <td>
                $students students, $submissions submissions
            </td>
            <td>
                $matches_and_topmatch
            </td>
            <td>
                <label><input type="checkbox" onclick='window.location.href = buildCourseUrl(["plagiarism", "gradeable", "{$id}", "nightly_rerun"]);' {$night_rerun_status} >Nightly Re-run </label>
            </td>
        </tr>
HTML;
            }
        }

        $return .= <<<HTML
    </table></center>
    </div>
</div>
HTML;

        $return .= <<<HTML
<script>
    checkRefreshLichenMainPage("{$this->core->buildCourseUrl(['plagiarism', 'check_refresh'])}" ,"{$semester}", "{$course}");
</script>
HTML;
        #refresh page ensures atleast one refresh of lichen mainpage when delete , rerun , edit or new configuration is saved.
        if ($refresh_page == "REFRESH_ME") {
            $return .= <<<HTML
<script>
    var last_data= "REFRESH_ME";
    localStorage.setItem("last_data", last_data);
</script>
HTML;
        }

        return $return;
    }

    public function showPlagiarismResult($semester, $course, $gradeable_id, $gradeable_title, $rankings) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection', $this->core->buildCourseUrl(['plagiarism']));
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('codemirror', 'codemirror.css'));
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('codemirror', 'codemirror.js'));
        $this->core->getOutput()->addInternalJs('plagiarism.js');

        $return = "";
        $return .= <<<HTML
        <script>
        $( document ).ready(function() {
    		setUpPlagView("${gradeable_id}");
		});
        </script>
<div style="padding:5px 5px 0px 5px;" class="full_height content forum_content forum_show_threads">
HTML;

        $return .= $this->core->getOutput()->renderTwigTemplate("admin/PlagiarismHighlightingKey.twig");

        $return .= <<<HTML
        <span style="line-height: 2">Gradeable: <b>$gradeable_title</b> <a style="float:right;" class="btn btn-primary" title="View Key" onclick="$('#Plagiarism-Highlighting-Key').css('display', 'block');">View Key</a></span>
        <hr style="margin-top: 10px;margin-bottom: 10px;" />
        <form id="users_with_plagiarism">
            User 1 (sorted by %match):
            <select name="user_id_1">
                <option value="">None</option>
HTML;
        foreach ($rankings as $ranking) {
            $return .= <<<HTML
                <option value="{$ranking[1]}">$ranking[3] $ranking[4] &lt;$ranking[1]&gt; ($ranking[0])</option>
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
                <a name="toggle" class="btn btn-primary" onclick="toggle();">Toggle</a>
            </span>
        </form><br />
        <div style="position:relative; height:80vh; overflow-y:hidden;" class="row">
        <div style="max-height: 100%; width:100%;" class="sub">
        <div style="float:left;width:48%;height:100%;line-height:1.5em;overflow:auto;padding:5px;border: solid 1px #555;background:white;border-width: 2px;">
        <textarea id="code_box_1" name="code_box_1"></textarea>
        </div>
        <div style="float:right;width:48%;height:100%;line-height:1.5em;overflow:auto;padding:5px;border: solid 1px #555;background:white;border-width: 2px;">
        <textarea id="code_box_2" name="code_box_2"></textarea>
        </div>
        </div>
        </div>

HTML;
        $return .= <<<HTML
</div>
<script>

</script>
HTML;
        return $return;
    }

    public function deletePlagiarismResultAndConfigForm() {
        return <<<HTML
    <div class="popup-form"  style="display: none;" id="delete-plagiarism-result-and-config-form">
        <form name="delete" method="post">
            <div class="popup-box">
                <div class="popup-window ui-draggable ui-draggable-handle" style="position: relative;">
                    <div class="form-title">
                        <h1>Delete Plagiarism Results</h1>
                    </div>
                    <div class="form-body">
                        <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
                        <p>Note: Deleting plagiarism results will also delete the saved configuration for the gradeable.</p><br>
                        Are you sure to delete Plagiarism Results for
                        <b><div name="gradeable_title"></div></b>
                        <div class="form-buttons">
                            <div class="form-button-container">
                                <a onclick="$('#delete-plagiarism-result-and-config-form').css('display', 'none');" class="btn btn-default">Cancel</a>
                                <input class="btn btn-danger" type="submit" value="Delete" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <script>
        $(".popup-window").draggable();
    </script>
HTML;
    }

    public function plagiarismPopUpToShowMatches() {
        return <<<HTML
    <ul id="popup_to_show_matches_id" tabindex="0" class="ui-menu ui-widget ui-widget-content ui-autocomplete ui-front" style="display: none;top:0px;left:0px;width:auto;" >
    </ul>
HTML;
    }

    public function configureGradeableForPlagiarismForm($new_or_edit, $gradeable_ids_titles, $prior_term_gradeables, $saved_config, $title) {
        $this->core->getOutput()->addBreadcrumb('Plagiarism Detection', $this->core->buildCourseUrl(['plagiarism']));
        $this->core->getOutput()->addBreadcrumb('Configure New Gradeable');
        $prior_term_gradeables_json = json_encode($prior_term_gradeables);
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        #default values for the form
        $gradeable_id = "";
        $all_version = "checked";
        $active_version = "";
        $all_files = "checked";
        $regex_matching_files = "";
        $regex = "";
        $language = ["python" => "selected", "java" => "", "plaintext" => "", "cpp" => "", "mips" => ""];
        $provided_code = "";
        $no_provided_code = "checked";
        $provided_code_filename = "";
        $threshold = "5";
        $sequence_length = "10";
        $prior_term_gradeables_number = $saved_config['prev_term_gradeables'] ? count($saved_config['prev_term_gradeables']) + 1 : 1;
        $ignore_submission_number = $saved_config['ignore_submissions'] ? count($saved_config['ignore_submissions']) + 1 : 1;
        $ignore = "";
        $no_ignore = "checked";


        #values which are in saved configuration
        if ($new_or_edit == "edit") {
            $gradeable_id = $saved_config['gradeable'];
            $all_version = ($saved_config['version'] == "all_version") ? "checked" : "";
            $active_version = ($saved_config['version'] == "active_version") ? "checked" : "";
            if ($saved_config['file_option'] == "matching_regex") {
                $all_files = "";
                $regex_matching_files = "checked";
                $regex = $saved_config['regex'];
            }
            $language[$saved_config['language']] = "selected";

            if ($saved_config["instructor_provided_code"] == true) {
                $provided_code_filename_array = (array_diff(scandir($saved_config["instructor_provided_code_path"]), array(".", "..")));
                foreach ($provided_code_filename_array as $filename) {
                    $provided_code_filename = $filename;
                }
                $provided_code = "checked";
                $no_provided_code = "";
            }

            $threshold = $saved_config['threshold'];
            $sequence_length = $saved_config['sequence_length'];

            if (count($saved_config['ignore_submissions']) > 0) {
                $ignore = "checked";
                $no_ignore = "";
            }
        }

        $return = "";
        $return .= <<<HTML
<div class="content">
<h1>Plagiarism Detection Configuration -- WORK IN PROGRESS</h1>
<br>
HTML;
        $return .= <<<HTML
    <div id="save-configuration-form" style="overflow:auto;">
        <form method="post" action="{$this->core->buildCourseUrl(['plagiarism', 'configuration', 'new'])}?new_or_edit={$new_or_edit}&gradeable_id={$gradeable_id}" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
            <input type="hidden" name="prior_term_gradeables_number" value="{$prior_term_gradeables_number}" />
            <input type="hidden" name="ignore_submission_number" value="{$ignore_submission_number}" /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Select Gradeable:</div>
                <div style="width:70%;float:right">
HTML;
        if ($new_or_edit == "new") {
            $return .= <<<HTML
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
HTML;
        }
        elseif ($new_or_edit == "edit") {
            $return .= <<<HTML
                    $title
HTML;
        }

        $return .= <<<HTML
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Instructor Provided Code:</div>
                <div style="width:70%;float:right">
                    <input type="radio" id="no_code_provided_id" value="no_code_provided" name="provided_code_option" {$no_provided_code} >
                    <label for="no_code_provided_id">No</label>
                    <input type="radio" id="code_provided_id" value="code_provided" name="provided_code_option" {$provided_code}>
                    <label for="code_provided_id">Yes</label><br />
                    <input type="file" name="provided_code_file">
HTML;
        if ($new_or_edit == "edit" && $saved_config["instructor_provided_code"]) {
            $return .= <<<HTML
                    <br />
                    <font size="-1">Current Provided Code: $provided_code_filename</font>
HTML;
        }
        $return .= <<<HTML
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Version:</div>
                <div style="width:70%;float:right">
HTML;

        $return .= <<<HTML
                    <input type="radio" id="all_version_id" value="all_version" name="version_option" {$all_version} >
                    <label for="all_version_id">All Version</label>
                    <input type="radio" id="active_version_id" value="active_version" name="version_option" {$active_version}>
                    <label for="active_version_id">Only Active Version</label><br />
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Files to be Compared:</div>
                <div style="width:70%;float:right">
HTML;


        $return .= <<<HTML
                    <input type="radio" id="all_files_id" value="all_files" name="file_option" {$all_files}>
                    <label for="all_files_id">All Files</label>
                    <input type="radio" id="regex_matching_files_id" value="regex_matching_files" name="file_option" {$regex_matching_files}>
                    <label for="regex_matching_files_id">Regex matching files</label><br />
                    <input type="text" name="regex_to_select_files" value="{$regex}"/>
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Language:</div>
                <div style="width:70%;float:right">
                    <select name="language">
                        <option value="python" {$language['python']}>Python</option>
                        <option value="cpp" {$language['cpp']}>C++</option>
                        <option value="java" {$language['java']}>Java</option>
                        <option value="plaintext" {$language['plaintext']}>Plain Text</option>
                        <option value="mips" {$language['mips']}>MIPS Assembly</option>
                    </select>
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;">
                                                                             <div style="width:20%;float:left">Threshold/Maximum number of students<br>(more than this number of students with matching code will be considered common code):</div>
                <div style="width:70%;float:right">
                    <input type="text" name="threshold"/ value="{$threshold}" />
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Sequence Length:</div>
                <div style="width:70%;float:right">
                    <input type="text" name="sequence_length" value="{$sequence_length}"/>
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;overflow:auto;">
                <div style="width:20%;float:left">Prior Terms Gradeables:</div>
                <div style="width:70%;float:right;overflow:auto;" name= "prev_gradeable_div">
HTML;
        $count = 0;
        if ($new_or_edit == "edit") {
            foreach ($saved_config['prev_term_gradeables'] as $saved_prev_term_gradeable_path) {
                $saved_prev_sem = strrev((explode("/", strrev($saved_prev_term_gradeable_path)))[3]);
                $saved_prev_course = strrev((explode("/", strrev($saved_prev_term_gradeable_path)))[2]);
                $saved_prev_gradeable = strrev((explode("/", strrev($saved_prev_term_gradeable_path)))[0]);

                $return .= <<<HTML
                    <select name="prev_sem_{$count}">
                        <option value="">None</option>
HTML;
                foreach ($prior_term_gradeables as $sem => $sem_courses) {
                    if ($sem == $saved_prev_sem) {
                        $return .= <<<HTML
                        <option value="{$sem}" selected>$sem</option>
HTML;
                        continue;
                    }
                    $return .= <<<HTML
                        <option value="{$sem}">$sem</option>
HTML;
                }
                $return .= <<<HTML
                    </select>
                    <select name="prev_course_{$count}">
                        <option value="">None</option>
HTML;

                foreach ($prior_term_gradeables[$saved_prev_sem] as $sem_course => $course_gradeables) {
                    if ($sem_course == $saved_prev_course) {
                        $return .= <<<HTML
                        <option value="{$sem_course}" selected>$sem_course</option>
HTML;
                        continue;
                    }
                    $return .= <<<HTML
                        <option value="{$sem_course}">$sem_course</option>
HTML;
                }

                $return .= <<<HTML
                    </select>
                    <select name="prev_gradeable_{$count}">
                        <option value="">None</option>
HTML;
                foreach ($prior_term_gradeables[$saved_prev_sem][$saved_prev_course] as $course_gradeable) {
                    if ($course_gradeable == $saved_prev_gradeable) {
                        $return .= <<<HTML
                        <option value="{$course_gradeable}" selected>$course_gradeable</option>
HTML;
                        continue;
                    }
                    $return .= <<<HTML
                        <option value="{$course_gradeable}">$course_gradeable</option>
HTML;
                }

                $return .= <<<HTML
                    </select>
                    <br />
HTML;
                $count++;
            }
        }

            $return .= <<<HTML
                    <select name="prev_sem_{$count}">
                        <option value="">None</option>
HTML;
        foreach ($prior_term_gradeables as $sem => $sem_courses) {
            $return .= <<<HTML
                        <option value="{$sem}">$sem</option>
HTML;
        }
        $return .= <<<HTML
                    </select>
                    <select name="prev_course_{$count}">
                        <option value="">None</option>
                    </select>
                    <select name="prev_gradeable_{$count}">
                        <option value="">None</option>
                    </select>
                </div><br />
                <div style="width:70%;float:right">
                    <span name="add_more_prev_gradeable" aria-label="Add more">
                        <i class="fas fa-plus-square"></i>Add more
                    </span>
                </div>
            </div><br /><br /><br /><br /><br />
            <div style="width:100%;overflow:auto;">
                <div style="width:20%;float:left">Are there any submissions that should be ignored?</div>
                <div name="ignore_submission_div" style="width:70%;float:right;overflow:auto;">
                    <input type="radio" id="ignore_none_id" value="no_ignore" name="ignore_submission_option" {$no_ignore} >
                    <label for="ignore_none_id">No</label>
                    <input type="radio" id="ignore_id" value="ignore" name="ignore_submission_option" {$ignore} >
                    <label for="ignore_id">Yes</label><br />
HTML;

        $count = 0;
        if ($new_or_edit == "edit") {
            foreach ($saved_config['ignore_submissions'] as $saved_ignore_submission) {
                $return .= <<<HTML
                    <input type="text" name="ignore_submission_{$count}" value="{$saved_ignore_submission}"/><br />
HTML;
                $count++;
            }
        }


        return $return . <<<HTML
                    <input type="text" name="ignore_submission_{$count}" />
                </div><br />
                <div style="width:70%;float:right">
                    <span name="add_more_ignore" aria-label="Add more">
                        <i class="fas fa-plus-square"></i>Add more
                    </span>
                </div>
            </div><br /><br />
            <div style="float: right; width: auto; margin-top: 5px;">
                <a href="{$this->core->buildCourseUrl(['plagiarism'])}" class="btn btn-danger">Cancel</a>
                <input class="btn btn-primary" type="submit" value="Save Configuration" />
            </div><br /><br /><br />
        </form>
    </div>
</div>
<script>
    var form = $("#save-configuration-form");
    var prior_term_gradeables = JSON.parse('{$prior_term_gradeables_json}');
    $("select").change(function(){
        var select_element_name = $(this).attr("name");
        configureNewGradeableForPlagiarismFormOptionChanged(prior_term_gradeables, select_element_name);
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
    }
}
