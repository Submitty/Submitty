<?php

/*
 * Variables from index.php:
$student_rcs
$rubric_id
 */

use \app\models\Rubric;

$calculate_diff = __CALCULATE_DIFF__;
if ($calculate_diff) {
    $output = <<<HTML

<script>
    function autoResize(id){
        var newheight;
        var newwidth;
    
        if(document.getElementById){
            newheight=document.getElementById(id).contentWindow.document .body.scrollHeight;
            newwidth=document.getElementById(id).contentWindow.document .body.scrollWidth;
        }
    
        document.getElementById(id).height= (newheight) + "px";
        document.getElementById(id).width= (newwidth) + "px";
    }
    
    calculatePercentageTotal();
		
    function calculatePercentageTotal() 
    {
        var total=0;
        
        $('#rubric-table select.grades').each(function()
        {
            total += parseFloat($(this).val());
/* 		    $(this).next('.accordian-body').collapse('show'); */
        });
    
        $("#score_total").html(total);
    }
		
    function updateLates()
    {
        var late_number = parseInt($("select#late-select").val());
        
        $('.lates').each(function()
        {
            if(late_number == 0)
            {
                var late_color = "green";
                var late_icon = '<i class="icon-ok icon-white">';
            }
            else if(late_number == 1)
            {														
                var late_color = "#FAA732";
                var late_icon = '<i class="icon-exclamation-sign icon-white"><br/>';
            }
            else if(late_number == 2)
            {
                var late_color = "#FAA732";
                var late_icon = '<i class="icon-exclamation-sign icon-white"><br/><i class="icon-exclamation-sign icon-white">';
            }
            else
            {
                var late_color = "#DA4F49";
                var late_icon = '<i class="icon-remove icon-white">';
                
                $('#rubric-table select.grades').each(function()
                {
                    $(this).val(0);
                });
            }
            
            $(this).css("background-color", late_color);
            $(this).html(late_icon);
        });
        
        calculatePercentageTotal()
    }
</script>

HTML;

}
$code_number = 0;

$rubric = new Rubric($student_rcs, $rubric_id);

$output .= <<<HTML

<span id="left" class="resbox">
HTML;

