<?php

/*
 * Variables from index.php:
    $s_user_id
    $g_id
*/
require "../models/LateDaysCalculation.php";
use \models\ElectronicGradeable;

$output = "";

$eg = new ElectronicGradeable($s_user_id, $g_id);
$now = new DateTime('now');

$eg_due_date = new DateTime($eg->eg_details['eg_submission_due_date']);

if ($eg->eg_details['eg_late_days'] > 0) {
    $eg_due_date->add(new DateInterval("PT{$eg->eg_details['eg_late_days']}H"));
}
$grade_select_extra = $now < $eg_due_date ? 'disabled="true"' : "";

//not sure if correct
$color = "#998100";

$calculate_diff = __CALCULATE_DIFF__;
if ($calculate_diff) {
    $output .= <<<HTML

<script>
    function openFile(url_file) {
        window.open("{$BASE_URL}/account/iframe/file-display.php?course={$_GET['course']}&semester={$_GET['semester']}&filename=" + url_file + "&add_submission_path=1","_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
        return false;
    }

    function openFrame(url_file, part, num) {
        var iframe = $('#file_viewer_' + part + '_' + num);
        if (!iframe.hasClass('open')) {
            var iframeId = "file_viewer_" + part + "_" + num + "_iframe";
            // handle pdf
            if(url_file.substring(url_file.length - 3) == "pdf") {
                iframe.html("<iframe id='" + iframeId + "' src='{$BASE_URL}/account/iframe/file-display.php?course={$_GET['course']}&semester={$_GET['semester']}&filename=" + url_file
                            + "' width='750px' height='600px' style='border: 0'></iframe>");
            }
            else {
                iframe.html("<iframe id='" + iframeId + "' onload='resizeFrame(\"" + iframeId + "\");' src='{$BASE_URL}/account/iframe/file-display.php?course={$_GET['course']}&semester={$_GET['semester']}&filename=" 
                            + url_file + "' width='750px' style='border: 0'></iframe>");
            }
            iframe.addClass('open');
        }

        if (!iframe.hasClass('shown')) {
            iframe.show();
            iframe.addClass('shown');
            $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('icon-plus').addClass('icon-minus');
        }
        else {
            iframe.hide();
            iframe.removeClass('shown');
            $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('icon-minus').addClass('icon-plus');
        }
        return false;
    }

    function resizeFrame(id) {
        var height = parseInt($("iframe#" + id).contents().find("body").css('height').slice(0,-2));
        if (height > 500) {
            document.getElementById(id).height= "500px";
        }
        else {
            document.getElementById(id).height = (height+18) + "px";
        }
    }

    function openDiv(id) {
        var elem = $('#' + id);
        //console.log(typeof(elem));
        if (elem.hasClass('open')) {
            elem.hide();
            elem.removeClass('open');
            $('#' + id + '-span').removeClass('icon-folder-open').addClass('icon-folder-closed');
        }
        else {
            elem.show();
            elem.addClass('open');
            $('#' + id + '-span').removeClass('icon-folder-closed').addClass('icon-folder-open');
        }
        return false;
    }

    function autoResize(id) {
        var newheight;
        if(document.getElementById) {
            newheight = document.getElementById(id).contentWindow.document.body.scrollHeight;
        }

        if (newheight < 10) {
            newheight = document.getElementById(id).contentWindow.document.body.offsetHeight;
        }
        
        if(newheight < 10){
            newheight = 600;
        }
        document.getElementById(id).height= (newheight) + "px";
    }

    function calculatePercentageTotal() {
        var total=0;

        $('#rubric-table').find('.grades').each(function() {
            if(!isNaN(parseFloat($(this).val()))) {
                total += parseFloat($(this).val());
            }
        });
        
        total = Math.max(parseFloat(total + {$eg->autograding_points}),0);

        $("#score_total").html(total+" / "+parseFloat({$eg->autograding_max}+{$eg->eg_details['eg_total']}) + "&emsp;&emsp;&emsp;"+
                            " AUTO-GRADING: " + {$eg->autograding_points} + "/" + {$eg->autograding_max});
    }

    function load_tab_icon(tab_id, iframe_id, points_user, points_total) {
        var is_difference = $('#'+iframe_id).contents().find("input[name='exists_difference']").val() == "1";
        tab_id = '#' + tab_id;
        if (points_total > 0) {
            if (points_total == points_user) {
                if (!is_difference) {
                    $(tab_id).addClass('check-full');
                }
                else {
                    $(tab_id).addClass('check-partial');
                }
            }
            else {
                $(tab_id).addClass('cross');
            }
        }
        else {
            if (is_difference) {
                $(tab_id).addClass('cross');
            }
            else {
                $(tab_id).addClass('check-full');
            }
        }

    }
    // delta in this function is the incremental step of points, currently hardcoded to 0.5pts
    function validateInput(id, question_total, delta){
        var ele = $('#' + id);
        if(isNaN(parseFloat(ele.val())) || ele.val() == ""){
            ele.val("");
            return;
        }
        if(ele.val() < 0 && parseFloat(question_total) > 0) {
            ele.val( 0 );
        }
        if(ele.val() > 0 && parseFloat(question_total) < 0) {
            ele.val( 0 );
        }
        if(ele.val() < parseFloat(question_total) && parseFloat(question_total) < 0) {
            ele.val(question_total);
        }
        if(ele.val() > parseFloat(question_total) && parseFloat(question_total) > 0) {
            ele.val(question_total);
        }
        if(ele.val() % delta != 0) {
            ele.val( Math.round(ele.val() / delta) * delta );
        }
    }
    // autoresize the comment box
    function autoResizeComment(e){
        e.target.style.height ="";
        e.target.style.height = e.target.scrollHeight + "px";
    }

    // expand all files in Submissiona and Results section
    function openAll() {
        // click on all with the class openAllDiv that hasn't been expanded yet
        $(".openAllDiv").each(function() {
            if ($(this).parent().find('span').hasClass('icon-folder-closed')) {
                $(this).click();
            }
        });

        // click on all with the class openAllFile that hasn't been expanded yet
        $(".openAllFile").each(function() {
            if($(this).find('span').hasClass('icon-plus')) {
                $(this.click());
            }
        });
    }

    // close all files in Submission and results section
    function closeAll() {
        // click on all with the class openAllFile that is expanded
        $(".openAllFile").each(function() {
            if($(this).find('span').hasClass('icon-minus')) {
                $(this.click());
            }
        });

        // click on all with the class openAllDiv that is expanded
        $(".openAllDiv").each(function() {
            if ($(this).parent().find('span').hasClass('icon-folder-open')) {
                $(this).click();
            }
        });
    }
</script>

HTML;

}

$code_number = 0;
$output .= <<<HTML


    <div id="left" class="draggable rubric_panel" style="left:5px;top:50px; height:55%;width:60%;">
<span class="grading_label">Auto-Grading Testcases</span>
HTML;

$source_number = 0;

$j = 0;
function display_files($file, &$output, $part, $indent = 1) {
    global $j;
    $margin_left = 15;
    $neg_margin_left = -15 * ($indent);
    if (is_array($file)) {
        foreach($file as $k => $v) {
            if (!is_integer($k)) {
                $id = str_replace("/", "_", $k);
                $indent += 1;
                $output .= <<<HTML
<div>
    <span id='{$id}-span' class='icon-folder-closed'></span><a class='openAllDiv' onclick='openDiv("{$id}");'>{$k}</a>
    <div id='{$id}' style='margin-left: {$margin_left}px; display: none'>
HTML;
            }
            display_files($v, $output, $part, $indent);
            if (!is_integer($k)) {
                $indent -= 1;
                $output .= <<<HTML
    </div>\n
</div>
HTML;
            }
        }
    }
    else {
      // TODO: urlencode necessary to handle '#' in a filename
      //       htmlentities probably not necessary (and could be harmful)
      //       may want to strip url parameters too
      $html_file = htmlentities($file);
      $url_file = urlencode(htmlentities($file));
      $output .= <<<HTML
    <div>
        <div class="file-viewer"><a class='openAllFile' onclick='openFrame("{$url_file}", {$part}, {$j})'>
            <span class='icon-plus'></span>{$html_file}</a>

        </div> <a onclick='openFile("{$url_file}")'>(Popout)</a><br />

        <div id="file_viewer_{$part}_{$j}" style='margin-left: {$neg_margin_left}px'></div>
    </div>
HTML;
        $j++;
    }
}

///////////////////////////////////

    //$output .= $eg->submission_details;
    if (!isset($eg->submission_details)) {
        $output .= <<<HTML
        <div id="inner-container">
            <div class="tabbable" style="padding-top: 10px;">
                <ul id="myTab" class="nav nav-tabs">
                    <li style="margin-right:2px; height:34px; width:20px; text-align:center; line-height:16px; padding-top:3px;">
                        <i class="icon-remove icon-white"></i>
                    </li>
                    <li class='active'><a href="#output-1" data-toggle="tab">
HTML;
        if ($eg->active_assignment == 0) {
            $output .= '<b style="color:#DA4F49;">Cancelled</b>';
        }
        else {
            $output .= '<b style="color:#DA4F49;">No Submission</b>';
        }

        $output .= <<<HTML
                    </a></li>
                </ul>
            </div>
        </div>
HTML;
    }

    $results_details = $eg->results_details;

    $submitted_details = $eg->submission_details;
    
    $submission_time = isset($results_details['submission_time']) ?  $results_details['submission_time'] : '';

    $output .= <<<HTML
        <div id="inner-container">
            <br />
HTML;
    if ($eg->active_assignment == 0){
        $output .= <<<HTML
            No submission <br />
HTML;
    }
    else{
        $output .= <<<HTML
            Submitted: {$submission_time}<br />
            Submission Number: {$eg->active_assignment} / {$eg->max_assignment}
HTML;
    }
    $output .= <<<HTML
            <div class="tabbable" style="padding-top:10px;">
                <ul id="myTab" class="nav nav-tabs">
HTML;

    $i = 0;
    $active = true;
    if(isset($results_details['testcases'])){
        foreach ($results_details['testcases'] as $k => $testcase) {
            $active_text  = ($active == true) ? 'active' : '';
            $j = $i + 1;
            $pa = $testcase['points_awarded'];
            $pt = $eg->config_details['testcases'][$k]['points'];
            $output .= <<<HTML
                        <li class="{$active_text}" >
                            <span id="tab-{$i}" class="diff"></span>
                            <a href="#output-{$i}" data-toggle="tab">Output Test {$j} [{$pa}/{$pt}]</a>
                        </li>
HTML;
            $i++;
            $active = false;
        }
    }
    $output .= <<<HTML

                </ul>
                <div class="tab-content" style="width: 100%; overflow-x: hidden; border:thin solid black">
HTML;

    $i = 0;
    $active = true;
    if(isset($results_details['testcases'])){
        foreach ($results_details['testcases'] as $k => $testcase) {
            $active_text = ($active) ? 'active' : '';
            $url = $BASE_URL."/account/iframe/test-pane.php?course={$_GET['course']}&semester={$_GET['semester']}&testcases=".urlencode(json_encode($testcase))."&directory=".urlencode($results_details['directory']);

            $output .= <<<HTML
                        <div class="tab-pane {$active_text}" id="output-{$i}">
                            <div style="width:95%; margin: auto auto auto auto; overflow-y:auto; overflow-x:hidden; padding-top:20px;">                                                                                               
                                <iframe src="{$url}" id='iframe-{$i}' width='750px' style='border: 0' onload="autoResize('iframe-{$i}'); load_tab_icon('tab-{$i}', 'iframe-{$i}', {$testcase['points_awarded']}, {$eg->config_details['testcases'][$k]['points']}); ">
                                </iframe>
                            </div>
                        </div>
HTML;
            $i++;
            $active = false;
            $source_number++;
        }
    }
    $output .= <<<HTML

                </div>
            </div>
            <div style='margin-top: 20px' class="tabbable">
HTML;
    $j = 0;
    $output .= <<<HTML
        </div>
    </div>
HTML;

    $active = false;


///////////////////////////////////////////////////

    $output .= <<<HTML
    </div>

<div id="right" class="draggable rubric_panel" style="top:65%; left: 5px;width: 60%; height: 30%">
<span class="grading_label">Submission and Results Browser</span>
<button onclick="openAll()">Expand All</button>
<button onclick="closeAll()">Close All</button>
HTML;
    $output .= "\n";
    display_files($eg->eg_files, $output, 1);

    $firstname = getDisplayName($eg->student);

    $output .= <<<HTML
</div>


<div id="stats" class="draggable rubric_panel" style="bottom: 0px; right:20px; width:35%; height: 25%;">
<span class="grading_label">Student Information</span>
    <div id="inner-container" style="margin:5px;">
        <div id="rubric-title">
            <div class="span2" style="float:left; text-align: left;"><b>{$eg->eg_details['g_title']}</b></div>
            <div class="span2" style="float:right; text-align: right; margin-top: -20px;"><b>

	        {$firstname} {$eg->student['user_lastname']}
HTML;

   $output .= <<<HTML
            <br/>
            ID: {$eg->student['user_id']}</b></div>
        </div>
HTML;
$submitted = ($eg->submitted) ? "1" : "0";
$individual = intval(isset($_GET["individual"]));
if (isset($_COOKIE['auto'])) {
    $cookie_auto = (intval($_COOKIE["auto"]) == 1 ? "checked" : "");
}
else {
    $cookie_auto = "";
}

$active_assignments = $eg->active_assignment;

$late_charged = 0;

$output .= <<<HTML
            <form id="rubric_form" action="{$BASE_URL}/account/submit/account-rubric.php?course={$_GET['course']}&semester={$_GET['semester']}&g_id={$eg->eg_details['g_id']}&student={$eg->student['user_id']}&individual={$individual}" method="post">
                <input type="hidden" name="csrf_token" value="{$_SESSION['csrf']}" />
                <input type="hidden" name="submitted" value="{$submitted}" />
                <input type="hidden" name="status" value="{$eg->status}" />
                <input type="hidden" name="is_graded" value="{$student_individual_graded}" />
                <input type="hidden" name="late" value="{$late_charged}" />
                <input type="hidden" name="active_assignment" value="{$active_assignments}" />
                <div style="margin-top: 0; margin-bottom:35px;">
                    <input type="checkbox" style="margin-top:0; margin-right:5px;" id="rubric-autoscroll-checkbox" {$cookie_auto} /><span style="font-size:11px;">Rubric Auto Scroll</span>
                </div>
HTML;

//Begin late day calculation////////////////////////////////////////////////////////////////////////////////////////////
$due_string = $eg_due_date->format('Y-m-d H:i:s');
$ldu = new LateDaysCalculation();
$ld_table = $ldu->generate_table_for_user_date($s_user_id, $eg_due_date);
$output .= $ld_table;
$gradeable= $ldu->get_gradeable($s_user_id, $eg->g_id);
$status = $gradeable['status'];
$late_charged = $gradeable['late_days_charged'];
//End late day calculation//////////////////////////////////////////////////////////////////////////////////////////////

if($status != "Good" && $status != "Late"){
    $color = "red";
    $output .= <<<HTML
                <script>
                    $('body').css('background-color', 'red');
                    $("#rubric_form").submit(function(event){
                       var confirm = window.confirm("This submission has a bad status. Are you sure you want to submit a grade for it?");
                       if(!confirm){
                           event.preventDefault();
                       }
                    });
                </script>
HTML;
}

$output .= <<<HTML
                <b>Status:</b> <span style="color: {$color};">{$status}</span><br />

    </div>
</div>
HTML;

//============================================================

$output .= <<<HTML
            <div id="rubric" class="draggable rubric_panel" style="top:50px; right:20px;width:35%; height: 65%;">
            <span class="grading_label">Grading Rubric</span>
            <div class="inner-container" style="margin:1px; height:100%">
                <table class="table table-bordered table-striped" id="rubric-table">
                    <thead>
HTML;
if(isset($_GET["individual"])) {
    $output .= <<<HTML
                        <!--<tr style="background-color:#EEE;">
                            <th style="padding-left: 1px; padding-right: 0px; border-bottom:5px #FAA732 solid;"><i class="icon-time" id="progress-icon" style="margin-top: 2px;"></th>
                            <th style="width:40px; border-bottom:5px #FAA732 solid;">Part</th>
                            <th style="border-bottom:5px #FAA732 solid;" colspan="2">Questions</th>
                        </tr> -->
HTML;
}
else {
    $output .= <<<HTML
                        <tr style="background-color:#EEE;">
                            <!--<th style="padding-left: 1px; padding-right: 0px;"><i class="icon-time" id="progress-icon" style="margin-top: 2px;"></th>
                            <th style="width:40px;">Part</th>
                            <th colspan="2">Questions</th>-->
                        </tr>
HTML;
}

$output .= <<<HTML
                    </thead>
                    <tbody>
HTML;

$c = 1;

$precision = floatval($eg->eg_details['eg_precision']);

foreach ($eg->questions as $question) {
    // hide auto-grading if it has no value
    if ($question['gc_max_value'] == 0){
        continue;
    }
    // FIXME add autograding extra credit 
    else if($question['gcd_score'] ==0 && substr($question['gc_title'], 0, 12) === "AUTO-GRADING"){
        $question['gcd_score'] = $eg->autograding_points;
    }
    
    $disabled = '';
    if(substr($question['gc_title'], 0, 12) === "AUTO-GRADING"){
        $disabled = 'disabled';
    }
    
    $output .= <<<HTML
                        <tr>
HTML;
    $penalty = !(intval($question['gc_max_value']) > 0);
    $message = htmlentities($question["gc_title"]);
    $note = htmlentities($question["gc_ta_comment"]);
    if ($note != "") {
        $note = "<br/><div style='margin-bottom:5px; color:#777;'><i><b>Note to TA: </b>" . $note . "</i></div>";
    }

    //adds an icon depending on the question type (extra credit, normal, penalty)
    //adds background color as well.
    if($question['gc_is_extra_credit'] == true) {
        $output .= <<<HTML
                            <td style="font-size: 12px; background-color: #D8F2D8;" colspan="4">
                                <i class="icon-plus"></i> <b>{$message}</b> {$note}
HTML;
    }
    else if($penalty) {
        $output .= <<<HTML
                            <td style="font-size: 12px; background-color: #FAD5D3;" colspan="4">
                                <i class="icon-minus"></i> <b>{$message}</b> {$note}
HTML;
    }
    else {
        $output .= <<<HTML
                            <td style="font-size: 12px;" colspan="4">
                                <b>{$message}</b> {$note}
HTML;
    }

    $student_note = htmlentities($question['gc_student_comment']);
    if ($student_note != ''){
        $student_note = "<div style='margin-bottom:5px; color:#777;'><i><b>Note to Student: </b>" . $student_note . "</i></div>";
        
    }
    $output .= <<<HTML
                                {$student_note}
                            </td>
                        </tr>
HTML;

    $comment = ($question['gcd_component_comment'] != "") ? "in" : "";
    
    $min_val = (intval($question['gc_max_value']) > 0) ? 0 : intval($question['gc_max_value']);
    $max_val = (intval($question['gc_max_value']) > 0) ? intval($question['gc_max_value']) : 0;
    if($question['gc_is_extra_credit'] == true) {
        $output .= <<<HTML
    <tr style="background-color: #f9f9f9;">
                            <td style="white-space:nowrap; vertical-align:middle; text-align:center; background-color: #D8F2D8;" colspan="1"><input type="number" id="grade-{$question['gc_order']}" class="grades" name="grade-{$question['gc_order']}" value="{$question['gcd_score']}"
                                min="{$min_val}" max="{$max_val}" step="{$precision}" placeholder="&plusmn;{$precision}" onchange="validateInput('grade-{$question["gc_order"]}', '{$question["gc_max_value"]}',  {$precision}); calculatePercentageTotal();" 
                                style="width:50px; resize:none;" {$disabled}></textarea><strong> / {$question['gc_max_value']}</strong></td>
                            <td style="width:98%; background-color: #D8F2D8;" colspan="3">
                                <div id="rubric-{$c}">
                                    <textarea name="comment-{$question["gc_order"]}" onkeyup="autoResizeComment(event);" rows="4" style="width:98%; height:100%; resize:none; float:left;" 
                                        placeholder="Message for the student..." comment-position="0" {$disabled}>{$question['gcd_component_comment']}</textarea>
HTML;
    }
    else if($penalty) {
        $output .= <<<HTML
    <tr style="background-color: #f9f9f9;">
                            <td style="white-space:nowrap; vertical-align:middle; text-align:center; background-color: #FAD5D3;" colspan="1"><input type="number" id="grade-{$question['gc_order']}" class="grades" name="grade-{$question['gc_order']}" value="{$question['gcd_score']}"
                                min="{$min_val}" max="{$max_val}" step="{$precision}" placeholder="&plusmn;{$precision}" onchange="validateInput('grade-{$question["gc_order"]}', '{$question["gc_max_value"]}',  {$precision}); calculatePercentageTotal();" 
                                style="width:50px; resize:none;" {$disabled}></textarea><strong> / {$question['gc_max_value']}</strong></td>
                            <td style="width:98%; background-color: #FAD5D3;" colspan="3">
                                <div id="rubric-{$c}">
                                    <textarea name="comment-{$question["gc_order"]}" onkeyup="autoResizeComment(event);" rows="4" style="width:98%; height:100%; resize:none; float:left;" 
                                        placeholder="Message for the student..." comment-position="0" {$disabled}>{$question['gcd_component_comment']}</textarea>
HTML;
    }
    else {
        $output .= <<<HTML
    <tr style="background-color: #f9f9f9;">
                            <td style="white-space:nowrap; vertical-align:middle; text-align:center;" colspan="1"><input type="number" id="grade-{$question['gc_order']}" class="grades" name="grade-{$question['gc_order']}" value="{$question['gcd_score']}"
                                min="{$min_val}" max="{$max_val}" step="{$precision}" placeholder="&plusmn;{$precision}" onchange="validateInput('grade-{$question["gc_order"]}', '{$question["gc_max_value"]}',  {$precision}); calculatePercentageTotal();" 
                                style="width:50px; resize:none;" {$disabled}></textarea><strong> / {$question['gc_max_value']}</strong></td>
                            <td style="width:98%;" colspan="3">
                                <div id="rubric-{$c}">
                                    <textarea name="comment-{$question["gc_order"]}" onkeyup="autoResizeComment(event);" rows="4" style="width:98%; height:100%; resize:none; float:left;" 
                                        placeholder="Message for the student..." comment-position="0" {$disabled}>{$question['gcd_component_comment']}</textarea>
HTML;
    }

    $comment = htmlspecialchars($question['gcd_component_comment']);
    if ($comment != "") {
        $output .= <<<HTML
                                    <div>
                                        <a class="btn" name="comment-{$question["gc_order"]}-up" style="border-radius: 0px; padding:0px;" onclick="updateCommentBox_{$question["gc_order"]}(-1);"
                                           disabled="true"><i class="icon-chevron-up" style="height:20px; width:13px;"></i></a>
                                        <br/>
                                        <a class="btn" name="comment-{$question["gc_order"]}-down" style="border-radius: 0px; padding:0px;" onclick="updateCommentBox_{$question["gc_order"]}(1);">
                                            <i class="icon-chevron-down" style="height:20px; width:13px;"></i>
                                        </a>
                                    </div>
                                    <script type="text/javascript">
                                        function updateCommentBox_{$question["gc_order"]}(delta)
                                        {
                                            var pastComments = [];
                                            pastComments[0] = "{$comment}";

HTML;
        $i = 1;
        $output .= <<<JS

                                            var new_position = parseInt($('[name=comment-{$question["gc_order"]}]').attr("comment-position"));
                                            new_position += delta;

                                            if(new_position >= pastComments.length - 1)
                                            {
                                                new_position = pastComments.length - 1;
                                                $('a[name=comment-{$question["gc_order"]}-down]').attr("disabled", "true");
                                            }
                                            else
                                            {
                                                $('a[name=comment-{$question["gc_order"]}-down]').removeAttr("disabled");
                                            }

                                            if(new_position <= 0)
                                            {
                                                new_position = 0;
                                                $('a[name=comment-{$question["gc_order"]}-up]').attr("disabled", "true");
                                            }
                                            else
                                            {
                                                $('a[name=comment-{$question["gc_order"]}-up]').removeAttr("disabled");
                                            }

                                            var textarea = $('textarea[name=comment-{$question["gc_order"]}]');
                                            textarea.attr("comment-position", new_position);
                                            textarea.html(pastComments[new_position]);
                                        }

JS;
        $output .= "                                    </script>";
    }
    $output .= <<<HTML

                                </div>
                            </td>
                        </tr>
HTML;
    $c++;
}

if(isset($_GET["individual"])) {
    $output .= <<<HTML
                        <tr>
                            <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top:5px #FAA732 solid;" colspan="1"><strong>TOTAL</strong></td>
                            <td style="background-color: #EEE; border-top:5px #FAA732 solid;" colspan="3"><strong id="score_total">0 / {$eg->eg_details['eg_total']} &emsp;&emsp;&emsp;
                            AUTO-GRADING {$eg->autograding_points} / {$eg->autograding_max}</strong></td>
                        </tr>
HTML;
}
else {
    $output .= <<<HTML
                        <tr>
                            <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top: 1px solid #CCC;" colspan="1"><strong>TOTAL</strong></td>
                            <td style="background-color: #EEE; border-top: 1px solid #CCC;" colspan="1"><strong id="score_total">0 / {$eg->eg_details['eg_total']}&emsp;&emsp;&emsp;
                            AUTO-GRADING {$eg->autograding_points} / {$eg->autograding_max}</strong></td>
                        </tr>
HTML;
}

$output .= <<<HTML
                    </tbody>
                </table>
                <div style="width:100%;"><b>General Comment:</b></div>
                <textarea name="comment-general" rows="5" style="width:98%; resize:none;" 
                          placeholder="Overall message for student about the gradeable...">{$eg->eg_details['gd_overall_comment']}</textarea>
HTML;
if (isset($eg->original_grader)) {
    $output .= <<<HTML
    <div style="width:100%; height:40px;">
        Graded By: {$eg->original_grader}<br />Overwrite Grader: <input type='checkbox' name='overwrite' value='1' /><br /><br />
    </div>
HTML;
}
else { //Adding this checkbox to simplify checking for grader overwrite.  It's hidden from view so that the first time someone grades, $_POST['overwrite'] is guarenteed to exist
	$output .= <<<HTML
	<input type='checkbox' class='hidden' name='overwrite' value='1' checked='checked' style='display:none;' /> 
HTML;
}
                
if (!($now < new DateTime($eg->eg_details['g_grade_start_date'])) && $eg->eg_details['eg_total'] > 0) {
    if((!isset($_GET["individual"])) || (isset($_GET["individual"]) && !$student_individual_graded)) {
        $output .= <<<HTML
        <input class="btn btn-large btn-primary" type="submit" value="Submit Homework Grade"/>
HTML;
    } else {
        $output .= <<<HTML
        <input class="btn btn-large btn-warning" type="submit" value="Submit Homework Re-Grade" onclick="createCookie('backup',1,1000);"/>
HTML;
    }
}
else {
    $output .= <<<HTML
        <input class="btn btn-large btn-primary" type="button" value="Cannot Submit Homework Grade" />
        <div style="width:100%; text-align:right; color:#777;">This homework has not been opened for grading.</div>
HTML;
}


$output .= <<<HTML
            </form>

    </div>
</div>
HTML;

$output .= <<<HTML
<script>
    calculatePercentageTotal();
</script>
HTML;

print $output;
