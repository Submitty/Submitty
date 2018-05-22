<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\models\AdminGradeable;

class AdminGradeableView extends AbstractView {
    /**
     * The one and only function that shows the entire page
     */
	public function show_add_gradeable($type_of_action, AdminGradeable $admin_gradeable) {

        $action = "upload_new_gradeable"; //decides how the page's data is displayed
        $button_string = "Add";
        $extra = "";
        $have_old = false;
        $edit = json_encode($type_of_action === "edit");
        $gradeables_array = array();

        foreach ($admin_gradeable->getTemplateList() as $g_id_title) { //makes an array of gradeable ids for javascript
            array_push($gradeables_array, $g_id_title['g_id']);
        }
        $js_gradeables_array = json_encode($gradeables_array);

        // //if the user is editing a gradeable instead of adding
        if ($type_of_action === "edit") {
            $have_old = true;
            $action = "upload_edit_gradeable";
            $button_string = "Save changes to";
            $extra = ($admin_gradeable->getHasGrades()) ? "<span style='color: red;'>(Grading has started! Edit Questions At Own Peril!)</span>" : "";
        }

		$html_output = <<<HTML
		<style type="text/css">

    body {
        overflow: scroll;
    }

    select {
        margin-top:7px;
        width: 60px;
        min-width: 60px;
    }

    #container-rubric {
        width:1200px;
        margin:100px auto;
        margin-top: 130px;
        background-color: #fff;
        border: 1px solid #999;
        border: 1px solid rgba(0,0,0,0.3);
        -webkit-border-radius: 6px;
        -moz-border-radius: 6px;
        border-radius: 6px;outline: 0;
        -webkit-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        -moz-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        -webkit-background-clip: padding-box;
        -moz-background-clip: padding-box;
        background-clip: padding-box;
        padding-top: 20px;
        padding-right: 20px;
        padding-left: 20px;
        padding-bottom: 20px;
    }

    .question-icon {
        display: block;
        float: left;
        margin-top: 5px;
        margin-left: 5px;
        position: relative;
        overflow: hidden;
    }

    .question-icon-cross {
        max-width: none;
        position: absolute;
        top:0;
        left:-313px;
    }

    .question-icon-up {
        max-width: none;
        position: absolute;
        top: -96px;
        left: -290px;
    }

    .question-icon-down {
        max-width: none;
        position: absolute;
        top: -96px;
        left: -313px;
    }

    .ui_tpicker_unit_hide {
        display: none;
    }
    
    /* align the radio, buttons and checkboxes with labels */
    input[type="radio"],input[type="checkbox"] {
        margin-top: -1px;
        vertical-align: middle;
    }
    
    fieldset {
        margin: 8px;
        border: 1px solid silver;
        padding: 8px;    
        border-radius: 4px;
    }
    
    legend{
        padding: 2px;  
        font-size: 12pt;
    }
        
</style>
<div id="container-rubric">
    <form id="gradeable-form" class="form-signin" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'admin_gradeable', 'action' => $action))}" 
          method="post" enctype="multipart/form-data" onsubmit="return checkForm();"> 

        <div class="modal-header" style="overflow: auto;">
            <h3 id="myModalLabel" style="float: left;">{$button_string} Gradeable {$extra}</h3>
HTML;
if ($type_of_action === "add" || $type_of_action === "add_template"){
  $html_output .= <<<HTML
            <div style="padding-left: 200px;">
                From Template: <select name="gradeable_template" style='width: 170px;' value=''>
            </div>
            <option>--None--</option>
HTML;

    foreach ($admin_gradeable->getTemplateList() as $g_id_title){
     $html_output .= <<<HTML
        <option 
HTML;
        if ($type_of_action === "add_template" && $admin_gradeable->getGId()===$g_id_title['g_id']) { $html_output .= "selected"; }
        $html_output .= <<<HTML
        value="{$g_id_title['g_id']}">{$g_id_title['g_title']}</option>
HTML;
    }
  $html_output .= <<<HTML
          </select>          
HTML;
}
  $html_output .= <<<HTML
            <button class="btn btn-primary" type="submit" style="margin-right:10px; float: right;">{$button_string} Gradeable</button>
HTML;
    $html_output .= <<<HTML
        </div>

