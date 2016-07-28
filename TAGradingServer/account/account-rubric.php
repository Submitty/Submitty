<?php

/*
 * Variables from index.php:
    $s_user_id
    $g_id
*/

use \app\models\ElectronicGradeable;

$eg = new ElectronicGradeable($s_user_id, $g_id);

$now = new DateTime('now');

$eg_due_date = new DateTime($eg->eg_details['eg_submission_due_date']);

if ($eg->eg_details['eg_late_days'] > 0) {
    $eg_due_date->add(new DateInterval("PT{$eg->eg_details['eg_late_days']}H"));
}
$grade_select_extra = $now < $eg_due_date ? 'disabled="true"' : "";

//not sure if correct
$color = "#998100";

if ($eg->status == 1 && $eg->days_late_used == 0) {
    $icon= '<i class="icon-ok icon-white"></i>';
    $icon_color = "#008000";
    $part_status = "Good";
}
else if ($eg->status == 1 && $eg->days_late_used > 0) {
    $icon= '<i class="icon-exclamation-sign icon-white"></i>';
    $color = "#998100";
    $icon_color = "#FAA732";
    $part_status = 'Late';
}
else {
    $icon = '<i class="icon-remove icon-white"></i>';
    $color  = "#DA4F49";
    $icon_color  = "#DA4F49";
    if ($eg->active_assignment == 0) {
        $part_status  = 'Cancelled';
    }
    else {
        $part_status  = 'Bad';
    }
}

