<?php
use \lib\Database;
use \lib\Functions;

include "../header.php";

check_administrator();

if($user_is_administrator)
{
    $have_old = false;
    $has_grades = false;
    $old_rubric = array(
        'rubric_id' => -1,
        'rubric_number' => "",
        'rubric_due_date' => date('Y/m/d 23:59:59'),
        'rubric_code' => "",
        'rubric_parts_sep' => false,
        'rubric_late_days' => __DEFAULT_LATE_DAYS__
    );
    $old_questions = array();
    if (isset($_GET['action']) && $_GET['action'] == 'edit') {
        $rubric_id = intval($_GET['id']);
        Database::query("SELECT * FROM rubrics WHERE rubric_id=?",array($rubric_id));
        if (count(Database::rows()) == 0) {
            die("No rubric found");
        }
        $old_rubric = Database::row();
        Database::query("SELECT * FROM questions WHERE rubric_id=? ORDER BY question_part_number, question_number", array($old_rubric['rubric_id']));
        $questions = Database::rows();
        foreach ($questions as $question) {
            $question['question_total'] = floatval($question['question_total']);
            $old_questions[$question['question_part_number']][$question['question_number']] = $question;
        }
        $have_old = true;

        Database::query("SELECT COUNT(*) as cnt FROM grades WHERE rubric_id=?", array($rubric_id));
        $has_grades = Database::row()['cnt'] > 0;
    }

    $useAutograder = (__USE_AUTOGRADER__) ? "true" : "false";
    $account_subpages_unlock = true;

    function selectBox($part, $question, $grade = 0) {
        $retVal = "<select name='point-{$part}-{$question}' class='points' onchange='calculatePercentageTotal();'>";
        for($i = 0; $i <= 100; $i += 0.5) {
            $selected = ($grade == $i) ? "selected" : "";
            $retVal .= "<option {$selected}>{$i}</option>";
        }
        $retVal .= "</select>";

        return $retVal;
    }

    $rubrics = array();
    $db->query("SELECT rubric_id from rubrics ORDER BY rubric_id", array());
    foreach ($db->rows() as $row) {
        $rubrics[$row['rubric_id']] = intval($row['rubric_id']);
    }

    if (!$have_old) {
        $rubricNumberQuery = (count($rubrics) > 0) ? end($rubrics) + 1 : 1;
        $rubric_name = "Homework {$rubricNumberQuery}";
        $rubric_submission_id = "hw".Functions::pad($rubricNumberQuery);
        $rubric_parts_submission_id[1] = "_part1";
        $part_count = 1;
        $string = "Add";
        $action = strtolower($string);
    }
    else {
        $rubricNumberQuery = $old_rubric['rubric_id'];
        $rubric_name = $old_rubric['rubric_name'];
        $rubric_submission_id = $old_rubric['rubric_submission_id'];
        $rubric_parts_submission_id = array();
        $part_count = 0;
        foreach(explode(",", $old_rubric['rubric_parts_submission_id']) as $k => $v) {
            $part_count++;
            $rubric_parts_submission_id[$k + 1] = $v;
        }

        $string = "Edit";
        $action = strtolower($string);
    }

    $rubric_sep_checked = ($old_rubric['rubric_parts_sep'] == 1) ? "checked" : "";

    $extra = ($has_grades) ? "<span style='color: red;'>(Grading has started! Edit Questions At Own Peril!)</span>" : "";
    print <<<HTML

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
    }

    .question-icon {
        display: block;
        float: left;
        margin-top: 5px;
        margin-left: 5px;
        position: relative;
        width: 12px;
        height: 12px;
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
</style>

<div id="container-rubric">
    <form class="form-signin" action="{$BASE_URL}/account/submit/admin-rubric.php?action={$action}&id={$old_rubric['rubric_id']}" method="post" enctype="multipart/form-data">
        <input type='hidden' name="part_count" value="{$part_count}" />
        <input type='hidden' name="csrf_token" value="{$_SESSION['csrf']}" />

        <div class="modal-header">
            <h3 id="myModalLabel">{$string} Rubric {$extra}</h3>
        </div>

        <div class="modal-body" style="/*padding-bottom:80px;*/ overflow:hidden;">
            Rubric Name: <input style='width: 227px' type='text' name='rubric_name' value="{$rubric_name}" />

            <span style="padding-left:143px">
                Submission Server ID: <input style='width: 200px' type='text' name='rubric_submission_id' value="{$rubric_submission_id}" />
            </span>

            <br/>

            Due Date:
            <!--<fieldset>-->
                <input name="date_submit" class="datepicker" type="text"
                style="cursor: auto; background-color: #FFF; width: 250px;">
            <!--</fieldset>-->
            &emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;
            Separate Parts:
            <input type="checkbox" name="rubric_parts_sep" value="1" {$rubric_sep_checked} onchange='togglePartSubmissionId()'/>
            &emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;
            Late Days:
            <input style="width: 50px" name="rubric_late_days" type="text" value="{$old_rubric['rubric_late_days']}"/>
            <br/>

            <table class="table table-bordered" id="rubricTable" style=" border: 1px solid #AAA;">
                <thead style="background: #E1E1E1;">
                    <tr>
                        <th style="width:61px;">Part</th>
                        <th>Question</th>
                        <th style="width:100px;">Points</th>
                    </tr>
                </thead>

                <tbody style="background: #f9f9f9;">
HTML;

    if (count($old_questions) == 0) {
        if (__USE_AUTOGRADER__) {
            $old_questions[0][1] = array('question_message'      => "AUTO-GRADING",
                                         'question_grading_note' => "",
                                         'question_total'        => 0,
                                         'question_extra_credit' => 0);
            $old_questions[0][2] = array('question_message'      => "AUTO-GRADING EXTRA CREDIT",
                                         'question_grading_note' => "",
                                         'question_total'        => 0,
                                         'question_extra_credit' => 1);
        }
        $old_questions[1][1] = array('question_message'      => "",
                                     'question_grading_note' => "",
                                     'question_total'        => 0,
                                     'question_extra_credit' => 0);
    }

    foreach ($old_questions as $k => $v) {
        $count = count($old_questions[$k]) + (($k > 0) ? 1 : 0);

        $disabled = ($k == 0) ? "disabled" : "";
        $readonly = ($k == 0) ? "readonly" : "";

        $first = true;
        foreach ($v as $num => $question) {
            print <<<HTML
                <tr id="row-{$k}-{$num}">
HTML;
            if ($first) {
                print <<<HTML
                    <td rowspan="{$count}" id="part-{$k}" style="position:relative">
                        <span id='spanPart{$k}'>{$k}</span><br />
HTML;
                if ($k > 0) {
                    print <<<HTML
                        <a id="delete-{$k}" class="question-icon" onclick="deletePart({$k});"><img class="question-icon-cross" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
                        <!--<a id="down-{$k}" class="question-icon" onclick="movePartDown({$k});"><img class="question-icon-down" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
                        <a id="up-{$k}" class="question-icon" onclick="movePartUp({$k});"><img class="question-icon-up" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>-->
                        <br />
                        <div class='part_submission_id'>
                            <input style='width: 47px' type="text" name="rubric_part_{$k}_id" value="{$rubric_parts_submission_id[$k]}" />
                        </div>
HTML;
                }

                print <<<HTML
                    </td>
HTML;
                $first = false;
            }

            $display_ta = ($question['question_grading_note'] != "") ? 'block' : 'none';

            print <<<HTML
                    <td style="overflow: hidden;">
                        <textarea name="comment-{$k}-{$num}" rows="1" style="width: 885px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;" {$readonly}>{$question['question_message']}</textarea>
                        <div class="btn btn-mini btn-default" onclick="toggleTA({$k}, {$num})" style="margin-top:-5px;">TA Note</div>
                        <textarea name="ta-{$k}-{$num}" id="individual-{$k}-{$num}" rows="1" placeholder=" Message to TA" style="width: 940px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: 5px; display: {$display_ta};">{$question['question_grading_note']}</textarea>
                    </td>

                    <td style="background-color:#EEE;">
HTML;
            $old_grade = (isset($question['question_total'])) ? $question['question_total'] : 0;
            print selectBox($k, $num, $old_grade);
            $checked = ($question['question_extra_credit'] == 1) ? "checked" : "";
            print (($question['question_extra_credit'] == 1 && $disabled == "disabled") ? "<input type='hidden' name='ec-{$k}-{$num}'' value='on' />" : "");
            print <<<HTML

                        <input onclick='calculatePercentageTotal();' name="ec-{$k}-{$num}" type="checkbox" {$checked} {$disabled} />
HTML;
            if ($k != 0) {
                print <<<HTML
                        <br />
                        <a id="delete-{$k}-{$num}" class="question-icon" onclick="deleteQuestion({$k}, {$num});"><img class="question-icon-cross" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
                        <a id="down-{$k}-{$num}" class="question-icon" onclick="moveQuestionDown({$k}, {$num});"><img class="question-icon-down" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
                        <a id="up-{$k}-{$num}" class="question-icon" onclick="moveQuestionUp({$k}, {$num});"><img class="question-icon-up" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>
HTML;
            }
            print <<<HTML
                    </td>
                </tr>
HTML;
        }
        if ($k > 0) {
            print <<<HTML
                <tr id="add-{$k}">
                    <td style="overflow: hidden;">
                        <div class="btn btn-small btn-success"  onclick="addQuestion({$k})"><i class="icon-plus icon-white"></i> Question</div>
                    </td>

                    <td style="border-left: 1px solid #F9F9F9;"></td>
                </tr>
HTML;
        }
    }
    print <<<HTML
                <tr>
                    <td>
                        <div class="btn btn-small btn-success" onclick="addPart()"><i class="icon-plus icon-white"></i> Part</div>
                    </td>
                    <td style="border-left: 1px solid #F9F9F9;"></td>
                    <td style="border-left: 1px solid #F9F9F9;"></td>
                </tr>
HTML;
    print <<<HTML
                    <tr>
                        <td style="background-color: #EEE; border-top: 2px solid #CCC;"></td>
                        <td style="background-color: #EEE; border-top: 2px solid #CCC; border-left: 1px solid #EEE;"><strong>TOTAL POINTS</strong></td>
                        <td style="background-color: #EEE; border-top: 2px solid #CCC;"><strong id="totalCalculation"></strong></td>
                    </tr>
                </tbody>
            </table>
HTML;

    $db->query("SELECT s.user_id, u.user_rcs, u.user_email, s.rubric_id, s.grading_section_id
    FROM homework_grading_sections as s, users as u WHERE u.user_id = s.user_id
    ORDER BY rubric_id, grading_section_id", array());
    $sections = array();
    foreach ($db->rows() as $row) {
        if (!isset($sections[$row['rubric_id']][$row['user_rcs']])) {
            $sections[$row['rubric_id']][$row['user_rcs']] = array();
        }
        $sections[$row['rubric_id']][$row['user_rcs']][] = $row['grading_section_id'];
    }
    asort($sections);

    $db->query("SELECT student_grading_id, count(student_id) as cnt FROM students GROUP BY student_grading_id ORDER BY student_grading_id", array());
    $a = array();
    foreach ($db->rows() as $row) {
        $a[] = "{$row['student_grading_id']} ({$row['cnt']} students)";
    }
    $a = implode(", ", $a);

    print "Available Grading Sections: {$a}<br /><br />";

    $i = 0;
    $db->query("SELECT * FROM users ORDER BY user_rcs ASC", array());
    $users = $db->rows();
    foreach($users as $user) {
        $value =  isset($sections[$old_rubric['rubric_id']][$user['user_rcs']]) ? implode(",", $sections[$old_rubric['rubric_id']][$user['user_rcs']]) : -1;
        print <<<HTML
            <span style='display:inline-block; width:300px; padding-right: 5px'>{$user['user_lastname']},
                    {$user['user_firstname']}:</span>
            <input style='width: 30px; text-align: center' type='text' name='{$user['user_id']}-section'
                    value='{$value}' />
            <br />
HTML;
        $i++;
    }

    // TODO: Style this less dumb
    $margintop = ($i*-40) . "px";
    $marginright =  650-(count($rubrics)*25) . "px";
    print <<<HTML
            <div>

                <table border="1" style="float: right; margin-top:{$margintop}; margin-right: {$marginright}">
                    <tr>
                        <td>User</td>
HTML;
    foreach ($rubrics as $id => $number) {
        print <<<HTML
                        <td style="width: 20px; text-align: center">
                            {$number}
                        </td>
HTML;
    }

    print <<<HTML
                </tr>
HTML;

    foreach ($users as $user) {
        print <<<HTML
                    <tr>
                        <td>{$user['user_rcs']}</td>
HTML;

        foreach ($rubrics as $id => $rubric) {
            $number = (isset($sections[$id][$user['user_rcs']])) ? implode(",",$sections[$id][$user['user_rcs']]) : "";
            print <<<HTML
                        <td style="text-align: center">
                            {$number}
                        </td>
HTML;
        }
        print <<<HTML
                    </tr>
HTML;
    }


    print <<<HTML
                </table>
            </div>
        </div>

        <div class="modal-footer">
                <button class="btn btn-primary" type="submit" style="margin-top: 10px;">{$string} Rubric</button>
        </div>
    </form>
</div>

<script type="text/javascript">
    var datepicker = $('.datepicker');
    datepicker.datetimepicker({
        timeFormat: "HH:mm:ss",
	    showTimezone: false
    });

    datepicker.datetimepicker('setDate', (new Date("{$old_rubric['rubric_due_date']}")));

    function toggleTA(part, question) {
        if(document.getElementById("individual-" + part + "-" + question ).style.display == "block") {
            $("#individual-" + part + "-" + question ).animate({marginBottom:"-80px"});
            //document.getElementById("individual-" + part + "-" + question ).innerHTML = "";
            setTimeout(function(){document.getElementById("individual-" + part + "-" + question ).style.display = "none";}, 175);

        }
        else {
            $("#individual-" + part + "-" + question ).animate({marginBottom:"5px"});
            setTimeout(function(){document.getElementById("individual-" + part + "-" + question ).style.display = "block";}, 175);
        }

        calculatePercentageTotal();
    }
HTML;

    $parts = "[";
    for($i = 0; $i <= max(array_keys($old_questions)); $i++) {
        $parts .= (isset($old_questions[$i]) ? (count($old_questions[$i]) + (($i > 0) ? 1 : 0)) : 0).",";
    }
    $parts = rtrim($parts, ",");
    $parts .= "]";

    print <<<JS

    var parts = {$parts};

    function addPart() {
        parts.push(2);
        var partName = parts.length - 1;
        var partNameString = "" + partName;
        var table = document.getElementById("rubricTable");
        var row = table.insertRow(table.rows.length - 2);
        row.id = 'row-' + partName + '-1';
        var cell1 = row.insertCell(0);
        cell1.rowSpan = "2";
        cell1.setAttribute("id", "part-"+partName);
        var cell2 = row.insertCell(1);
        cell2.style.overflow = "hidden";
        var cell3 = row.insertCell(2);
        cell3.style.backgroundColor = "#EEE";
        cell1.innerHTML = '<span id="spanPart' + partName + '">' + partNameString + "</span><br />" +
        '<a id="delete-' + partName + '" class="question-icon" onclick="deletePart(' + partName + ');"><img class="question-icon-cross" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>' +
        //'<a id="down-' + partName + '" class="question-icon" onclick="movePartDown(' + partName + ');"><img class="question-icon-down" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>' +
        //'<a id="up-' + partName + '" class="question-icon" onclick="movePartUp(' + partName + ');"><img class="question-icon-up" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>' +
        '<br />' +
        "<div class='part_submission_id'><input style='width: 47px;' type='text' name='rubric_part_"+partNameString+"_id' value='_part"+partNameString+"' /></div>";
        cell2.innerHTML = '<textarea name="comment-' + partName + '-1" rows="1" style="width: 885px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;"></textarea></span>'+
                          '<div class="btn btn-mini btn-default" onclick="toggleTA(' + partName + ',1)" style="margin-top:-5px;">TA Note</div>'+
                          '<textarea name="ta-' + partName + '-1" id="individual-' + partName + '-1" rows="1" placeholder=" Message to TA" style="width: 954px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: -80px; display: none;"></textarea>';
        cell3.innerHTML = selectBox(partName, "1") + ' <input onclick="calculatePercentageTotal();" name="ec-' + partName + '-1" type="checkbox" />';

        cell3.innerHTML += "<br />" +
                        "<a id='delete-" + partName + "-1' class=\"question-icon\" onclick=\"deleteQuestion(" + partName + ", 1);\"><img class=\"question-icon-cross\" src=\"../toolbox/include/bootstrap/img/glyphicons-halflings.png\"></a>" +
                        "<a id='down-" + partName + "-1' class=\"question-icon\" onclick=\"moveQuestionDown(" + partName + ", 1);\"><img class=\"question-icon-down\" src=\"../toolbox/include/bootstrap/img/glyphicons-halflings.png\"></a>" +
                        "<a id='up-" + partName + "-1' class=\"question-icon\" onclick=\"moveQuestionUp(" + partName + ", 1);\"><img class=\"question-icon-up\" src=\"../toolbox/include/bootstrap/img/glyphicons-halflings.png\"></a>";
        row = table.insertRow(table.rows.length - 2);
        row.id = 'add-' + partName;
        cell1 = row.insertCell(0);
        cell2 = row.insertCell(1);
        cell2.style.borderLeft = '1px solid #F9F9F9';
        cell1.innerHTML='<div class="btn btn-small btn-success"  onclick="addQuestion('+partName+')"><i class="icon-plus icon-white"></i> Question</div>';
        var elem = $("input[name='part_count']");
        elem.val(parseInt(elem.val())+1);
        togglePartSubmissionId();
    }

    function addQuestion(partName)
    {
        var part = Number(partName);
        if (part <= 0) {
            return;
        }

        var number = 0;
        for (var i = 0; i < parts.length && i <= part; i++) {
            number += parts[i];
        }

        document.getElementById("part-"+partName).rowSpan = '' + (Number(document.getElementById("part-"+partName).rowSpan) + 1);
        var table = document.getElementById("rubricTable");
        var row = table.insertRow(number);
        row.id = 'row-' + partName + '-' + parts[part];
        var cell1 = row.insertCell(0);
        cell1.style.overflow = "hidden";
        var cell2 = row.insertCell(1);
        cell2.style.backgroundColor = "#EEE";
        cell1.innerHTML = '<textarea name="comment-' + partName + "-" + parts[part] + '" rows="1" style="width:885px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-right: 1px;"></textarea></span>'+
                          '<div class="btn btn-mini btn-default" onclick="toggleTA(' + partName + "," + parts[part] + ')" style="margin-top:-5px;">TA Note</div>'+
                          '<textarea name="ta-' + partName + "-" + parts[part] + '" id="individual-' + partName + "-" + parts[part] + '" rows="1" placeholder=" Message to TA" style="width: 940px; padding: 0 0 0 10px; resize: none; margin-top: 5px; margin-bottom: -80px; display: none;"></textarea>';
        cell2.innerHTML = selectBox(partName, parts[part]) + ' <input onclick="calculatePercentageTotal();" name="ec-'+partName+'-'+parts[part]+'" type="checkbox" />';
        cell2.innerHTML += "<br />" +
                        "<a id='delete-" + partName + "-" + parts[part]+"' class=\"question-icon\" onclick=\"deleteQuestion(" + partName + ", " + parts[part] + ");\"><img class=\"question-icon-cross\" src=\"../toolbox/include/bootstrap/img/glyphicons-halflings.png\"></a>" +
                        "<a id='down-" + partName + "-" + parts[part]+"' class=\"question-icon\" onclick=\"moveQuestionDown(" + partName + ", " + parts[part] + ");\"><img class=\"question-icon-down\" src=\"../toolbox/include/bootstrap/img/glyphicons-halflings.png\"></a>" +
                        "<a id='up-" + partName + "-" + parts[part]+"' class=\"question-icon\" onclick=\"moveQuestionUp(" + partName + ", " + parts[part] + ");\"><img class=\"question-icon-up\" src=\"../toolbox/include/bootstrap/img/glyphicons-halflings.png\"></a>";
        parts[Number(partName)] += 1;
    }

    function selectBox(part, question)
    {
        var retVal = '<select name="point-' + part + "-" + question + '" class="points" onchange="calculatePercentageTotal()">';
        for(var i = 0; i <= 100; i++) {
            retVal = retVal + '<option>' + (i * 0.5) + '</option>';
        }
        retVal = retVal + '</select>';

        return retVal;
    }

    function togglePartSubmissionId() {
        if ($("input[name='rubric_parts_sep']").prop('checked') == true) {
            $('.part_submission_id').show();
        }
        else {
            $('.part_submission_id').hide();
        }
    }

    function calculatePercentageTotal() {
        var total = 0;
        var ec = 0;
        $('select.points').each(function(){
            var elem = $(this).attr('name').replace('point','ec');
            if (!$('[name="'+elem+'"]').prop('checked') == true) {
                total += +($(this).val());
            }
            else {
                ec += +($(this).val());
            }
        });
        document.getElementById("totalCalculation").innerHTML = total + " (" + ec + ")";
    }

    function deleteQuestion(part, question) {
        if (part <= 0 || question <= 0) {
            return;
        }

        var row = $('tr#row-' + part + '-' + question);

        var part_id = "";
        if (question == 1) {
            part_id = row.children()[0].children[2].children[0].value;
        }
        row.remove();
        for(var i = question+1; i < parts[part]; i++) {
            updateRow(part, part, i, i-1);
        }
        parts[part] -= 1;

        if (parts[part] == 1) {
            $('tr#add-' + part).remove();
            for (var ii = part + 1; ii < parts.length; ii++) {
                for (var jj = 1; jj < parts[ii]; jj++) {
                    updateRow(ii, ii-1, jj, jj);
                }
                $('span#spanPart' + ii).text(ii-1).attr('id', 'spanPart' + (ii-1));
                $('input[name=rubric_part_' + ii + '_id').attr('name', 'rubric_part_' + (ii-1) + '_id');
                $('tr#add-' + ii).attr('id', 'add-' + (ii-1)).find('div.btn').attr('onclick', 'addQuestion(' + (ii-1) + ')');
                $('td#part-' + ii).attr('id', 'part-' + (ii-1));
                $('a#delete-' + ii).attr('id', 'delete-' + (ii-1)).attr('onclick', 'deletePart(' + (ii-1) + ');');
                //$('a#down-' + ii).attr('id', 'down-' + (ii-1)).attr('onclick', 'movePartDown(' + (ii-1) + ');');
                //$('a#up-' + ii).attr('id', 'up-' + (ii-1)).attr('onclick', 'movePartUp(' + (ii-1) + ');');
            }
            parts.splice(part, 1);
        }
        else {
            if (question == 1) {
                $('tr#row-' + part + '-1').prepend('' +
                '<td rowspan="' + parts[part] + '" id="part-' + part + '"><span id="spanPart' + part + '">' + part + '</span><br />' +
                '<a id="delete-' + part + '" class="question-icon" onclick="deletePart(' + part + ');"><img class="question-icon-cross" src="../toolbox/include/bootstrap/img/glyphicons-halflings.png"></a>' +
                '<br />' +
                '<div class="part_submission_id" style="display: none;"><input style="width: 47px;" type="text" name="rubric_part_' + part + '_id" value="' + part_id + '"></div></td>');
            }
            else {
                $('tr#row-' + part + '-1').children('#part-' + part).attr('rowspan', parts[part]);
            }
        }
        calculatePercentageTotal();
    }

    function updateRow(oldPart, newPart, oldNum, newNum) {
        var row = $('tr#row-' + oldPart + '-' + oldNum);
        row.attr('id', 'row-' + newPart + '-' + newNum);
        row.find('textarea[name=comment-' + oldPart + '-' + oldNum + ']').attr('name', 'comment-' + newPart + '-' + newNum);
        row.find('div.btn').attr('onclick', 'toggleTA(' + newPart + ',' + newNum + ')');
        row.find('textarea[name=ta-' + oldPart + '-' + oldNum + ']').attr('name', 'ta-' + newPart + '-' + newNum).attr('id', 'individual-' + newPart + '-' + newNum);
        row.find('select[name=point-' + oldPart + '-' + oldNum + ']').attr('name', 'point-' + newPart + '-' + newNum);
        row.find('input[name=ec-' + oldPart + '-' + oldNum + ']').attr('name', 'ec-' + newPart + '-' + newNum);
        row.find('a[id=delete-' + oldPart + '-' + oldNum + ']').attr('id', 'delete-' + newPart + '-' + newNum).attr('onclick', 'deleteQuestion(' + newPart + ', '+ newNum + ')');
        row.find('a[id=down-' + oldPart + '-' + oldNum + ']').attr('id', 'down-' + newPart + '-' + newNum).attr('onclick', 'moveQuestionDown(' + newPart + ', '+ newNum + ')');
        row.find('a[id=up-' + oldPart + '-' + oldNum + ']').attr('id', 'up-' + newPart + '-' + newNum).attr('onclick', 'moveQuestionUp(' + newPart + ', '+ newNum + ')');
    }

    function moveQuestionDown(part, question) {
        if ((parts[part] - 1) == question || question <= 0 || part <= 0) {
            return;
        }

        var currentRow = $('tr#row-' + part + '-' + question);
        var newRow = $('tr#row-' + part + '-' + (question+1));
        var child = 0;
        if (question == 1) {
            child = 1;
        }


        var temp = currentRow.children()[child].children[0].value;
        currentRow.children()[child].children[0].value = newRow.children()[0].children[0].value;
        newRow.children()[0].children[0].value = temp;

        temp = currentRow.children()[child].children[2].value;
        currentRow.children()[child].children[2].value = newRow.children()[0].children[2].value;
        newRow.children()[0].children[2].value = temp;

        child += 1;

        temp = currentRow.children()[child].children[0].value;
        currentRow.children()[child].children[0].value = newRow.children()[1].children[0].value;
        newRow.children()[1].children[0].value = temp;

        temp = currentRow.children()[child].children[1].checked;
        currentRow.children()[child].children[1].checked = newRow.children()[1].children[1].checked;
        newRow.children()[1].children[1].checked = temp;
    }

    function moveQuestionUp(part, question) {
        if (question == 1 || question <= 0 || part <= 0) {
            return;
        }

        var currentRow = $('tr#row-' + part + '-' + question);
        var newRow = $('tr#row-' + part + '-' + (question-1));
        var child = 0;
        if (question == 2) {
            child = 1;
        }


        var temp = currentRow.children()[0].children[0].value;
        currentRow.children()[0].children[0].value = newRow.children()[child].children[0].value;
        newRow.children()[child].children[0].value = temp;

        temp = currentRow.children()[0].children[2].value;
        currentRow.children()[0].children[2].value = newRow.children()[child].children[2].value;
        newRow.children()[child].children[2].value = temp;

        child += 1;

        temp = currentRow.children()[1].children[0].value;
        currentRow.children()[1].children[0].value = newRow.children()[child].children[0].value;
        newRow.children()[child].children[0].value = temp;

        temp = currentRow.children()[1].children[1].checked;
        currentRow.children()[1].children[1].checked = newRow.children()[child].children[1].checked;
        newRow.children()[child].children[1].checked = temp;
    }

    function deletePart(part) {
        if (part < 1) {
            return;
        }

        for (var i = 1; i < parts[part]; i++) {
            $('tr#row-' + part + '-' + i).remove();
        }
        $('tr#add-' + part).remove();


        for (var ii = part + 1; ii < parts.length; ii++) {
            for (var jj = 1; jj < parts[ii]; jj++) {
                updateRow(ii, ii-1, jj, jj);
            }
            $('span#spanPart' + ii).text(ii-1).attr('id', 'spanPart' + (ii-1));
            $('input[name=rubric_part_' + ii + '_id').attr('name', 'rubric_part_' + (ii-1) + '_id');
            $('tr#add-' + ii).attr('id', 'add-' + (ii-1)).find('div.btn').attr('onclick', 'addQuestion(' + (ii-1) + ')');;
            $('td#part-' + ii).attr('id', 'part-' + (ii-1));
            $('a#delete-' + ii).attr('id', 'delete-' + (ii-1)).attr('onclick', 'deletePart(' + (ii-1) + ');');
            //$('a#down-' + ii).attr('id', 'down-' + (ii-1)).attr('onclick', 'movePartDown(' + (ii-1) + ');');
            //$('a#up-' + ii).attr('id', 'up-' + (ii-1)).attr('onclick', 'movePartUp(' + (ii-1) + ');');
        }
        parts.splice(part, 1);

        calculatePercentageTotal();
    }

    function movePartDown(part) {
        if (parts.length - 1 <= part || part <= 0) {
            return;
        }

        for (var i = 1; i < parts[part]; i++) {
            updateRow(part, -1, i, i);
        }

        for (var j = 1; j < parts[part+1]; j++) {
            updateRow(part+1, part, j, j);
        }

        for (var k = 1; k < parts[part]; k++) {
            updateRow(-1, part+1, k, k);
        }
    }

    function movePartUp(part) {
        if (part <= 1 || parts.length - 1 < part) {
            return;
        }
    }

    togglePartSubmissionId();
    calculatePercentageTotal();
JS;
    print <<<HTML
</script>
HTML;
}

include "../footer.php";