<div class="modal-body">
<b>Please Read: <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable">Submitty Instructions on "Create or Edit a Gradeable"</a></b>
</div>

		<div class="modal-body" style="/*padding-bottom:80px;*/ overflow:visible;">
            What is the unique id of this gradeable? (e.g., <kbd>hw01</kbd>, <kbd>lab_12</kbd>, or <kbd>midterm</kbd>): <input style='width: 200px' type='text' name='gradeable_id' id="gradeable_id" class="required" value="{$admin_gradeable->getGId()}" placeholder="(Required)"/>
            <br />
            What is the title of this gradeable?: <input style='width: 227px' type='text' name='gradeable_title' id='gradeable_title_id' class="required" value="{$admin_gradeable->getGTitle()}" placeholder="(Required)" required/>
            <br />
            What is the URL to the assignment instructions? (shown to student) <input style='width: 227px' type='text' name='instructions_url' value="{$admin_gradeable->getGInstructionsUrl()}" placeholder="(Optional)" />
            <br />
            What is the <a target=_blank href="http://submitty.org/instructor/create_edit_gradeable#types-of-gradeables">type of the gradeable</a>?: <div id="required_type" style="color:red; display:inline;">(Required)</div>

            <fieldset>
                <input type='radio' id="radio_electronic_file" class="electronic_file" name="gradeable_type" value="Electronic File"
HTML;
    if (($type_of_action === "edit" || $type_of_action === "add_template") && $admin_gradeable->getGGradeableType()===0) { $html_output .= ' checked="checked"'; }
    $html_output .= <<<HTML
            > 
            Electronic File
            <input type='radio' id="radio_checkpoints" class="checkpoints" name="gradeable_type" value="Checkpoints"
HTML;
            if (($type_of_action === "edit" || $type_of_action === "add_template") && $admin_gradeable->getGGradeableType()===1) { $html_output .= ' checked="checked"'; }
    $html_output .= <<<HTML
            >
            Checkpoints
            <input type='radio' id="radio_numeric" class="numeric" name="gradeable_type" value="Numeric"
HTML;
            if (($type_of_action === "edit" || $type_of_action === "add_template") && $admin_gradeable->getGGradeableType()===2) { $html_output .= ' checked="checked"'; }
    $html_output .= <<<HTML
            >
            Numeric/Text
            <!-- This is only relevant to Electronic Files -->
            <div class="gradeable_type_options electronic_file" id="electronic_file" >
                <br />
                Is this a team assignment? <em style='color:green;'>Team assignments are new as of Fall 2017.  Email questions/bugs/feedback to: submitty@cs.rpi.edu.</em>
                <fieldset>
                    <input type="radio" id = "team_yes_radio" class="team_yes" name="team_assignment" value="true"
HTML;
                if (($type_of_action === "edit" || $type_of_action === "add_template") && $admin_gradeable->getEgTeamAssignment()) { $html_output .= ' checked="checked"'; }
                $html_output .= <<<HTML
                > Yes
                    <input type="radio" id = "team_no_radio" class="team_no" name="team_assignment" value ="false"
HTML;
                if ((($type_of_action === "edit" || $type_of_action === "add_template") && !$admin_gradeable->getEgTeamAssignment()) || $type_of_action === "add") { $html_output .= ' checked="checked"'; }
                $html_output .= <<<HTML
                > No
                    <div class="team_assignment team_yes" id="team_yes">
                        <br />
                            <div>                                
                                Use teams from a previous gradeable:
HTML;
                    if ($type_of_action === "edit" || $type_of_action === "add_template") {
                        $html_output .= <<<HTML
                                <select id='gradeable_teams' name="gradeable_teams" style='width: 170px;' value='{$admin_gradeable->getEgInheritTeamsFrom()}'>
                            </div>
                            <option value=''>--None--</option>
HTML;

                        foreach ($admin_gradeable->getInheritTeamsList() as $g_id_title){
                        $html_output .= <<<HTML
                            <option 
HTML;
                            if ($type_of_action === "add_template" && $admin_gradeable->getEgInheritTeamsFrom()===$g_id_title['g_id']) { $html_output .= "selected"; }
                            $html_output .= <<<HTML
                            value="{$g_id_title['g_id']}">{$g_id_title['g_title']}
HTML;
                        }
                        $html_output .= <<<HTML
                        </option>
HTML;                        
                    }
                    else {
                        $html_output .= <<<HTML
                            <input id='gradeable_teams_read' name='gradeable_teams_read' style='width=170px' value='{$admin_gradeable->getEgInheritTeamsFrom()}'/>
HTML;                            
                    }
                    $html_output .= <<<HTML
                     <div id="team_config">
                        <br />
                        What is the maximum team size? <input style="width: 50px" name="eg_max_team_size" class="int_val" type="text" value="{$admin_gradeable->getEgMaxTeamSize()}"/>
                        <br />
                        What is the <em style='color: orange;'><b>Team Lock Date</b></em>? (Instructors can still manually manage teams):
                        <input name="date_team_lock" id="date_team_lock" class="date_picker" type="text" value="{$admin_gradeable->getEgTeamLockDate()}"
                        style="cursor: auto; background-color: #FFF; width: 250px;">
                        <br />
                     </div>
                     </div>
                     <div class="team_assignment team_no" id="team_no"></div>
                </fieldset>      
                <br />
                
                Are students uploading files or submitting to a Version Control System (VCS) repository?<br />
                <fieldset>

                    <input type="radio" id="upload_file_radio" class="upload_file" name="upload_type" value="upload_file"
