<?php

/*
 * Variables from index.php:
$student_rcs
$rubric_id
 */

use \app\models\Rubric;

$rubric = new Rubric($student_rcs, $rubric_id);

$now = new DateTime('now');
$homeworkDate = new DateTime($rubric->rubric_details['rubric_due_date']);
if ($rubric->rubric_details['rubric_late_days'] > 0) {
    $homeworkDate->add(new DateInterval("PT{$rubric->rubric_details['rubric_late_days']}H"));
}
$grade_select_extra = $now < $homeworkDate ? 'disabled="true"' : "";

$part_status = array();
$icon = array();
$icon_color = array();
$color = array();

if ($rubric->status == 1) {
    $icon[0] = '<i class="icon-ok icon-white"></i>';
    $icon_color[0] = "#008000";
}
else {
    $icon[0] = '<i class="icon-remove icon-white"></i>';
    $icon_color[0] = "#DA4F49";
}

for ($i = 1; $i <= $rubric->rubric_parts; $i++) {
    $color[$i] = "#008000";
    $icon_color[$i] = "#008000";
    $part_status[$i] = 'Good';
    $icon[$i] = '<i class="icon-ok icon-white"></i>';
    if($rubric->parts_status[$i] == 0) {
        $color[$i] = "#DA4F49";
        $icon_color[$i] = "#DA4F49";
        $part_status[$i] = 'Bad';
        $icon[$i] = '<i class="icon-remove icon-white"></i>';
    } else if($rubric->parts_status[$i] == 2) {
        $color[$i] = "#998100";
        $icon_color[$i] = "#FAA732";
        $part_status[$i] = 'Late';
        $icon[$i] = '<i class="icon-exclamation-sign icon-white"></i>';
    }
}