$source_number = 0;
foreach ($rubric->results_details as $part => $results_details) {
    $submitted_details = $rubric->submitted_details[$part];
    
    $output .= <<<HTML

    <div id="content">
        <div id="inner-container">
	        <div id="inner-container-spacer"></div>
            Part: {$part}<br />
            Submitted: {$results_details['submission_time']}<br />
            Submission Number: {$results_details['submission_number']}
            <div id="inner-container-spacer"></div>
            <div class="tabbable">
                <ul id="myTab" class="nav nav-tabs">
                    <li style="margin-right:2px; height:34px; width:20px; text-align:center; line-height:16px; padding-top:3px; -webkit-border-radius: 4px 4px 0 0; -moz-border-radius: 4px 4px 0 0; border-radius: 4px 4px 0 0; background-color: green;">
                        <i class="icon-ok icon-white"></i>
                    </li>
HTML;
    
    $i = 0;
    $active = true;
    foreach ($results_details['testcases'] as $testcase) {
        $active_text  = ($active == true) ? 'active' : '';
        $css = 'check-full'; // cross
        $text = 'Y'; // 'N'
        $j = $i + 1;
        $pa = $results_details['points_awarded'];
        $pt = 0;
        $output .= <<<HTML

                    <li class="{$active_text}" >
                        <span class="diff {$css}">{$text}</span>
                        <a href="#output-{$part}-{$i}" data-toggle="tab">Output Test {$j} [{$pa}/{$pt}]</a>
                    </li>
HTML;
        $i++;
        $active = false;
    }
    
    $c = 0;
    foreach (array_merge($rubric->submitted_files[$part], $rubric->results_files[$part]) as $file) {
        $output .= <<<HTML

                    <li>
                        <a href="#source-{$part}-{$c}" data-toggle="tab" style="background-color:#F6F6F6;">{$file}</a>
                    </li>
HTML;
        $c++;
    }
    
    $output .= <<<HTML

                </ul>
                <div class="tab-content" style="width: 100%; overflow-x: hidden;">
HTML;
    
    $i = 0;
    $active = true;
    foreach ($results_details['testcases'] as $testcase) {
        $active_text = ($active) ? 'active' : '';
        $url = $BASE_URL."/account/ajax/account-new-rubric.php?course={$_GET['course']}&testcases=".urlencode(json_encode($testcase))."&directory=".urlencode($results_details['directory']);
        $output .= <<<HTML

                    <div class="tab-pane {$active_text}" id="output-{$part}-{$i}">
                        <div style="width:95%; margin: auto auto auto auto; overflow-y:auto; overflow-x:hidden; padding-top:20px;">
                            <iframe src="{$url}" id='iframe-{$part}-{$i}' width='750px' style='border: 0' onLoad="autoResize('iframe-{$part}-{$i}');">
                            </iframe>
                            <br />
                            Logfile
                            <textarea id="code{$source_number}">
HTML;
        $output .= file_get_contents($results_details['directory']."/".$testcase['execute_logfile']);
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
    
    $c = 0;
    foreach ($rubric->submitted_files[$part] as $file) {
        $output .= <<<HTML

                    <div class="tab-pane" id="source-{$part}-{$c}">
                        <div style="width:95%; margin: auto auto auto auto; overflow-y:auto; overflow-x:hidden; padding-top:20px;">
                            <textarea id="code{$source_number}">
HTML;
        $output .= file_get_contents($submitted_details['directory']."/".$file);
        $output .= <<<HTML
                            </textarea>
HTML;
        $output .= sourceSettingsJS($file, $source_number);
        $output .= <<<HTML
                        </div>
                    </div>
HTML;
        $c++;
        $source_number++;
    }
    
    foreach ($rubric->results_files[$part] as $file) {
        $output .= <<<HTML

                    <div class="tab-pane" id="source-{$part}-{$c}">
                        <div style="width:95%; margin: auto auto auto auto; overflow-y:auto; overflow-x:hidden; padding-top:20px;">
                             <textarea id="code{$source_number}">
HTML;
        $output .= file_get_contents($results_details['directory']."/".$file);
        $output .= <<<HTML
                            </textarea>
HTML;
        
        $output .= sourceSettingsJS($file, $source_number);
        $output .= <<<HTML
                        </div>
                    </div>
HTML;
        $source_number++;
        $c++;
    }
    
    $output .= <<<HTML

                </div>
            </div>
        </div>
    </div>
HTML;
    $active = false;
}

$output .= <<<HTML
</span>

<span id="pane"></span>

<span id="panemover" onmousedown="dragStart(event, 'left', 'right'); return false;" onmousemove="drag(event, 'left', 'right');" onmouseout="dragRelease();" onmouseup="dragRelease();"></span>

<span id="right" class="resbox">
    <div id="rubric" style="overflow-y:scroll;">
		<div id="inner-container">
            <div id="rubric-title">
				<div class="span2" style="float:left; text-align: left;"><b>Homework {$rubric->rubric_details['rubric_number']}</b></div>
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

$output .= <<<HTML
			<form action="{$BASE_URL}/account/submit/account-rubric.php?course={$_GET['course']}&hw={$rubric->rubric_details['rubric_number']}&student={$rubric->student['student_rcs']}&individual={$individual}" method="post">
			    <input type="hidden" name="submitted" value="{$submitted}" />
			    <input type="hidden" name="status" value="{$rubric->status}" />
			    <input type="hidden" name="late" value="{$rubric->late_days}" />
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
				Late Days Used on Assignment:&nbsp;{$rubric->late_days}<br />
HTML;

if ($rubric->late_days_exception > 0) {
    $output .= <<<HTML
                <span style="color: green">Student has an exception of {$rubric->late_days_exception} late day(s).</span><br />                  
HTML;
}

$output .= <<<HTML
				<b>Late Days Used:</b>&nbsp;{$rubric->used_late_days}<br />
HTML;


if($rubric->late_days_max_surpassed) {
    print <<<HTML
                <b style="color:#DA4F49;">Too many total late days used for this assignment</b><br />
HTML;
}

$print_status = ($rubric->status == 1) ? "Good" : "Bad";
$output .= <<<HTML
                <b>Status: </b>$print_status
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
        $output .= '<td class="lates" rowspan="' . $rubric->questions_count[$question['question_part_number']] * 2 . '" style="padding:8px 0px; width: 1px; line-height:16px; padding-left:1px;background-color: green;"><i class="icon-ok icon-white"></i></td>';
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
                                <select name="grade-{$question['question_part_number']}-{$question['question_number']}" id="changer" class="grades" style="width: 65px; height: 25px; min-width:0px;" onchange="calculatePercentageTotal();">
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
                            <td colspan="3" style="padding:0px; border-top:none;">
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

                                            $('textarea[name=comment-{$question["question_part_number"]}-{$question["question_number"]}]').attr("comment-position", new_position);
                                            $('textarea[name=comment-{$question["question_part_number"]}-{$question["question_number"]}]').html(pastComments[new_position]);
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

if((!isset($_GET["individual"])) || (isset($_GET["individual"]) && !$student_individual_graded))  {
    $output .= <<<HTML
    <input class="btn btn-large btn-primary" type="submit" value="Submit Homework Grade"/>
    <div id="inner-container-spacer" style="height:75px;"></div>
HTML;
}
else {
    $output .= <<<HTML
    <input class="btn btn-large btn-warning" type="submit" value="Submit Homework Re-Grade" onclick="createCookie('backup',1,1000);"/>
    <div style="width:100%; text-align:right; color:#777;">{$rubric->rubric_details['grade_finish_timestamp']}</div>
    <div id="inner-container-spacer" style="height:55px;"></div>
HTML;
}

$output .= <<<HTML
            </form>
        </div>
    </div>
</span>
HTML;

print $output;
?>