HTML;
                    if ($admin_gradeable->getEgIsRepository() === false) { $html_output .= ' checked="checked"'; }

                $html_output .= <<<HTML
                    > Upload File(s)

                    <input type="radio" id="repository_radio" class="upload_repo" name="upload_type" value="repository"
HTML;
                    if ($admin_gradeable->getEgIsRepository() === true) { $html_output .= ' checked="checked"'; }
                $html_output .= <<<HTML
                    > Version Control System (VCS) Repository
                      
                    <div class="upload_type upload_file" id="upload_file"></div>
                     
                    <div class="upload_type upload_repo" id="repository">
                        <br />
                        <b>Path for the Version Control System (VCS) repository:</b><br />
                        VCS base URL: <kbd>{$admin_gradeable->getVcsBaseUrl()}</kbd><br />
                        The VCS base URL is configured in Course Settings. If there is a base URL, you can define the rest of the path below. If there is no base URL because the entire path changes for each assignment, you can input the full path below. If the entire URL is decided by the student, you can leave this input blank.<br />
                        You are allowed to use the following string replacement variables in format $&#123;&hellip;&#125;<br />
                        <ul style="list-style-position: inside;">
                            <li>gradeable_id</li>
                            <li>user_id OR team_id OR repo_id (only use one)</li>
                        </ul>
                        ex. <kbd>/&#123;&#36;gradeable_id&#125;/&#123;&#36;user_id&#125;</kbd> or <kbd>https://github.com/test-course/&#123;&#36;gradeable_id&#125;/&#123;&#36;repo_id&#125;</kbd><br />
                        <input style='width: 83%' type='text' name='subdirectory' value="" placeholder="(Optional)"/><br />
                        VCS URL: <kbd id="vcs_url"></kbd>
                        <br />
                    </div>
                    
                </fieldset>
            </div>
            <br />
            <!-- When the form is completed and the "SAVE GRADEABLE" button is pushed
                If this is an electronic assignment:
                    Generate a new config/class.json
                    NOTE: similar to the current format with this new gradeable and all other electonic gradeables
                    Writes the inner contents for BUILD_csciXXXX.sh script
                    (probably can't do this due to security concerns) Run BUILD_csciXXXX.sh script
                If this is an edit of an existing AND there are existing grades this gradeable
                regenerates the grade reports. And possibly re-runs the generate grade summaries?
            -->
            <div>
                <class="modal-footer">
                    <button class="btn btn-primary" type="submit" style="margin-top: 10px; float: right;">{$button_string} Gradeable</button>
            </div>
        </div>
    </form>
</div>

<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css" />
<link type='text/css' rel='stylesheet' href="http://trentrichardson.com/examples/timepicker/jquery-ui-timepicker-addon.css" />
<script type="text/javascript" language="javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" language="javascript" src="js/jquery-ui.min.js"></script>
<script type="text/javascript" language="javascript" src="js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript">

    $(document).ready(function() {
        
        $('.gradeable_type_options').hide();
        
        if ($('input[name="gradeable_type"]').is(':checked')){
            $('input[name="gradeable_type"]').each(function(){
                if(!($(this).is(':checked')) && ({$edit})){
                    $(this).attr("disabled",true);
                }
            });
        }

        if ($('input[name="team_assignment"]').is(':checked')){
            $('input[name="team_assignment"]').each(function(){
                if(!($(this).is(':checked')) && ({$edit})){
                    $(this).attr("disabled",true);
                }
            });
        }

        $( "input" ).change(function() {
           var max = parseFloat($(this).attr('max'));
           var skip1 = (isNaN(max)) ? true : false;
           var min = parseFloat($(this).attr('min'));
           var skip2 = (isNaN(min)) ? true : false;
           if (!skip1 && $(this).val() > max)
           {
              $(this).val(max);
           }
           else if (!skip2 && $(this).val() < min)
           {
              $(this).val(min);
           }       
         }); 
          

        if($('gradeable_teams').val() !== '') {
            $('team_config').hide();
        }
        $('[name="gradeable_teams"]').change(
        function(){
            if(this.value === '') {
                $('#team_config').show();
            }
            else {
                $('#team_config').hide();
            }
        });

        $('input:radio[name="upload_type"]').change(function() {
            if ($(this).is(':checked')) {
                if ($(this).val() == 'repository') {
                    $('#repository').show();
                } else {
                    $('#repository').hide();
                }
            }
        });

        $('input:radio[name="pdf_page"]').change(function() {
            $("input[name^='page_component']").each(function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
            $('.pdf_page_input').hide();
            $('#pdf_page').hide();
            if ($(this).is(':checked')) {
                if ($(this).val() == 'true') {
                    $("input[name^='page_component']").each(function() {
                        if (this.value < 1) {
                            this.value = 1;
                        }
                    });
                    $('.pdf_page_input').show();
                    $('#pdf_page').show();
                }
            }
        });
        
        $('[name="gradeable_template"]').change(
        function(){
            var arrayUrlParts = [];
            arrayUrlParts["component"] = ["admin"];
            arrayUrlParts["page"] = ["admin_gradeable"];
            arrayUrlParts["action"] = ["upload_new_template"];
            arrayUrlParts["template_id"] = [this.value];

            var new_url = buildUrl(arrayUrlParts);
            window.location.href = new_url;
        });
        
        
        $('#repository').hide();
        if($('#radio_electronic_file').is(':checked')){                         
            if($('#repository_radio').is(':checked')){
                $('#repository').show();
            }
            
            $('#electronic_file').show();

            if($('#team_yes_radio').is(':checked')){
                $('input[name="eg_max_team_size"]').val('{$admin_gradeable->getEgMaxTeamSize()}');
                $('input[name="date_team_lock"]').val('{$admin_gradeable->getEgTeamLockDate()}');
                $('#team_yes').show();
            }
            else {
                $('#team_yes').hide();
            }
        }
        else if ($('#radio_checkpoints').is(':checked')){
            var components = {$admin_gradeable->getOldComponentsJson()};
            // remove the default checkpoint
            removeCheckpoint(); 
            $.each(components, function(i,elem){
                var extra_credit = false;
                if (elem.gc_max_value == 0) extra_credit = true;
                addCheckpoint(elem.gc_title, extra_credit);
            });
            $('#checkpoints').show();
            $('#grading_questions').show();
        }
        else if ($('#radio_numeric').is(':checked')){ 
            var components = {$admin_gradeable->getOldComponentsJson()};
            $.each(components, function(i,elem){
                if(i < {$admin_gradeable->getNumNumeric()}){
                    var extra_credit = false;
                    if (elem.gc_upper_clamp > elem.gc_max_value){
                        addNumeric(elem.gc_title,elem.gc_upper_clamp,true);
                    }
                    else{
                        addNumeric(elem.gc_title,elem.gc_max_value,false);
                    }
                }
                else{
                    addText(elem.gc_title);
                }
            });
            $('#numeric_num-items').val({$admin_gradeable->getNumNumeric()});
            $('#numeric_num_text_items').val({$admin_gradeable->getNumText()});
            $('#numeric').show();
            $('#grading_questions').show();
        }
        if({$edit}){
            $('input[name="gradeable_id"]').attr('readonly', true)
            .attr('style', function() { return $(this).attr('style') + '; background-color: #999999' });
        }

        $('input:radio[name="team_assignment"]').change(
    function(){
        if($('#team_yes_radio').is(':checked')){
            $('input[name="eg_max_team_size"]').val('{$admin_gradeable->getEgMaxTeamSize()}');
            $('input[name="date_team_lock"]').val('{$admin_gradeable->getEgTeamLockDate()}');
            $('#team_yes').show();
            if($('#peer_yes_radio').is(':checked')) {
                $('#peer_yes_radio').prop('checked', false);
                $('#peer_no_radio').prop('checked', true);
                $('input:radio[name="peer_grading"]').trigger("change");
            }
        }
        else {
            $('#team_yes').hide();
        }
    });

         $('input:radio[name="gradeable_type"]').change(
    function(){
        $('#required_type').hide();
        $('.gradeable_type_options').hide();
        if ($(this).is(':checked')){ 
            if($(this).val() == 'Electronic File'){ 
                $('#electronic_file').show();

                if($('#team_yes_radio').is(':checked')){
                    $('input[name="eg_max_team_size"]').val('{$admin_gradeable->getEgMaxTeamSize()}');
                    $('input[name="date_team_lock"]').val('{$admin_gradeable->getEgTeamLockDate()}');
                    $('#team_yes').show();
                }
                else {
                    $('#team_yes').hide();
                }
            }
            else if ($(this).val() == 'Checkpoints'){ 
                $('#checkpoints').show();
            }
            else if ($(this).val() == 'Numeric'){ 
                $('#numeric').show();
            }
        }
    });

    });

    $('#gradeable-form').on('submit', function(e){
            $('<input />').attr('type', 'hidden')
                .attr('name', 'gradeableJSON')
                .attr('value', JSON.stringify($('form').serializeObject()))
                .appendTo('#gradeable-form');
    });


    var vcs_base_url = "{$admin_gradeable->getVcsBaseUrl()}";
    function setVcsUrl(subdirectory) {
        if (subdirectory.indexOf('://') > -1 || subdirectory[0] == '/') {
            $('#vcs_url').text(subdirectory);
        }
        else {
            $('#vcs_url').text(vcs_base_url.replace(/[\/]+$/g, '') + '/' + subdirectory);
        }
    }
    
    $(function () {
        $('input[name="subdirectory"]').on('change paste keyup', function() {
            setVcsUrl(this.value);
        });
        setVcsUrl($('input[name="subdirectory"]').val());
        $("#alert-message").dialog({
            modal: true,
            autoOpen: false,
            buttons: {
                Ok: function () {
                     $(this).dialog("close");
                 }
             }
         });
    });

    function checkForm() {
        var gradeable_id = $('#gradeable_id').val();
        var gradeable_title = $('gradeable_title_id').val();
        var all_gradeable_ids = $js_gradeables_array;

        var vcs_url = $('#vcs_url').text();
        var subdirectory = $('input[name="subdirectory"]').val();

        var check1 = $('#radio_electronic_file').is(':checked');
        var check2 = $('#radio_checkpoints').is(':checked');
        var check3 = $('#radio_numeric').is(':checked');
        var checkRegister = $('#registration-section').is(':checked');
        var checkRotate = $('#rotating-section').is(':checked');


        // Gradeable Id Checks
        var has_space = gradeable_id.includes(" ");
        var regex_test = /^[a-zA-Z0-9_-]*$/.test(gradeable_id);
        if (!regex_test || has_space || gradeable_id == "" || gradeable_id === null) {
            $( "#alert-message" ).dialog( "open" );
            return false;
        }
        if (!($edit)) {
            var x;
            for (x = 0; x < all_gradeable_ids.length; x++) {
                if (all_gradeable_ids[x] === gradeable_id) {
                    alert("Gradeable already exists");
                    return false;
                }
            }
        }
        
        
        if($('#team_yes_radio').is(':checked')) {
            if ($("input[name^='eg_max_team_size']").val() < 2) {
                alert("Maximum team size must be at least 2");
                return false;
            }
        }
        if(check1) {
            if ($('input:radio[name="upload_type"]:checked').attr('value') === 'repository') {
                var subdirectory_parts = subdirectory.split("{");
                var x=0;
                // if this is a vcs path extension, make sure it starts with '/'
                console.log(vcs_url);
                if (vcs_url.indexOf('://') === -1 && vcs_url[0] !== "/") {
                    alert("VCS path needs to either be a URL or start with a /");
                    return false;
                }
                // check that path is made up of valid variables
                var allowed_variables = ["\$gradeable_id", "\$user_id", "\$team_id", "\$repo_id"];
                var used_id = false;
                for (var x = 1; x < subdirectory_parts.length; x++) {
                    subdirectory_part = subdirectory_parts[x].substring(0, subdirectory_parts[x].lastIndexOf("}"));
                    if (allowed_variables.indexOf(subdirectory_part) === -1) {
                        alert("For the VCS path, '" + subdirectory_part + "' is not a valid variable name.")
                        return false;
                    }
                    if (!used_id && ((subdirectory_part === "\$user_id") || (subdirectory_part === "\$team_id") || (subdirectory_part === "\$repo_id")))  {
                        used_id = true;
                        continue;
                    }
                    if (used_id && ((subdirectory_part === "\$user_id") || (subdirectory_part === "\$team_id") || (subdirectory_part === "\$repo_id"))) {
                        alert("You can only use one of \$user_id, \$team_id and \$repo_id in VCS path");
                        return false;
                    }
                }
                
            }
        }
        if(!check1 && !check2 && !check3) {
            alert("A type of gradeable must be selected");
            return false;
        }
    }
    </script>
HTML;
    $html_output .= <<<HTML
<div id="alert-message" title="WARNING">
  <p>Gradeable ID must not be blank and only contain characters <strong> a-z A-Z 0-9 _ - </strong> </p>
</div>
HTML;

	return $html_output;
	}



}