$calculate_diff = __CALCULATE_DIFF__;
if ($calculate_diff) {
    $output = <<<HTML

<script>
    function openFile(file) {
        window.open("{$BASE_URL}/account/iframe/file-display.php?course={$_GET['course']}&filename=" + file + "&add_submission_path=1","_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
        return false;
    }

    function openFrame(file, part, num) {
        var iframe = $('#file_viewer_' + part + '_' + num);
        if (!iframe.hasClass('open')) {
            var iframeId = "file_viewer_" + part + "_" + num + "_iframe";
            // handle pdf
            if(file.substring(file.length - 3) == "pdf") {
                iframe.html("<iframe id='" + iframeId + "' src='{$BASE_URL}/account/iframe/file-display.php?course={$_GET['course']}&filename=" + file 
                            + "' width='750px' height='600px' style='border: 0'></iframe>");
            }
            else {
                iframe.html("<iframe id='" + iframeId + "' onload='resizeFrame(\"" + iframeId + "\");' src='{$BASE_URL}/account/iframe/file-display.php?course={$_GET['course']}&filename=" 
                            + file + "' width='750px' style='border: 0'></iframe>");
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
            newheight=document.getElementById(id).contentWindow.document.body.scrollHeight;
        }

        if (newheight < 10) {
            newheight = document.getElementById(id).contentWindow.document.body.offsetHeight;
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

        $("#score_total").html(total + " / {$eg->eg_details['eg_total']}");
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
        if(ele.val() < 0) {
            ele.val( 0 );
        }
        if(ele.val() > parseFloat(question_total)) {
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
</script>

HTML;

}

$code_number = 0;
$display_stats = ($show_stats == 0) ? "none" : "inline-block";
$display_rubric = ($show_rubric == 0) ? "none" : "inline-block";
$display_left = ($show_left == 0) ? "none" : "inline-block";
$display_right = ($show_right == 0) ? "none" : "inline-block";
$output .= <<<HTML

<span id="left" class="resbox" style="display: {$display_left};">
    <div id="content">
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
    <span id='{$id}-span' class='icon-folder-closed'></span><a onclick='openDiv("{$id}");'>{$k}</a>
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
        $file = htmlentities($file);
        $output .= <<<HTML
    <div>
        <div class="file-viewer"><a onclick='openFrame("{$file}", {$part}, {$j})'>
            <span class='icon-plus'></span>{$file}</a>
        </div> <a onclick='openFile("{$file}")'>(Popout)</a><br />
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
            <div id="inner-container-spacer"></div>
            <div id="inner-container-spacer"></div>

            <div class="tabbable">
                <ul id="myTab" class="nav nav-tabs">
                    <li style="margin-right:2px; height:34px; width:20px; text-align:center; line-height:16px; padding-top:3px; -webkit-border-radius: 4px 4px 0 0; -moz-border-radius: 4px 4px 0 0; border-radius: 4px 4px 0 0; background-color: #DA4F49;">
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
            <div id="inner-container-spacer"></div>
            <br />
            Submitted: {$submission_time}<br />
            Submission Number: {$eg->active_assignment} / {$eg->max_assignment}
            <div id="inner-container-spacer"></div>
            <div class="tabbable">
                <ul id="myTab" class="nav nav-tabs">
                    <li style="margin-right:2px; height:34px; width:20px; text-align:center; line-height:16px; padding-top:3px; -webkit-border-radius: 4px 4px 0 0; -moz-border-radius: 4px 4px 0 0; border-radius: 4px 4px 0 0; background-color: {$icon_color};">
                        {$icon}
                    </li>
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
                <div class="tab-content" style="width: 100%; overflow-x: hidden;">
HTML;

    $i = 0;
    $active = true;
    if(isset($results_details['testcases'])){
        foreach ($results_details['testcases'] as $k => $testcase) {
            $active_text = ($active) ? 'active' : '';
            $url = $BASE_URL."/account/iframe/test-pane.php?course={$_GET['course']}&testcases=".urlencode(json_encode($testcase))."&directory=".urlencode($results_details['directory']);

            $output .= <<<HTML
                        <div class="tab-pane {$active_text}" id="output-{$i}">
                            <div style="width:95%; margin: auto auto auto auto; overflow-y:auto; overflow-x:hidden; padding-top:20px;">                                                                                               
                                <iframe src="{$url}" id='iframe-{$i}' width='750px' style='border: 0' onload="autoResize('iframe-{$i}'); load_tab_icon('tab-{$i}', 'iframe-{$i}', {$testcase['points_awarded']}, {$eg->config_details['testcases'][$k]['points']}); ">
                                </iframe>
                                <br />
                                Logfile
                                <textarea id="code{$source_number}">
HTML;
            //$output .= htmlentities(file_get_contents($results_details['directory']."/".$testcase['execute_logfile']));
            $output .= <<<HTML
                                </textarea>
HTML;
            $output .= sourceSettingsJS($testcase['execute_logfile'], $source_number);
            $output .= <<<HTML
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
    $output .= "\n"."Old file viewer here.";
    $output .= <<<HTML
        </div>
    </div>
    <hr style="background-color: #000; height: 1px; margin-left: 25px; margin-right: 25px;"/>
HTML;

    $active = false;


///////////////////////////////////////////////////

    $output .= <<<HTML
    </div>
</span><!-- puts no space between spans

--><span id="pane"></span><!--
--><span id="panemover" onmousedown="dragStart(event, 'left', 'right'); return false;" onmousemove="drag(event, 'left', 'right');" onmouseout="dragRelease();" onmouseup="dragRelease();"></span><!--

--><span id="right" class="resbox" style="display: {$display_right}; overflow-y:auto;">
<div id="inner-container-spacer"></div><div id="inner-container" >
HTML;
    $output .= "\n";
    display_files($eg->eg_files, $output, 1);

    $output .= <<<HTML
</div>
</span><!---->

<span id="stats" class="resbox" style="display: {$display_stats}; z-index: 200;" onmousedown="changeStackingOrder(event); dragPanelStart(event, 'stats'); return false;" 
      onmousemove="dragPanel(event, 'stats');"  onmouseup="dragPanelEnd(event);">
    <div class="draggable" style="background-color: #99cccc; height:20px; cursor: move;" onmousedown="dragPanelStart(event, 'stats'); return false;" 
         onmousemove="dragPanel(event, 'stats');"  onmouseup="dragPanelEnd(event);">
    <span title='Hide Panel' class='icon-down' onmousedown="handleKeyPress('KeyS')" ></span>
    </div>
    <div id="inner-container" style="margin:5px;">
        <div id="rubric-title">
            <div class="span2" style="float:left; text-align: left;"><b>{$eg->eg_details['g_title']}</b></div>
            <div class="span2" style="float:right; text-align: right; margin-top: -20px;"><b>{$eg->student['user_lastname']}, 
                {$eg->student['user_firstname']}<br/>ID: {$eg->student['user_id']}</b></div>
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

$output .= <<<HTML
            <form action="{$BASE_URL}/account/submit/account-rubric.php?course={$_GET['course']}&g_id={$eg->eg_details['g_id']}&student={$eg->student['user_id']}&individual={$individual}" method="post">
                <input type="hidden" name="csrf_token" value="{$_SESSION['csrf']}" />
                <input type="hidden" name="submitted" value="{$submitted}" />
                <input type="hidden" name="status" value="{$eg->status}" />
                <input type="hidden" name="late" value="{$eg->days_late}" />
                <input type="hidden" name="active_assignment" value="{$active_assignments}" />
                <div style="margin-top: 0; margin-bottom:35px;">
                    <input type="checkbox" style="margin-top:0; margin-right:5px;" id="rubric-autoscroll-checkbox" {$cookie_auto} /><span style="font-size:11px;">Rubric Auto Scroll</span>
                </div>
HTML;

if ($eg->eg_details['eg_late_days'] >= 0) {
    $late_days = $eg->eg_details['eg_late_days'];
    $plural = (($late_days > 1) ? 's': '');
    $output .= <<<HTML
                <span style="color: black">Gradeable allows {$late_days} late day{$plural}.</span><br />
HTML;
}

if ($eg->student['student_allowed_lates'] >= 0) {
    $output .= <<<HTML
                <span style="color: black">Student has used {$eg->student['used_late_days']}/{$eg->student['student_allowed_lates']} late day(s) this semester.</span><br />
HTML;
}

$output .= <<<HTML
                Late Days Used on Assignment:&nbsp;{$eg->days_late}<br />
HTML;

if ($eg->late_days_exception > 0) {
    $output .= <<<HTML
                <span style="color: green">Student has an exception of {$eg->late_days_exception} late day(s).</span><br />
HTML;
    $output .= <<<HTML
                <b>Late Days Used:</b>&nbsp;{$eg->days_late_used}<br />
HTML;
}

if($eg->status == 0 && $eg->submitted == 1) {
    $output .= <<<HTML
                <b style="color:#DA4F49;">Too many total late days used for this assignment</b><br />
HTML;
}

$print_status = ($eg->status == 1) ? "Good" : "Bad";
$output .= <<<HTML
                <b>Status:</b> <span style="color: {$color};">{$part_status}</span><br />
    </div>
</span>

<span id="rubric" class="resbox" style="display: {$display_rubric}; z-index: 199; overflow-y=hidden;" onmousedown="changeStackingOrder(event); dragPanelStart(event, 'rubric');" onmousemove="dragPanel(event, 'rubric');" onmouseup="dragPanelEnd(event);">
    <div class="draggable" style="background-color: #99cccc; height:20px; cursor: move;"  >
        <span title='Hide Panel' class='icon-down' onmousedown="handleKeyPress('KeyG')" ></span>
    </div>
    <div class="inner-container" style="overflow-y:auto; margin:1px; height:100%">

HTML;


//============================================================

$output .= <<<HTML

                <table class="table table-bordered table-striped" id="rubric-table">
                    <thead>
HTML;
if(isset($_GET["individual"])) {
    $output .= <<<HTML
                        <tr style="background-color:#EEE;">
                            <th style="padding-left: 1px; padding-right: 0px; border-bottom:5px #FAA732 solid;"><i class="icon-time" id="progress-icon" style="margin-top: 2px;"></th>
                            <th style="width:40px; border-bottom:5px #FAA732 solid;">Part</th>
                            <th style="border-bottom:5px #FAA732 solid;" colspan="2">Questions</th>
                        </tr>
HTML;
}
else {
    $output .= <<<HTML
                        <tr style="background-color:#EEE;">
                            <th style="padding-left: 1px; padding-right: 0px;"><i class="icon-time" id="progress-icon" style="margin-top: 2px;"></th>
                            <th style="width:40px;">Part</th>
                            <th colspan="2">Questions</th>
                        </tr>
HTML;
}

$output .= <<<HTML
                    </thead>
                    <tbody>
HTML;

$c = 1;

foreach ($eg->questions as $question) {
    $output .= <<<HTML

                        <tr>
HTML;

    $message = htmlentities($question["gc_title"]);
    $note = htmlentities($question["gc_ta_comment"]);
    if ($note != "") {
        $note = "<br/><div style='margin-bottom:5px; color:#777;'><i><b>Note: </b>" . $note . "</i></div>";
    }
    $output .= <<<HTML
                            <td style="font-size: 12px" colspan="2">
                                {$message} {$note}
                            </td>
                        </tr>
HTML;

    $comment = ($question['gcd_component_comment'] != "") ? "in" : "";
    $output .= <<<HTML
    <tr style="background-color: #f9f9f9;">
                            <td style="white-space:nowrap; vertical-align:middle; text-align:center;"><input type="number" id="grade-{$question['gc_order']}" class="grades" name="grade-{$question['gc_order']}" value="{$question['gcd_score']}" min="0" max="{$question['gc_max_value']}" step="0.5" placeholder="&plusmn;0.5" onchange="validateInput('grade-{$question["gc_order"]}', '{$question["gc_max_value"]}',  0.5); calculatePercentageTotal();" style="width:50px; resize:none;"></textarea><strong> / {$question['gc_max_value']}</strong></td>
                            <td style="width:100%; padding:0px">
                                <div id="rubric-{$c}">
                                    <textarea name="comment-{$question["gc_order"]}" onkeyup="autoResizeComment(event);" rows="4" style="width:100%; height:100%; resize:none; margin:0px 0px; border-radius:0px; border:none; padding:5px; float:left; margin-right:-25px;" placeholder="Message for the student..." comment-position="0">{$question['gcd_component_comment']}</textarea>
HTML;

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
                            <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top:5px #FAA732 solid;"><strong>CURRENT GRADE</strong></td>
                            <td style="background-color: #EEE; border-top:5px #FAA732 solid;"><strong id="score_total">0 / {$eg->eg_details['eg_total']}</strong></td>
                        </tr>
HTML;
}
else {
    $output .= <<<HTML
                        <tr>
                            <td style="background-color: #EEE; border-top: 1px solid #CCC;"></td>
                            <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top: 1px solid #CCC;"></td>
                            <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top: 1px solid #CCC;"><strong>CURRENT GRADE</strong></td>
                            <td style="background-color: #EEE; border-top: 1px solid #CCC;"><strong id="score_total">0 / {$eg->eg_details['eg_total']}</strong></td>
                        </tr>
HTML;
}

$output .= <<<HTML
                    </tbody>
                </table>
                <div style="width:100%;"><b>General Comment:</b></div>
                <textarea name="comment-general" rows="5" style="width:98%; padding:5px; resize:none;" 
                          placeholder="Overall message for student about the gradeable...">{$eg->eg_details['gd_overall_comment']}</textarea>
HTML;
if (isset($eg->eg_details['user_email'])) {
    $output .= <<<HTML
    <div style="width:100%; height:40px;">
        Graded By: {$eg->eg_details['user_email']}<br />Overwrite Grader: <input type='checkbox' name='overwrite' value='1' /><br /><br />
    </div>
HTML;
}
             
if (!($now < new DateTime($eg->eg_details['g_grade_start_date']))) {
    if((!isset($_GET["individual"])) || (isset($_GET["individual"]) && !$student_individual_graded)) {
        $output .= <<<HTML
        <input class="btn btn-large btn-primary" type="submit" value="Submit Homework Grade"/>
HTML;
    } else {
        $output .= <<<HTML
        <input class="btn btn-large btn-warning" type="submit" value="Submit Homework Re-Grade" onclick="createCookie('backup',1,1000);"/>
HTML;
    }
    // TODO support  graded timestamp  <div style="width:100%; text-align:right; color:#777;">{$eg->eg_details['grade_finish_timestamp']}</div>

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
</span>
HTML;

$output .= <<<HTML
<script>
    calculatePercentageTotal();
</script>
HTML;

print $output;