$calculate_diff = __CALCULATE_DIFF__;
if ($calculate_diff) {
    $output = <<<HTML

<script>
    function openFile(file) {
        window.open("{$BASE_URL}/account/iframe/file-display.php?course={$_GET['course']}&filename=" + file + "&add_submission_path=1","_blank","toolbar=no,scrollbars=yes, resizable=yes, width=700, height=600");
        return false;
    }
    function openFrame(file, part, num) {
        var iframe = $('.file_viewer_' + part + '_' + num);
        if (!iframe.hasClass('open')) {
            iframe.html("<iframe src='{$BASE_URL}/account/iframe/file-display.php?course={$_GET['course']}&filename=" + file + "' height='500px' width='750px' style='border: 0'></iframe>");
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

    function autoResize(id) {
        var newheight;
        var newwidth;

        if(document.getElementById) {
            newheight=document.getElementById(id).contentWindow.document .body.scrollHeight;
            newwidth=document.getElementById(id).contentWindow.document .body.scrollWidth;
        }

        document.getElementById(id).height= (newheight) + "px";
        document.getElementById(id).width= (newwidth) + "px";
    }

    function calculatePercentageTotal() {
        var total=0;

        $('#rubric-table').find('select.grades').each(function() {
            total += parseFloat($(this).val());
/*             $(this).next('.accordian-body').collapse('show'); */
        });

        $("#score_total").html(total);
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
</script>

HTML;

}

$code_number = 0;

$output .= <<<HTML

<span id="left" class="resbox">
    <div id="content">
HTML;

$source_number = 0;
for($part = 1; $part <= $rubric->rubric_parts; $part++) {

    if ($rubric->rubric_details['rubric_parts_sep']) {
        $show_part = "Part: {$part}<br />";
    }
    else {
        $show_part = "";
    }

    //$output .= $rubric->submission_details[$part];
    if (!isset($rubric->submission_details[$part])) {
        $output .= <<<HTML
        <div id="inner-container">
            <div id="inner-container-spacer"></div>
            {$show_part}
            <div id="inner-container-spacer"></div>
            <div class="tabbable">
                <ul id="myTab" class="nav nav-tabs">
                    <li style="margin-right:2px; height:34px; width:20px; text-align:center; line-height:16px; padding-top:3px; -webkit-border-radius: 4px 4px 0 0; -moz-border-radius: 4px 4px 0 0; border-radius: 4px 4px 0 0; background-color: #DA4F49;">
                        <i class="icon-remove icon-white"></i>
                    </li>
                    <li class='active'><a href="#output-{$part}-1" data-toggle="tab">
                        <b style="color:#DA4F49;">No Submission</b>
                    </a></li>
                </ul>
            </div>
        </div>
HTML;
        continue;
    }

    $results_details = $rubric->results_details[$part];

    $submitted_details = $rubric->submission_details[$part];

    $output .= <<<HTML
        <div id="inner-container">
            <div id="inner-container-spacer"></div>
            <br />
            Submitted: {$results_details['submission_time']}<br />
            {$show_part}
            Submission Number: {$rubric->active_assignment[$part]} / {$rubric->max_assignment[$part]}
            <div id="inner-container-spacer"></div>
            <div class="tabbable">
                <ul id="myTab" class="nav nav-tabs">
                    <li style="margin-right:2px; height:34px; width:20px; text-align:center; line-height:16px; padding-top:3px; -webkit-border-radius: 4px 4px 0 0; -moz-border-radius: 4px 4px 0 0; border-radius: 4px 4px 0 0; background-color: {$icon_color[$part]};">
                        {$icon[$part]}
                    </li>
HTML;

    $i = 0;
    $active = true;
    foreach ($results_details['testcases'] as $k => $testcase) {
        $active_text  = ($active == true) ? 'active' : '';
        $j = $i + 1;
        $pa = $testcase['points_awarded'];
        $pt = $rubric->config_details[$part]['testcases'][$k]['points'];
        $output .= <<<HTML

                    <li class="{$active_text}" >
                        <span id="tab-{$part}-{$i}" class="diff"></span>
                        <a href="#output-{$part}-{$i}" data-toggle="tab">Output Test {$j} [{$pa}/{$pt}]</a>
                    </li>
HTML;
        $i++;
        $active = false;
    }

    $output .= <<<HTML

                </ul>
                <div class="tab-content" style="width: 100%; overflow-x: hidden;">
HTML;

    $i = 0;
    $active = true;
    foreach ($results_details['testcases'] as $testcase) {
        $active_text = ($active) ? 'active' : '';
        $url = $BASE_URL."/account/iframe/test-pane.php?course={$_GET['course']}&testcases=".urlencode(json_encode($testcase))."&directory=".urlencode($results_details['directory']);

        $output .= <<<HTML

                    <div class="tab-pane {$active_text}" id="output-{$part}-{$i}">
                        <div style="width:95%; margin: auto auto auto auto; overflow-y:auto; overflow-x:hidden; padding-top:20px;">
                            <iframe src="{$url}" id='iframe-{$part}-{$i}' width='750px' style='border: 0' onLoad="autoResize('iframe-{$part}-{$i}'); load_tab_icon('tab-{$part}-{$i}', 'iframe-{$part}-{$i}', {$testcase['points_awarded']}, {$rubric->config_details[$part]['testcases'][$k]['points']}); ">
                            </iframe>
                            <br />
                            Logfile
                            <textarea id="code{$source_number}">
HTML;
        $output .= htmlentities(file_get_contents($results_details['directory']."/".$testcase['execute_logfile']));
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


    $output .= <<<HTML

                </div>
            </div>
            <div style='margin-top: 20px' class="tabbable">
HTML;
    $j = 0;
    foreach ($rubric->rubric_files[$part] as $file) {
        $file = htmlentities($file);
        $output .= <<<HTML
                <div>
                    <div class="file-viewer"><a onclick='openFrame("{$file}", {$part}, {$j})'><span class='icon-plus'></span>{$file}</a></div> <a onclick='openFile("{$file}")'>(Popout)</a><br />
                    <div class="file_viewer_{$part}_{$j}"></div>
                </div>
HTML;
        $j++;
    }
    $output .= <<<HTML
            </div>
        </div>
HTML;
    $active = false;
}
$output .= <<<HTML
    </div>
</span>

<span id="pane"></span>

<span id="panemover" onmousedown="dragStart(event, 'left', 'right'); return false;" onmousemove="drag(event, 'left', 'right');" onmouseout="dragRelease();" onmouseup="dragRelease();"></span>

<span id="right" class="resbox">
    <div id="rubric" style="overflow-y:scroll;">
        <div id="inner-container">
            <div id="rubric-title">
                <div class="span2" style="float:left; text-align: left;"><b>{$rubric->rubric_details['rubric_name']}</b></div>
                <div class="span2" style="float:right; text-align: right; margin-top: -20px;"><b>{$rubric->student['student_last_name']}, {$rubric->student['student_first_name']}<br/>RCS: {$rubric->student['student_rcs']}</b></div>
            </div>
HTML;

$submitted = ($rubric->submitted) ? "1" : "0";
$individual = intval(isset($_GET["individual"]));
if (isset($_COOKIE['auto'])) {
    $cookie_auto = (intval($_COOKIE["auto"]) == 1 ? "checked" : "");
}
else {
    $cookie_auto = "";
}

$active_assignments = implode(",",$rubric->active_assignment);
$grade_parts_status = implode(",", $rubric->parts_status);
$grade_parts_submitted = implode(",", $rubric->parts_submitted);
$grade_parts_days_late = implode(",", $rubric->parts_days_late);

$output .= <<<HTML
            <form action="{$BASE_URL}/account/submit/account-rubric.php?course={$_GET['course']}&hw={$rubric->rubric_details['rubric_id']}&student={$rubric->student['student_rcs']}&individual={$individual}" method="post">
                <input type="hidden" name="submitted" value="{$submitted}" />
                <input type="hidden" name="status" value="{$rubric->status}" />
                <input type="hidden" name="late" value="{$rubric->days_late}" />
                <input type="hidden" name="active_assignment" value="{$active_assignments}" />
                <input type="hidden" name="grade_parts_days_late" value="{$grade_parts_days_late}" />
                <input type="hidden" name="grade_parts_submitted" value="{$grade_parts_submitted}" />
                <input type="hidden" name="grade_parts_status" value="{$grade_parts_status}" />
                <div id="inner-container-seperator" style="background-color:#AAA; margin-top: 0; margin-bottom:0;"></div>

                <div style="margin-top: 0; margin-bottom:35px;">
                    <input type="checkbox" style="margin-top:0; margin-right:5px;" id="rubric-autoscroll-checkbox" {$cookie_auto} /><span style="font-size:11px;">Rubric Auto Scroll</span>
                </div>
HTML;

if ($rubric->rubric_details['rubric_late_days'] >= 0) {
    $output .= <<<HTML
                <span style="color: black">Homework allows {$rubric->rubric_details['rubric_late_days']} late day(s).</span><br />
HTML;
}

if ($rubric->student['student_allowed_lates'] >= 0) {
    $output .= <<<HTML
                <span style="color: black">Student has used {$rubric->student['used_late_days']}/{$rubric->student['student_allowed_lates']} late day(s) this semester.</span><br />
HTML;
}

$output .= <<<HTML
                Late Days Used on Assignment:&nbsp;{$rubric->days_late}<br />
HTML;
if ($rubric->rubric_details['rubric_parts_sep']) {
    for($i = 1; $i <= $rubric->rubric_parts; $i++) {
        $output .= <<<HTML
                <span style="margin-left: 50px"">Late Days for Part {$i}: {$rubric->parts_days_late[$i]}</span><br />
HTML;
    }
}
if ($rubric->late_days_exception > 0) {
    $output .= <<<HTML
                <span style="color: green">Student has an exception of {$rubric->late_days_exception} late day(s).</span><br />
HTML;
    $output .= <<<HTML
                <b>Late Days Used:</b>&nbsp;{$rubric->days_late_used}<br />
HTML;
}

if($rubric->status == 0 && $rubric->submitted == 1) {
    $output .= <<<HTML
                <b style="color:#DA4F49;">Too many total late days used for this assignment</b><br />
HTML;
}

$print_status = ($rubric->status == 1) ? "Good" : "Bad";
$output .= <<<HTML
                <b>Status: </b>$print_status<br />
HTML;
if ($rubric->rubric_details['rubric_parts_sep']) {
    for ($i = 1; $i <= $rubric->rubric_parts; $i++) {
        $output .= <<<HTML
                <span style="margin-left: 50px;">Part {$i} Status: <span style="color: {$color[$i]};">{$part_status[$i]}</span></span>
HTML;
    }
}

$output .= <<<HTML
                <br/><br/>
                <table class="table table-bordered table-striped" id="rubric-table">
                    <thead>
HTML;
if(isset($_GET["individual"])) {
    $output .= <<<HTML
                        <tr style="background-color:#EEE;">
                            <th style="padding-left: 1px; padding-right: 0px; border-bottom:5px #FAA732 solid;"><i class="icon-time" id="progress-icon" style="margin-top: 2px;"></th>
                            <th style="width:40px; border-bottom:5px #FAA732 solid;">Part</th>
                            <th style="border-bottom:5px #FAA732 solid;">Question</th>
                            <th style="width:40px; border-bottom:5px #FAA732 solid;">Points</th>
                            <th style="width:40px; border-bottom:5px #FAA732 solid;">Total</th>
                        </tr>
HTML;
}
else {
    $output .= <<<HTML
                        <tr style="background-color:#EEE;">
                            <th style="padding-left: 1px; padding-right: 0px;"><i class="icon-time" id="progress-icon" style="margin-top: 2px;"></th>
                            <th style="width:40px;">Part</th>
                            <th>Question</th>
                            <th style="width:40px;">Points</th>
                            <th style="width:40px;">Total</th>
                        </tr>
HTML;
}

$output .= <<<HTML
                    </thead>
                    <tbody>
HTML;

$c = 1;
$last_seen_part = -1;
foreach ($rubric->questions as $question) {
    $output .= <<<HTML

                        <tr class="accordion-toggle" data-toggle="collapse" data-target="#rubric-{$c}">
HTML;
    if ($last_seen_part != $question['question_part_number']) {
        $output .= '<td class="lates" rowspan="' . $rubric->questions_count[$question['question_part_number']] * 2 . '" style="padding:8px 0px; width: 1px; line-height:16px; padding-left:1px;background-color: '.$icon_color[$question['question_part_number']].';">'.$icon[$question['question_part_number']].'</td>';
        $output .= '<td rowspan="' . $rubric->questions_count[$question['question_part_number']] * 2 . '">' . $question['question_part_number'] . '</td>';
        $last_seen_part = $question['question_part_number'];
    }

    $message = htmlentities($question["question_message"]);
    $note = htmlentities($question["question_grading_note"]);
    if ($note != "") {
        $note = "<br/><br/><div style='margin-bottom:5px; color:#777;'><i><b>Note: </b>" . $note . "</i></div>";
    }
    $output .= <<<HTML
                            <td style="font-size: 12px">
                                {$message} {$note}
                            </td>
                            <td>
                                <select name="grade-{$question['question_part_number']}-{$question['question_number']}" id="changer" class="grades" style="width: 65px; height: 25px; min-width:0px;" onchange="calculatePercentageTotal();" {$grade_select_extra}>
HTML;

    for ($i = 0; $i <= $question['question_total'] * 2; $i++) {
        $output .= '<option' . (($i * 0.5) == $question['grade_question_score'] ? " selected" : "") . '>' . round(($i * 0.5), 1) . '</option>';
    }

    $comment = ($question['grade_question_comment'] != "") ? "in" : "";
    $output .= <<<HTML
                                </select>
                            </td>
                            <td><strong>{$question['question_total']}</strong></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="padding:0; border-top:none;">
                                <div class="accordian-body collapse {$comment}" id="rubric-{$c}">
                                    <textarea name="comment-{$question["question_part_number"]}-{$question["question_number"]}" rows="2" style="width:100%; padding:0px; resize:none; margin:0px 0px; border-radius:0px; border:none; padding:5px; border-left:3px #DDD solid; float:left; margin-right:-28px;" placeholder="Message for the student..." comment-position="0">{$question['grade_question_comment']}</textarea>
HTML;

    $comment = clean_string_javascript($question['grade_question_comment']);
    $comments = \lib\DatabaseUtils::fromPGToPHPArray($question['comments']);
    array_walk($comments, "clean_string_javascript");
    unset($comments[$comment]);
    if (count($comments) > 0 || ($comment != "" && count($comments) > 1)) {
        $output .= <<<HTML
                                    <div>
                                        <a class="btn" name="comment-{$question['question_part_number']}-{$question["question_number"]}-up" style="border-radius: 0px; padding:0px;" onclick="updateCommentBox_{$question["question_part_number"]}_{$question["question_number"]}(-1);" disabled="true"><i class="icon-chevron-up" style="height:20px; width:13px;"></i></a>
                                        <br/>
                                        <a class="btn" name="comment-{$question["question_part_number"]}-{$question["question_number"]}-down" style="border-radius: 0px; padding:0px;" onclick="updateCommentBox_{$question["question_part_number"]}_{$question["question_number"]}(1);"><i class="icon-chevron-down" style="height:20px; width:13px;"></i></a>
                                    </div>
                                    <script type="text/javascript">
                                        function updateCommentBox_{$question["question_part_number"]}_{$question["question_number"]}(delta)
                                        {
                                            var pastComments = [];
                                            pastComments[0] = "{$comment}";

HTML;
        $i = 1;
        foreach($comments as $comment)
        {
            $output .= 'pastComments[' . $i++ . '] = "' . $comment . '";';
            $output .= "\n";
        }
        $output .= <<<JS

                                            var new_position = parseInt($('[name=comment-{$question["question_part_number"]}-{$question["question_number"]}]').attr("comment-position"));
                                            new_position += delta;

                                            if(new_position >= pastComments.length - 1)
                                            {
                                                new_position = pastComments.length - 1;
                                                $('a[name=comment-{$question["question_part_number"]}-{$question["question_number"]}-down]').attr("disabled", "true");
                                            }
                                            else
                                            {
                                                $('a[name=comment-{$question["question_part_number"]}-{$question["question_number"]}-down]').removeAttr("disabled");
                                            }

                                            if(new_position <= 0)
                                            {
                                                new_position = 0;
                                                $('a[name=comment-{$question["question_part_number"]}-{$question["question_number"]}-up]').attr("disabled", "true");
                                            }
                                            else
                                            {
                                                $('a[name=comment-{$question["question_part_number"]}-{$question["question_number"]}-up]').removeAttr("disabled");
                                            }

                                            var textarea = $('textarea[name=comment-{$question["question_part_number"]}-{$question["question_number"]}]');
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
                            <td style="background-color: #EEE; border-top:5px #FAA732 solid;"></td>
                            <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top:5px #FAA732 solid;"></td>
                            <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top:5px #FAA732 solid;"><strong>CURRENT GRADE</strong></td>
                            <td style="background-color: #EEE; border-top:5px #FAA732 solid;"><strong id="score_total">0</strong></td>
                            <td style="background-color: #EEE; border-top:5px #FAA732 solid;"><strong>{$rubric->rubric_details['rubric_total']}</strong></td>
                        </tr>
HTML;
}
else {
    $output .= <<<HTML
                        <tr>
                            <td style="background-color: #EEE; border-top: 1px solid #CCC;"></td>
                            <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top: 1px solid #CCC;"></td>
                            <td style="background-color: #EEE; border-left: 1px solid #EEE; border-top: 1px solid #CCC;"><strong>CURRENT GRADE</strong></td>
                            <td style="background-color: #EEE; border-top: 1px solid #CCC;"><strong id="score_total">0</strong></td>
                            <td style="background-color: #EEE; border-top: 1px solid #CCC;"><strong>{$rubric->rubric_details['rubric_total']}</strong></td>
                        </tr>
HTML;
}

$output .= <<<HTML
                    </tbody>
                </table>
                <div style="width:100%;"><b>General Comment:</b></div>
                <textarea name="comment-general" rows="5" style="width:98%; padding:5px; resize:none;" placeholder="Overall message for student about the homework...">{$rubric->rubric_details['grade_comment']}</textarea>
                <div style="width:100%; height:40px;"></div>
HTML;
if (isset($rubric->rubric_details['user_email'])) {
    $output .= "Graded By: {$rubric->rubric_details['user_email']}<br />Overwrite Grader: <input type='checkbox' name='overwrite' /><br /><br />";
}

if (!($now < $homeworkDate)) {
    if((!isset($_GET["individual"])) || (isset($_GET["individual"]) && !$student_individual_graded)) {
        $output .= <<<HTML
        <input class="btn btn-large btn-primary" type="submit" value="Submit Homework Grade"/>
        <div id="inner-container-spacer" style="height:75px;"></div>
HTML;
    } else {
        $output .= <<<HTML
        <input class="btn btn-large btn-warning" type="submit" value="Submit Homework Re-Grade" onclick="createCookie('backup',1,1000);"/>
        <div style="width:100%; text-align:right; color:#777;">{$rubric->rubric_details['grade_finish_timestamp']}</div>
        <div id="inner-container-spacer" style="height:55px;"></div>
HTML;
    }
}
else {
    $output .= <<<HTML
        <input class="btn btn-large btn-primary" type="button" value="Cannot Submit Homework Grade" />
        <div style="width:100%; text-align:right; color:#777;">This homework has yet been opened for grading.</div>
        <div id="inner-container-spacer" style="height:55px;"></div>
HTML;
}

$output .= <<<HTML
            </form>
        </div>
    </div>
</span>
HTML;

$output .= <<<HTML
<script>
calculatePercentageTotal();
</script>
HTML;

print $output;