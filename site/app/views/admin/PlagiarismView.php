<?php
namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\FileUtils;

class PlagiarismView extends AbstractView {

    public function plagiarismMainPage($semester, $course, $gradeables_with_plagiarism_result, $refresh_page, $nightly_rerun_info) {
        $return = "";
        $return .= <<<HTML
<div class="content">
    <h1>Plagiarism Detection -- WORK IN PROGRESS</h1><br>
    <div class="nav-buttons">
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism', 'semester' => $semester, 'course'=> $course, 'action' => 'configure_new_gradeable_for_plagiarism_form'))}">+ Configure New Gradeable for Plagiarism Detection</a>
    </div><br /><br />
    <div class="sub">
    <center>
    <table style="border-collapse: separate;border-spacing: 15px 10px;">
HTML;
        $course_path = $this->core->getConfig()->getCoursePath();
        foreach ($gradeables_with_plagiarism_result as $gradeable) {
            $title = $gradeable['g_title'];
            $id = $gradeable['g_id'];
        
            $delete_form_action = $this->core->buildUrl(array('component' => 'admin', 'semester' => $semester, 'course'=> $course, 'page' => 'plagiarism', 'action' => 'delete_plagiarism_result_and_config', 'gradeable_id' => $id));
            
            if(file_exists($course_path."/lichen/ranking/".$id.".txt")) {
                $timestamp = date("F d Y H:i:s.",filemtime($course_path."/lichen/ranking/".$id.".txt"));
                $students = array_diff(scandir($course_path."/lichen/concatenated/".$id), array('.', '..'));
                $submissions =0;
                foreach($students as $student) {
                    $submissions += count(array_diff(scandir($course_path."/lichen/concatenated/".$id."/".$student), array('.', '..')));
                }
                $students = count($students);    
            }
            else {
                $timestamp = "N/A";
                $students = "N/A";
                $submissions = "N/A";
            }
            $night_rerun_status= "";
            if($nightly_rerun_info[$id] ==true) {
                $night_rerun_status = "checked";
            }

            #lichen job in queue for this gradeable but processing not started
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

            #lichen job in processing stage for this gradeable but not completed
            else if (file_exists("/var/local/submitty/daemon_job_queue/PROCESSING_lichen__" . $semester . "__" . $course . "__" . $id . ".json")) {
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

            #no lichen job
            else {
                $ranking_file_path= "/var/local/submitty/courses/".$semester."/".$course."/lichen/ranking/".$id.".txt";
                if(file_get_contents($ranking_file_path) == "") {
                    $matches_and_topmatch= "0 students matched, N/A top match";
                    
                    $return .= <<<HTML
        <tr>
            <td>$title
            </td>
HTML;
                }
                else {
                    $content =file_get_contents($ranking_file_path);
                    $content = trim(str_replace(array("\r", "\n"), '', $content));
                    $rankings = preg_split('/ +/', $content);
                    $rankings = array_chunk($rankings,3);
                    $matches_and_topmatch = count($rankings)." students matched, ".$rankings[0][0]." top match";
                    
                    $return .= <<<HTML
        <tr>
            <td><a href="{$this->core->buildUrl(array('component' => 'admin', 'semester' => $semester, 'course'=> $course, 'page' => 'plagiarism', 'action' => 'show_plagiarism_result', 'gradeable_id' => $id))}">$title</a>
            </td>
HTML;
                }
                
                $return .= <<<HTML
            <td><a href="{$this->core->buildUrl(array('component' => 'admin', 'semester' => $semester, 'course'=> $course, 'page' => 'plagiarism', 'action' => 'edit_plagiarism_saved_config', 'gradeable_id' => $id))}"><i class="fas fa-pencil-alt" aria-hidden="true"></i></a>
            </td>
            <td><a href="{$this->core->buildUrl(array('component' => 'admin', 'semester' => $semester, 'course'=> $course, 'page' => 'plagiarism', 'action' => 're_run_plagiarism', 'gradeable_id' => $id))}"><i class="fas fa-sync" aria-hidden="true"></i></a>
            </td>
            <td><a onclick="deletePlagiarismResultAndConfigForm('{$delete_form_action}', '{$title}');"><i class="fas fa-trash" aria-hidden="true"></i></a>
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
                <label><input type="checkbox" onclick='window.location.href = buildUrl({"component":"admin", "page" :"plagiarism", "course":"{$course}", "semester": "{$semester}", "action": "toggle_nightly_rerun", "gradeable_id":"{$id}"});' {$night_rerun_status} >Nightly Re-run </label>
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
<script type="text/javascript">
    checkRefreshLichenMainPage("{$this->core->buildUrl(array('component' => 'admin', 'semester' => $semester, 'course'=> $course, 'page' => 'plagiarism', 'action' => 'check_refresh_lichen_mainpage'))}" ,"{$semester}", "{$course}");
</script>
HTML;
        #refresh page ensures atleast one refresh of lichen mainpage when delete , rerun , edit or new configuration is saved.
        if($refresh_page == "REFRESH_ME") {
            $return .= <<<HTML
<script type="text/javascript">
    var last_data= "REFRESH_ME";
    localStorage.setItem("last_data", last_data);
</script>
HTML;
        }

        return $return;   
    }

    public function showPlagiarismResult($semester, $course, $gradeable_id, $gradeable_title , $rankings) {
        $return = "";
        $return .= <<<HTML
        <script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}vendor/codemirror/codemirror.js"></script>
        <script>

            $(document).ready(function() {
                var editor0 = $('.CodeMirror')[0].CodeMirror;
                var editor1 = $('.CodeMirror')[1].CodeMirror;

                //console.log('hello');

                editor0.getWrapperElement().onmousedown = function(e) {
                    var lineCh = editor0.coordsChar({ left: e.clientX, top: e.clientY });
                    //console.log(lineCh);
                    var markers = editor0.findMarksAt(lineCh);
                    if (markers.length === 0) { return; }
                    var lineData = markers[0].find();
                    var clickedMark = markers[0];
                    
                    if(markers[0].css.toLowerCase().indexOf("#ffff00") != -1) { //Can be used to determine click

                        // var redSegments = document.getElementsByClassName("red_plag");

                        //console.log($(".red_plag")[0]);

                        var allMarks = editor0.getAllMarks();

                        allMarks.forEach(m => {
                            if(m.className === "red_plag") {
                                m.css = "border: 1px solid black; background: " + m.attributes["data_prev_color"];
                                m.className = "";
                            }
                        });

                        //console.log($($(redSegments[0]).parent().parent().parent())(".CodeMirror-linenumber").html());

                        // if(redSegments.length > 0) {
                        //     var r = redSegments[0];
                        //     console.log($(r).parent());
                        //     var position = r.
                        //     var rLineCh = editor0.coordsChar( {left: position.left, top: position.top} );
                        //     //console.log(rLineCh);
                        //     var redMarkers = editor0.findMarksAt(lineCh);
                        //     //console.log(redMarkers);
                        //     var redClickedMark = redMarkers[0];

                        //     redClickedMark.css = "border: 1px solid black; background:" + redClickedMark.attributes["data_prev_color"];
                        //     //redClickedMark.className = "";
                        //     console.log(redClickedMark);
                        // }

                        


                        // redSegments.forEach(e => {
                        //     console.log(e);
                        // });

                        setTimeout(function() {
                            //your code to be executed after 1 second
                            clickedMark.css = "border: 1px solid black; background:#FF0000";
                            clickedMark.className = "red_plag";
                            clickedMark.attributes = {"data_prev_color": "#ffff00"};
                            editor0.refresh();
                        }, 250);

                            getMatchesForClickedMatch("{$gradeable_id}", event, lineData.from, lineData.to, "code_box_1", "orange", null, "", "");


                        //editor0.markText(lineData.from, lineData.to, {attributes: {"data_color_prev": "#ffff00"}, 'className': 'red_plag', 'css': 'background: #FF0000;'});

                        //Use jquery to modify all classesWith red_plag back to prev_color

                    }
                    if(markers[0].css.toLowerCase().indexOf("#ffa500") != -1) { //Can be used to determine click


                        var redSegments = document.getElementsByClassName("red_plag");

                        //console.log(redSegments);

                        
                        // redSegments.forEach(e => {
                        //     console.log(e);
                        // });


                        //editor0.markText(lineData.from, lineData.to, {attributes: {"data_color_prev": "#ffa500"}, 'className': 'red_plag', 'css': 'background: #FF0000;'});
                        var marks_editor2 = editor1.getAllMarks();
                        marks_editor2.forEach(mark => {
                            if(mark.attributes.data_start == markers[0].attributes.data_start && mark.attributes.data_end == markers[0].attributes.data_end) {
                                //mark.className = 'red_plag';
                                var marker_linedata = mark.find();
                                //mark.css = "border: 1px solid black; border-right:1px solid red;background: #FF0000";
                                //console.log(mark);
                                //console.log(marker_linedata);

                                var allMarks = editor0.getAllMarks();

                                // allMarks.forEach(m => {
                                //     if(m.className === "red_plag") {
                                //         m.css = "border: 1px solid black; background: " + m.attributes["data_prev_color"];
                                //         m.className = "";
                                //     }
                                // });

                                // allMarks = marks_editor2;

                                // allMarks.forEach(m => {
                                //     if(m.className === "red_plag") {
                                //         m.css = "border: 1px solid black; background: " + m.attributes["data_prev_color"];
                                //         m.className = "";
                                //     }
                                // });

                                clickedMark.css = "border: 1px solid black; background:#FF0000";
                                clickedMark.className = "red_plag";
                                clickedMark.attributes = {"data_prev_color": "#ffff00"};
                                editor0.refresh();

                                mark.css = "border: 1px solid black; background: #FF0000";
                                mark.className = 'red_plag';
                                mark.attributes = {"data_color_prev": "#ffa500"};
                                editor1.refresh();

                                //var top = editor1.charCoords(marker_linedata.from.line).top;

                                editor1.scrollIntoView(marker_linedata.to);
                                // 
                                
                            }
                        });
                        //getMatchesForClickedMatch("{$gradeable_id}", event, lineData.from, lineData.to, "code_box_2", "orange", markers[0].attributes, "", "");
                    }
                }
            });

        </script>
        <link rel="stylesheet" href="{$this->core->getConfig()->getBaseUrl()}vendor/codemirror/codemirror.css" />
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
                <a name="toggle" class="btn btn-primary" onclick="toggleUsersPlagiarism('{$gradeable_id}');">Toggle</a>
            </span>   
        </form><br />
        <div style="position:relative; height:100%; overflow-y:hidden;" class="row">
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
    var form = $("#users_with_plagiarism");
    var code_user_1 = CodeMirror.fromTextArea(document.getElementById('code_box_1'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true
    });
    var code_user_2 = CodeMirror.fromTextArea(document.getElementById('code_box_2'), {
        lineNumbers: true,
        readOnly: true,
        cursorHeight: 0.0,
        lineWrapping: true
    });

    code_user_2.setSize("100%", "100%");
    code_user_1.setSize("100%", "100%");
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

    public function deletePlagiarismResultAndConfigForm() {
        $return = <<<HTML
    <div class="popup-form"  style="display: none;" id="delete-plagiarism-result-and-config-form">
        <form name="delete" method="post" action="">
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
                    </div>
                    <div class="form-buttons">
                        <div class="form-button-container">
                            <a onclick="$('#delete-plagiarism-result-and-config-form').css('display', 'none');" class="btn btn-default">Cancel</a>
                            <input class="btn btn-danger" type="submit" value="Delete" />
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
        return $return;       
    }

    public function plagiarismPopUpToShowMatches() {
        $return = <<<HTML
    <ul id="popup_to_show_matches_id" tabindex="0" class="ui-menu ui-widget ui-widget-content ui-autocomplete ui-front" style="display: none;top:0px;left:0px;width:auto;" >
    </ul>
HTML;
        return $return;       
    }

    public function configureGradeableForPlagiarismForm($new_or_edit, $gradeable_ids_titles = null, $prior_term_gradeables, $saved_config = null) {
        $prior_term_gradeables_json = json_encode($prior_term_gradeables);
        $semester = $this->core->getConfig()->getSemester();
        $course = $this->core->getConfig()->getCourse();

        #default values for the form
        $gradeable_id="";   
        $all_version="checked";
        $active_version=""; 
        $all_files="checked";
        $regex_matching_files="";
        $regex="";
        $language =["python"=>"selected", "java"=>"", "plaintext"=>"", "cpp"=>""];
        $provided_code="";
        $no_provided_code="checked";
        $provided_code_filename="";
        $threshold="5";
        $sequence_length="10";
        $prior_term_gradeables_number = $saved_config['prev_term_gradeables'] ? count($saved_config['prev_term_gradeables'])+1 : 1;
        $ignore_submission_number = $saved_config['ignore_submissions'] ? count($saved_config['ignore_submissions'])+1 : 1;
        $ignore="";
        $no_ignore="checked";


        #values which are in saved configuration
        if($new_or_edit == "edit") {
            $gradeable_id = $saved_config['gradeable'];
            $all_version = ($saved_config['version'] == "all_version")?"checked":"";
            $active_version = ($saved_config['version'] == "active_version")?"checked":"";
            if($saved_config['file_option'] == "matching_regex") {
                $all_files="";
                $regex_matching_files="checked";
                $regex=$saved_config['regex'];
            }
            $language[$saved_config['language']] = "selected";

            if($saved_config["instructor_provided_code"] == true) {
                $provided_code_filename_array = (array_diff(scandir($saved_config["instructor_provided_code_path"]), array(".", "..")));
                foreach($provided_code_filename_array as $filename) {
                    $provided_code_filename= $filename;
                }
                $provided_code="checked";
                $no_provided_code="";
            }

            $threshold = $saved_config['threshold'];
            $sequence_length = $saved_config['sequence_length'];

            if(count($saved_config['ignore_submissions']) > 0) {
                $ignore="checked";
                $no_ignore="";
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
        <form method="post" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'plagiarism', 'course' => $course, 'semester' => $semester, 'new_or_edit'=> $new_or_edit , 'action' => 'save_new_plagiarism_configuration', 'gradeable_id' => $gradeable_id))}" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
            <input type="hidden" name="prior_term_gradeables_number" value="{$prior_term_gradeables_number}" />
            <input type="hidden" name="ignore_submission_number" value="{$ignore_submission_number}" /><br />
            <div style="width:100%;">
                <div style="width:20%;float:left">Select Gradeable:</div> 
                <div style="width:70%;float:right">
HTML;
        if($new_or_edit == "new") {
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

        else if($new_or_edit == "edit") {
            $title = '';
            if (isset($saved_config['gradeable']) && $saved_config['gradeable'] !== null) {
               $title = $this->core->getQueries()->getGradeableConfig($saved_config['gradeable'])->getTitle();
            }
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
        if($new_or_edit == "edit" && $saved_config["instructor_provided_code"]) {
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
            $count=0;
            if($new_or_edit == "edit") {
                foreach ($saved_config['prev_term_gradeables'] as $saved_prev_term_gradeable_path) {
                    $saved_prev_sem = strrev((explode("/", strrev($saved_prev_term_gradeable_path)))[3]);
                    $saved_prev_course = strrev((explode("/", strrev($saved_prev_term_gradeable_path)))[2]);
                    $saved_prev_gradeable = strrev((explode("/", strrev($saved_prev_term_gradeable_path)))[0]);

                    $return .= <<<HTML
                    <select name="prev_sem_{$count}">
                        <option value="">None</option>
HTML;
                    foreach($prior_term_gradeables as $sem => $sem_courses) {
                        if( $sem == $saved_prev_sem) {
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

                    foreach($prior_term_gradeables[$saved_prev_sem] as $sem_course => $course_gradeables) {
                        if( $sem_course == $saved_prev_course) {
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
                    foreach($prior_term_gradeables[$saved_prev_sem][$saved_prev_course] as $course_gradeable) {
                        if( $course_gradeable == $saved_prev_gradeable) {
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
                    <span name="add_more_prev_gradeable">
                        <i class="fas fa-plus-square" aria-hidden="true" ></i>Add more
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

        $count=0;
            if($new_or_edit == "edit") {
                foreach ($saved_config['ignore_submissions'] as $saved_ignore_submission) {
                    $return .= <<<HTML
                    <input type="text" name="ignore_submission_{$count}" value="{$saved_ignore_submission}"/><br />
HTML;
                    $count++; 
                }
            }        


        $return .= <<<HTML
                    <input type="text" name="ignore_submission_{$count}" />
                </div><br />
                <div style="width:70%;float:right">
                    <span name="add_more_ignore">
                        <i class="fas fa-plus-square" aria-hidden="true" ></i>Add more
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

    return $return;
    }
}
